<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$pageTitle = 'Launch Ad Campaign — ' . get_setting('site_name', 'Adsbooster.net');
require_once __DIR__ . '/modules/ai_engine.php';

$userId   = current_user_id();
$userData = current_user();
$adCredits = (float)($userData['ad_credits'] ?? 0);

$errors = [];
$success = '';

// Pre-fill state
$old = [
    'platform'        => trim($_GET['platform'] ?? 'meta'),
    'campaign_name'   => '',
    'objective'       => 'traffic',
    'budget_type'     => 'daily',
    'budget_amount'   => '10.00',
    'age_min'         => '18',
    'age_max'         => '65',
    'geo'             => 'United States, United Kingdom, Canada',
    'interests'       => 'E-commerce, Digital Marketing, AI Tools',
    'headline'        => trim($_GET['headline'] ?? 'Scale Your Business Faster with Adsbooster'),
    'primary_text'    => trim($_GET['primary_text'] ?? 'Launch high-performing ad campaigns across Meta, TikTok, and Google Ads from one unified dashboard.'),
    'destination_url' => 'https://adsbooster.net',
    'media_url'       => trim($_GET['image_url'] ?? ''),
    'media_type'      => 'image',
    'media_thumb'     => trim($_GET['image_url'] ?? '')
];

// Handle video_id parameter from UGC Studio
if (!empty($_GET['video_id'])) {
    $videoCreative = db()->fetch("SELECT * FROM ai_video_creatives WHERE id = ? AND user_id = ?", [(int)$_GET['video_id'], $userId]);
    if ($videoCreative) {
        $old['campaign_name'] = 'Video Campaign — ' . ($videoCreative['hook_text'] ?: 'UGC Ad');
        $old['headline']      = $videoCreative['hook_text'] ?: $old['headline'];
        $old['primary_text']  = ($videoCreative['script_body'] ?? '') ? $videoCreative['script_body'] . "\n\n" . ($videoCreative['cta_text'] ?? '') : $old['primary_text'];
        $old['media_url']     = $videoCreative['video_url'];
        $old['media_type']    = 'video';
        $old['media_thumb']   = $videoCreative['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png';
        if ($videoCreative['avatar_id'] === 'commercial_cinematic') {
            $old['platform'] = 'meta';
        } else {
            $old['platform'] = 'tiktok';
        }
    }
}

// Handle creative_id parameter from UGC Image Generator
if (!empty($_GET['creative_id'])) {
    $imgCreative = db()->fetch("SELECT * FROM ai_creatives WHERE id = ? AND user_id = ?", [(int)$_GET['creative_id'], $userId]);
    if ($imgCreative) {
        $data = json_decode($imgCreative['result_output'] ?? '', true) ?: [];
        $imgUrl = $data['image_url'] ?? $imgCreative['result_output'];
        $old['campaign_name'] = 'UGC Campaign — ' . ($data['product_name'] ?? 'Product');
        $old['headline']      = $data['hook_headline'] ?? $old['headline'];
        $old['primary_text']  = ($data['ad_copy'] ?? '') ?: $old['primary_text'];
        $old['media_url']     = $imgUrl;
        $old['media_type']    = 'image';
        $old['media_thumb']   = $imgUrl;
    }
}

// Fetch user's existing generated library assets
$userVideos = db()->fetchAll("SELECT * FROM ai_video_creatives WHERE user_id = ? ORDER BY created_at DESC LIMIT 20", [$userId]);
$userImages = db()->fetchAll("SELECT * FROM ai_creatives WHERE user_id = ? AND type = 'image' ORDER BY created_at DESC LIMIT 20", [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($_POST['compliance'])) {
        $errors[] = 'You must confirm ad policy compliance.';
    } else {
        $old['platform']        = trim($_POST['platform'] ?? 'meta');
        $old['campaign_name']   = trim($_POST['campaign_name'] ?? '');
        $old['objective']       = trim($_POST['objective'] ?? 'traffic');
        $old['budget_type']     = trim($_POST['budget_type'] ?? 'daily');
        $old['budget_amount']   = trim($_POST['budget_amount'] ?? '');
        $old['age_min']         = trim($_POST['age_min'] ?? '18');
        $old['age_max']         = trim($_POST['age_max'] ?? '65');
        $old['geo']             = trim($_POST['geo'] ?? '');
        $old['interests']       = trim($_POST['interests'] ?? '');
        $old['headline']        = trim($_POST['headline'] ?? '');
        $old['primary_text']    = trim($_POST['primary_text'] ?? '');
        $old['destination_url'] = trim($_POST['destination_url'] ?? '');
        $old['media_url']       = trim($_POST['existing_media_url'] ?? '');
        $old['media_type']      = trim($_POST['selected_media_type'] ?? 'image');

        if ($old['platform'] === '') $errors[] = 'Please select a platform.';
        if ($old['campaign_name'] === '') $errors[] = 'Campaign name is required.';
        if ($old['objective'] === '') $errors[] = 'Please select an objective.';
        if (!is_numeric($old['budget_amount']) || (float)$old['budget_amount'] <= 0) $errors[] = 'Please enter a valid budget amount.';
        if ($old['headline'] === '') $errors[] = 'Headline is required.';
        if ($old['primary_text'] === '') $errors[] = 'Primary text is required.';
        if (!filter_var($old['destination_url'], FILTER_VALIDATE_URL)) $errors[] = 'Please enter a valid destination URL.';

        $budgetAmount = (float)($old['budget_amount'] ?? 0);
        $estimatedCost = ($old['budget_type'] === 'lifetime') ? $budgetAmount : $budgetAmount * 30;

        if (empty($errors)) {
            if ($adCredits < $estimatedCost) {
                $errors[] = 'Insufficient ad credits. You need $' . number_format($estimatedCost, 2) . ' but have $' . number_format($adCredits, 2) . '. Please top up your wallet.';
            } else {
                $creativePath = $old['media_url'] ?: null;

                // Handle file upload override if provided
                if (!empty($_FILES['creative']['tmp_name']) && $_FILES['creative']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['creative']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','pdf'];
                    if (!in_array($ext, $allowed)) {
                        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowed);
                    } else {
                        $uploadDir = ROOT_PATH . '/uploads/creatives/' . date('Y/m');
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
                        move_uploaded_file($_FILES['creative']['tmp_name'], $uploadDir . '/' . $filename);
                        $creativePath = 'uploads/creatives/' . date('Y/m') . '/' . $filename;
                    }
                }

                if (empty($errors)) {
                    try {
                        db()->query("BEGIN");

                        $newBalance = $adCredits - $estimatedCost;
                        db()->update('users', ['ad_credits' => $newBalance], 'id = ?', [$userId]);

                        $campaignId = db()->insert('ad_campaigns', [
                            'user_id'            => $userId,
                            'campaign_name'      => $old['campaign_name'],
                            'campaign_type'      => $old['platform'],
                            'objective'          => $old['objective'],
                            'budget_type'        => $old['budget_type'],
                            'budget_amount'      => $budgetAmount,
                            'targeting_spec'     => json_encode([
                                'age_min'   => (int)$old['age_min'],
                                'age_max'   => (int)$old['age_max'],
                                'geo'       => $old['geo'],
                                'interests' => $old['interests'],
                            ]),
                            'headline'           => $old['headline'],
                            'primary_text'       => $old['primary_text'],
                            'destination_url'    => $old['destination_url'],
                            'creative_media_url' => $creativePath,
                            'status'             => 'active',
                            'autopilot_enabled'  => 1
                        ]);

                        db()->insert('transactions', [
                            'user_id'     => $userId,
                            'gateway'     => 'wallet',
                            'type'        => 'ad_campaign_fee',
                            'amount'      => $estimatedCost,
                            'currency'    => 'USD',
                            'status'      => 'successful',
                        ]);

                        db()->query("COMMIT");

                        set_flash('success', "🚀 Ad Campaign '{$old['campaign_name']}' launched successfully in real time!");
                        redirect(url('dashboard'));

                    } catch (\Throwable $e) {
                        db()->query("ROLLBACK");
                        error_log("Create ad failed: " . $e->getMessage());
                        $errors[] = 'Failed to create campaign: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$estimatedCost = ($old['budget_type'] === 'lifetime') ? (float)$old['budget_amount'] : (float)$old['budget_amount'] * 30;

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="w-100">
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">
                <i class="ph-bold ph-rocket-launch text-accent me-2"></i> Launch Real-Time Ad Campaign
            </h1>
            <p class="page-subtitle mb-0">Configure targeting, budget, and pick from your AI generated UGC images or videos with live multi-platform smartphone preview.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-light rounded-pill px-3 font-size-13" data-bs-toggle="modal" data-bs-target="#assetLibraryModal">
                <i class="ph-bold ph-squares-four text-accent me-1"></i> Pick from AI Library
            </button>
            <a href="<?= url('dashboard') ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                <i class="ph ph-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Column (Left) -->
        <div class="col-xl-7 col-lg-7">
            <div class="card-dark p-4 rounded-4" style="background:#0d1524 !important; border:1px solid rgba(255,255,255,0.08) !important;">
                <form method="POST" action="<?= url('create-ad') ?>" enctype="multipart/form-data" id="adForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="existing_media_url" id="inp_existing_media_url" value="<?= e($old['media_url']) ?>">
                    <input type="hidden" name="selected_media_type" id="inp_selected_media_type" value="<?= e($old['media_type']) ?>">

                    <!-- Platform Selector -->
                    <div style="margin-bottom:28px;">
                        <label class="form-label fw-bold text-white mb-2" style="font-size:14px;">1. Select Ad Network</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;">
                            <?php
                            $platforms = [
                                ['id' => 'meta',       'icon' => 'ph-facebook-logo',  'label' => 'Meta (FB)',  'color' => '#1877F2'],
                                ['id' => 'instagram',  'icon' => 'ph-instagram-logo', 'label' => 'Instagram',  'color' => '#E4405F'],
                                ['id' => 'tiktok',     'icon' => 'ph-tiktok-logo',    'label' => 'TikTok Ads', 'color' => '#00f2fe'],
                                ['id' => 'google_ads', 'icon' => 'ph-google-logo',    'label' => 'Google Ads', 'color' => '#4285F4'],
                                ['id' => 'snapchat',   'icon' => 'ph-ghost',          'label' => 'Snapchat',   'color' => '#FFFC00'],
                            ];
                            foreach ($platforms as $p): ?>
                                <label class="platform-card <?= $old['platform'] === $p['id'] ? 'selected' : '' ?>" style="display:flex;align-items:center;gap:8px;padding:12px 14px;border-radius:12px;cursor:pointer;border:1px solid <?= $old['platform'] === $p['id'] ? 'var(--accent)' : 'rgba(255,255,255,0.08)' ?>;background:<?= $old['platform'] === $p['id'] ? 'rgba(200,255,0,0.08)' : '#080c14' ?>;transition:all 0.2s;" onclick="setPlatform('<?= $p['id'] ?>', '<?= $p['label'] ?>', this)">
                                    <input type="radio" name="platform" value="<?= $p['id'] ?>" <?= $old['platform'] === $p['id'] ? 'checked' : '' ?> style="display:none;">
                                    <i class="ph <?= $p['icon'] ?>" style="font-size:20px;color:<?= $p['color'] ?>;"></i>
                                    <span style="color:#fff;font-weight:600;font-size:12.5px;"><?= e($p['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Campaign Name & Objective -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label for="campaign_name" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Campaign Name</label>
                            <input type="text" id="campaign_name" name="campaign_name" class="form-control" value="<?= e($old['campaign_name'] ?: 'Summer Scale Campaign') ?>" required placeholder="e.g. Summer Growth Campaign 2026" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:10px 14px;">
                        </div>
                        <div class="col-md-5">
                            <label for="objective" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Objective</label>
                            <select id="objective" name="objective" class="form-select" required style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:10px 14px;">
                                <option value="traffic" <?= $old['objective'] === 'traffic' ? 'selected' : '' ?>>Website Traffic</option>
                                <option value="leads" <?= $old['objective'] === 'leads' ? 'selected' : '' ?>>Lead Generation</option>
                                <option value="conversions" <?= $old['objective'] === 'conversions' ? 'selected' : '' ?>>Sales &amp; Conversions</option>
                                <option value="brand_awareness" <?= $old['objective'] === 'brand_awareness' ? 'selected' : '' ?>>Brand Awareness</option>
                                <option value="app_installs" <?= $old['objective'] === 'app_installs' ? 'selected' : '' ?>>App Installs</option>
                            </select>
                        </div>
                    </div>

                    <!-- Budget & Estimated Cost -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="budget_type" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Budget Structure</label>
                            <select id="budget_type" name="budget_type" class="form-select" onchange="updateEstimate()" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:10px 14px;">
                                <option value="daily" <?= $old['budget_type'] === 'daily' ? 'selected' : '' ?>>Daily Budget (30 Days)</option>
                                <option value="lifetime" <?= $old['budget_type'] === 'lifetime' ? 'selected' : '' ?>>Lifetime Budget</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="budget_amount" class="form-label" style="font-weight:600;color:var(--fg);font-size:13px;">Budget Amount ($ USD)</label>
                            <input type="number" id="budget_amount" name="budget_amount" step="0.01" min="1" class="form-control" value="<?= e($old['budget_amount']) ?>" required placeholder="10.00" oninput="updateEstimate()" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:10px 14px;">
                        </div>
                    </div>

                    <!-- Targeting Details -->
                    <div style="background:#080c14;border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:18px;margin-bottom:28px;">
                        <label class="form-label" style="font-weight:700;color:#fff;font-size:13px;margin-bottom:12px;">Audience &amp; Geographic Targeting</label>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:11px;color:var(--fg3);">Min Age</label>
                                <input type="number" id="age_min" name="age_min" min="13" max="65" class="form-control" value="<?= e($old['age_min']) ?>" style="background:#0d1524;border:1px solid rgba(255,255,255,0.08);color:#fff;">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-size:11px;color:var(--fg3);">Max Age</label>
                                <input type="number" id="age_max" name="age_max" min="13" max="65" class="form-control" value="<?= e($old['age_max']) ?>" style="background:#0d1524;border:1px solid rgba(255,255,255,0.08);color:#fff;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-size:11px;color:var(--fg3);">Locations</label>
                                <input type="text" id="geo" name="geo" class="form-control" value="<?= e($old['geo']) ?>" placeholder="e.g. United States, United Kingdom" style="background:#0d1524;border:1px solid rgba(255,255,255,0.08);color:#fff;">
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="font-size:11px;color:var(--fg3);">Interests &amp; Keywords</label>
                                <input type="text" id="interests" name="interests" class="form-control" value="<?= e($old['interests']) ?>" placeholder="e.g. E-commerce, Fitness, Real Estate" style="background:#0d1524;border:1px solid rgba(255,255,255,0.08);color:#fff;">
                            </div>
                        </div>
                    </div>

                    <!-- Ad Copy & Creative -->
                    <div style="margin-bottom:24px;">
                        <label for="headline" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Ad Headline</label>
                        <input type="text" id="headline" name="headline" maxlength="150" class="form-control" value="<?= e($old['headline']) ?>" required placeholder="Grab attention with a powerful headline..." oninput="updateLivePreview()" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 16px;">
                    </div>

                    <div style="margin-bottom:24px;">
                        <label for="primary_text" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Primary Ad Text / Caption</label>
                        <textarea id="primary_text" name="primary_text" rows="4" class="form-control" required placeholder="Describe your product or offer compellingly..." oninput="updateLivePreview()" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 16px;"><?= e($old['primary_text']) ?></textarea>
                    </div>

                    <div style="margin-bottom:24px;">
                        <label for="destination_url" class="form-label" style="font-weight:700;color:#fff;font-size:13px;">Destination Landing Page URL</label>
                        <input type="url" id="destination_url" name="destination_url" class="form-control" value="<?= e($old['destination_url']) ?>" required placeholder="https://yourwebsite.com/offer" oninput="updateLivePreview()" style="background:#080c14;border:1px solid rgba(255,255,255,0.12);color:#fff;padding:12px 16px;">
                    </div>

                    <!-- Creative Media Selector (AI Library or Upload) -->
                    <div style="margin-bottom:28px;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0" style="font-weight:700;color:#fff;font-size:13px;">Creative Media (Image or Video)</label>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#assetLibraryModal">
                                <i class="ph-bold ph-squares-four me-1"></i> Pick from AI Library
                            </button>
                        </div>
                        
                        <div id="selectedCreativeCard" class="p-3 rounded-3 mb-2" style="background:#080c14;border:1px solid rgba(200,255,0,0.3);<?= empty($old['media_url']) ? 'display:none;' : '' ?>">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-2 overflow-hidden bg-black" style="width:54px;height:54px;">
                                        <img id="selMediaThumb" src="<?= BASE_URL ?>/<?= e($old['media_thumb'] ?: 'assets/images/thumbs/about-five-img1.png') ?>" class="w-100 h-100 object-fit-cover">
                                    </div>
                                    <div>
                                        <div class="text-white fw-bold font-size-13" id="selMediaTitle">Selected: <?= e(ucfirst($old['media_type'])) ?> Asset</div>
                                        <div class="text-white-50 font-size-11" id="selMediaUrlText"><?= e($old['media_url']) ?></div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1" onclick="clearSelectedCreative()" title="Remove">
                                    <i class="ph ph-x"></i>
                                </button>
                            </div>
                        </div>

                        <div style="border:2px dashed rgba(255,255,255,0.15);border-radius:12px;padding:20px;text-align:center;background:#080c14;cursor:pointer;position:relative;" onclick="document.getElementById('creative').click()">
                            <i class="ph ph-upload-simple" style="font-size:28px;color:var(--accent);margin-bottom:6px;"></i>
                            <div style="font-weight:600;color:#fff;font-size:13.5px;">Or Upload New File From Device</div>
                            <span style="font-size:11.5px;color:var(--fg3);">JPG, PNG, WebP, GIF, MP4, MOV (Max 50MB)</span>
                            <input type="file" id="creative" name="creative" accept="image/*,video/*" style="display:none;" onchange="handleCreativeUpload(this)">
                        </div>
                    </div>

                    <!-- Balance Summary Card -->
                    <div style="background:rgba(200,255,0,0.06);border:1px solid rgba(200,255,0,0.25);border-radius:12px;padding:18px 24px;margin-bottom:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <span style="color:var(--fg2);font-size:13px;">Available Ad Credits:</span>
                            <span style="color:#fff;font-weight:700;font-size:15px;">$<?= number_format($adCredits, 2) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <span style="color:var(--fg2);font-size:13px;">Estimated Campaign Cost:</span>
                            <span style="color:var(--accent);font-weight:700;font-size:17px;" id="estCost">$<?= number_format($estimatedCost, 2) ?></span>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="compliance" id="compliance" required style="cursor:pointer;" checked>
                        <label class="form-check-label" for="compliance" style="color:var(--fg2);font-size:12.5px;cursor:pointer;">
                            I confirm that this ad creative and copy comply with the Advertising Policies of the selected ad network.
                        </label>
                    </div>

                    <button type="submit" class="btn-accent w-100" style="padding:14px;font-size:15px;font-weight:700;border-radius:12px;">
                        <i class="ph ph-rocket me-1"></i> Launch Campaign on Autopilot
                    </button>
                </form>
            </div>
        </div>

        <!-- Live Real-Time Device Mockup Preview Column (Right) -->
        <div class="col-xl-5 col-lg-5">
            <div style="position:sticky;top:90px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i class="ph ph-device-mobile" style="font-size:20px;color:var(--accent);"></i>
                        <span style="font-weight:700;color:#fff;font-size:14px;">Real-Time Device Preview</span>
                    </div>
                    <span class="badge-status badge-active" style="font-size:11px;" id="previewNetworkPill"><?= ucfirst($old['platform']) ?> Feed</span>
                </div>

                <!-- Smartphone Mockup Wrapper -->
                <div style="background:#020617;border:3px solid #1e293b;border-radius:36px;box-shadow:0 20px 40px rgba(0,0,0,0.6);padding:14px;max-width:380px;margin:0 auto;position:relative;">
                    <!-- Phone Speaker Notch -->
                    <div style="width:100px;height:4px;background:#334155;border-radius:10px;margin:4px auto 14px;"></div>

                    <!-- Screen Inner -->
                    <div style="background:#0f172a;border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,0.06);">
                        <!-- Ad Header -->
                        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.04);">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg, var(--accent), #10b981);display:flex;align-items:center;justify-content:center;color:#080c14;font-weight:700;font-size:13px;">
                                    <?= strtoupper(substr($userData['full_name'] ?? 'B', 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:700;color:#fff;font-size:13px;" id="prevBrandName"><?= htmlspecialchars($userData['full_name'] ?? 'Your Brand') ?></div>
                                    <div style="font-size:10px;color:var(--fg3);display:flex;align-items:center;gap:4px;">
                                        <span>Sponsored</span> • <i class="ph ph-globe" style="font-size:10px;"></i>
                                    </div>
                                </div>
                            </div>
                            <i class="ph ph-dots-three text-muted"></i>
                        </div>

                        <!-- Ad Primary Text -->
                        <div style="padding:10px 14px;font-size:12.5px;color:#f1f5f9;line-height:1.45;" id="prevPrimaryText">
                            <?= htmlspecialchars($old['primary_text']) ?>
                        </div>

                        <!-- Creative Media Container (Image or Video) -->
                        <div id="prevMediaBox" style="width:100%;height:220px;background:#1e293b;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;">
                            <img id="prevImg" src="<?= e($old['media_url']) ?>" alt="Ad Creative Preview" style="width:100%;height:100%;object-fit:cover;<?= ($old['media_url'] && $old['media_type'] === 'image') ? 'display:block;' : 'display:none;' ?>">
                            
                            <video id="prevVideo" src="<?= e($old['media_url']) ?>" poster="<?= BASE_URL ?>/<?= e($old['media_thumb']) ?>" controls loop muted style="width:100%;height:100%;object-fit:cover;<?= ($old['media_url'] && $old['media_type'] === 'video') ? 'display:block;' : 'display:none;' ?>"></video>

                            <div id="prevPlaceholder" style="text-align:center;padding:20px;color:var(--fg3);<?= !empty($old['media_url']) ? 'display:none;' : '' ?>">
                                <i class="ph ph-image" style="font-size:36px;margin-bottom:6px;display:block;"></i>
                                <span style="font-size:12px;">Live creative preview will render here</span>
                            </div>
                        </div>

                        <!-- Ad CTA Bar -->
                        <div style="padding:12px 14px;background:#0d1524;border-top:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:10px;color:var(--fg3);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" id="prevDomain">
                                    ADSBOOSTER.NET
                                </div>
                                <div style="font-weight:700;color:#fff;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" id="prevHeadline">
                                    <?= htmlspecialchars($old['headline']) ?>
                                </div>
                            </div>
                            <button type="button" class="btn-accent" style="padding:6px 14px;font-size:11.5px;font-weight:700;border-radius:6px;white-space:nowrap;">
                                Learn More
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Bar -->
                    <div style="width:120px;height:4px;background:#334155;border-radius:10px;margin:14px auto 4px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Asset Picker from AI Library -->
<div class="modal fade" id="assetLibraryModal" tabindex="-1" aria-labelledby="assetLibraryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4" style="background:#0d1524;border:1px solid rgba(255,255,255,0.1) !important;">
            <div class="modal-header border-secondary border-opacity-10 pb-3">
                <h5 class="modal-title text-white fw-bold d-flex align-items-center gap-2" id="assetLibraryModalLabel">
                    <i class="ph-bold ph-squares-four text-accent"></i> Choose from Generated AI Media Library
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <ul class="nav nav-pills gap-2 mb-4" id="libraryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-4 py-2 font-size-13 fw-bold" id="lib-videos-tab" data-bs-toggle="pill" data-bs-target="#tab-lib-videos" type="button" role="tab">
                            <i class="ph ph-video-camera me-1"></i> AI Videos (<?= count($userVideos) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-4 py-2 font-size-13 fw-bold" id="lib-images-tab" data-bs-toggle="pill" data-bs-target="#tab-lib-images" type="button" role="tab">
                            <i class="ph ph-image me-1"></i> AI UGC Images (<?= count($userImages) ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="libraryTabsContent">
                    <!-- Tab: AI Videos -->
                    <div class="tab-pane fade show active" id="tab-lib-videos" role="tabpanel">
                        <?php if (empty($userVideos)): ?>
                            <div class="text-center py-5 text-white-50">
                                <i class="ph ph-video-camera fs-1 mb-2 d-block text-secondary"></i>
                                <p>No AI video creatives generated yet.</p>
                                <a href="<?= url('ugc-video-studio') ?>" class="btn btn-sm btn-primary rounded-pill">Create AI UGC Video</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($userVideos as $vid): ?>
                                <div class="col-md-6">
                                    <div class="card border-0 bg-dark p-3 rounded-3 h-100 d-flex flex-column justify-content-between">
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="ratio ratio-1x1 rounded-2 overflow-hidden bg-black flex-shrink-0" style="width:72px;">
                                                <img src="<?= BASE_URL ?>/<?= e($vid['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png') ?>" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <div class="min-w-0">
                                                <span class="badge bg-primary text-black fw-bold font-size-10 mb-1"><?= (int)$vid['duration_seconds'] ?>s <?= e(ucfirst($vid['avatar_id'])) ?></span>
                                                <h6 class="text-white fw-bold font-size-13 mb-1 text-truncate"><?= e($vid['hook_text']) ?></h6>
                                                <p class="text-white-50 font-size-11 mb-0 text-truncate-2"><?= e($vid['script_body']) ?></p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-accent w-100 rounded-pill fw-bold" onclick="selectLibraryMedia('video', '<?= e($vid['video_url']) ?>', '<?= BASE_URL ?>/<?= e($vid['thumbnail_url'] ?? 'assets/images/thumbs/about-five-img1.png') ?>', <?= htmlspecialchars(json_encode($vid['hook_text']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode(($vid['script_body'] ?? '') . "\n\n" . ($vid['cta_text'] ?? '')), ENT_QUOTES) ?>)">
                                            <i class="ph ph-check-circle me-1"></i> Use This Video
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab: AI Images -->
                    <div class="tab-pane fade" id="tab-lib-images" role="tabpanel">
                        <?php if (empty($userImages)): ?>
                            <div class="text-center py-5 text-white-50">
                                <i class="ph ph-image fs-1 mb-2 d-block text-secondary"></i>
                                <p>No AI images generated yet.</p>
                                <a href="<?= url('ugc-generator') ?>" class="btn btn-sm btn-primary rounded-pill">Generate UGC Images</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($userImages as $img): 
                                    $imgData = json_decode($img['result_output'] ?? '', true) ?: [];
                                    $imgSrc = $imgData['image_url'] ?? $img['result_output'];
                                    $imgTitle = $imgData['hook_headline'] ?? ($imgData['product_name'] ?? 'UGC Image');
                                    $imgCopy = $imgData['ad_copy'] ?? '';
                                ?>
                                <div class="col-md-6">
                                    <div class="card border-0 bg-dark p-3 rounded-3 h-100 d-flex flex-column justify-content-between">
                                        <div class="d-flex gap-3 mb-3">
                                            <div class="ratio ratio-1x1 rounded-2 overflow-hidden bg-black flex-shrink-0" style="width:72px;">
                                                <img src="<?= e($imgSrc) ?>" class="w-100 h-100 object-fit-cover">
                                            </div>
                                            <div class="min-w-0">
                                                <span class="badge bg-secondary text-white font-size-10 mb-1"><?= e($imgData['aspect_ratio'] ?? '1:1') ?></span>
                                                <h6 class="text-white fw-bold font-size-13 mb-1 text-truncate"><?= e($imgTitle) ?></h6>
                                                <p class="text-white-50 font-size-11 mb-0 text-truncate-2"><?= e($imgCopy ?: $img['prompt']) ?></p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-accent w-100 rounded-pill fw-bold" onclick="selectLibraryMedia('image', '<?= e($imgSrc) ?>', '<?= e($imgSrc) ?>', <?= htmlspecialchars(json_encode($imgTitle), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($imgCopy), ENT_QUOTES) ?>)">
                                            <i class="ph ph-check-circle me-1"></i> Use This Image
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setPlatform(id, label, el) {
    document.querySelectorAll('.platform-card').forEach(c => {
        c.style.borderColor = 'rgba(255,255,255,0.08)';
        c.style.background = '#080c14';
    });
    el.style.borderColor = 'var(--accent)';
    el.style.background = 'rgba(200,255,0,0.08)';
    el.querySelector('input').checked = true;

    const pill = document.getElementById('previewNetworkPill');
    if (pill) pill.textContent = label + ' Feed';
}

function updateEstimate() {
    const type = document.getElementById('budget_type').value;
    const amount = parseFloat(document.getElementById('budget_amount').value) || 0;
    const total = (type === 'lifetime') ? amount : amount * 30;
    document.getElementById('estCost').textContent = '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateLivePreview() {
    const headline = document.getElementById('headline').value || 'Your Ad Headline';
    const primaryText = document.getElementById('primary_text').value || 'Your ad primary message will appear here...';
    const url = document.getElementById('destination_url').value || 'https://example.com';

    document.getElementById('prevHeadline').textContent = headline;
    document.getElementById('prevPrimaryText').textContent = primaryText;

    try {
        const parsed = new URL(url);
        document.getElementById('prevDomain').textContent = parsed.hostname.toUpperCase();
    } catch(e) {
        document.getElementById('prevDomain').textContent = 'WEBSITE.COM';
    }
}

function selectLibraryMedia(type, mediaUrl, thumbUrl, headline, copy) {
    document.getElementById('inp_existing_media_url').value = mediaUrl;
    document.getElementById('inp_selected_media_type').value = type;

    if (headline) document.getElementById('headline').value = headline;
    if (copy) document.getElementById('primary_text').value = copy;

    const card = document.getElementById('selectedCreativeCard');
    const thumb = document.getElementById('selMediaThumb');
    const title = document.getElementById('selMediaTitle');
    const urlText = document.getElementById('selMediaUrlText');

    if (card) card.style.display = 'block';
    if (thumb) thumb.src = thumbUrl || mediaUrl;
    if (title) title.textContent = 'Selected: ' + (type === 'video' ? 'AI Video Creative' : 'AI UGC Image');
    if (urlText) urlText.textContent = mediaUrl;

    const prevImg = document.getElementById('prevImg');
    const prevVideo = document.getElementById('prevVideo');
    const prevPlaceholder = document.getElementById('prevPlaceholder');

    if (type === 'video') {
        if (prevImg) prevImg.style.display = 'none';
        if (prevVideo) {
            prevVideo.src = mediaUrl;
            prevVideo.poster = thumbUrl;
            prevVideo.style.display = 'block';
        }
    } else {
        if (prevVideo) prevVideo.style.display = 'none';
        if (prevImg) {
            prevImg.src = mediaUrl;
            prevImg.style.display = 'block';
        }
    }
    if (prevPlaceholder) prevPlaceholder.style.display = 'none';

    updateLivePreview();

    // Close modal
    const modalEl = document.getElementById('assetLibraryModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

function clearSelectedCreative() {
    document.getElementById('inp_existing_media_url').value = '';
    document.getElementById('inp_selected_media_type').value = 'image';
    const card = document.getElementById('selectedCreativeCard');
    if (card) card.style.display = 'none';

    const prevImg = document.getElementById('prevImg');
    const prevVideo = document.getElementById('prevVideo');
    const prevPlaceholder = document.getElementById('prevPlaceholder');
    if (prevImg) prevImg.style.display = 'none';
    if (prevVideo) prevVideo.style.display = 'none';
    if (prevPlaceholder) prevPlaceholder.style.display = 'block';
}

function handleCreativeUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const isVideo = file.type.startsWith('video/');
        const reader = new FileReader();

        reader.onload = function(e) {
            const prevImg = document.getElementById('prevImg');
            const prevVideo = document.getElementById('prevVideo');
            const prevPlaceholder = document.getElementById('prevPlaceholder');

            if (isVideo) {
                if (prevImg) prevImg.style.display = 'none';
                if (prevVideo) {
                    prevVideo.src = e.target.result;
                    prevVideo.style.display = 'block';
                }
            } else {
                if (prevVideo) prevVideo.style.display = 'none';
                if (prevImg) {
                    prevImg.src = e.target.result;
                    prevImg.style.display = 'block';
                }
            }
            if (prevPlaceholder) prevPlaceholder.style.display = 'none';

            // Show card
            const card = document.getElementById('selectedCreativeCard');
            if (card) {
                card.style.display = 'block';
                document.getElementById('selMediaTitle').textContent = 'Uploaded: ' + file.name;
                document.getElementById('selMediaUrlText').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            }
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
