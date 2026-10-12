<?php declare(strict_types=1);
require_once __DIR__ . '/config/site_setting_and_config.php';

$pageTitle = 'Billing & Wallet — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/payments.php';

$userId = current_user_id();
$user = current_user();
$message = '';
$selectedPlanId = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;

// Handle top-up + plan subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid security token.</div>';
    } else {
        $type = $_POST['action'];
        $gateway = strtolower(trim($_POST['gateway'] ?? 'paystack'));

        if (!in_array($gateway, ['paystack', 'flutterwave'])) {
            $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid payment gateway.</div>';
        } elseif ($type === 'subscribe_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $plan = $planId > 0 ? db()->fetchOne("SELECT * FROM pricing_plans WHERE id = ? AND status = 'active'", [$planId]) : null;
            if ($plan === null) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Selected plan is not available.</div>';
            } else {
                $planPrice = (float)($plan['price'] ?? 0);
                if ($planPrice <= 0) {
                    $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid plan price.</div>';
                } else {
                    $result = PaymentProcessor::initiatePayment($userId, 'plan_subscription', $planPrice, 'USD', $gateway, ['plan_id' => $planId, 'plan_name' => (string)($plan['name'] ?? '')]);
                    if ($result['success'] && isset($result['redirect_url'])) {
                        header('Location: ' . $result['redirect_url']);
                        exit;
                    } else {
                        $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">' . e($result['message'] ?? 'Payment failed.') . '</div>';
                    }
                }
            }
        } else {
            $amount = (float)($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Invalid amount.</div>';
            } elseif ($amount < 5) {
                $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">Minimum top-up is $5.</div>';
            } else {
                $creditType = ($type === 'topup_ai') ? 'ai_credit_topup' : 'ad_credit_topup';
                $result = PaymentProcessor::initiatePayment($userId, $creditType, $amount, 'USD', $gateway);
                if ($result['success'] && isset($result['redirect_url'])) {
                    header('Location: ' . $result['redirect_url']);
                    exit;
                } else {
                    $message = '<div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--red)">' . e($result['message'] ?? 'Payment failed.') . '</div>';
                }
            }
        }
    }
}

$status = function_exists('integration_status') ? integration_status() : [];
$paystackLive = !empty($status['paystack']['configured']);
$flutterLive  = !empty($status['flutterwave']['configured']);
$anyLive = $paystackLive || $flutterLive;

$plans = [];
try {
    $plans = db()->fetchAll("SELECT * FROM pricing_plans WHERE status = 'active' ORDER BY price ASC");
} catch (\Throwable $e) {
    $plans = [];
}

$transactions = db()->fetchAll(
    "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
    [$userId]
);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <?= $message ?>

    <h1 class="page-title">Billing &amp; Wallet</h1>
    <p class="page-subtitle">Top up credits, upgrade your plan, and track every transaction.</p>

    <?php if (!$anyLive): ?>
        <div class="card-dark" style="padding:14px 18px;margin-bottom:20px;border-left:3px solid var(--orange);display:flex;gap:12px;align-items:flex-start;">
            <i class="ph ph-warning-circle" style="font-size:20px;color:var(--orange);flex-shrink:0;margin-top:2px;"></i>
            <div style="font-size:13px;color:var(--fg2);">
                <strong style="color:var(--fg);">Test mode:</strong> no live payment keys detected.
                Checkout runs in test mode until keys are added in <strong style="color:var(--fg);">config/apikeys.php</strong>.
            </div>
        </div>
    <?php endif; ?>

    <!-- Gateway selector -->
    <div class="card-dark" style="padding:20px 22px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
            <div class="stat-icon" style="background:rgba(34,197,94,.1);">
                <i class="ph ph-shield-check" style="font-size:18px;color:var(--green);"></i>
            </div>
            <div>
                <h3 style="font-weight:700;color:var(--fg);margin:0;font-size:15px;">Payment Gateway</h3>
                <p style="font-size:12px;color:var(--fg3);margin:0;">Selected gateway applies to top-ups and plan subscriptions.</p>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <label id="gwPaystackCard" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--accent);border-radius:var(--radius);cursor:pointer;background:var(--bg2);">
                <input type="radio" name="gateway_choice" value="paystack" checked style="accent-color:var(--accent);">
                <i class="ph ph-bank" style="font-size:20px;color:var(--accent);"></i>
                <span style="flex:1;">
                    <strong style="display:block;color:var(--fg);font-size:14px;">Paystack</strong>
                    <span style="font-size:12px;color:var(--fg3);">Cards, transfers &amp; mobile money</span>
                </span>
                <?php if ($paystackLive): ?>
                    <span class="badge-active">Live</span>
                <?php else: ?>
                    <span class="badge-pending">Test mode</span>
                <?php endif; ?>
            </label>
            <label id="gwFlutterwaveCard" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;background:var(--bg2);">
                <input type="radio" name="gateway_choice" value="flutterwave" style="accent-color:var(--accent);">
                <i class="ph ph-wallet" style="font-size:20px;color:var(--blue);"></i>
                <span style="flex:1;">
                    <strong style="display:block;color:var(--fg);font-size:14px;">Flutterwave</strong>
                    <span style="font-size:12px;color:var(--fg3);">Cards, USSD &amp; bank payments</span>
                </span>
                <?php if ($flutterLive): ?>
                    <span class="badge-active">Live</span>
                <?php else: ?>
                    <span class="badge-pending">Test mode</span>
                <?php endif; ?>
            </label>
        </div>
    </div>

    <!-- Wallet top-up cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:20px;">
        <!-- Ad Credits -->
        <div class="card-dark stat-card" style="padding:22px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div class="stat-icon" style="background:rgba(200,255,0,.1);">
                    <i class="ph ph-megaphone" style="font-size:20px;color:var(--accent);"></i>
                </div>
                <div>
                    <p class="stat-label" style="margin:0;">Ad Credits</p>
                    <h3 class="stat-value" style="margin:0;">$<?= number_format((float)($user['ad_credits'] ?? 0), 2) ?></h3>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <?php foreach ([5, 10, 25, 50, 100] as $q): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="adAmount" data-amt="<?= $q ?>">$<?= $q ?></button>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup_ad">
                <input type="hidden" name="gateway" value="paystack" class="gateway-field">
                <input type="number" id="adAmount" name="amount" class="form-control" placeholder="Amount ($)" min="5" step="0.01" required style="flex:1;">
                <button type="submit" class="btn-accent btn-sm"><i class="ph ph-plus"></i> Top Up</button>
            </form>
        </div>
        <!-- AI Credits -->
        <div class="card-dark stat-card" style="padding:22px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div class="stat-icon" style="background:rgba(96,165,250,.12);">
                    <i class="ph ph-sparkle" style="font-size:20px;color:var(--blue);"></i>
                </div>
                <div>
                    <p class="stat-label" style="margin:0;">AI Credits</p>
                    <h3 class="stat-value" style="margin:0;"><?= number_format((int)($user['ai_credits'] ?? 0)) ?></h3>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <?php foreach ([5, 10, 25, 50, 100] as $q): ?>
                    <button type="button" class="btn-outline-dark btn-sm quick-amt" data-target="aiAmount" data-amt="<?= $q ?>">$<?= $q ?></button>
                <?php endforeach; ?>
            </div>
            <form method="POST" style="display:flex;gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="topup_ai">
                <input type="hidden" name="gateway" value="paystack" class="gateway-field">
                <input type="number" id="aiAmount" name="amount" class="form-control" placeholder="Amount ($)" min="5" step="0.01" required style="flex:1;">
                <button type="submit" class="btn-accent btn-sm"><i class="ph ph-plus"></i> Top Up</button>
            </form>
        </div>
    </div>

    <!-- Plans upgrade -->
    <div class="card-dark" style="padding:22px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="stat-icon" style="background:rgba(200,255,0,.1);">
                    <i class="ph ph-crown" style="font-size:18px;color:var(--accent);"></i>
                </div>
                <div>
                    <h3 style="font-weight:700;color:var(--fg);margin:0;font-size:15px;">Upgrade Your Plan</h3>
                    <p style="font-size:12px;color:var(--fg3);margin:0;">Unlock higher limits and premium features.</p>
                </div>
            </div>
            <a href="<?= url('pricing-plan') ?>" class="btn-outline-dark btn-sm"><i class="ph ph-grid-four"></i> View all plans</a>
        </div>

        <?php if (empty($plans)): ?>
            <div style="text-align:center;padding:32px 16px;border:1px dashed var(--border);border-radius:var(--radius);">
                <i class="ph ph-package" style="font-size:32px;color:var(--fg3);"></i>
                <p style="color:var(--fg);font-weight:600;margin:10px 0 4px;">No plans available right now</p>
                <p style="color:var(--fg2);font-size:13px;margin:0;">Check back soon — new plans are on the way.</p>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px;">
                <?php foreach ($plans as $plan):
                    $pFeatures = json_decode($plan['features_json'] ?? $plan['features'] ?? '[]', true);
                    if (!is_array($pFeatures)) $pFeatures = [];
                    $isSel = $selectedPlanId > 0 && (int)$plan['id'] === $selectedPlanId;
                ?>
                    <div class="card-dark" style="padding:20px;<?= $isSel ? 'border-color:var(--accent);' : '' ?>">
                        <?php if (!empty($plan['is_featured'])): ?>
                            <span class="badge-active" style="margin-bottom:10px;display:inline-block;">Most Popular</span>
                        <?php endif; ?>
                        <h4 style="font-weight:700;color:var(--fg);margin:0 0 4px;font-size:16px;"><?= e((string)($plan['name'] ?? 'Plan')) ?></h4>
                        <p style="margin:0 0 10px;">
                            <strong style="font-size:24px;font-weight:800;color:var(--fg);">$<?= number_format((float)($plan['price'] ?? 0), 2) ?></strong>
                            <span style="font-size:12px;color:var(--fg3);"> / <?= e((string)($plan['billing_cycle'] ?? 'month')) ?></span>
                        </p>

                        <!-- Bundled Credits Pill -->
                        <div style="background:rgba(200,255,0,0.08);border:1px solid rgba(200,255,0,0.25);border-radius:8px;padding:8px 10px;margin-bottom:12px;font-size:12px;">
                            <div style="color:var(--accent);font-weight:700;display:flex;align-items:center;gap:5px;">
                                <i class="ph ph-gift"></i> Bundled Credits Included:
                            </div>
                            <div style="color:var(--fg);margin-top:3px;font-weight:600;">
                                +$<?= number_format((float)($plan['ad_credits_included'] ?? 0), 2) ?> Ad Credits • <?= (int)($plan['ai_credits_included'] ?? 0) ?> AI Credits
                            </div>
                        </div>

                        <?php if (!empty($pFeatures)): ?>
                            <ul style="list-style:none;padding:0;margin:12px 0;display:flex;flex-direction:column;gap:8px;">
                                <?php foreach (array_slice($pFeatures, 0, 5) as $f): ?>
                                    <li style="display:flex;gap:8px;align-items:flex-start;font-size:13px;color:var(--fg2);">
                                        <i class="ph ph-check-circle" style="color:var(--green);font-size:15px;margin-top:1px;"></i>
                                        <span><?= e(is_string($f) ? $f : json_encode($f)) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <form method="POST" style="margin-top:14px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="subscribe_plan">
                            <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">
                            <input type="hidden" name="gateway" value="paystack" class="gateway-field">
                            <button type="submit" class="btn-accent btn-sm" style="width:100%;justify-content:center;">
                                <i class="ph ph-crown"></i> Subscribe — $<?= number_format((float)($plan['price'] ?? 0), 2) ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Transaction history -->
    <div class="card-dark" style="padding:22px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <div class="stat-icon" style="background:rgba(34,197,94,.1);">
                <i class="ph ph-receipt" style="font-size:18px;color:var(--green);"></i>
            </div>
            <h3 style="font-weight:700;margin:0;color:var(--fg);font-size:15px;">Transaction History</h3>
        </div>
        <?php if (empty($transactions)): ?>
            <div style="text-align:center;padding:28px 16px;border:1px dashed var(--border);border-radius:var(--radius);">
                <i class="ph ph-receipt" style="font-size:28px;color:var(--fg3);"></i>
                <p style="color:var(--fg2);font-size:13px;margin:8px 0 0;">No transactions yet. Your top-ups and subscriptions will appear here.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table-dark" style="width:100%;margin:0;min-width:640px;">
                    <thead>
                        <tr>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Type</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Gateway</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Amount</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Status</th>
                            <th style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--fg3);text-align:left;padding:10px 12px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t):
                            $sClass = match ($t['status'] ?? '') {
                                'successful', 'completed', 'success' => 'badge-active',
                                'pending'    => 'badge-pending',
                                'failed'     => 'badge-danger',
                                default      => 'badge-inactive',
                            };
                        ?>
                            <tr>
                                <td style="font-size:13px;color:var(--fg);text-transform:capitalize;padding:10px 12px;"><?= e(str_replace('_', ' ', (string)($t['type'] ?? ''))) ?></td>
                                <td style="font-size:13px;color:var(--fg);text-transform:capitalize;padding:10px 12px;"><?= e((string)($t['gateway'] ?? '—')) ?></td>
                                <td style="font-weight:600;color:var(--fg);padding:10px 12px;">$<?= number_format((float)($t['amount'] ?? 0), 2) ?></td>
                                <td style="padding:10px 12px;">
                                    <span class="<?= $sClass ?>" style="text-transform:capitalize;"><?= e((string)($t['status'] ?? 'unknown')) ?></span>
                                </td>
                                <td style="font-size:13px;color:var(--fg2);padding:10px 12px;"><?= !empty($t['created_at']) ? date('M d, Y H:i', strtotime((string)$t['created_at'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.quick-amt').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.getAttribute('data-target'));
        if (el) { el.value = btn.getAttribute('data-amt'); el.focus(); }
    });
});
function syncGateway() {
    var sel = document.querySelector('input[name="gateway_choice"]:checked');
    var gw = sel ? sel.value : 'paystack';
    document.querySelectorAll('.gateway-field').forEach(function (f) { f.value = gw; });
    var p = document.getElementById('gwPaystackCard');
    var fl = document.getElementById('gwFlutterwaveCard');
    if (p) p.style.borderColor = gw === 'paystack' ? 'var(--accent)' : 'var(--border)';
    if (fl) fl.style.borderColor = gw === 'flutterwave' ? 'var(--accent)' : 'var(--border)';
}
document.querySelectorAll('input[name="gateway_choice"]').forEach(function (r) {
    r.addEventListener('change', syncGateway);
});
syncGateway();
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
