<?php
declare(strict_types=1);

/**
 * Site Configuration & Settings Loader
 * Adsbooster.net
 * 
 * Derives BASE_URL from the incoming request.
 * Provides get_setting() and get_content() helpers with in-request static cache.
 */

// Prevent double inclusion
if (defined('BASE_URL')) {
    return;
}

// ============================================================
// Dynamic BASE_URL derivation
// ============================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
$appDir = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));

$subDir = '';
if (!empty($docRoot) && !empty($appDir) && str_starts_with(strtolower($appDir), strtolower($docRoot))) {
    $subDir = substr($appDir, strlen($docRoot));
    $subDir = '/' . trim(str_replace('\\', '/', $subDir), '/');
    if ($subDir === '/') {
        $subDir = '';
    }
} else {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('/^[a-zA-Z]:/i', $scriptName)) {
        $scriptName = '';
    }
    $scriptDir = rtrim(dirname($scriptName), '/\\');
    if ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\') {
        $scriptDir = '';
    }
    $subDir = $scriptDir;
}

$baseUrl = $protocol . '://' . $host . $subDir;

define('BASE_URL', $baseUrl);
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_URL', BASE_URL . '/assets');

/**
 * Generate clean URL (without .php extension)
 */
function url(string $path = '', array $params = []): string
{
    $cleanPath = preg_replace('/\.php$/i', '', ltrim($path, '/'));
    
    // Clean parameterized routes
    if ($cleanPath === 'bounty-details' && isset($params['id'])) {
        $cleanPath .= '/' . urlencode((string)$params['id']);
        unset($params['id']);
    } elseif ($cleanPath === 'portfolio' && isset($params['u'])) {
        $cleanPath .= '/' . urlencode((string)$params['u']);
        unset($params['u']);
    } elseif ($cleanPath === 'client-approval' && isset($params['token'])) {
        $cleanPath .= '/' . urlencode((string)$params['token']);
        unset($params['token']);
    }

    $url = empty($cleanPath) ? BASE_URL : BASE_URL . '/' . $cleanPath;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

// ============================================================
// Harden output before anything else can leak a stack trace
// ============================================================
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@ini_set('html_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// ============================================================
// Start session (hardened cookie parameters)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    // Harden the session id before it is ever generated.
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.sid_length', '48');
    @ini_set('session.sid_bits_per_character', '6');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // true in production behind TLS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Rotate the id periodically to blunt session fixation / hijacking.
    if (empty($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif ((time() - (int)$_SESSION['__created']) > 1800 && !headers_sent()) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }
}
if (ob_get_level() === 0) {
    ob_start();
}

// ============================================================
// Autoload vendor dependencies (PHPMailer, etc.)
// ============================================================
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// ============================================================
// Database connection
// ============================================================
require_once ROOT_PATH . '/config/db.php';

// ============================================================
// Central API keys store (constants + api_key() helper)
// ============================================================
require_once ROOT_PATH . '/config/apikeys.php';

// ============================================================
// Central security helpers (escaping, sanitizer, uploads, SSRF)
// ============================================================
require_once ROOT_PATH . '/includes/security.php';

// ============================================================
// Settings cache (single query per page load)
// ============================================================
static $settingsCache = null;
static $contentCache = null;

/**
 * Get a site setting by key
 */
function get_setting(string $key, string $default = ''): string
{
    global $settingsCache;

    if ($settingsCache === null) {
        $rows = db()->fetchAll("SELECT setting_key, setting_value FROM site_settings");
        $settingsCache = [];
        foreach ($rows as $row) {
            $settingsCache[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settingsCache[$key] ?? $default;
}

/**
 * Get dynamic content by page/section/item
 */
function get_content(string $page, string $section, string $item, string $fallback = ''): string
{
    global $contentCache;

    if ($contentCache === null) {
        $rows = db()->fetchAll(
            "SELECT page_slug, section_key, item_key, content_value, media_url FROM dynamic_content"
        );
        $contentCache = [];
        foreach ($rows as $row) {
            $key = $row['page_slug'] . '|' . $row['section_key'] . '|' . $row['item_key'];
            $contentCache[$key] = $row;
        }
    }

    $key = $page . '|' . $section . '|' . $item;
    if (isset($contentCache[$key])) {
        return $contentCache[$key]['content_value'] ?? $fallback;
    }

    return $fallback;
}

/**
 * Get dynamic content media URL
 */
function get_content_media(string $page, string $section, string $item, string $fallback = ''): string
{
    global $contentCache;

    if ($contentCache === null) {
        $rows = db()->fetchAll(
            "SELECT page_slug, section_key, item_key, content_value, media_url FROM dynamic_content"
        );
        $contentCache = [];
        foreach ($rows as $row) {
            $key = $row['page_slug'] . '|' . $row['section_key'] . '|' . $row['item_key'];
            $contentCache[$key] = $row;
        }
    }

    $key = $page . '|' . $section . '|' . $item;
    if (isset($contentCache[$key]) && !empty($contentCache[$key]['media_url'])) {
        return $contentCache[$key]['media_url'];
    }

    return $fallback;
}

/**
 * Generate CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Output a hidden CSRF input field
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Check if user is authenticated
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function current_user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Check if current user is admin
 */
function is_admin(): bool
{
    return current_user_role() === 'admin';
}

/**
 * Redirect helper
 */
function redirect(string $url): void
{
    if (!headers_sent()) {
        header("Location: {$url}");
        exit;
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Sanitize output
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helper
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// ============================================================
// CSRF alias (used by auth files)
// ============================================================
function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return verify_csrf($token);
}

// ============================================================
// Current user helper
// ============================================================
function current_user(): ?array
{
    $userId = current_user_id();
    if (!$userId) {
        return null;
    }
    return db()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
}

// ============================================================
// User login (sets session data)
// ============================================================
function auth_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']         = (int)$user['id'];
    $_SESSION['user_role']       = $user['role'];
    $_SESSION['user_name']       = $user['full_name'];
    $_SESSION['user_email']      = $user['email'];
    $_SESSION['account_type']    = $user['account_type'] ?? 'individual';
    $_SESSION['logged_in']       = true;
}

// ============================================================
// Admin login (sets admin session data)
// ============================================================
function auth_admin_login(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']    = (int)$admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name']  = $admin['full_name'];
    $_SESSION['admin_role']  = $admin['role'];
    $_SESSION['admin_logged_in'] = true;
}

// ============================================================
// Simple rate limiter (file-based)
// ============================================================
function check_rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $cacheDir = ROOT_PATH . '/cache/rate_limit';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $filePath = $cacheDir . '/' . md5($key . $ip) . '.json';

    $data = ['attempts' => 0, 'first_at' => time()];
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        $data = json_decode($raw, true) ?: $data;
    }

    if ((time() - ($data['first_at'] ?? 0)) > $windowSeconds) {
        $data = ['attempts' => 0, 'first_at' => time()];
    }

    if ($data['attempts'] >= $maxAttempts) {
        return false;
    }

    $data['attempts']++;
    file_put_contents($filePath, json_encode($data));
    return true;
}

// ============================================================
// Remember Me Authentication System
// ============================================================

/**
 * Issue a secure remember me token, store hash in DB, and set browser cookies.
 */
function issue_remember_token(int $userId): void
{
    try {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

        db()->update('users', [
            'remember_token'            => $hashedToken,
            'remember_token_expires_at' => $expiresAt,
        ], 'id = ?', [$userId]);

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $cookieParams = [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        setcookie('remember_token', $token, $cookieParams);
        setcookie('remember_user', (string)$userId, $cookieParams);
        $_COOKIE['remember_token'] = $token;
        $_COOKIE['remember_user'] = (string)$userId;
    } catch (\Throwable $e) {
        error_log('issue_remember_token error: ' . $e->getMessage());
    }
}

/**
 * Clear remember me token in DB and invalidate cookies.
 */
function clear_remember_token(?int $userId = null): void
{
    try {
        if ($userId !== null && $userId > 0) {
            db()->update('users', [
                'remember_token'            => null,
                'remember_token_expires_at' => null,
            ], 'id = ?', [$userId]);
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $cookieParams = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        setcookie('remember_token', '', $cookieParams);
        setcookie('remember_user', '', $cookieParams);
        unset($_COOKIE['remember_token'], $_COOKIE['remember_user']);
    } catch (\Throwable $e) {
        error_log('clear_remember_token error: ' . $e->getMessage());
    }
}

/**
 * Middleware: Verify remember me cookie and automatically authenticate session.
 */
function check_remember_login(): void
{
    if (is_logged_in()) {
        return;
    }

    $rawUserId = $_COOKIE['remember_user'] ?? '';
    $rawToken  = $_COOKIE['remember_token'] ?? '';

    if (empty($rawUserId) || empty($rawToken) || !is_numeric($rawUserId)) {
        return;
    }

    $userId = (int)$rawUserId;
    if ($userId <= 0) {
        return;
    }

    try {
        $user = db()->fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [$userId]);
        if (!$user || empty($user['remember_token'])) {
            clear_remember_token($userId);
            return;
        }

        // Check if token has expired
        if (!empty($user['remember_token_expires_at']) && strtotime($user['remember_token_expires_at']) < time()) {
            clear_remember_token($userId);
            return;
        }

        // Compare SHA-256 hash using timing-safe comparison
        $inputHash = hash('sha256', $rawToken);
        if (!hash_equals($user['remember_token'], $inputHash)) {
            clear_remember_token($userId);
            return;
        }

        // Login user
        auth_login($user);

        // Rotate remember token to prevent replay attacks
        issue_remember_token($userId);
    } catch (\Throwable $e) {
        error_log('check_remember_login error: ' . $e->getMessage());
    }
}

// Automatically check and restore user session from Remember Me cookie
check_remember_login();

