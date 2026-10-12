<?php
declare(strict_types=1);

/**
 * Server-Side Tracking Bridge (Meta CAPI & TikTok Events API)
 * Adsbooster.net
 */

class ServerTrackingEngine
{
    /**
     * Create or retrieve pixel token
     */
    public static function createPixel(int $userId, array $params): array
    {
        $token = 'px_' . bin2hex(random_bytes(16));
        $name = trim($params['name'] ?? 'Primary Growth Pixel');
        $metaPixelId = trim($params['meta_pixel_id'] ?? '');
        $metaCapiToken = trim($params['meta_capi_access_token'] ?? '');
        $metaTestCode = trim($params['meta_test_event_code'] ?? '');
        $tiktokPixelId = trim($params['tiktok_pixel_id'] ?? '');
        $tiktokToken = trim($params['tiktok_events_access_token'] ?? '');
        $tiktokTestCode = trim($params['tiktok_test_event_code'] ?? '');

        $pixelId = db()->insert('server_tracking_pixels', [
            'user_id' => $userId,
            'pixel_token' => $token,
            'name' => $name,
            'meta_pixel_id' => $metaPixelId,
            'meta_capi_access_token' => $metaCapiToken,
            'meta_test_event_code' => $metaTestCode,
            'tiktok_pixel_id' => $tiktokPixelId,
            'tiktok_events_access_token' => $tiktokToken,
            'tiktok_test_event_code' => $tiktokTestCode,
            'domain_verified' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'is_active' => 1
        ]);

        return [
            'id' => $pixelId,
            'token' => $token,
            'name' => $name
        ];
    }

    /**
     * Ingest and Dispatch Event (Server-to-Server)
     */
    public static function trackEvent(string $pixelToken, string $eventName, array $userData = [], array $customData = [], string $sourceUrl = ''): array
    {
        $pixel = db()->fetch("SELECT * FROM server_tracking_pixels WHERE pixel_token = ? AND is_active = 1", [$pixelToken]);
        if (!$pixel) {
            return ['success' => false, 'error' => 'Invalid or inactive pixel token'];
        }

        // Hash sensitive user data with SHA256 (GDPR / CAPI compliance)
        $hashedUserData = [
            'em' => !empty($userData['email']) ? hash('sha256', strtolower(trim($userData['email']))) : null,
            'ph' => !empty($userData['phone']) ? hash('sha256', preg_replace('/[^0-9]/', '', $userData['phone'])) : null,
            'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0'
        ];

        $eventId = 'evt_' . bin2hex(random_bytes(12));
        $source  = $sourceUrl ?: ($_SERVER['HTTP_REFERER'] ?? BASE_URL);

        // Insert into database event log. Statuses start as 'pending' and are
        // updated below from the real HTTP response of each platform — we must
        // never record a dispatch we did not actually perform.
        $logId = db()->insert('server_tracking_events', [
            'pixel_id' => (int)$pixel['id'],
            'user_id' => (int)$pixel['user_id'],
            'event_name' => $eventName,
            'event_id' => $eventId,
            'event_source_url' => $source,
            'user_data_hashed' => json_encode($hashedUserData),
            'custom_data' => json_encode($customData),
            'meta_capi_status' => 'pending',
            'tiktok_events_status' => 'pending',
        ]);

        $metaStatus   = 'skipped';
        $tiktokStatus = 'skipped';

        if (!empty($pixel['meta_capi_access_token']) && !empty($pixel['meta_pixel_id'])) {
            $metaStatus = self::postToMeta($pixel, $eventName, $eventId, $source, $hashedUserData, $customData);
        }

        if (!empty($pixel['tiktok_events_access_token']) && !empty($pixel['tiktok_pixel_id'])) {
            $tiktokStatus = self::postToTikTok($pixel, $eventName, $eventId, $source, $hashedUserData, $customData);
        }

        db()->update('server_tracking_events', [
            'meta_capi_status'   => $metaStatus,
            'tiktok_events_status' => $tiktokStatus,
        ], 'id = ?', [$logId]);

        $metaOk   = str_starts_with($metaStatus, '200') || $metaStatus === 'skipped';
        $tiktokOk = str_starts_with($tiktokStatus, '200') || $tiktokStatus === 'skipped';

        return [
            'success'       => $metaOk && $tiktokOk,
            'event_id'      => $eventId,
            'event_name'    => $eventName,
            'meta_status'   => $metaStatus,
            'tiktok_status' => $tiktokStatus,
            'log_id'        => (int)$logId,
        ];
    }

    /**
     * POST one event to Meta's Conversions API. Returns a status string that
     * starts with the real HTTP code, or a plain-language failure reason.
     */
    private static function postToMeta(array $pixel, string $eventName, string $eventId, string $source, array $userData, array $customData): string
    {
        $pixelId = (string)$pixel['meta_pixel_id'];
        $token   = (string)$pixel['meta_capi_access_token'];

        $body = [
            'data' => [[
                'event_name'      => $eventName,
                'event_time'      => time(),
                'event_id'        => $eventId,
                'event_source_url'=> $source,
                'action_source'   => 'website',
                'user_data'       => array_filter($userData, static fn ($v) => $v !== null && $v !== ''),
                'custom_data'     => $customData,
            ]],
        ];
        if (!empty($pixel['meta_test_event_code'])) {
            $body['test_event_code'] = (string)$pixel['meta_test_event_code'];
        }

        $url = "https://graph.facebook.com/v19.0/{$pixelId}/events?access_token=" . urlencode($token);
        $resp = self::postJson($url, $body, [], 15);

        if ($resp['error'] !== '') {
            return 'error: ' . self::shortErr($resp['error']);
        }
        if ($resp['code'] === 200) {
            return '200 OK';
        }
        return 'HTTP ' . $resp['code'] . ($resp['body'] !== '' ? ': ' . self::shortErr($resp['body']) : '');
    }

    /**
     * POST one event to TikTok Events API.
     */
    private static function postToTikTok(array $pixel, string $eventName, string $eventId, string $source, array $userData, array $customData): string
    {
        $pixelId = (string)$pixel['tiktok_pixel_id'];
        $token   = (string)$pixel['tiktok_events_access_token'];

        $properties = [];
        foreach (['value', 'currency', 'order_id', 'content_id', 'content_type', 'contents'] as $k) {
            if (isset($customData[$k]) && $customData[$k] !== '' && $customData[$k] !== null) {
                $properties[$k] = $customData[$k];
            }
        }

        $body = [
            'pixel_id'  => $pixelId,
            'event'     => $eventName,
            'event_id'  => $eventId,
            'timestamp' => (string)date('c'),
            'context'   => [
                'user' => [
                    'external_id' => $userData['em'] ?? '',
                    'ip_address'  => $userData['client_ip_address'] ?? '',
                    'user_agent'  => $userData['client_user_agent'] ?? '',
                ],
                'page' => ['url' => $source],
            ],
            'properties' => $properties,
        ];
        $headers = [
            'Content-Type: application/json',
            'Access-Token: ' . $token,
        ];
        if (!empty($pixel['tiktok_test_event_code'])) {
            $headers[] = 'Test-Event-Code: ' . (string)$pixel['tiktok_test_event_code'];
        }

        $resp = self::postJson('https://business-api.tiktok.com/open_api/v1.3/pixel/track/', $body, $headers, 15);

        if ($resp['error'] !== '') {
            return 'error: ' . self::shortErr($resp['error']);
        }
        if ($resp['code'] === 200) {
            // TikTok returns 200 with a business code in the body.
            $json = json_decode($resp['body'], true);
            $bizCode = is_array($json) ? ($json['code'] ?? 0) : 0;
            if ((int)$bizCode === 0) {
                return '200 OK';
            }
            return 'HTTP 200, API code ' . (int)$bizCode . ': ' . self::shortErr($resp['body']);
        }
        return 'HTTP ' . $resp['code'] . ($resp['body'] !== '' ? ': ' . self::shortErr($resp['body']) : '');
    }

    /** Shared JSON POST. Never throws — returns code/body/error for logging. */
    private static function postJson(string $url, array $payload, array $headers, int $timeout): array
    {
        $bundle = function_exists('ca_bundle_found') ? ca_bundle_found() : '';
        $verify = $bundle !== '';

        $ch = curl_init($url);
        $opts = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_USERAGENT      => 'AdsboosterCAPI/2.0',
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
        if ($verify) {
            $opts[CURLOPT_CAINFO] = $bundle;
        }
        curl_setopt_array($ch, $opts);
        $body   = curl_exec($ch);
        $code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = (string)curl_error($ch);
        curl_close($ch);

        return ['code' => $code, 'body' => is_string($body) ? $body : '', 'error' => $err];
    }

    private static function shortErr(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
        return mb_substr($s, 0, 160);
    }

    /**
     * Generate HTML / JS Snippet for User's Website
     */
    public static function getTrackingSnippet(string $pixelToken): string
    {
        $endpoint = BASE_URL . '/api/v1/capi-bridge.php';
        return <<<HTML
<!-- Adsbooster.net Server-Side CAPI Tracking Script -->
<script>
!function(w,d,t,u){
  w.Adsbooster=w.Adsbooster||function(){(w.Adsbooster.q=w.Adsbooster.q||[]).push(arguments)};
  w.Adsbooster('init', '{$pixelToken}');
  w.Adsbooster('track', 'PageView');
  var s=d.createElement(t);s.async=1;s.src=u;
  var f=d.getElementsByTagName(t)[0];f.parentNode.insertBefore(s,f);
}(window,document,'script','{$endpoint}?js=1');
</script>
<!-- End Adsbooster Tracking -->
HTML;
    }
}
