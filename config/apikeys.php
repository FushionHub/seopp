<?php declare(strict_types=1);

/**
 * Central API Keys Store — Adsbooster.net
 *
 * Single source of truth for ALL live integration credentials:
 * social platforms, Google (Calendar/Business/Ads), AI models,
 * payments, email/SMTP, scraper.
 *
 * Priority order for every key (see api_key() below):
 *   1. PHP constant defined here (deploy-time override)
 *   2. Environment variable (production-safe)
 *   3. `site_settings` DB row (editable from Admin CMS)
 *
 * To go live: fill the empty '' values below OR set env vars
 * OR update the matching site_settings rows from admin/cms.php.
 */

// ── Meta (Facebook + Instagram via Meta OAuth) ──
// Local development overrides are loaded FIRST so they can fill these blanks.
// `config/keys.local.php` is never web-served (see .htaccess) and is git-ignored.
$__localKeys = __DIR__ . '/keys.local.php';
if (is_readable($__localKeys)) {
    /** @var array<string,string> $__local */
    $__local = (static function () use ($__localKeys): array {
        $v = (static function () use ($__localKeys) { return require $__localKeys; })();
        return is_array($v) ? $v : [];
    })();
    foreach ($__local as $__k => $__v) {
        if (is_string($__v) && $__v !== '' && !defined($__k)) {
            define($__k, $__v);
        }
    }
    unset($__local, $__k, $__v);
}
unset($__localKeys);

defined('META_APP_ID')     or define('META_APP_ID', '');
defined('META_APP_SECRET') or define('META_APP_SECRET', '');

// ── TikTok ──
defined('TIKTOK_CLIENT_KEY')    or define('TIKTOK_CLIENT_KEY', '');
defined('TIKTOK_CLIENT_SECRET') or define('TIKTOK_CLIENT_SECRET', '');

// ── Google: OAuth (Calendar + Business Profile + Ads share this) ──
defined('GOOGLE_CLIENT_ID')     or define('GOOGLE_CLIENT_ID', '');
defined('GOOGLE_CLIENT_SECRET') or define('GOOGLE_CLIENT_SECRET', '');
// Google Ads developer token (separate from OAuth)
defined('GOOGLE_ADS_DEVELOPER_TOKEN') or define('GOOGLE_ADS_DEVELOPER_TOKEN', '');

// ── Snapchat ──
defined('SNAPCHAT_CLIENT_ID')     or define('SNAPCHAT_CLIENT_ID', '');
defined('SNAPCHAT_CLIENT_SECRET') or define('SNAPCHAT_CLIENT_SECRET', '');

// ── Twitter / X (OAuth 2.0) ──
defined('TWITTER_CLIENT_ID')     or define('TWITTER_CLIENT_ID', '');
defined('TWITTER_CLIENT_SECRET') or define('TWITTER_CLIENT_SECRET', '');

// ── LinkedIn (OAuth 2.0) ──
defined('LINKEDIN_CLIENT_ID')     or define('LINKEDIN_CLIENT_ID', '');
defined('LINKEDIN_CLIENT_SECRET') or define('LINKEDIN_CLIENT_SECRET', '');

// ── AI Models ──
// SECURITY: never commit live credentials. Leave these empty and supply the
// real values through one of the three supported channels:
//   1. Environment variables (recommended in production)
//   2. `config/keys.local.php` (development only — blocked by .htaccess + .gitignore)
//   3. `site_settings` rows, editable from Admin → Settings
defined('GROQ_API_KEY')       or define('GROQ_API_KEY', '');
defined('GEMINI_API_KEY')     or define('GEMINI_API_KEY', '');
defined('OPENROUTER_API_KEY') or define('OPENROUTER_API_KEY', '');

// ── AI Video renderers (optional — the built-in Storyboard Studio needs none) ──
defined('REPLICATE_API_TOKEN') or define('REPLICATE_API_TOKEN', '');
defined('FAL_KEY')             or define('FAL_KEY', '');
defined('RUNWAY_API_KEY')      or define('RUNWAY_API_KEY', '');
defined('LUMA_API_KEY')        or define('LUMA_API_KEY', '');

// ── Scraper ──
defined('FIRECRAWL_API_KEY') or define('FIRECRAWL_API_KEY', '');

// ── Payments: Paystack ──
defined('PAYSTACK_PUBLIC_KEY') or define('PAYSTACK_PUBLIC_KEY', '');
defined('PAYSTACK_SECRET_KEY') or define('PAYSTACK_SECRET_KEY', '');
defined('PAYSTACK_TEST_KEY')   or define('PAYSTACK_TEST_KEY', '');
defined('PAYSTACK_LIVE_KEY')   or define('PAYSTACK_LIVE_KEY', '');

// ── Payments: Flutterwave ──
defined('FLUTTERWAVE_PUBLIC_KEY') or define('FLUTTERWAVE_PUBLIC_KEY', '');
defined('FLUTTERWAVE_SECRET_KEY') or define('FLUTTERWAVE_SECRET_KEY', '');
defined('FLUTTERWAVE_TEST_KEY')   or define('FLUTTERWAVE_TEST_KEY', '');
defined('FLUTTERWAVE_LIVE_KEY')   or define('FLUTTERWAVE_LIVE_KEY', '');

// ── Email / SMTP (Email-first architecture) ──
defined('SMTP_HOST')  or define('SMTP_HOST', '');
defined('SMTP_PORT')  or define('SMTP_PORT', '587');
defined('SMTP_USER')  or define('SMTP_USER', '');
defined('SMTP_PASS')  or define('SMTP_PASS', '');
defined('FROM_EMAIL') or define('FROM_EMAIL', 'support@adsbooster.net');
defined('FROM_NAME')  or define('FROM_NAME', 'Adsbooster.net');

if (!function_exists('api_key')) {
    /**
     * Resolve an integration key with constant → env → DB fallback.
     *
     * @param string $constant  PHP constant name, e.g. 'GROQ_API_KEY'
     * @param string $dbKey     site_settings key, e.g. 'groq_api_key'
     * @param string $default   fallback when nothing is configured
     */
    function api_key(string $constant, string $dbKey = '', string $default = ''): string
    {
        if (defined($constant) && (string)constant($constant) !== '') {
            return (string)constant($constant);
        }
        $env = getenv($constant);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        if ($dbKey !== '' && function_exists('get_setting')) {
            try {
                $v = get_setting($dbKey, '');
                if ($v !== '') return $v;
            } catch (\Throwable $e) { /* DB unavailable — fall through */ }
        }
        return $default;
    }
}

if (!function_exists('integration_status')) {
    /**
     * Live-readiness summary for every integration.
     * Used by ai-settings.php, social hub, billing, admin.
     * @return array<string,array{configured:bool,label:string}>
     */
    function integration_status(): array
    {
        $live = function (string $c, string $db): bool {
            return api_key($c, $db, '') !== '';
        };
        return [
            'meta'            => ['configured' => $live('META_APP_ID', 'meta_app_id'),            'label' => 'Meta / Facebook'],
            'instagram'       => ['configured' => $live('META_APP_ID', 'meta_app_id'),            'label' => 'Instagram (via Meta)'],
            'tiktok'          => ['configured' => $live('TIKTOK_CLIENT_KEY', 'tiktok_client_key'),'label' => 'TikTok'],
            'google_ads'      => ['configured' => $live('GOOGLE_ADS_DEVELOPER_TOKEN', 'google_ads_developer_token') || $live('GOOGLE_CLIENT_ID', 'google_client_id'), 'label' => 'Google Ads'],
            'snapchat'        => ['configured' => $live('SNAPCHAT_CLIENT_ID', 'snapchat_client_id'), 'label' => 'Snapchat'],
            'google_calendar' => ['configured' => $live('GOOGLE_CLIENT_ID', 'google_client_id'),  'label' => 'Google Calendar'],
            'google_business' => ['configured' => $live('GOOGLE_CLIENT_ID', 'google_client_id'),  'label' => 'Google Business'],
            'twitter'         => ['configured' => $live('TWITTER_CLIENT_ID', 'twitter_client_id'), 'label' => 'Twitter / X'],
            'linkedin'        => ['configured' => $live('LINKEDIN_CLIENT_ID', 'linkedin_client_id'), 'label' => 'LinkedIn'],
            'groq'            => ['configured' => $live('GROQ_API_KEY', 'groq_api_key'),          'label' => 'Groq (AI text)'],
            'gemini'          => ['configured' => $live('GEMINI_API_KEY', 'gemini_api_key'),      'label' => 'Gemini (AI image)'],
            'openrouter'      => ['configured' => $live('OPENROUTER_API_KEY', 'openrouter_api_key'), 'label' => 'OpenRouter (AI video)'],
            'paystack'        => ['configured' => $live('PAYSTACK_SECRET_KEY', 'paystack_secret_key') || $live('PAYSTACK_LIVE_KEY', 'paystack_live_key'), 'label' => 'Paystack'],
            'flutterwave'     => ['configured' => $live('FLUTTERWAVE_SECRET_KEY', 'flutterwave_secret_key') || $live('FLUTTERWAVE_LIVE_KEY', 'flutterwave_live_key'), 'label' => 'Flutterwave'],
        ];
    }
}

if (!function_exists('oauth_auth_url')) {
    /**
     * Build standard OAuth authorization redirect URL for a social platform.
     */
    function oauth_auth_url(string $platform, string $redirectUri, string $state = ''): ?string
    {
        $stateParam = $state !== '' ? $state : bin2hex(random_bytes(16));
        $_SESSION['oauth_state_' . $platform] = $stateParam;

        return match ($platform) {
            'meta', 'instagram' => (function() use ($redirectUri, $stateParam) {
                $appId = api_key('META_APP_ID', 'meta_app_id');
                if ($appId === '') return null;
                $scopes = 'public_profile,email,pages_show_list,pages_read_engagement,pages_manage_posts,ads_management,instagram_basic,instagram_content_publish';
                return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
                    'client_id'     => $appId,
                    'redirect_uri'  => $redirectUri,
                    'state'         => $stateParam,
                    'scope'         => $scopes,
                    'response_type' => 'code',
                ]);
            })(),
            'tiktok' => (function() use ($redirectUri, $stateParam) {
                $clientKey = api_key('TIKTOK_CLIENT_KEY', 'tiktok_client_key');
                if ($clientKey === '') return null;
                return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
                    'client_key'    => $clientKey,
                    'scope'         => 'user.info.basic,video.upload,video.publish',
                    'response_type' => 'code',
                    'redirect_uri'  => $redirectUri,
                    'state'         => $stateParam,
                ]);
            })(),
            'google_ads', 'google_calendar', 'google_business' => (function() use ($redirectUri, $stateParam) {
                $clientId = api_key('GOOGLE_CLIENT_ID', 'google_client_id');
                if ($clientId === '') return null;
                return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id'              => $clientId,
                    'redirect_uri'           => $redirectUri,
                    'response_type'          => 'code',
                    'scope'                  => 'openid email profile https://www.googleapis.com/auth/adwords https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/business.manage',
                    'access_type'            => 'offline',
                    'prompt'                 => 'consent',
                    'state'                  => $stateParam,
                ]);
            })(),
            'snapchat' => (function() use ($redirectUri, $stateParam) {
                $clientId = api_key('SNAPCHAT_CLIENT_ID', 'snapchat_client_id');
                if ($clientId === '') return null;
                return 'https://accounts.snapchat.com/login/oauth2/authorize?' . http_build_query([
                    'client_id'     => $clientId,
                    'redirect_uri'  => $redirectUri,
                    'response_type' => 'code',
                    'scope'         => 'snapchat-marketing-api',
                    'state'         => $stateParam,
                ]);
            })(),
            'twitter' => (function() use ($redirectUri, $stateParam) {
                $clientId = api_key('TWITTER_CLIENT_ID', 'twitter_client_id');
                if ($clientId === '') return null;
                return 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
                    'client_id'             => $clientId,
                    'redirect_uri'          => $redirectUri,
                    'response_type'         => 'code',
                    'scope'                 => 'tweet.read tweet.write users.read offline.access',
                    'state'                 => $stateParam,
                    'code_challenge'        => 'challenge',
                    'code_challenge_method' => 'plain',
                ]);
            })(),
            'linkedin' => (function() use ($redirectUri, $stateParam) {
                $clientId = api_key('LINKEDIN_CLIENT_ID', 'linkedin_client_id');
                if ($clientId === '') return null;
                return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                    'client_id'     => $clientId,
                    'redirect_uri'  => $redirectUri,
                    'response_type' => 'code',
                    'scope'         => 'openid profile email w_member_social',
                    'state'         => $stateParam,
                ]);
            })(),
            default => null,
        };
    }
}
