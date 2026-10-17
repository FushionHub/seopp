<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/apikeys.php';
require_once __DIR__ . '/modules/notification_service.php';

$userId = current_user_id();
$platform = trim($_GET['platform'] ?? '');

// Support platforms that return state parameter instead of preserving query string.
// The stored nonce is the source of truth â€” a bare `state=meta` string is NOT
// accepted, otherwise account linking / login CSRF is possible.
if ($platform === '' && !empty($_GET['state'])) {
    $rawState = trim((string)$_GET['state']);
    if (str_starts_with($rawState, '{')) {
        $decoded = @json_decode($rawState, true);
        if (is_array($decoded)) {
            $platform = trim((string)($decoded['platform'] ?? ''));
            // JSON state must also carry the nonce we issued.
            $stateNonce = (string)($decoded['nonce'] ?? '');
            $issued     = (string)($_SESSION['oauth_state_' . $platform] ?? '');
            if ($issued === '' || $stateNonce === '' || !hash_equals($issued, $stateNonce)) {
                set_flash('error', 'OAuth state could not be verified. Please reconnect the account.');
                redirect(url('social-connect'));
            }
        }
    } else {
        $validPlatforms = ['meta', 'instagram', 'tiktok', 'google_ads', 'snapchat', 'twitter', 'linkedin', 'google_calendar', 'google_business'];
        foreach ($validPlatforms as $vp) {
            $issued = (string)($_SESSION['oauth_state_' . $vp] ?? '');
            if ($issued !== '' && hash_equals($issued, $rawState)) {
                $platform = $vp;
                unset($_SESSION['oauth_state_' . $vp]);   // single use
                break;
            }
        }
        // Fall back to an explicit ?platform= query param only.
        if ($platform === '' && isset($_GET['platform'])) {
            $platform = trim((string)$_GET['platform']);
        }
    }
}

$validPlatforms = ['meta', 'instagram', 'tiktok', 'google_ads', 'snapchat', 'twitter', 'linkedin', 'google_calendar', 'google_business'];
if (!in_array($platform, $validPlatforms, true)) {
    set_flash('error', 'Unknown OAuth provider.');
    redirect(url('social-connect'));
}

$code = trim($_GET['code'] ?? '');
$error = trim($_GET['error'] ?? $_GET['error_description'] ?? '');

if ($error !== '') {
    set_flash('error', 'OAuth authorization failed: ' . htmlspecialchars($error));
    redirect(url('social-connect'));
}

if ($platform === '' || $code === '') {
    set_flash('error', 'Missing authorization code or platform parameter from OAuth provider.');
    redirect(url('social-connect'));
}

// Live token exchange based on platform credentials in config/apikeys.php
$redirectUri = BASE_URL . '/oauth-callback.php?platform=' . urlencode($platform);
$accessToken = null;
$platformUserId = null;
$accountName = null;

try {
    if (in_array($platform, ['meta', 'instagram'], true)) {
        $appId = api_key('META_APP_ID', 'meta_app_id');
        $secret = api_key('META_APP_SECRET', 'meta_app_secret');
        if ($appId && $secret) {
            $url = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
                'client_id'     => $appId,
                'client_secret' => $secret,
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
            ]);
            $resp = @file_get_contents($url);
            if ($resp && ($json = json_decode($resp, true)) && isset($json['access_token'])) {
                $accessToken = $json['access_token'];
                $me = @json_decode(@file_get_contents("https://graph.facebook.com/v19.0/me?fields=id,name&access_token=" . urlencode($accessToken)), true);
                $accountName = $me['name'] ?? 'Meta Business Profile';
                $platformUserId = $me['id'] ?? 'meta_' . substr(md5($code), 0, 8);
            }
        }
    } elseif (str_starts_with($platform, 'google')) {
        $clientId = api_key('GOOGLE_CLIENT_ID', 'google_client_id');
        $secret = api_key('GOOGLE_CLIENT_SECRET', 'google_client_secret');
        if ($clientId && $secret) {
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'code'          => $code,
                    'client_id'     => $clientId,
                    'client_secret' => $secret,
                    'redirect_uri'  => $redirectUri,
                    'grant_type'    => 'authorization_code',
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            if ($resp && ($json = json_decode($resp, true)) && isset($json['access_token'])) {
                $accessToken = $json['access_token'];
                $me = @json_decode(@file_get_contents("https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . urlencode($accessToken)), true);
                $accountName = $me['email'] ?? 'Google Account';
                $platformUserId = $me['id'] ?? 'google_' . substr(md5($code), 0, 8);
            }
        }
    } elseif ($platform === 'tiktok') {
        $clientKey = api_key('TIKTOK_CLIENT_KEY', 'tiktok_client_key');
        $clientSecret = api_key('TIKTOK_CLIENT_SECRET', 'tiktok_client_secret');
        if ($clientKey && $clientSecret) {
            $ch = curl_init('https://open.tiktokapis.com/v2/oauth/token/');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_key'    => $clientKey,
                    'client_secret' => $clientSecret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => $redirectUri,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$resp, true);
            if (!empty($json['data']['access_token'])) {
                $accessToken = $json['data']['access_token'];
                $platformUserId = $json['data']['open_id'] ?? ('tiktok_' . substr(md5($code), 0, 8));
                // Fetch display name
                $meCh = curl_init('https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name,avatar_url');
                curl_setopt_array($meCh, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $meResp = json_decode((string)curl_exec($meCh), true);
                curl_close($meCh);
                $accountName = $meResp['data']['user']['display_name'] ?? 'TikTok Business Account';
            }
        }
    } elseif ($platform === 'twitter') {
        $clientId = api_key('TWITTER_CLIENT_ID', 'twitter_client_id');
        $clientSecret = api_key('TWITTER_CLIENT_SECRET', 'twitter_client_secret');
        if ($clientId) {
            $ch = curl_init('https://api.twitter.com/2/oauth2/token');
            $postFields = [
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
                'code_verifier' => 'challenge',
            ];
            $headers = ['Content-Type: application/x-www-form-urlencoded'];
            if ($clientSecret !== '') {
                $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postFields),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$resp, true);
            if (!empty($json['access_token'])) {
                $accessToken = $json['access_token'];
                $meCh = curl_init('https://api.twitter.com/2/users/me');
                curl_setopt_array($meCh, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $meResp = json_decode((string)curl_exec($meCh), true);
                curl_close($meCh);
                $accountName = isset($meResp['data']['username']) ? '@' . $meResp['data']['username'] : 'X / Twitter Profile';
                $platformUserId = $meResp['data']['id'] ?? ('tw_' . substr(md5($code), 0, 8));
            }
        }
    } elseif ($platform === 'linkedin') {
        $clientId = api_key('LINKEDIN_CLIENT_ID', 'linkedin_client_id');
        $clientSecret = api_key('LINKEDIN_CLIENT_SECRET', 'linkedin_client_secret');
        if ($clientId && $clientSecret) {
            $ch = curl_init('https://www.linkedin.com/oauth/v2/accessToken');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri'  => $redirectUri,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$resp, true);
            if (!empty($json['access_token'])) {
                $accessToken = $json['access_token'];
                $meCh = curl_init('https://api.linkedin.com/v2/userinfo');
                curl_setopt_array($meCh, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $meResp = json_decode((string)curl_exec($meCh), true);
                curl_close($meCh);
                $accountName = $meResp['name'] ?? 'LinkedIn Marketing Profile';
                $platformUserId = $meResp['sub'] ?? ('li_' . substr(md5($code), 0, 8));
            }
        }
    } elseif ($platform === 'snapchat') {
        $clientId = api_key('SNAPCHAT_CLIENT_ID', 'snapchat_client_id');
        $clientSecret = api_key('SNAPCHAT_CLIENT_SECRET', 'snapchat_client_secret');
        if ($clientId && $clientSecret) {
            $ch = curl_init('https://accounts.snapchat.com/login/oauth2/access_token');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => $redirectUri,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $json = json_decode((string)$resp, true);
            if (!empty($json['access_token'])) {
                $accessToken = $json['access_token'];
                $accountName = 'Snapchat Ad Account';
                $platformUserId = 'snap_' . substr(md5($code), 0, 8);
            }
        }
    }
} catch (\Throwable $e) {
    error_log("OAuth exchange failed for {$platform}: " . $e->getMessage());
}


if (!$accessToken) {
    set_flash('error', 'OAuth authorization failed: Unable to exchange authorization code with ' . ucfirst($platform) . '. Please ensure live API keys are configured in config/apikeys.php.');
    redirect(url('social-connect') . '?platform=' . urlencode($platform));
}

$existing = db()->fetchOne("SELECT id FROM connected_accounts WHERE user_id = ? AND platform = ?", [$userId, $platform]);
$metaData = [
    'connected_at'   => date('Y-m-d H:i:s'),
    'oauth_verified' => true,
    'sync_status'    => 'active',
];

if ($existing) {
    db()->update('connected_accounts', [
        'access_token'     => $accessToken,
        'platform_user_id' => $platformUserId,
        'account_name'     => $accountName,
        'metadata_json'    => json_encode($metaData),
        'token_expiry'     => date('Y-m-d H:i:s', strtotime('+60 days')),
    ], 'id = ?', [(int)$existing['id']]);
} else {
    db()->insert('connected_accounts', [
        'user_id'          => $userId,
        'platform'         => $platform,
        'access_token'     => $accessToken,
        'platform_user_id' => $platformUserId,
        'account_name'     => $accountName,
        'metadata_json'    => json_encode($metaData),
        'token_expiry'     => date('Y-m-d H:i:s', strtotime('+60 days')),
    ]);
}

try {
    NotificationService::create(
        $userId,
        'social_connected',
        'OAuth Account Connected',
        ucfirst($platform) . ' was successfully connected via OAuth.',
        '/social-connect.php'
    );
} catch (\Throwable $e) {}

set_flash('success', ucfirst($platform) . ' successfully authorized and connected via OAuth!');
redirect(url('social-connect'));
