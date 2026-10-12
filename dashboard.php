<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';
$pageTitle = 'Dashboard — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';

$userId   = current_user_id();
$userData = current_user();

$adCredits    = (float)($userData['ad_credits'] ?? 0);
$aiCredits    = (int)($userData['ai_credits'] ?? 0);
$referralCode = $userData['referral_code'] ?? '';
$referralCount = 0;
try { $referralCount = db()->count('users', 'referred_by = ?', [$referralCode]); } catch (Throwable $e) {}

$planRow = null;
try {
    $planRow = db()->fetchOne(
        "SELECT p.name as plan_name, t.created_at as expires_at FROM transactions t JOIN pricing_plans p ON p.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(t.metadata, '$.plan_id')) AS UNSIGNED) WHERE t.user_id = ? AND t.type = 'plan_subscription' AND t.status = 'successful' ORDER BY t.created_at DESC LIMIT 1",
        [$userId]
    );
} catch (Throwable $e) {}
$planName = $planRow['plan_name'] ?? 'Free Tier';

$connections = db()->fetchAll("SELECT platform FROM connected_accounts WHERE user_id = ?", [$userId]);
$connectedPlatforms = array_column($connections, 'platform');

$recentCampaigns = db()->fetchAll("SELECT id, campaign_name, campaign_type, status, budget_amount, created_at FROM ad_campaigns WHERE user_id = ? ORDER BY created_at DESC LIMIT 6", [$userId]);
$recentTransactions = db()->fetchAll("SELECT id, type, amount, currency, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 6", [$userId]);

$hour = (int)date('H');
$timeGreeting = match (true) {
    $hour < 12 => 'Good morning',
    $hour < 17 => 'Good afternoon',
    default    => 'Good evening',
};
$referralUrl = BASE_URL . '/register.php?ref=' . urlencode($referralCode);
?>

<!-- Top Welcome & Quick Action Bar -->
<div class="page-header" style="margin-bottom:28px">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <h1 class="page-title" style="margin:0"><?= $timeGreeting ?>, <?= $_appName ?></h1>
            <span class="badge-status badge-active" style="font-size:11px;padding:3px 10px">Live</span>
        </div>
        <p class="page-subtitle">Here is a real-time summary of your ad campaigns, AI generation, and marketing channels.</p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <a href="<?= url('create-ad') ?>" class="btn-accent">
            <i class="ph ph-plus-circle" style="font-size:18px"></i> New Campaign
        </a>
        <a href="<?= url('post-creator') ?>" class="btn-outline-dark">
            <i class="ph ph-note-pencil" style="font-size:18px"></i> Create Post
        </a>
        <a href="<?= url('billing') ?>" class="btn-outline-dark">
            <i class="ph ph-wallet" style="font-size:18px"></i> Add Funds
        </a>
    </div>
</div>

<?php
// Review prompt eligibility: Pro users anytime; Free users after 5 days of usage
$showReviewPrompt = false;
$promptDismissed = (int)($_SESSION['review_prompt_dismissed'] ?? $_COOKIE['review_prompt_dismissed'] ?? 0);
if (time() > $promptDismissed) {
    try {
        $hasReviewed = (bool)db()->fetchOne("SELECT id FROM site_reviews WHERE user_id = ?", [$userId]);
        if (!$hasReviewed) {
            $createdAt = strtotime($userData['created_at'] ?? 'now');
            $daysSinceSignup = (int)floor((time() - $createdAt) / 86400);
            $isProUser = in_array($userData['role'] ?? '', ['admin', 'pro', 'agency'], true) || !empty($planRow);
            if ($isProUser || $daysSinceSignup >= 5) {
                $showReviewPrompt = true;
            }
        }
    } catch (\Throwable $e) {}
}
?>
<?php if ($showReviewPrompt): ?>
    <div class="card bg-card border border-main-600 border-opacity-30 rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, rgba(196,240,0,0.06) 0%, #0d1524 100%) !important; border: 1px solid rgba(196,240,0,0.25) !important;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:rgba(196,240,0,0.15);color:#c4f000;font-size:24px;">
                    <i class="ph-fill ph-star"></i>
                </div>
                <div>
                    <div class="text-white fw-bold font-size-15 mb-1">How is your experience scaling with Adsbooster.net?</div>
                    <div class="text-white-50 font-size-13" style="color:#94a3b8 !important;">Share your rating and thoughts to be featured on our community homepage!</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= url('reviews') ?>" class="btn btn-main-600 text-dark fw-bold rounded-pill px-4 py-2 font-size-13">
                    <i class="ph-bold ph-pencil-simple me-1"></i> Leave Review
                </a>
                <a href="<?= url('reviews', ['skip' => 1]) ?>" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13 text-white-50">
                    Skip for now
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Stats Row: 4 Full-Width Responsive Metric Cards -->
<div class="grid-4" style="margin-bottom:28px">
    <!-- Ad Credits -->
    <div class="stat-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--accent-dim);color:var(--accent)">
                <i class="ph ph-monetization-on"></i>
            </div>
            <a href="<?= url('billing') ?>" style="font-size:12px;font-weight:600;color:var(--accent);display:inline-flex;align-items:center;gap:4px">
                Top Up <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div>
            <div class="stat-value">$<?= number_format($adCredits, 2) ?></div>
            <div class="stat-label">Ad Wallet Credits</div>
        </div>
    </div>

    <!-- AI Credits -->
    <div class="stat-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--blue-dim);color:var(--blue)">
                <i class="ph ph-sparkle"></i>
            </div>
            <a href="<?= url('post-creator') ?>" style="font-size:12px;font-weight:600;color:var(--blue);display:inline-flex;align-items:center;gap:4px">
                Generate <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div>
            <div class="stat-value"><?= number_format($aiCredits) ?></div>
            <div class="stat-label">AI Generation Credits</div>
        </div>
    </div>

    <!-- Campaigns -->
    <div class="stat-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--orange-dim);color:var(--orange)">
                <i class="ph ph-megaphone"></i>
            </div>
            <a href="<?= url('analytics') ?>" style="font-size:12px;font-weight:600;color:var(--orange);display:inline-flex;align-items:center;gap:4px">
                Analytics <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div>
            <div class="stat-value"><?= count($recentCampaigns) ?></div>
            <div class="stat-label">Total Campaigns Launched</div>
        </div>
    </div>

    <!-- Plan -->
    <div class="stat-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--purple-dim);color:var(--purple)">
                <i class="ph ph-crown"></i>
            </div>
            <a href="<?= url('pricing-plan') ?>" style="font-size:12px;font-weight:600;color:var(--purple);display:inline-flex;align-items:center;gap:4px">
                Upgrade <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div>
            <div class="stat-value" style="font-size:22px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($planName) ?></div>
            <div class="stat-label">Current Membership</div>
        </div>
    </div>
</div>

<!-- 1. Quick Launch Workspace (Essential Tools) -->
<div class="card-dark" style="margin-bottom:24px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:10px">
            <i class="ph ph-lightning" style="font-size:20px;color:var(--accent)"></i>
            <h6 style="margin:0;font-size:15px;font-weight:700">Quick Launch Workspace</h6>
        </div>
        <span style="font-size:12px;color:var(--fg3)">Fast shortcuts to essential tools</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(136px,1fr));gap:12px">
        <a href="<?= url('create-ad') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-megaphone" style="font-size:22px;color:var(--accent)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Create Ad</span>
        </a>
        <a href="<?= url('post-creator') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--blue-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-note-pencil" style="font-size:22px;color:var(--blue)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Post Creator</span>
        </a>
        <a href="<?= url('analytics') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--green-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-chart-line-up" style="font-size:22px;color:var(--green)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Analytics</span>
        </a>
        <a href="<?= url('marketplace') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--purple-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-storefront" style="font-size:22px;color:var(--purple)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Marketplace</span>
        </a>
        <a href="<?= url('audience-builder') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--orange-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-users-three" style="font-size:22px;color:var(--orange)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Audiences</span>
        </a>
        <a href="<?= url('ab-test') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(56,189,248,0.12);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-git-fork" style="font-size:22px;color:var(--blue)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">A/B Testing</span>
        </a>
        <a href="<?= url('billing') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-credit-card" style="font-size:22px;color:var(--accent)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Wallet</span>
        </a>
        <a href="<?= url('tickets') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(100,116,139,0.15);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-ticket" style="font-size:22px;color:var(--fg2)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Support</span>
        </a>
    </div>
</div>

<!-- 2. Next-Gen Powerhouse AI & Growth Engines -->
<div class="card-dark" style="margin-bottom:28px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
        <div style="display:flex;align-items:center;gap:10px">
            <i class="ph ph-sparkle" style="font-size:20px;color:var(--accent)"></i>
            <h6 style="margin:0;font-size:15px;font-weight:700">Next-Gen AI &amp; Growth Powerhouse</h6>
        </div>
        <span style="font-size:12px;color:var(--accent)">Enterprise Multi-Model Engines</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">
        <a href="<?= url('ugc-video-studio') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px;border-color:rgba(200,255,0,0.3)">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-video-camera" style="font-size:22px;color:var(--accent)"></i>
            </div>
            <span style="font-size:13px;font-weight:700;color:#fff">AI Video Studio</span>
        </a>
        <a href="<?= url('ai-media-buyer') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-shield-warning" style="font-size:22px;color:#ef4444"></i>
            </div>
            <span style="font-size:13px;font-weight:600">AI Media Buyer</span>
        </a>
        <a href="<?= url('ad-spy') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-detective" style="font-size:22px;color:#f59e0b"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Ad Spy Engine</span>
        </a>
        <a href="<?= url('ecommerce-sync') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-shopping-bag" style="font-size:22px;color:#10b981"></i>
            </div>
            <span style="font-size:13px;font-weight:600">E-Com Sync</span>
        </a>
        <a href="<?= url('social-inbox') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--blue-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-chats-circle" style="font-size:22px;color:var(--blue)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Social Closer</span>
        </a>
        <a href="<?= url('bounties') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(200,255,0,0.15);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-hand-coins" style="font-size:22px;color:var(--accent)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Creator Bounties</span>
        </a>
        <a href="<?= url('agency-portal') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:var(--purple-dim);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-buildings" style="font-size:22px;color:var(--purple)"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Agency Portal</span>
        </a>
        <a href="<?= url('server-tracking') ?>" class="platform-card" style="flex-direction:column;text-align:center;padding:18px 12px;gap:10px">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(6,182,212,0.15);display:flex;align-items:center;justify-content:center">
                <i class="ph ph-broadcast" style="font-size:22px;color:#06b6d4"></i>
            </div>
            <span style="font-size:13px;font-weight:600">Server CAPI</span>
        </a>
    </div>
</div>

<!-- Connected Advertising Channels -->
<div class="card-dark" style="margin-bottom:28px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
            <h6 style="margin:0;font-size:15px;font-weight:700">Advertising &amp; Social Channels</h6>
            <p style="margin:2px 0 0;font-size:12.5px;color:var(--fg3)">Connect your networks to sync audiences, automate ads, and publish content.</p>
        </div>
        <a href="<?= url('social-connect') ?>" class="btn-outline-dark btn-sm">
            <i class="ph ph-gear"></i> Manage Connections
        </a>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php
        $allPlatforms = [
            'meta'            => ['label' => 'Meta / Facebook', 'icon' => 'ph-facebook-logo'],
            'instagram'       => ['label' => 'Instagram',       'icon' => 'ph-instagram-logo'],
            'tiktok'          => ['label' => 'TikTok',          'icon' => 'ph-video-camera'],
            'google_ads'      => ['label' => 'Google Ads',      'icon' => 'ph-google-logo'],
            'snapchat'        => ['label' => 'Snapchat',        'icon' => 'ph-ghost'],
            'google_business' => ['label' => 'Google Business', 'icon' => 'ph-storefront'],
            'google_calendar' => ['label' => 'Google Calendar', 'icon' => 'ph-calendar'],
        ];
        foreach ($allPlatforms as $key => $p):
            $connected = in_array($key, $connectedPlatforms, true);
        ?>
            <a href="<?= url('social-connect', ['platform' => $key]) ?>" class="platform-card <?= $connected ? 'selected' : '' ?>" style="flex:0 0 auto;padding:10px 16px;gap:10px;border-radius:99px">
                <i class="ph <?= $p['icon'] ?>" style="font-size:18px;color:<?= $connected ? 'var(--accent)' : 'var(--fg3)' ?>"></i>
                <span style="font-size:13px"><?= $p['label'] ?></span>
                <span style="width:8px;height:8px;border-radius:50%;background:<?= $connected ? 'var(--green)' : 'var(--fg3)' ?>;flex-shrink:0;box-shadow:<?= $connected ? '0 0 8px var(--green)' : 'none' ?>"></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Main Data Split: Campaigns & Transactions -->
<div class="grid-2" style="margin-bottom:28px">
    <!-- Recent Campaigns -->
    <div class="card-dark" style="padding:0;overflow:hidden;display:flex;flex-direction:column">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:rgba(0,0,0,0.15)">
            <div style="display:flex;align-items:center;gap:8px">
                <i class="ph ph-megaphone" style="color:var(--accent);font-size:18px"></i>
                <h6 style="margin:0;font-size:14px;font-weight:700">Recent Ad Campaigns</h6>
            </div>
            <a href="<?= url('create-ad') ?>" class="btn-accent btn-sm">
                <i class="ph ph-plus"></i> New
            </a>
        </div>
        <div style="flex:1">
            <?php if (empty($recentCampaigns)): ?>
                <div class="empty-state" style="padding:40px 20px">
                    <i class="ph ph-megaphone"></i>
                    <p style="font-weight:600;color:var(--fg);margin-bottom:4px">No campaigns launched yet</p>
                    <p style="font-size:13px;color:var(--fg3);margin-bottom:16px">Create your first ad to begin generating impressions and clicks.</p>
                    <a href="<?= url('create-ad') ?>" class="btn-accent btn-sm">Create First Ad</a>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <table class="table-dark">
                        <thead>
                            <tr>
                                <th>Campaign</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th>Budget</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentCampaigns as $c):
                                $sc = match($c['status'] ?? '') { 'active' => 'active', 'paused' => 'inactive', 'completed' => 'accent', default => 'pending' };
                            ?>
                                <tr>
                                    <td style="font-weight:600">
                                        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px"><?= e($c['campaign_name']) ?></div>
                                    </td>
                                    <td><span class="badge-platform"><?= e(ucfirst($c['campaign_type'])) ?></span></td>
                                    <td><span class="badge-status badge-<?= $sc ?>"><?= e(ucfirst($c['status'] ?? 'draft')) ?></span></td>
                                    <td style="font-weight:700;font-family:'JetBrains Mono',monospace">$<?= number_format((float)$c['budget_amount'], 2) ?></td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <a href="<?= url('edit-campaign', ['id' => (int)$c['id']]) ?>" class="btn-outline-dark btn-sm">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card-dark" style="padding:0;overflow:hidden;display:flex;flex-direction:column">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:rgba(0,0,0,0.15)">
            <div style="display:flex;align-items:center;gap:8px">
                <i class="ph ph-receipt" style="color:var(--blue);font-size:18px"></i>
                <h6 style="margin:0;font-size:14px;font-weight:700">Wallet Transactions</h6>
            </div>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm">View All</a>
        </div>
        <div style="flex:1">
            <?php if (empty($recentTransactions)): ?>
                <div class="empty-state" style="padding:40px 20px">
                    <i class="ph ph-credit-card"></i>
                    <p style="font-weight:600;color:var(--fg);margin-bottom:4px">No transactions recorded</p>
                    <p style="font-size:13px;color:var(--fg3);margin-bottom:16px">Top up your wallet to activate paid promotions and upgrades.</p>
                    <a href="<?= url('billing') ?>" class="btn-accent btn-sm">Fund Wallet</a>
                </div>
            <?php else: ?>
                <div class="table-wrap" style="border:none;border-radius:0">
                    <table class="table-dark">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="text-align:right">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $t):
                                $tsc = match($t['status'] ?? '') { 'completed','successful' => 'active', 'pending' => 'pending', 'failed' => 'danger', default => 'pending' };
                            ?>
                                <tr>
                                    <td style="text-transform:capitalize;font-weight:500">
                                        <?= e(str_replace('_', ' ', $t['type'])) ?>
                                    </td>
                                    <td style="font-weight:700;font-family:'JetBrains Mono',monospace">$<?= number_format((float)$t['amount'], 2) ?></td>
                                    <td><span class="badge-status badge-<?= $tsc ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                                    <td style="text-align:right;color:var(--fg3);font-size:12.5px"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Referral Partner Full-Width Banner -->
<div class="card-dark" style="background:linear-gradient(135deg, rgba(200,255,0,0.06) 0%, rgba(14,22,38,0.95) 100%);border-color:var(--border-accent);padding:24px 28px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:24px">
        <div style="display:flex;align-items:center;gap:32px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:14px">
                <div style="width:48px;height:48px;border-radius:12px;background:var(--accent-dim);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:24px">
                    <i class="ph ph-share-network"></i>
                </div>
                <div>
                    <h6 style="margin:0;font-size:16px;font-weight:700">Referral Partner Program</h6>
                    <p style="margin:2px 0 0;font-size:13px;color:var(--fg2)">Earn $5.00 for every client who signs up and funds their campaigns.</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:24px">
                <div>
                    <div style="font-size:24px;font-weight:800;color:var(--fg);line-height:1"><?= number_format($referralCount) ?></div>
                    <div style="font-size:12px;color:var(--fg3);margin-top:2px">Invited</div>
                </div>
                <div style="width:1px;height:32px;background:var(--border)"></div>
                <div>
                    <div style="font-size:24px;font-weight:800;color:var(--accent);line-height:1">$<?= number_format($referralCount * 5, 2) ?></div>
                    <div style="font-size:12px;color:var(--fg3);margin-top:2px">Earned</div>
                </div>
            </div>
        </div>

        <!-- 1-Click Referral Link -->
        <div style="flex:1;min-width:300px;max-width:540px">
            <div style="display:flex;gap:8px">
                <input type="text" readonly value="<?= e($referralUrl) ?>" class="form-control" style="font-family:'JetBrains Mono',monospace;font-size:12.5px;color:var(--accent);background:var(--bg)!important" id="refLinkInput">
                <button type="button" class="btn-accent btn-sm" style="white-space:nowrap" onclick="copyRefLink(this)">
                    <i class="ph ph-copy"></i> <span id="copyRefBtnText">Copy</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function copyRefLink(btn) {
    var input = document.getElementById('refLinkInput');
    if (!input) return;
    navigator.clipboard.writeText(input.value).then(function() {
        var span = document.getElementById('copyRefBtnText');
        if (span) span.textContent = 'Copied!';
        btn.classList.add('btn-outline-dark');
        btn.classList.remove('btn-accent');
        setTimeout(function() {
            if (span) span.textContent = 'Copy';
            btn.classList.add('btn-accent');
            btn.classList.remove('btn-outline-dark');
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
