<?php declare(strict_types=1);

$pageTitle = 'Developer API Documentation (A–Z) — Adsbooster.net';
$pageDescription = 'Comprehensive reference manual for integrating Adsbooster REST API v1, multi-network social posting, ad campaigns, AI UGC image generation, and webhooks.';
require_once __DIR__ . '/includes/app_header.php';
?>

<style>
.dev-doc-nav {
    position: sticky;
    top: 90px;
}
.dev-doc-nav a {
    color: #94a3b8;
    text-decoration: none;
    display: block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.dev-doc-nav a:hover, .dev-doc-nav a.active {
    color: #c4f000;
    background: rgba(196, 240, 0, 0.08);
}
.method-pill {
    font-size: 11px;
    font-weight: 800;
    padding: 4px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.method-get { background: rgba(56, 189, 248, 0.15); color: #38bdf8; }
.method-post { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.method-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.code-block {
    background: #060910 !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 10px;
    padding: 16px;
    font-family: 'Fira Code', 'Courier New', monospace;
    font-size: 12.5px;
    color: #e2e8f0;
    position: relative;
    overflow-x: auto;
}
.copy-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #94a3b8;
    border-radius: 6px;
    font-size: 11px;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.copy-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.15);
}
</style>

<div class="w-100">
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">
                <i class="ph-bold ph-code text-accent me-2"></i> Developer Documentation &amp; REST API (A–Z)
            </h1>
            <p class="page-subtitle mb-0">Build automated workflows, generate AI UGC ad imagery, publish posts live to connected social networks, manage ad budgets, and receive signed webhooks.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('api-settings') ?>" class="btn btn-accent btn-sm rounded-pill px-3">
                <i class="ph ph-key me-1"></i> Developer Console
            </a>
            <a href="<?= url('status') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="ph ph-activity me-1"></i> System Status (99.99%)
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Navigation (Sticky) -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="card-dark p-3 rounded-4 dev-doc-nav" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="text-white-50 font-size-11 fw-bold text-uppercase px-2 mb-2">Getting Started</div>
                <a href="#quickstart">Overview &amp; Base URL</a>
                <a href="#authentication">Unified API Key Auth</a>
                <a href="#ratelimits">Rate Limits (60 req/min)</a>
                <a href="#webhooks">Webhooks &amp; Signatures</a>
                <a href="#oauth-redirects">OAuth Redirect URIs</a>
                
                <div class="text-white-50 font-size-11 fw-bold text-uppercase px-2 mt-3 mb-2">Endpoint Reference</div>

                <a href="#ep-credits">1. Balances &amp; Wallet</a>
                <a href="#ep-campaigns">2. Ad Campaigns</a>
                <a href="#ep-posts">3. Multi-Network Social Posts</a>
                <a href="#ep-ugc">4. AI UGC Image Studio</a>
                <a href="#ep-directory">5. Verified Directory &amp; Leads</a>
                <a href="#ep-reviews">6. Platform Reviews &amp; Ratings</a>
                <a href="#ep-webhooks">7. Webhooks Management</a>

                <div class="text-white-50 font-size-11 fw-bold text-uppercase px-2 mt-3 mb-2">Client SDKs</div>
                <a href="#sdks">cURL, JavaScript, Python, PHP</a>
            </div>
        </div>

        <!-- Main Documentation Body -->
        <div class="col-lg-9">

            <!-- Section: Overview & Base URL -->
            <div id="quickstart" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">Getting Started with Adsbooster API v1</h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    The Adsbooster API provides programmatic access to our autonomous ad scaling engine, multi-channel social posting suite, AI UGC image studio, and local verified business directory. All requests require HTTPS and return standard JSON payloads.
                </p>

                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 flex-wrap gap-2 mb-2" style="background:#060910;border:1px solid rgba(255,255,255,0.08);">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-main-600 bg-opacity-20 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-2 font-size-11">Production Base URL</span>
                        <code class="text-accent fw-bold font-size-14"><?= BASE_URL ?>/api/v1</code>
                    </div>
                    <span class="text-white-50 font-size-12">Format: JSON / UTF-8</span>
                </div>
            </div>

            <!-- Section: Authentication -->
            <div id="authentication" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">
                    <i class="ph-bold ph-shield-check text-accent me-1"></i> Single Master API Key Protection
                </h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    Adsbooster uses a single unified Master API Key for all features across ad campaigns, social publishing, AI image generation, and directory APIs. You can pass your key using either of these three secure formats:
                </p>

                <div class="code-block mb-3">
                    <button class="copy-btn" onclick="navigator.clipboard.writeText('X-API-Key: adsb_live_your_api_key_here')">Copy</button>
# Option 1: Custom Header (Recommended)
X-API-Key: adsb_live_your_api_key_here

# Option 2: Standard Bearer Token
Authorization: Bearer adsb_live_your_api_key_here

# Option 3: Query Parameter
GET <?= BASE_URL ?>/api/v1/credits.php?api_key=adsb_live_your_api_key_here
                </div>
                <div class="p-3 rounded-3" style="background:rgba(200,255,0,0.05);border:1px solid rgba(200,255,0,0.2);">
                    <span class="text-accent fw-bold font-size-13">Security Note:</span>
                    <span class="text-white-50 font-size-13">Your API key is hashed with SHA-256 in our database. If an API key is compromised, you can revoke and regenerate it instantly from your <a href="<?= url('api-settings') ?>" class="text-accent text-decoration-none hover-underline">API Settings</a> console.</span>
                </div>
            </div>

            <!-- Section: Rate Limits -->
            <div id="ratelimits" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">
                    <i class="ph-bold ph-gauge text-accent me-1"></i> Rate Limiting &amp; HTTP Headers
                </h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    To guarantee platform stability and prevent abuse, API requests are governed by a <strong>60 requests per minute</strong> sliding window. Standard rate-limit headers are returned on every single API response:
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-bordered border-secondary border-opacity-15 font-size-13 mb-0">
                        <thead>
                            <tr>
                                <th>Header</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>X-RateLimit-Limit</code></td>
                                <td>Maximum permitted requests per minute</td>
                                <td class="text-accent">60</td>
                            </tr>
                            <tr>
                                <td><code>X-RateLimit-Remaining</code></td>
                                <td>Remaining quota in the current 60-second window</td>
                                <td class="text-accent">58</td>
                            </tr>
                            <tr>
                                <td><code>X-RateLimit-Reset</code></td>
                                <td>Unix timestamp when the quota resets</td>
                                <td class="text-accent"><?= time() + 45 ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-13 mb-0" style="color:#cbd5e1 !important;">
                    When your quota is exceeded, the server returns <code>HTTP 429 Too Many Requests</code> with a <code>Retry-After</code> header indicating seconds to wait.
                </p>
            </div>

            <!-- Section: Webhooks -->
            <div id="webhooks" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-2">
                    <i class="ph-bold ph-webhooks-logo text-accent me-1"></i> Webhooks &amp; HMAC Signature Verification
                </h3>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    Adsbooster dispatches real-time webhook events to your server when campaigns are updated, posts are published live, or ad spend milestones are reached. Every webhook contains an <code>X-Adsbooster-Signature</code> header generated using HMAC-SHA256.
                </p>

                <div class="code-block mb-3">
                    <button class="copy-btn" onclick="navigator.clipboard.writeText('$computed = hash_hmac(\'sha256\', file_get_contents(\'php://input\'), $webhookSecret);')">Copy</button>
// PHP Example: Verifying Webhook Signatures
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ADSBOOSTER_SIGNATURE'] ?? '';
$secret = 'whsec_your_webhook_signing_secret';

$expectedSignature = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    die('Invalid Webhook Signature');
}
// Proceed to handle payload safely
                </div>
            </div>

            <!-- Section: OAuth 2.0 Redirect URIs & Social App Setup -->
            <div id="oauth-redirects" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h3 class="text-white fw-bold h5 mb-0">
                        <i class="ph-bold ph-link text-accent me-1"></i> OAuth 2.0 Redirect URIs &amp; Social Media Setup
                    </h3>
                    <span class="badge bg-main-600 bg-opacity-15 text-main-600 border border-main-600 border-opacity-30 rounded-pill px-2 py-0 font-size-11 fw-bold">Live Multi-Network</span>
                </div>
                <p class="text-white-50 font-size-14 mb-3" style="color:#cbd5e1 !important;">
                    When configuring developer applications in each social platform's developer portal, register these exact Authorized OAuth Redirect URIs (Callback URLs). Adsbooster handles the authorization code exchange and token refresh in real time via <code class="text-accent"><?= BASE_URL ?>/oauth-callback.php</code>.
                </p>

                <!-- Global Base Redirect URI -->
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 flex-wrap gap-2 mb-4" style="background:#060910;border:1px solid rgba(255,255,255,0.08);">
                    <div>
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase">Universal Master Redirect URI</div>
                        <code class="text-accent fw-bold font-size-14"><?= BASE_URL ?>/oauth-callback.php</code>
                    </div>
                    <button class="btn btn-outline-light btn-sm rounded-pill font-size-12 px-3" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php')">
                        <i class="ph ph-copy me-1"></i> Copy Universal URI
                    </button>
                </div>

                <!-- Platform Details Cards/Table -->
                <div class="table-responsive">
                    <table class="table table-dark align-middle mb-0" style="background:transparent; border-color: rgba(255,255,255,0.08);">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.12); font-size:11px; text-transform:uppercase; color:#94a3b8;">
                                <th style="width:180px;">Platform</th>
                                <th>Authorized Redirect URI (Callback URL)</th>
                                <th>Developer Portal &amp; Settings Path</th>
                                <th style="width:170px;">Required Scopes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Meta / Facebook / Instagram -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(24,119,242,0.15);display:flex;align-items:center;justify-content:center;color:#1877F2;">
                                            <i class="ph-bold ph-facebook-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Meta (Facebook &amp; Instagram)</div>
                                            <div class="text-white-50 font-size-11">Graph API v19.0+</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);"><?= BASE_URL ?>/oauth-callback.php?platform=meta</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php?platform=meta')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Instagram URI: <code><?= BASE_URL ?>/oauth-callback.php?platform=instagram</code></div>
                                </td>
                                <td>
                                    <a href="https://developers.facebook.com/apps" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Meta for Developers <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Facebook Login &rarr; Settings &rarr; Valid OAuth Redirect URIs</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">pages_manage_posts, instagram_content_publish, ads_management</span>
                                </td>
                            </tr>

                            <!-- TikTok -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(0,242,254,0.15);display:flex;align-items:center;justify-content:center;color:#00f2fe;">
                                            <i class="ph-bold ph-tiktok-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">TikTok for Business</div>
                                            <div class="text-white-50 font-size-11">Commercial Content API</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);"><?= BASE_URL ?>/oauth-callback.php?platform=tiktok</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php?platform=tiktok')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Clean fallback: <code><?= BASE_URL ?>/oauth-callback.php</code></div>
                                </td>
                                <td>
                                    <a href="https://business-api.tiktok.com/portal/" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        TikTok Developer Portal <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">App Details &rarr; Redirect URI</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">user.info.basic, video.upload, video.publish</span>
                                </td>
                            </tr>

                            <!-- Twitter / X -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;color:#fff;">
                                            <i class="ph-bold ph-x-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Twitter / X</div>
                                            <div class="text-white-50 font-size-11">OAuth 2.0 PKCE v2</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);"><?= BASE_URL ?>/oauth-callback.php?platform=twitter</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php?platform=twitter')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Clean fallback: <code><?= BASE_URL ?>/oauth-callback.php</code></div>
                                </td>
                                <td>
                                    <a href="https://developer.x.com/en/portal/dashboard" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        X Developer Portal <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">User authentication &rarr; Callback / Redirect URL</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">tweet.read, tweet.write, users.read, offline.access</span>
                                </td>
                            </tr>

                            <!-- LinkedIn -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(10,102,194,0.15);display:flex;align-items:center;justify-content:center;color:#0A66C2;">
                                            <i class="ph-bold ph-linkedin-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">LinkedIn Marketing</div>
                                            <div class="text-white-50 font-size-11">Consumer &amp; Share v2</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);"><?= BASE_URL ?>/oauth-callback.php?platform=linkedin</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php?platform=linkedin')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Clean fallback: <code><?= BASE_URL ?>/oauth-callback.php</code></div>
                                </td>
                                <td>
                                    <a href="https://www.linkedin.com/developers/apps" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        LinkedIn Developer Portal <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Auth &rarr; Authorized redirect URLs for your app</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">openid, profile, email, w_member_social</span>
                                </td>
                            </tr>

                            <!-- Google Ads & Services -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(66,133,244,0.15);display:flex;align-items:center;justify-content:center;color:#4285F4;">
                                            <i class="ph-bold ph-google-logo font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Google Cloud &amp; Ads</div>
                                            <div class="text-white-50 font-size-11">Google Ads, Calendar, Business</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);"><?= BASE_URL ?>/oauth-callback.php?platform=google_ads</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php?platform=google_ads')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Calendar: <code><?= BASE_URL ?>/oauth-callback.php?platform=google_calendar</code></div>
                                    <div class="text-white-50 font-size-11">Clean Google URI: <code><?= BASE_URL ?>/oauth-callback.php</code></div>
                                </td>
                                <td>
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Google Cloud Console <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">Credentials &rarr; OAuth 2.0 Client ID &rarr; Authorized redirect URIs</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">openid, email, profile, https://www.googleapis.com/auth/adwords</span>
                                </td>
                            </tr>

                            <!-- Snapchat -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(255,252,0,0.15);display:flex;align-items:center;justify-content:center;color:#FFFC00;">
                                            <i class="ph-bold ph-ghost font-size-16"></i>
                                        </div>
                                        <div>
                                            <div class="text-white fw-bold font-size-13">Snapchat Ads</div>
                                            <div class="text-white-50 font-size-11">Snap Marketing API</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <code class="text-accent font-size-12" style="background:#060910;padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.08);"><?= BASE_URL ?>/oauth-callback.php?platform=snapchat</code>
                                        <button class="btn btn-outline-light btn-sm p-1 px-2 font-size-11" onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/oauth-callback.php?platform=snapchat')" title="Copy URI"><i class="ph ph-copy"></i></button>
                                    </div>
                                    <div class="text-white-50 font-size-11 mt-1">Clean fallback: <code><?= BASE_URL ?>/oauth-callback.php</code></div>
                                </td>
                                <td>
                                    <a href="https://businessapi.snapchat.com/portal/" target="_blank" class="text-white font-size-12 text-decoration-none hover-underline d-inline-flex align-items-center gap-1">
                                        Snap Business Developer <i class="ph ph-arrow-square-out font-size-10"></i>
                                    </a>
                                    <div class="text-white-50 font-size-11">App Management &rarr; OAuth App &rarr; Redirect URI</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary font-size-10" style="white-space:normal;line-height:1.4;">snapchat-marketing-api</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 rounded-3 mt-3" style="background:rgba(56,189,248,0.05);border:1px solid rgba(56,189,248,0.2);">
                    <span class="text-info fw-bold font-size-13"><i class="ph-bold ph-info me-1"></i> Developer Tip:</span>
                    <span class="text-white-50 font-size-13">Both the platform-parameterized URI (e.g. <code><?= BASE_URL ?>/oauth-callback.php?platform=meta</code>) and the clean universal URI (<code><?= BASE_URL ?>/oauth-callback.php</code>) are supported. If a platform strictly prohibits query strings in redirect URIs (e.g. Google Cloud), register the clean universal URI.</span>
                </div>
            </div>

            <!-- Endpoint 1: Balances & Wallet -->
            <div id="ep-credits" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">

                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/credits.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Retrieve your real-time wallet balances, ad credits, AI credits, and active subscription plan.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">cURL Request</div>
                        <div class="code-block">
curl -X GET "<?= BASE_URL ?>/api/v1/credits.php" \
  -H "X-API-Key: YOUR_API_KEY"
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (200 OK)</div>
                        <div class="code-block">
{
  "success": true,
  "user": {
    "id": 1,
    "full_name": "Admin User",
    "ad_credits": 250.00,
    "ai_credits": 480
  },
  "plan": { "name": "Growth Pro", "status": "active" }
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 2: Ad Campaigns -->
            <div id="ep-campaigns" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <span class="method-pill method-get">GET</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/campaigns.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Create multi-network ad campaigns across Meta, TikTok, and Google Ads from unified ad wallet credits.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Create Campaign Payload (POST)</div>
                        <div class="code-block">
{
  "campaign_name": "Summer D2C Scale",
  "campaign_type": "meta",
  "budget_amount": 100.00,
  "daily_budget": 20.00,
  "objective": "conversions",
  "target_audience": "US eCom buyers 22-45"
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "campaign_id": 42,
  "campaign_name": "Summer D2C Scale",
  "status": "pending_approval",
  "message": "Campaign queued for network dispatch."
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 3: Social Posts -->
            <div id="ep-posts" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/posts.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Publish content live across connected accounts (Meta, TikTok, Twitter/X, LinkedIn) or queue into the scheduling pipeline.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Live Publish Payload (POST)</div>
                        <div class="code-block">
{
  "platform": "twitter",
  "caption": "Scaling brand revenue with @Adsbooster AI!",
  "media_url": "https://example.com/asset.png",
  "publish_now": true
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "status": "published",
  "platform": "twitter",
  "external_id": "183884910283948",
  "latency_ms": 68,
  "message": "Post published live across network."
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 4: AI UGC Image Studio -->
            <div id="ep-ugc" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <span class="method-pill method-get">GET</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/ugc.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Synthesize photorealistic UGC ad imagery with customizable personas, aspect ratios, promotional trust badges, and variations.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Generate UGC Image Payload</div>
                        <div class="code-block">
{
  "product_name": "Luxe Eye Cream",
  "ugc_style": "selfie_hold",
  "creator_persona": "gen_z",
  "aspect_ratio": "1:1",
  "target_audience": "Women 25-45",
  "badge_text": "⭐⭐⭐⭐⭐ Top Rated",
  "seed": 482910
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "creative": {
    "image_url": "/assets/uploads/ai/gemini/ugc_482910_1.png",
    "hook_headline": "Stop wasting budget on 10-step routines",
    "ad_copy": "Grab yours today with free express shipping!",
    "badge_text": "⭐⭐⭐⭐⭐ Top Rated",
    "seed": 482910
  },
  "credits_remaining": 478
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 5: Directory & Leads -->
            <div id="ep-directory" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/directory.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Query verified local business listings, search by category or keyword, and publish new profiles.</p>

                <div class="code-block">
curl -X GET "<?= BASE_URL ?>/api/v1/directory.php?category=Retail&limit=5" \
  -H "X-API-Key: YOUR_API_KEY"
                </div>
            </div>

            <!-- Endpoint 6: Platform Reviews -->
            <div id="ep-reviews" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-get">GET</span>
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/api/v1/reviews.php</code>
                </div>
                <p class="text-white-50 font-size-13 mb-3">Fetch verified reviews and ratings displayed across the platform homepage, or submit reviews (Pro members anytime; Free users after 5 days).</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Submit Review Payload</div>
                        <div class="code-block">
{
  "rating": 5,
  "title": "Unmatched ROAS Acceleration",
  "review_text": "The combination of the AI UGC Image Studio and direct TikTok publishing has eliminated creative fatigue for our agency.",
  "user_role": "Performance Agency Owner"
}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Response (201 Created)</div>
                        <div class="code-block">
{
  "success": true,
  "review_id": 14,
  "message": "Thank you! Your review has been published to the community."
}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 7: AI Video Studio -->
            <div id="ep-video-studio" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/ugc-video-studio.php</code>
                    <span class="badge bg-warning bg-opacity-25 text-warning font-size-10">Session + CSRF</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">
                    Full video lifecycle powered by Google AI: Veo renders video directly when a <code>GEMINI_API_KEY</code> is configured, and the Nano Banana 2 image models generate every scene still. Works in the app (same-origin) or from any client that holds a logged-in session cookie.
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-sm font-size-12 mb-0" style="border-color:rgba(255,255,255,.08);">
                        <thead><tr><th class="text-white-50">action</th><th class="text-white-50">Params</th><th class="text-white-50">What it does</th></tr></thead>
                        <tbody>
                            <tr><td><code>create_video</code></td><td><code>hook_text</code> or <code>script_body</code>, optional <code>video_type, avatar_id, voice_id, style_id, caption_style, audio_track, broll_keyword, aspect_ratio, duration_seconds</code></td><td>Builds the scene plan, generates a Nano Banana 2 still per scene, returns <code>status=rendering_client</code> + <code>scenes[]</code> (browser composites the MP4/WebM), or <code>status=generating</code> when Veo accepted the job (poll it).</td></tr>
                            <tr><td><code>update_video</code></td><td><code>creative_id</code> + any editable field above</td><td><b>Edit in place:</b> same creative id, new params, fresh AI stills. The old file stays on disk until the new render is saved.</td></tr>
                            <tr><td><code>regenerate_video</code></td><td><code>creative_id</code></td><td><b>Remix:</b> same script, brand-new scene stills (new Gemini seed).</td></tr>
                            <tr><td><code>get_video_status</code></td><td><code>creative_id</code></td><td>Poll Veo/external jobs until <code>completed</code> or <code>failed</code>.</td></tr>
                            <tr><td><code>save_render</code></td><td><code>creative_id</code>, <code>video</code> (multipart), optional <code>thumbnail</code> (data URI)</td><td>Upload the finished browser render; deducts credits and marks <code>completed</code>.</td></tr>
                            <tr><td><code>delete_video</code></td><td><code>creative_id</code></td><td>Deletes the creative and its stored files.</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-12 mb-2">Every request needs the <code>csrf_token</code> field (echoed by the page as <code>const CSRF</code>) and the session cookie.</p>

                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-curl" type="button">cURL</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-js" type="button">JavaScript</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-py" type="button">Python</button></li>
                    <li class="nav-item"><button class="nav-link rounded-pill px-3 py-1 font-size-12" data-bs-toggle="pill" data-bs-target="#vs-php" type="button">PHP</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="vs-curl">
                        <div class="code-block"># 1) Sign in and keep the session cookie
curl -c jar.txt -d "csrf_token=PAGE_CSRF&email=you%40mail.com&password=secret" <?= BASE_URL ?>/login

# 2) Regenerate an existing video (same script, new Nano Banana 2 stills)
curl -b jar.txt -X POST <?= BASE_URL ?>/ugc-video-studio.php \
  -d "action=regenerate_video" \
  -d "csrf_token=STUDIO_CSRF" \
  -d "creative_id=17"</div>
                    </div>
                    <div class="tab-pane fade" id="vs-js">
                        <div class="code-block">// In-app: the studio page already exposes CSRF + LIB state
const fd = new FormData();
fd.append('action', 'regenerate_video');
fd.append('csrf_token', CSRF);
fd.append('creative_id', '17');
const res = await fetch(window.location.pathname, { method:'POST', body:fd, credentials:'same-origin' });
const { success, video } = await res.json();
if (success && video.status === 'rendering_client') {
  // composite video.scenes[] with canvas + MediaRecorder, then save_render
}</div>
                    </div>
                    <div class="tab-pane fade" id="vs-py">
                        <div class="code-block">import requests

s = requests.Session()
page = s.get("<?= BASE_URL ?>/ugc-video-studio.php")
import re
csrf = re.search(r'const CSRF\s*=\s*"([^"]+)"', page.text).group(1)

r = s.post("<?= BASE_URL ?>/ugc-video-studio.php",
           data={"action": "regenerate_video", "csrf_token": csrf, "creative_id": "17"})
video = r.json()["video"]
print(video["status"], len(video["scenes"]), "scenes")</div>
                    </div>
                    <div class="tab-pane fade" id="vs-php">
                        <div class="code-block">$ch = curl_init('<?= BASE_URL ?>/ugc-video-studio.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => 'jar.txt',      // session from the login step
    CURLOPT_POSTFIELDS => http_build_query([
        'action'      => 'regenerate_video',
        'csrf_token'  => 'STUDIO_CSRF',
        'creative_id' => '17',
    ]),
]);
$video = json_decode(curl_exec($ch), true)['video'];
echo $video['status']; // rendering_client → composite in browser, or poll get_video_status</div>
                    </div>
                </div>
            </div>

            <!-- Endpoint 8: Post Creator & Social Publishing -->
            <div id="ep-post-creator" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="method-pill method-post">POST</span>
                    <code class="text-white fw-bold font-size-15">/post-creator.php</code>
                    <span class="badge bg-warning bg-opacity-25 text-warning font-size-10">Session + CSRF</span>
                </div>
                <p class="text-white-50 font-size-13 mb-3">
                    AI content pipeline: Groq writes the caption, Gemini Nano Banana 2 paints the illustration, and <code>publish_now</code> dispatches both live to every connected account (Meta, TikTok, Twitter/X, LinkedIn, Snapchat) in a single request.
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Generate an AI post image (JSON, 5 credits)</div>
                        <div class="code-block">action=generate_illustration
csrf_token=…
illustration_prompt=Flat lay of our skincare set
                          on marble, morning light

→ { "success": true,
    "url": "/assets/uploads/ai/gemini/post_1_…​.png",
    "model": "gemini-3-pro-image-preview" }</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-white-50 font-size-11 fw-bold text-uppercase mb-1">Publish live to every connected channel</div>
                        <div class="code-block">action=publish_now
csrf_token=…
caption=New drop 🚀 link in bio #ad
platforms[]=meta&platforms[]=tiktok
existing_media_url=/assets/uploads/ai/gemini/post_1_…​.png
media_type=image

→ "Live Post Published! Channels: Meta, TikTok"</div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-dark table-sm font-size-12 mb-0" style="border-color:rgba(255,255,255,.08);">
                        <thead><tr><th class="text-white-50">action</th><th class="text-white-50">Params</th><th class="text-white-50">What it does</th></tr></thead>
                        <tbody>
                            <tr><td><code>generate_ai</code></td><td><code>ai_prompt</code>, <code>ai_platform</code></td><td>Groq writes a platform-tuned caption (1 credit).</td></tr>
                            <tr><td><code>generate_illustration</code></td><td><code>illustration_prompt</code></td><td>Gemini Nano Banana 2 renders the post image (5 credits) and stores it same-origin.</td></tr>
                            <tr><td><code>publish_now</code></td><td><code>caption</code>, <code>platforms[]</code>, optional <code>existing_media_url</code> + <code>media_type</code> or a <code>media_upload</code> file</td><td>Live multi-network dispatch via each platform's OAuth token (<code>SocialPostEngine::publishMultiLive</code>).</td></tr>
                            <tr><td><code>schedule</code></td><td><code>scheduled_at</code> + same media fields</td><td>Queues into <code>scheduled_posts</code> for the automation worker.</td></tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-white-50 font-size-12 mb-2">Connect accounts first under <a href="<?= url('social-connect') ?>" class="text-accent">Social Connect</a> — publishing uses real OAuth tokens, never passwords.</p>
            </div>

            <!-- Client SDKs -->
            <div id="sdks" class="card-dark p-4 rounded-4 mb-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <h3 class="text-white fw-bold h5 mb-3">
                    <i class="ph-bold ph-terminal-window text-accent me-1"></i> Multi-Language Code Snippets
                </h3>

                <!-- Tabs for SDKs -->
                <ul class="nav nav-pills mb-3" id="sdkTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1 font-size-12" id="js-tab" data-bs-toggle="pill" data-bs-target="#sdk-js" type="button">JavaScript (Fetch)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 font-size-12" id="py-tab" data-bs-toggle="pill" data-bs-target="#sdk-py" type="button">Python (Requests)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 font-size-12" id="php-tab" data-bs-toggle="pill" data-bs-target="#sdk-php" type="button">PHP (cURL)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 font-size-12" id="curl-tab" data-bs-toggle="pill" data-bs-target="#sdk-curl" type="button">cURL</button>
                    </li>
                </ul>

                <div class="tab-content" id="sdkTabContent">
                    <div class="tab-pane fade show active" id="sdk-js">
                        <div class="code-block">
const response = await fetch('<?= BASE_URL ?>/api/v1/ugc.php', {
  method: 'POST',
  headers: {
    'X-API-Key': 'adsb_live_your_api_key',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    product_name: 'Smart Hydration Bottle',
    ugc_style: 'selfie_hold',
    aspect_ratio: '1:1'
  })
});
const data = await response.json();
console.log('Generated UGC Image:', data.creative.image_url);
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sdk-py">
                        <div class="code-block">
import requests

url = "<?= BASE_URL ?>/api/v1/ugc.php"
headers = {
    "X-API-Key": "adsb_live_your_api_key",
    "Content-Type": "application/json"
}
payload = {
    "product_name": "Smart Hydration Bottle",
    "ugc_style": "selfie_hold",
    "aspect_ratio": "1:1"
}
response = requests.post(url, json=payload, headers=headers)
print(response.json())
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sdk-php">
                        <div class="code-block">
$ch = curl_init('<?= BASE_URL ?>/api/v1/ugc.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: adsb_live_your_api_key',
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'product_name' => 'Smart Hydration Bottle',
        'ugc_style' => 'selfie_hold',
        'aspect_ratio' => '1:1'
    ])
]);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
print_r($result);
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sdk-curl">
                        <div class="code-block">
curl -X POST <?= BASE_URL ?>/api/v1/ugc.php \
  -H "X-API-Key: adsb_live_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"product_name":"Smart Hydration Bottle","ugc_style":"selfie_hold","aspect_ratio":"1:1"}'
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
