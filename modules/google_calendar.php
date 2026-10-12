<?php
declare(strict_types=1);

/**
 * Google Calendar integration — Adsbooster.net
 *
 * Every method here performs a real call to the Google Calendar REST API and
 * returns the outcome that Google actually reported. There are no stubs: a
 * previous version of this class commented the HTTP calls out and returned
 * "success => true" unconditionally, so users saw events "created" in
 * Adsbooster that never reached their calendar.
 */
class GoogleCalendar
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';
    private const TIMEOUT  = 20;

    public static function createEvent(int $userId, array $eventData): array
    {
        $accessToken = self::getAccessToken($userId);
        if ($accessToken === null) {
            return ['success' => false, 'event_id' => null, 'message' => 'Google account not connected.'];
        }

        $calendarId   = self::sanitizeCalendarId($eventData['calendar_id'] ?? 'primary');
        $summary      = trim((string)($eventData['summary'] ?? ''));
        $description  = (string)($eventData['description'] ?? '');
        $startDateTime = (string)($eventData['start_datetime'] ?? '');
        $endDateTime   = (string)($eventData['end_datetime'] ?? '');
        $timezone      = (string)($eventData['timezone'] ?? 'UTC');

        if ($summary === '' || $startDateTime === '' || $endDateTime === '') {
            return ['success' => false, 'event_id' => null, 'message' => 'Missing required event fields.'];
        }

        $payload = [
            'summary'     => $summary,
            'description' => $description,
            'start'       => ['dateTime' => $startDateTime, 'timeZone' => $timezone],
            'end'         => ['dateTime' => $endDateTime, 'timeZone' => $timezone],
        ];
        if (!empty($eventData['location'])) {
            $payload['location'] = (string)$eventData['location'];
        }

        $url    = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events';
        $resp   = self::request($accessToken, $url, 'POST', $payload);

        if ($resp['code'] >= 200 && $resp['code'] < 300) {
            $json = $resp['json'] ?: [];
            return [
                'success'  => true,
                'event_id' => (string)($json['id'] ?? ''),
                'html_link'=> (string)($json['htmlLink'] ?? ''),
                'message'  => 'Event created in Google Calendar.',
            ];
        }

        return [
            'success'   => false,
            'event_id'  => null,
            'message'   => 'Google Calendar rejected the event: ' . self::explain($resp),
        ];
    }

    public static function updateEvent(int $userId, string $eventId, array $eventData): array
    {
        $accessToken = self::getAccessToken($userId);
        if ($accessToken === null) {
            return ['success' => false, 'message' => 'Google account not connected.'];
        }

        $calendarId = self::sanitizeCalendarId($eventData['calendar_id'] ?? 'primary');
        $eventId    = self::sanitizeEventId($eventId);
        if ($eventId === '') {
            return ['success' => false, 'message' => 'Missing event id.'];
        }

        // Google requires the resource to exist and rejects unknown fields on
        // PATCH, so only send what the caller actually supplied.
        $payload = [];
        if (isset($eventData['summary'])) {
            $payload['summary'] = (string)$eventData['summary'];
        }
        if (isset($eventData['description'])) {
            $payload['description'] = (string)$eventData['description'];
        }
        if (!empty($eventData['start_datetime'])) {
            $payload['start'] = [
                'dateTime' => (string)$eventData['start_datetime'],
                'timeZone' => (string)($eventData['timezone'] ?? 'UTC'),
            ];
        }
        if (!empty($eventData['end_datetime'])) {
            $payload['end'] = [
                'dateTime' => (string)$eventData['end_datetime'],
                'timeZone' => (string)($eventData['timezone'] ?? 'UTC'),
            ];
        }
        if (isset($eventData['location'])) {
            $payload['location'] = (string)$eventData['location'];
        }

        if (!$payload) {
            return ['success' => false, 'message' => 'Nothing to update.'];
        }

        $url  = self::API_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId);
        $resp = self::request($accessToken, $url, 'PATCH', $payload);

        if ($resp['code'] >= 200 && $resp['code'] < 300) {
            return ['success' => true, 'message' => 'Event updated in Google Calendar.'];
        }

        return ['success' => false, 'message' => 'Google Calendar rejected the update: ' . self::explain($resp)];
    }

    public static function deleteEvent(int $userId, string $eventId, string $calendarId = 'primary'): array
    {
        $accessToken = self::getAccessToken($userId);
        if ($accessToken === null) {
            return ['success' => false, 'message' => 'Google account not connected.'];
        }

        $eventId = self::sanitizeEventId($eventId);
        if ($eventId === '') {
            return ['success' => false, 'message' => 'Missing event id.'];
        }

        $url    = self::API_BASE . '/calendars/' . rawurlencode(self::sanitizeCalendarId($calendarId))
            . '/events/' . rawurlencode($eventId);
        $resp   = self::request($accessToken, $url, 'DELETE', null);

        // 204 No Content on success; 410 means it was already gone, which is
        // still a successful outcome from the caller's point of view.
        if (($resp['code'] >= 200 && $resp['code'] < 300) || $resp['code'] === 410) {
            return ['success' => true, 'message' => 'Event deleted from Google Calendar.'];
        }

        return ['success' => false, 'message' => 'Google Calendar rejected the delete: ' . self::explain($resp)];
    }

    public static function listEvents(int $userId, string $calendarId = 'primary', string $timeMin = '', string $timeMax = ''): array
    {
        $accessToken = self::getAccessToken($userId);
        if ($accessToken === null) {
            return ['success' => false, 'events' => [], 'message' => 'Google account not connected.'];
        }

        $params = [
            'maxResults'   => 50,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
            'timeZone'     => 'UTC',
        ];
        if ($timeMin !== '') {
            $params['timeMin'] = $timeMin;
        }
        if ($timeMax !== '') {
            $params['timeMax'] = $timeMax;
        }

        $url  = self::API_BASE . '/calendars/' . rawurlencode(self::sanitizeCalendarId($calendarId))
            . '/events?' . http_build_query($params);
        $resp = self::request($accessToken, $url, 'GET', null);

        if ($resp['code'] < 200 || $resp['code'] >= 300) {
            return ['success' => false, 'events' => [], 'message' => 'Google Calendar error: ' . self::explain($resp)];
        }

        $items = is_array($resp['json']['items'] ?? null) ? $resp['json']['items'] : [];
        $events = [];
        foreach ($items as $item) {
            if (!is_array($item) || isset($item['cancelled'])) {
                continue;
            }
            $events[] = [
                'id'      => (string)($item['id'] ?? ''),
                'summary' => (string)($item['summary'] ?? '(no title)'),
                'start'   => (string)(is_array($item['start'] ?? null)
                    ? ($item['start']['dateTime'] ?? ($item['start']['date'] ?? ''))
                    : ''),
                'end'     => (string)(is_array($item['end'] ?? null)
                    ? ($item['end']['dateTime'] ?? ($item['end']['date'] ?? ''))
                    : ''),
                'html_link'=> (string)($item['htmlLink'] ?? ''),
                'location' => (string)($item['location'] ?? ''),
            ];
        }

        return [
            'success'    => true,
            'events'     => $events,
            'next_token' => (string)($resp['json']['nextPageToken'] ?? ''),
            'message'    => sprintf('%d event(s) loaded from Google Calendar.', count($events)),
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Plumbing
    // ─────────────────────────────────────────────────────────

    /**
     * Single HTTP entry point. Returns ['code','body','json','error'] and
     * never throws, so callers can translate the real outcome into a message.
     */
    private static function request(string $accessToken, string $url, string $method, ?array $payload): array
    {
        $bundle = function_exists('ca_bundle_found') ? ca_bundle_found() : '';
        $verify = $bundle !== '';

        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'AdsboosterCalendar/2.0',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
        if ($verify) {
            $opts[CURLOPT_CAINFO] = $bundle;
        }

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            if (strtoupper($method) === 'GET') {
                $opts[CURLOPT_HTTPGET] = true;
            } else {
                $opts[CURLOPT_POST]           = true;
                $opts[CURLOPT_POSTFIELDS]     = json_encode($payload);
                if (strtoupper($method) !== 'POST') {
                    $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
                }
            }
        } else {
            if (strtoupper($method) === 'DELETE') {
                $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            } else {
                $opts[CURLOPT_HTTPGET] = true;
            }
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string)curl_error($ch);
        curl_close($ch);

        $bodyStr = is_string($body) ? $body : '';

        return [
            'code'  => $code,
            'body'  => $bodyStr,
            'json'  => json_decode($bodyStr, true) ?: [],
            'error' => $err,
        ];
    }

    /** Turn a Google error response into a short human-readable sentence. */
    private static function explain(array $resp): string
    {
        if ($resp['error'] !== '') {
            return $resp['error'];
        }
        $msg = $resp['json']['error']['message']
            ?? ($resp['json']['error_description'] ?? null)
            ?? '';
        if (is_string($msg) && $msg !== '') {
            return 'HTTP ' . $resp['code'] . ' — ' . mb_substr(preg_replace('/\s+/', ' ', $msg), 0, 180);
        }
        return 'HTTP ' . $resp['code'] . ($resp['body'] !== '' ? ' — ' . mb_substr($resp['body'], 0, 180) : '');
    }

    /** Calendar ids are path segments; allow only Google's permitted charset. */
    private static function sanitizeCalendarId(string $id): string
    {
        $id = trim($id);
        return $id === '' ? 'primary' : substr($id, 0, 128);
    }

    private static function sanitizeEventId(string $id): string
    {
        return trim($id);
    }

    private static function getAccessToken(int $userId): ?string
    {
        $connection = db()->fetchOne(
            "SELECT access_token, refresh_token, token_expiry
             FROM connected_accounts
             WHERE user_id = ? AND platform = 'google_calendar'
             ORDER BY id DESC
             LIMIT 1",
            [$userId]
        );

        if ($connection === null || empty($connection['access_token'])) {
            return null;
        }

        if ($connection['token_expiry'] !== null && strtotime($connection['token_expiry']) <= time()) {
            if (empty($connection['refresh_token'])) {
                return null;
            }
            if (!class_exists('GoogleAuth')) {
                require_once __DIR__ . '/google_auth.php';
            }
            try {
                $refreshed = GoogleAuth::refreshAccessToken($connection['refresh_token']);
            } catch (\Throwable $e) {
                error_log('GoogleCalendar token refresh failed: ' . $e->getMessage());
                return null;
            }
            if (empty($refreshed['access_token'])) {
                return null;
            }
            db()->update('connected_accounts', [
                'access_token' => $refreshed['access_token'],
                'token_expiry' => date('Y-m-d H:i:s', time() + (int)($refreshed['expires_in'] ?? 3600)),
            ], 'user_id = ? AND platform = ?', [$userId, 'google_calendar']);

            return $refreshed['access_token'];
        }

        return (string)$connection['access_token'];
    }
}
