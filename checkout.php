<?php declare(strict_types=1);

require_once __DIR__ . '/modules/payments.php';

$userId  = current_user_id();
$errors  = [];

$planSlug  = trim($_GET['plan'] ?? '');
$creditAmt = (float)($_GET['credits'] ?? 0);
$currency  = strtoupper(trim($_GET['currency'] ?? 'NGN'));
$gateway   = '';

$planDetails = null;
if ($planSlug !== '') {
    $planDetails = db()->fetchOne("SELECT * FROM pricing_plans WHERE slug = ? AND is_active = 1", [$planSlug]);
    if (!$planDetails) {
        $errors[] = 'Invalid or inactive plan.';
    }
}

$isCredits = false;
if ($creditAmt > 0 && !$planDetails) {
    $isCredits = true;
    $planDetails = [
        'name'        => number_format($creditAmt) . ' AI Credits',
        'description' => 'Credit top-up for AI-powered tools',
        'price'       => $creditAmt,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token.';
    } else {
        $gateway = $_POST['gateway'] ?? '';

        if (!in_array($gateway, ['paystack', 'flutterwave'], true)) {
            $errors[] = 'Please select a valid payment gateway.';
        } else {
            $type = $isCredits ? 'ai_credits' : 'subscription';
            $amount = $isCredits ? $creditAmt : (float)($planDetails['price'] ?? 0);

            $result = PaymentProcessor::initiatePayment($userId, $type, $amount, $currency, $gateway);

            if ($result['success'] && $result['redirect_url']) {
                redirect($result['redirect_url']);
            } else {
                $errors[] = $result['message'] ?? 'Payment initiation failed.';
            }
        }
    }
}

$pageTitle = 'Checkout — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/app_header.php';
?>

<style>
    .checkout-section { background: linear-gradient(135deg, #0d0d0d 0%, #1a1a2e 100%); min-height: 100vh; }
    .checkout-card { background: #1a1a1a; border: 1px solid #2a2a2a; }
    .gateway-option { border: 2px solid #2a2a2a; border-radius: .75rem; padding: 1rem; cursor: pointer; transition: border-color .25s, background .25s; }
    .gateway-option:hover { border-color: #1db954; }
    .gateway-option.selected { border-color: #1db954; background: rgba(29,185,84,.08); }
    .gateway-option input[type="radio"] { display: none; }
</style>

<div style="max-width:560px;margin:0 auto;">
        <div class="text-center mb-5">
            <h1 class="text-white fw-bold display-6">Checkout</h1>
            <p class="text-white-50">Complete your purchase securely</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger-100 text-danger-700 border-0 tw-p-4 rounded-lg mb-4">
                <?php foreach ($errors as $err): ?>
                    <p class="mb-0"><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$planDetails): ?>
            <div class="text-center py-5">
                <i class="ph-bold ph-shopping-cart text-white-50" style="font-size:3rem;"></i>
                <h4 class="text-white mt-3">No item selected</h4>
                <p class="text-white-50">Please select a plan or credit package from the pricing page.</p>
                <a href="<?= url('pricing-plan') ?>" class="btn btn-main rounded-pill fw-semibold">View Plans</a>
            </div>
        <?php else: ?>
            <!-- Order Summary -->
            <div class="checkout-card rounded-3 p-4 mb-4">
                <h5 class="text-white fw-bold mb-4"><i class="ph-bold ph-receipt me-2 text-main-600"></i>Order Summary</h5>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-neutral-800">
                    <div>
                        <h6 class="text-white fw-bold mb-1"><?= e($planDetails['name']) ?></h6>
                        <?php if (!empty($planDetails['description'])): ?>
                            <p class="text-white-50 small mb-0"><?= e($planDetails['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <span class="text-main-600 fw-bold h4 mb-0"><?= e($currency) ?> <?= number_format((float)$planDetails['price'], 2) ?></span>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-white fw-semibold">Total</span>
                    <span class="text-main-600 fw-bold h4 mb-0"><?= e($currency) ?> <?= number_format((float)$planDetails['price'], 2) ?></span>
                </div>
            </div>

            <!-- Payment Gateway -->
            <form method="POST" id="checkoutForm">
                <?= csrf_field() ?>
                <input type="hidden" name="plan" value="<?= e($planSlug) ?>">
                <?php if ($isCredits): ?>
                    <input type="hidden" name="credits" value="<?= $creditAmt ?>">
                <?php endif; ?>
                <input type="hidden" name="currency" value="<?= e($currency) ?>">

                <div class="checkout-card rounded-3 p-4 mb-4">
                    <h5 class="text-white fw-bold mb-4"><i class="ph-bold ph-credit-card me-2 text-main-600"></i>Payment Gateway</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="gateway-option d-flex align-items-center gap-3" id="gw-paystack">
                                <input type="radio" name="gateway" value="paystack" <?= $gateway === 'paystack' ? 'checked' : '' ?>>
                                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                    <span class="text-white fw-bold small">P</span>
                                </div>
                                <div>
                                    <span class="text-white fw-semibold small">Paystack</span>
                                    <small class="d-block text-white-50" style="font-size:.7rem;">Card, Bank, USSD</small>
                                </div>
                                <i class="ph-bold ph-check-circle text-main-600 ms-auto gateway-check" style="display:none;"></i>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="gateway-option d-flex align-items-center gap-3" id="gw-flutterwave">
                                <input type="radio" name="gateway" value="flutterwave" <?= $gateway === 'flutterwave' ? 'checked' : '' ?>>
                                <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                    <span class="text-white fw-bold small">F</span>
                                </div>
                                <div>
                                    <span class="text-white fw-semibold small">Flutterwave</span>
                                    <small class="d-block text-white-50" style="font-size:.7rem;">Card, Bank, Mobile</small>
                                </div>
                                <i class="ph-bold ph-check-circle text-main-600 ms-auto gateway-check" style="display:none;"></i>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-main w-100 rounded-pill fw-semibold py-3" id="payBtn">
                    <span class="button__flair"></span>
                    <span class="button__label">
                        <i class="ph-bold ph-lock me-1"></i>
                        Pay <?= e($currency) ?> <?= number_format((float)$planDetails['price'], 2) ?>
                    </span>
                </button>

                <p class="text-center text-white-50 small mt-3">
                    <i class="ph-bold ph-shield-check me-1"></i>
                    Secured by Paystack / Flutterwave
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.gateway-option').forEach(function(el) {
    el.addEventListener('click', function() {
        document.querySelectorAll('.gateway-option').forEach(function(o) {
            o.classList.remove('selected');
            o.querySelector('.gateway-check').style.display = 'none';
        });
        el.classList.add('selected');
        el.querySelector('.gateway-check').style.display = 'inline';
        el.querySelector('input[type="radio"]').checked = true;
    });
    if (el.querySelector('input[type="radio"]').checked) {
        el.classList.add('selected');
        el.querySelector('.gateway-check').style.display = 'inline';
    }
});
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
