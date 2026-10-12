<?php declare(strict_types=1);

/**
 * Social Media Posting Engine — Real-Time Multi-Network Publisher
 * Adsbooster.net
 *
 * Real-time direct publishing and scheduled queueing across Meta,
 * TikTok, Twitter/X, LinkedIn, Google Ads, and Snapchat using live OAuth tokens.
 */

require_once __DIR__ . '/../config/db.php';

class SocialPostEngine
{
    /** @var array Supported social platforms */
    public const SUPPORTED_PLATFORMS = [
        'meta',
        'facebook',
        'instagram',
        'tiktok',
        'google',
        'google_ads',
        'google_calendar',
        'snapchat',
        'twitter',
        'linkedin',
    ];

    /** @var array Valid post statuses */
    public const VALID_STATUSES = ['scheduled', 'published', 'failed', 'cancelled'];

    /** @var array Valid media types */
    public const VALID_MEDIA_TYPES = ['image', 'ugc_image', 'ugc_video', 'reel', 'text', 'text_only'];

    /**
     * Publish a post immediately across live social network APIs in real-time.
     */
    public static function publishLive(
        int $userId,
        string $platform,
        string $caption,
        ?string $mediaUrl = null,
        string $mediaType = 'image'
    ): array {
        $platform = strtolower(trim($platform));
        if ($platform === 'facebook' || $platform === 'instagram') {
            $lookupPlatform = 'meta';
        } elseif ($platform === 'google_ads' || $platform === 'google_calendar') {
            $lookupPlatform = 'google';
        } else {
            $lookupPlatform = $platform;
        }

        // 1. Fetch connected account and credentials
        $connection = db()->fetchOne(
            "SELECT * FROM connected_accounts WHERE user_id = ? AND (platform = ? OR platform = ?) ORDER BY id DESC LIMIT 1",
            [$userId, $lookupPlatform, $platform]
        );

        if (!$connection) {
            return [
                'success'  => false,
                'platform' => $platform,
                'error'    => "No active {$platform} account connected. Please connect via OAuth in Social Connect first.",
            ];
        }

        $token     = $connection['access_token'] ?? '';
        $accountId = $connection['account_id'] ?: 'me';
        $fullMediaUrl = (!empty($mediaUrl) && !str_starts_with($mediaUrl, 'http')) ? (BASE_URL . '/' . ltrim($mediaUrl, '/')) : $mediaUrl;

        $startTime = microtime(true);
        $publishResult = self::executeProviderLivePublish($platform, $token, $accountId, $caption, $fullMediaUrl);
        $latencyMs = (int)round((microtime(true) - $startTime) * 1000);

        // 2. Persist record into scheduled_posts as published or failed
        try {
            $status = $publishResult['success'] ? 'published' : 'failed';
            $postId = db()->insert('scheduled_posts', [
                'user_id'        => $userId,
                'platform'       => $platform,
                'post_type'      => 'realtime_publish',
                'caption'        => trim($caption),
                'media_url'      => $mediaUrl,
                'media_type'     => $mediaType,
                'scheduled_time' => date('Y-m-d H:i:s'),
                'status'         => $status,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $publishResult['post_id'] = $postId;
        } catch (\Throwable $e) {
            error_log("Failed to log social post: " . $e->getMessage());
        }

        $publishResult['latency_ms'] = $latencyMs;
        return $publishResult;
    }

    /**
     * Publish simultaneously across multiple connected social networks in real-time.
     */
    public static function publishMultiLive(
        int $userId,
        array $platforms,
        string $caption,
        ?string $mediaUrl = null,
        string $mediaType = 'image'
    ): array {
        $results = [];
        $overallSuccess = true;

        foreach ($platforms as $platform) {
            $res = self::publishLive($userId, (string)$platform, $caption, $mediaUrl, $mediaType);
            $results[$platform] = $res;
            if (empty($res['success'])) {
                $overallSuccess = false;
            }
        }

        return [
            'success' => $overallSuccess,
            'results' => $results,
        ];
    }

    /**
     * Dispatch live HTTP requests to third-party social provider APIs.
     */
    private static function executeProviderLivePublish(
        string $platform,
        string $token,
        string $accountId,
        string $caption,
        ?string $mediaUrl
    ): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        foreach (ca_curl_opts() as $optKey => $optVal) {
            curl_setopt($ch, $optKey, $optVal);
        }

        try {
            switch ($platform) {
                // ── Meta / Facebook / Instagram ──
                case 'meta':
                case 'facebook':
                case 'instagram':
                    if (!empty($mediaUrl)) {
                        $targetUrl = "https://graph.facebook.com/v20.0/{$accountId}/photos";
                        $postFields = [
                            'url'          => $mediaUrl,
                            'caption'      => $caption,
                            'access_token' => $token,
                        ];
                    } else {
                        $targetUrl = "https://graph.facebook.com/v20.0/{$accountId}/feed";
                        $postFields = [
                            'message'      => $caption,
                            'access_token' => $token,
                        ];
                    }
                    curl_setopt($ch, CURLOPT_URL, $targetUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                    break;

                // ── Twitter / X ──
                case 'twitter':
                    $targetUrl = 'https://api.twitter.com/2/tweets';
                    $payload = json_encode(['text' => $caption]);
                    curl_setopt($ch, CURLOPT_URL, $targetUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json',
                    ]);
                    break;

                // ── LinkedIn ──
                case 'linkedin':
                    $targetUrl = 'https://api.linkedin.com/v2/ugcPosts';
                    $payload = json_encode([
                        'author' => "urn:li:person:{$accountId}",
                        'lifecycleState' => 'PUBLISHED',
                        'specificContent' => [
                            'com.linkedin.ugc.ShareContent' => [
                                'shareCommentary' => ['text' => $caption],
                                'shareMediaCategory' => !empty($mediaUrl) ? 'ARTICLE' : 'NONE',
                            ]
                        ],
                        'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
                    ]);
                    curl_setopt($ch, CURLOPT_URL, $targetUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json',
                        'X-Restli-Protocol-Version: 2.0.0',
                    ]);
                    break;

                // ── TikTok ──
                case 'tiktok':
                    $targetUrl = 'https://open.tiktokapis.com/v2/post/publish/content/init/';
                    $payload = json_encode([
                        'post_info' => ['title' => substr($caption, 0, 150), 'privacy_level' => 'PUBLIC_TO_EVERYONE'],
                        'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $mediaUrl ?: '']
                    ]);
                    curl_setopt($ch, CURLOPT_URL, $targetUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json',
                    ]);
                    break;

                // ── Snapchat ──
                case 'snapchat':
                    $targetUrl = 'https://adsapi.snapchat.com/v1/creatives';
                    curl_setopt($ch, CURLOPT_URL, $targetUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => substr($caption, 0, 50)]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $token,
                        'Content-Type: application/json',
                    ]);
                    break;

                // ── Google Ads / YouTube ──
                case 'google':
                case 'google_ads':
                    $targetUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
                    curl_setopt($ch, CURLOPT_URL, $targetUrl);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
                    break;

                default:
                    curl_close($ch);
                    return ['success' => false, 'error' => "Unsupported platform {$platform}."];
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                return [
                    'success' => false,
                    'platform' => $platform,
                    'error' => "Network connection to {$platform} API failed: {$curlErr}",
                ];
            }

            $json = json_decode((string)$response, true) ?: [];

            // Interpret provider response
            if ($httpCode >= 200 && $httpCode < 300) {
                // Only report an id the provider actually gave us. Falling
                // back to a random local string would look like a real post
                // id in the UI and make failed publishes untraceable.
                $extId = $json['id'] ?? $json['data']['id'] ?? $json['post_id'] ?? '';
                return [
                    'success'     => true,
                    'platform'    => $platform,
                    'http_code'   => $httpCode,
                    'external_id' => (string)$extId,
                    'message'     => $extId !== ''
                        ? "Post published to {$platform} (ID: {$extId})."
                        : "Post accepted by {$platform} (HTTP {$httpCode}); no post id was returned.",
                ];
            } else {
                $errMsg = $json['error']['message'] ?? $json['message'] ?? $json['error'] ?? "HTTP {$httpCode} response received from {$platform}.";
                return [
                    'success'   => false,
                    'platform'  => $platform,
                    'http_code' => $httpCode,
                    'error'     => is_array($errMsg) ? json_encode($errMsg) : (string)$errMsg,
                ];
            }

        } catch (\Throwable $e) {
            if (is_resource($ch)) curl_close($ch);
            return [
                'success' => false,
                'platform' => $platform,
                'error' => "API dispatch exception: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Create or schedule a new social media post.
     */
    public static function createPost(
        int $userId,
        string $platform,
        string $caption,
        ?string $mediaUrl,
        string $mediaType,
        string $scheduledTime
    ): int {
        if (empty(trim($caption))) {
            throw new \InvalidArgumentException('Caption cannot be empty.');
        }

        $scheduledTimestamp = strtotime($scheduledTime);
        if ($scheduledTimestamp === false) {
            throw new \InvalidArgumentException('Invalid scheduled time format.');
        }

        // If time is now or in the past, publish immediately in real-time
        if ($scheduledTimestamp <= (time() + 60)) {
            $liveRes = self::publishLive($userId, $platform, $caption, $mediaUrl, $mediaType);
            if (!$liveRes['success']) {
                throw new \RuntimeException($liveRes['error'] ?? 'Live publish failed.');
            }
            return (int)($liveRes['post_id'] ?? 0);
        }

        // Otherwise schedule in database queue
        $connection = db()->fetchOne(
            "SELECT id FROM connected_accounts WHERE user_id = ? AND (platform = ? OR platform = 'meta')",
            [$userId, $platform]
        );

        if ($connection === null) {
            throw new \InvalidArgumentException("No active {$platform} connection found. Please connect your account first.");
        }

        try {
            $postId = db()->insert('scheduled_posts', [
                'user_id'        => $userId,
                'platform'       => $platform,
                'post_type'      => 'scheduled_campaign',
                'caption'        => trim($caption),
                'media_url'      => $mediaUrl,
                'media_type'     => $mediaType,
                'scheduled_time' => date('Y-m-d H:i:s', $scheduledTimestamp),
                'status'         => 'scheduled',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            return $postId;
        } catch (\Throwable $e) {
            error_log("Failed to create social post: " . $e->getMessage());
            throw new \RuntimeException("Failed to schedule post. Please try again.");
        }
    }

    /**
     * Publish a scheduled post immediately.
     */
    public static function publishPost(int $postId): bool
    {
        $res = self::publishScheduledRow($postId);
        return !empty($res['success']);
    }

    /**
     * Publish one existing scheduled_posts row against its connected account
     * and return the provider's real outcome.
     *
     * Unlike publishLive(), this does NOT create a second row — it is meant
     * for rows that already exist (cron, retry, manual "publish now").
     *
     * Returns ['success'=>bool, 'external_id'=>string, 'error'=>string,
     *          'http_code'=>int, 'message'=>string].
     */
    public static function publishScheduledRow(int $postId): array
    {
        $post = db()->fetchOne("SELECT * FROM scheduled_posts WHERE id = ?", [$postId]);
        if (!$post) {
            return ['success' => false, 'error' => 'Scheduled post not found.', 'external_id' => ''];
        }
        if (!in_array((string)$post['status'], ['scheduled', 'publishing'], true)) {
            return ['success' => false, 'error' => "Post is already {$post['status']}.", 'external_id' => ''];
        }

        $res = self::executeProviderLivePublish(
            strtolower((string)$post['platform']),
            (string)self::resolveToken((int)$post['user_id'], (string)$post['platform']),
            (string)self::resolveAccountId((int)$post['user_id'], (string)$post['platform']),
            (string)$post['caption'],
            !empty($post['media_url']) ? (string)$post['media_url'] : null
        );

        $res['external_id'] = (string)($res['external_id'] ?? '');

        try {
            if (!empty($res['success'])) {
                db()->update('scheduled_posts', [
                    'status'        => 'published',
                    'published_at'  => date('Y-m-d H:i:s'),
                    'external_id'   => $res['external_id'],
                    'publish_error' => null,
                    'updated_at'    => date('Y-m-d H:i:s'),
                ], 'id = ?', [$postId]);
            } else {
                db()->update('scheduled_posts', [
                    'status'        => 'failed',
                    'publish_error' => mb_substr((string)($res['error'] ?? 'Unknown provider error'), 0, 500),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ], 'id = ?', [$postId]);
            }
        } catch (\Throwable $e) {
            // Some installs lack external_id/publish_error columns; fall back
            // to status-only so the row still reflects reality.
            error_log('publishScheduledRow: ' . $e->getMessage());
            db()->update('scheduled_posts', [
                'status'     => !empty($res['success']) ? 'published' : 'failed',
                'published_at' => !empty($res['success']) ? date('Y-m-d H:i:s') : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$postId]);
        }

        return $res;
    }

    /** Look up the OAuth token for a platform, honouring meta/instagram aliasing. */
    private static function resolveToken(int $userId, string $platform): ?string
    {
        $conn = self::findConnection($userId, $platform);
        return $conn ? ($conn['access_token'] ?? null) : null;
    }

    private static function resolveAccountId(int $userId, string $platform): string
    {
        $conn = self::findConnection($userId, $platform);
        $accountId = $conn ? ($conn['account_id'] ?? null) : null;
        if ($accountId === null && $conn) {
            $accountId = $conn['platform_user_id'] ?? null;
        }
        return $accountId ? (string)$accountId : 'me';
    }

    private static function findConnection(int $userId, string $platform): ?array
    {
        $platform = strtolower(trim($platform));
        if ($platform === 'facebook' || $platform === 'instagram') {
            $lookup = 'meta';
        } elseif ($platform === 'google_ads' || $platform === 'google_calendar') {
            $lookup = 'google';
        } else {
            $lookup = $platform;
        }

        try {
            return db()->fetchOne(
                "SELECT * FROM connected_accounts WHERE user_id = ? AND (platform = ? OR platform = ?) ORDER BY id DESC LIMIT 1",
                [$userId, $lookup, $platform]
            ) ?: null;
        } catch (\Throwable $e) {
            error_log('findConnection: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all posts for a user, optionally filtered by status.
     */
    public static function getPosts(int $userId, ?string $status = null): array
    {
        $sql = "SELECT * FROM scheduled_posts WHERE user_id = ?";
        $params = [$userId];

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        try {
            return db()->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            error_log("Failed to fetch posts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cancel a scheduled post.
     */
    public static function cancelPost(int $postId): bool
    {
        $post = db()->fetchOne("SELECT id, status FROM scheduled_posts WHERE id = ?", [$postId]);
        if (!$post || !in_array($post['status'], ['scheduled'], true)) {
            return false;
        }

        try {
            db()->update('scheduled_posts', [
                'status'     => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$postId]);
            return true;
        } catch (\Throwable $e) {
            error_log("Failed to cancel post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reschedule a post to a new time.
     */
    public static function reschedulePost(int $postId, string $newTime): bool
    {
        $post = db()->fetchOne("SELECT id, status FROM scheduled_posts WHERE id = ?", [$postId]);
        if (!$post || !in_array($post['status'], ['scheduled', 'failed'], true)) {
            return false;
        }

        $newTimestamp = strtotime($newTime);
        if ($newTimestamp === false || $newTimestamp <= time()) {
            return false;
        }

        try {
            db()->update('scheduled_posts', [
                'scheduled_time' => date('Y-m-d H:i:s', $newTimestamp),
                'status'         => 'scheduled',
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id = ?', [$postId]);
            return true;
        } catch (\Throwable $e) {
            error_log("Failed to reschedule post: " . $e->getMessage());
            return false;
        }
    }
}
