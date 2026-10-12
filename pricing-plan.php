<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Pricing Plans - ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/app_header.php';

$plans = [];
try {
    $plans = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' ORDER BY price ASC");
} catch (\Throwable $e) {
    $plans = [];
}
?>

<div class="w-100">
    <div class="page-header" style="margin-bottom:28px">
        <div>
            <h1 class="page-title">Pricing Plans</h1>
            <p class="page-subtitle">Choose the plan that fits your growth. Upgrade or switch anytime from billing.</p>
        </div>
        <div>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm">
                <i class="ph ph-arrow-left"></i> Manage Billing
            </a>
        </div>
    </div>

    <?php if (empty($plans)): ?>
        <div class="card-dark" style="padding:48px 24px;text-align:center;">
            <div class="stat-icon" style="margin:0 auto 14px;background:rgba(200,255,0,.08);">
                <i class="ph ph-package" style="font-size:24px;color:var(--accent);"></i>
            </div>
            <h3 style="color:var(--fg);font-weight:700;margin:0 0 6px;">No plans available</h3>
            <p style="color:var(--fg2);font-size:13px;margin:0 0 16px;">Plans are being set up. Please check back soon.</p>
            <a href="<?= url('billing') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-arrow-left"></i> Back to billing</a>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px;">
            <?php foreach ($plans as $plan):
                $features = json_decode($plan['features_json'] ?? $plan['features'] ?? '[]', true);
                if (!is_array($features)) $features = [];
                $featured = !empty($plan['is_featured']);
            ?>
                <div class="card-dark" style="padding:24px;display:flex;flex-direction:column;<?= $featured ? 'border-color:var(--accent);box-shadow:0 0 0 1px var(--accent);position:relative;' : '' ?>">
                    <?php if ($featured): ?>
                        <span class="badge-active" style="position:absolute;top:-11px;left:20px;"><i class="ph ph-crown"></i> Most Popular</span>
                    <?php endif; ?>
                    <h3 style="font-weight:700;color:var(--fg);margin:0 0 4px;font-size:17px;"><?= e((string)($plan['name'] ?? 'Plan')) ?></h3>
                    <?php if (!empty($plan['description'])): ?>
                        <p style="font-size:13px;color:var(--fg2);margin:0 0 12px;"><?= e((string)$plan['description']) ?></p>
                    <?php endif; ?>
                    <p style="margin:0 0 10px;">
                        <strong style="font-size:30px;font-weight:800;color:var(--fg);">$<?= number_format((float)($plan['price'] ?? 0), 2) ?></strong>
                        <span style="font-size:12px;color:var(--fg3);"> / <?= e((string)($plan['billing_cycle'] ?? 'month')) ?></span>
                    </p>

                    <!-- Bundled Credits Pill -->
                    <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:14px;font-size:12px;">
                        <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                            <i class="ph ph-gift"></i> Bundled Credits Included:
                        </div>
                        <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                            +$<?= number_format((float)($plan['ad_credits_included'] ?? 0), 2) ?> Ad Credits • <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI Credits
                        </div>
                    </div>
                    <?php if (!empty($features)): ?>
                        <ul style="list-style:none;padding:0;margin:0 0 20px;display:flex;flex-direction:column;gap:9px;flex:1;">
                            <?php foreach ($features as $feature): ?>
                                <li style="display:flex;gap:9px;align-items:flex-start;font-size:13px;color:var(--fg2);">
                                    <i class="ph ph-check-circle" style="color:var(--green);font-size:16px;margin-top:1px;flex-shrink:0;"></i>
                                    <span><?= e(is_string($feature) ? $feature : json_encode($feature)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div style="flex:1;"></div>
                    <?php endif; ?>
                    <a href="<?= url('billing', ['plan' => (int)$plan['id']]) ?>" class="<?= $featured ? 'btn-accent btn-sm' : 'btn-outline-dark btn-sm' ?>" style="width:100%;justify-content:center;text-decoration:none;">
                        <i class="ph ph-check"></i> Choose Plan
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
