<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

if (!is_logged_in()) {
    redirect(url('login'));
}

$user = current_user();
$userId = current_user_id();

if (!empty($user['onboarding_completed'])) {
    redirect(url('dashboard'));
}

require_once __DIR__ . '/modules/firecrawl_scraper.php';

$step = max(1, min(4, (int)($_GET['step'] ?? 1)));
$message = '';
$scrapeResult = null;

// Get connected accounts
$connections = db()->fetchAll(
    "SELECT platform FROM connected_accounts WHERE user_id = ?",
    [$userId]
);
$connectedPlatforms = array_column($connections, 'platform');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'connect_social') {
            $platform = trim($_POST['platform'] ?? '');
            $validPlatforms = ['meta', 'instagram', 'tiktok', 'google_ads', 'snapchat', 'google_calendar', 'google_business'];
            if (in_array($platform, $validPlatforms)) {
                $exists = db()->fetch("SELECT id FROM connected_accounts WHERE user_id = ? AND platform = ?", [$userId, $platform]);
                if (!$exists) {
                    $simToken = 'sim_' . bin2hex(random_bytes(16));
                    db()->insert('connected_accounts', [
                        'user_id'          => $userId,
                        'platform'         => $platform,
                        'access_token'     => $simToken,
                        'platform_user_id' => 'onb_' . bin2hex(random_bytes(8)),
                    ]);
                    $connectedPlatforms[] = $platform;
                    $message = '<div class="alert alert-success">' . ucfirst(str_replace('_', ' ', $platform)) . ' connected!</div>';
                }
            }
        } elseif ($action === 'disconnect_social') {
            $platform = trim($_POST['platform'] ?? '');
            db()->delete('connected_accounts', 'user_id = ? AND platform = ?', [$userId, $platform]);
            $connectedPlatforms = array_diff($connectedPlatforms, [$platform]);
            $message = '<div class="alert alert-info">' . ucfirst(str_replace('_', ' ', $platform)) . ' disconnected.</div>';
        } elseif ($action === 'scrape_website') {
            $url = trim($_POST['website_url'] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $message = '<div class="alert alert-danger">Please enter a valid URL.</div>';
            } else {
                $scrapeResult = FirecrawlScraper::scrapeUrl($url, ['onlyMainContent' => true]);
                if ($scrapeResult['success']) {
                    $message = '<div class="alert alert-success">Website scraped! We found content from: ' . e($scrapeResult['metadata']['title'] ?? $url) . '</div>';
                } else {
                    $message = '<div class="alert alert-warning">' . e($scrapeResult['message'] ?? 'Scraping failed.') . '</div>';
                }
            }
        } elseif ($action === 'next_step') {
            $step = min(4, $step + 1);
        } elseif ($action === 'prev_step') {
            $step = max(1, $step - 1);
        } elseif ($action === 'complete') {
            db()->update('users', ['onboarding_completed' => 1], 'id = ?', [$userId]);
            redirect(url('dashboard'));
        }
    }
}

$pageTitle = 'Getting Started â€” ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/includes/header.php';

$socialPlatforms = [
    ['id' => 'meta',             'icon' => 'ph-facebook-logo',  'label' => 'Meta (Facebook)',  'color' => '#1877F2'],
    ['id' => 'instagram',        'icon' => 'ph-instagram-logo', 'label' => 'Instagram',        'color' => '#E4405F'],
    ['id' => 'tiktok',           'icon' => 'ph-music-note',     'label' => 'TikTok',           'color' => '#000000'],
    ['id' => 'google_ads',       'icon' => 'ph-google-logo',    'label' => 'Google Ads',       'color' => '#4285F4'],
    ['id' => 'snapchat',         'icon' => 'ph-ghost',          'label' => 'Snapchat',         'color' => '#FFFC00'],
];
$businessPlatforms = [
    ['id' => 'google_business',  'icon' => 'ph-storefront',     'label' => 'Google Business Profile', 'color' => '#4285F4'],
    ['id' => 'google_calendar',  'icon' => 'ph-calendar',       'label' => 'Google Calendar',         'color' => '#4285F4'],
];
?>

<section class="section-padding py-5" style="min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:700px;">

        <div class="text-center tw-mb-6">
            <h1 class="h2 text-white fw-bold">Welcome, <?= e($user['full_name']) ?>!</h1>
            <p class="text-white-50 tw-mt-2">Set up your Adsbooster account in a few quick steps.</p>
        </div>

        <!-- Progress -->
        <div class="d-flex justify-content-center tw-gap-2 tw-mb-8">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="tw-w-10 tw-h-10 tw-rounded-full d-flex align-items-center justify-content-center fw-bold tw-text-sm <?= $i <= $step ? 'bg-main-600 text-white' : 'bg-dark-900 text-white-50 border border-neutral-700' ?>">
                    <?= $i ?>
                </div>
            <?php endfor; ?>
        </div>

        <?= $message ?>

        <!-- Step 1: Connect Social Accounts -->
        <?php if ($step === 1): ?>
        <div class="bg-white-06 border border-neutral-700 tw-rounded-xl tw-p-6 tw-mb-6">
            <h4 class="text-white fw-bold tw-mb-2">Connect Social Accounts</h4>
            <p class="text-white-50 tw-text-sm tw-mb-6">Link your accounts to run ads and schedule posts. All connections are optional.</p>

            <div class="row g-3">
                <?php foreach ($socialPlatforms as $p):
                    $isConnected = in_array($p['id'], $connectedPlatforms);
                ?>
                    <div class="col-md-6">
                        <div class="bg-dark-900 border <?= $isConnected ? 'border-success-600' : 'border-neutral-700' ?> tw-rounded-lg tw-p-4 d-flex align-items-center tw-gap-3">
                            <i class="ph-bold <?= $p['icon'] ?> tw-text-xl" style="color:<?= $p['color'] ?>"></i>
                            <span class="text-white fw-medium tw-text-sm flex-grow-1"><?= e($p['label']) ?></span>
                            <?php if ($isConnected): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="disconnect_social">
                                    <input type="hidden" name="platform" value="<?= e($p['id']) ?>">
                                    <button type="submit" class="badge bg-success-600 bg-opacity-15 text-success-600 rounded-pill tw-px-3 tw-py-1 tw-text-xs border-0 cursor-pointer">Connected</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="connect_social">
                                    <input type="hidden" name="platform" value="<?= e($p['id']) ?>">
                                    <button type="submit" class="badge bg-main-600 bg-opacity-15 text-main-600 rounded-pill tw-px-3 tw-py-1 tw-text-xs border-0 cursor-pointer">Connect</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Google Business & Calendar -->
        <?php elseif ($step === 2): ?>
        <div class="bg-white-06 border border-neutral-700 tw-rounded-xl tw-p-6 tw-mb-6">
            <h4 class="text-white fw-bold tw-mb-2">Business Integrations</h4>
            <p class="text-white-50 tw-text-sm tw-mb-6">Optionally connect Google Business Profile and Calendar for local ads and scheduling.</p>

            <div class="row g-3">
                <?php foreach ($businessPlatforms as $p):
                    $isConnected = in_array($p['id'], $connectedPlatforms);
                ?>
                    <div class="col-md-6">
                        <div class="bg-dark-900 border <?= $isConnected ? 'border-success-600' : 'border-neutral-700' ?> tw-rounded-lg tw-p-4 d-flex align-items-center tw-gap-3">
                            <i class="ph-bold <?= $p['icon'] ?> tw-text-xl" style="color:<?= $p['color'] ?>"></i>
                            <span class="text-white fw-medium tw-text-sm flex-grow-1"><?= e($p['label']) ?></span>
                            <?php if ($isConnected): ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="disconnect_social">
                                    <input type="hidden" name="platform" value="<?= e($p['id']) ?>">
                                    <button type="submit" class="badge bg-success-600 bg-opacity-15 text-success-600 rounded-pill tw-px-3 tw-py-1 tw-text-xs border-0 cursor-pointer">Connected</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="connect_social">
                                    <input type="hidden" name="platform" value="<?= e($p['id']) ?>">
                                    <button type="submit" class="badge bg-main-600 bg-opacity-15 text-main-600 rounded-pill tw-px-3 tw-py-1 tw-text-xs border-0 cursor-pointer">Connect</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 3: Website Scraping -->
        <?php elseif ($step === 3): ?>
        <div class="bg-white-06 border border-neutral-700 tw-rounded-xl tw-p-6 tw-mb-6">
            <h4 class="text-white fw-bold tw-mb-2">Scrape Your Website <span class="badge bg-white-06 text-white-50 tw-text-xs fw-normal">Optional</span></h4>
            <p class="text-white-50 tw-text-sm tw-mb-6">Enter your website URL to extract content for AI-powered ad generation. This helps us create better ad copy and targeting.</p>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="scrape_website">
                <div class="d-flex tw-gap-3">
                    <input type="url" name="website_url" class="form-control bg-dark-900 text-white border-neutral-700 tw-p-3" placeholder="https://yourwebsite.com" required>
                    <button type="submit" class="btn btn-main rounded-pill tw-px-5 tw-text-sm fw-semibold flex-shrink-0">
                        <i class="ph-bold ph-spider tw-me-1"></i>Scrape
                    </button>
                </div>
            </form>

            <?php if ($scrapeResult && $scrapeResult['success']): ?>
                <div class="tw-mt-4 tw-p-4 bg-dark-900 border border-success-600 border-opacity-25 rounded-lg">
                    <h6 class="text-success-600 fw-bold tw-mb-2"><i class="ph-bold ph-check-circle tw-me-1"></i>Content Extracted</h6>
                    <p class="text-white-50 tw-text-sm tw-mb-1"><strong>Title:</strong> <?= e($scrapeResult['metadata']['title'] ?? 'N/A') ?></p>
                    <p class="text-white-50 tw-text-sm tw-mb-1"><strong>Description:</strong> <?= e(mb_substr($scrapeResult['metadata']['description'] ?? '', 0, 200)) ?></p>
                    <p class="text-white-50 tw-text-sm tw-mb-0"><strong>Content preview:</strong> <?= e(mb_substr($scrapeResult['content'] ?? '', 0, 300)) ?>...</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 4: Launch -->
        <?php elseif ($step === 4): ?>
        <div class="bg-white-06 border border-neutral-700 tw-rounded-xl tw-p-6 tw-mb-6 text-center">
            <div class="tw-w-20 tw-h-20 bg-main-600 bg-opacity-15 rounded-circle d-inline-flex align-items-center justify-content-center tw-mb-4">
                <i class="ph-bold ph-rocket-launch tw-text-3xl text-main-600"></i>
            </div>
            <h4 class="text-white fw-bold tw-mb-2">You're All Set!</h4>
            <p class="text-white-50 tw-mb-0">Your account is ready. Head to the dashboard to create your first campaign.</p>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="d-flex tw-gap-3 justify-content-between">
            <?php if ($step > 1): ?>
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="prev_step">
                    <input type="hidden" name="step" value="<?= $step - 1 ?>">
                    <button type="submit" class="btn bg-white-06 border-neutral-700 text-white tw-px-6 tw-py-3 rounded-pill fw-medium">
                        <i class="ph-bold ph-arrow-left tw-me-1"></i>Back
                    </button>
                </form>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <?php if ($step < 4): ?>
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="next_step">
                    <button type="submit" class="btn btn-main rounded-pill tw-px-6 tw-py-3 fw-semibold">
                        <?= $step === 3 ? 'Continue' : 'Next' ?> <i class="ph-bold ph-arrow-right tw-ms-1"></i>
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-main rounded-pill tw-px-6 tw-py-3 fw-semibold">
                        Go to Dashboard <i class="ph-bold ph-arrow-right tw-ms-1"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Skip link -->
        <?php if ($step === 1): ?>
            <div class="text-center tw-mt-6">
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="text-white-50 hover-text-white tw-text-sm border-0 bg-transparent text-decoration-underline">Skip onboarding</button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
