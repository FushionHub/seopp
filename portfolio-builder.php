<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';

$userId = current_user_id();
$user   = current_user();

$portfolio   = db()->fetchOne("SELECT * FROM user_portfolios WHERE user_id = ?", [$userId]);
$vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ?", [$userId]);

$errors = [];
$success = '';

// Handle Actions (AI Parse or Save Full Portfolio & vCard)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'parse_resume') {
        $resumeText = '';

        // Check file upload (PDF, DOCX, TXT)
        if (!empty($_FILES['resume_file']['name']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['resume_file']['tmp_name'];
            $origName = $_FILES['resume_file']['name'];
            $resumeText = AIEngine::extractTextFromFile($tmpPath, $origName);
        }

        $pastedText = trim((string)($_POST['resume_text'] ?? ''));
        if ($pastedText !== '') {
            $resumeText = $resumeText !== '' ? ($resumeText . "\n\n" . $pastedText) : $pastedText;
        }

        if (empty(trim($resumeText))) {
            $errors[] = 'Please paste your resume text or upload a resume file (.pdf, .docx, .txt).';
        } else {
            $parsed = AIEngine::analyzeResume($resumeText, $userId);
            if (!empty($parsed['success']) && !empty($parsed['data'])) {
                $d = $parsed['data'];
                $cand = $d['candidate'] ?? [];
                $uname = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $cand['email'] ?: $user['email'])[0])));
                if (empty($uname)) $uname = 'user_' . $userId;

                $tpl = in_array($_POST['template_style'] ?? '', ['dark_luxury', 'creative_neon', 'executive_minimal', 'minimalist_aurora'], true)
                    ? $_POST['template_style']
                    : ($portfolio['template_style'] ?? 'dark_luxury');

                $flatSkills = $d['flat_skills'] ?? ['Meta Ads', 'TikTok Ads', 'UGC Creative', 'Copywriting'];
                $services   = $d['services'] ?? [];
                $projects   = $d['portfolio_items'] ?? [];

                // 1. Update/Insert user_portfolios
                $dataPortfolio = [
                    'user_id'              => $userId,
                    'username'             => $uname,
                    'display_name'         => $cand['display_name'] ?: $user['full_name'],
                    'job_title'            => $cand['job_title'] ?: 'Performance Advertiser & Creator',
                    'headline'             => $cand['headline'] ?: '',
                    'bio'                  => $cand['bio'] ?: '',
                    'template_style'       => $tpl,
                    'phone'                => $cand['phone'] ?: ($user['phone'] ?? '+1 (555) 019-2831'),
                    'email'                => $cand['email'] ?: $user['email'],
                    'company'              => $cand['company'] ?: ($user['company'] ?? 'Adsbooster Partner Network'),
                    'location'             => $cand['location'] ?: 'Worldwide / Remote',
                    'website'              => $cand['website'] ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                    'skills_json'          => json_encode(array_values($flatSkills)),
                    'services_json'        => json_encode($services),
                    'portfolio_items_json' => json_encode($projects),
                    'is_public'            => 1,
                    'updated_at'           => date('Y-m-d H:i:s'),
                ];

                if ($portfolio) {
                    db()->update('user_portfolios', $dataPortfolio, 'id = ?', [$portfolio['id']]);
                } else {
                    $dataPortfolio['created_at'] = date('Y-m-d H:i:s');
                    db()->insert('user_portfolios', $dataPortfolio);
                }

                // 2. Update/Insert user_vcards
                $dataVcard = [
                    'user_id'          => $userId,
                    'username'         => $uname,
                    'headline'         => $cand['headline'] ?: '',
                    'bio'              => $cand['bio'] ?: '',
                    'template'         => $tpl,
                    'phone'            => $cand['phone'] ?: ($user['phone'] ?? '+1 (555) 019-2831'),
                    'whatsapp'         => $cand['phone'] ?: ($user['phone'] ?? '+1 (555) 019-2831'),
                    'company'          => $cand['company'] ?: ($user['company'] ?? 'Adsbooster.net'),
                    'job_title'        => $cand['job_title'] ?: 'Performance Advertiser',
                    'location'         => $cand['location'] ?: 'Worldwide / Remote',
                    'website'          => $cand['website'] ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                    'skills_json'      => json_encode(array_values($flatSkills)),
                    'projects_json'    => json_encode($projects),
                    'is_public'        => 1,
                    'updated_at'       => date('Y-m-d H:i:s'),
                ];

                if ($vcardRecord) {
                    db()->update('user_vcards', $dataVcard, 'id = ?', [$vcardRecord['id']]);
                } else {
                    $dataVcard['created_at'] = date('Y-m-d H:i:s');
                    db()->insert('user_vcards', $dataVcard);
                }

                // 3. Update users table headline & company
                db()->update('users', [
                    'headline' => $cand['headline'] ?: '',
                    'bio'      => $cand['bio'] ?: '',
                    'company'  => $cand['company'] ?: ($user['company'] ?? ''),
                ], 'id = ?', [$userId]);

                $success = 'AI successfully parsed your resume (ATS Score: ' . ((int)($d['ats_score'] ?? 85)) . '/100) and synced your portfolio & digital vCard!';
                $portfolio   = db()->fetchOne("SELECT * FROM user_portfolios WHERE user_id = ?", [$userId]);
                $vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ?", [$userId]);
            } else {
                $errors[] = 'AI parsing could not identify resume details. Please try pasting plain text.';
            }
        }
    } elseif ($action === 'save_portfolio') {
        $displayName   = trim((string)($_POST['display_name'] ?? ''));
        $jobTitle      = trim((string)($_POST['job_title'] ?? ''));
        $headline      = trim((string)($_POST['headline'] ?? ''));
        $bio           = trim((string)($_POST['bio'] ?? ''));
        $phone         = trim((string)($_POST['phone'] ?? ''));
        $whatsapp      = trim((string)($_POST['whatsapp'] ?? $phone));
        $email         = trim((string)($_POST['email'] ?? ''));
        $company       = trim((string)($_POST['company'] ?? ''));
        $location      = trim((string)($_POST['location'] ?? ''));
        $website       = trim((string)($_POST['website'] ?? ''));
        $avatarUrl     = trim((string)($_POST['avatar_url'] ?? ''));
        $templateStyle = in_array($_POST['template_style'] ?? '', ['dark_luxury', 'creative_neon', 'executive_minimal', 'minimalist_aurora'], true) 
            ? $_POST['template_style'] 
            : 'dark_luxury';

        // Skills parse
        $skillsInput = trim((string)($_POST['skills'] ?? ''));
        $skillsArray = array_values(array_filter(array_map('trim', explode(',', $skillsInput))));

        // Social Links parse
        $socials = [
            'linkedin'  => trim((string)($_POST['social_linkedin'] ?? '')),
            'twitter'   => trim((string)($_POST['social_twitter'] ?? '')),
            'instagram' => trim((string)($_POST['social_instagram'] ?? '')),
            'github'    => trim((string)($_POST['social_github'] ?? '')),
            'youtube'   => trim((string)($_POST['social_youtube'] ?? '')),
        ];

        // Projects parse (from repeaters)
        $projectTitles  = $_POST['project_title'] ?? [];
        $projectMetrics = $_POST['project_metric'] ?? [];
        $projectDescs   = $_POST['project_description'] ?? [];
        $projectLinks   = $_POST['project_link'] ?? [];
        $projectsList   = [];

        if (is_array($projectTitles)) {
            for ($i = 0; $i < count($projectTitles); $i++) {
                $pTitle = trim((string)($projectTitles[$i] ?? ''));
                if ($pTitle !== '') {
                    $projectsList[] = [
                        'title'       => $pTitle,
                        'metric'      => trim((string)($projectMetrics[$i] ?? '')),
                        'description' => trim((string)($projectDescs[$i] ?? '')),
                        'link'        => trim((string)($projectLinks[$i] ?? '')),
                    ];
                }
            }
        }

        // Services parse (from repeaters)
        $serviceTitles = $_POST['service_title'] ?? [];
        $servicePrices = $_POST['service_price'] ?? [];
        $serviceDescs  = $_POST['service_description'] ?? [];
        $servicesList  = [];

        if (is_array($serviceTitles)) {
            for ($i = 0; $i < count($serviceTitles); $i++) {
                $sTitle = trim((string)($serviceTitles[$i] ?? ''));
                if ($sTitle !== '') {
                    $servicesList[] = [
                        'title'       => $sTitle,
                        'price'       => trim((string)($servicePrices[$i] ?? '')),
                        'description' => trim((string)($serviceDescs[$i] ?? '')),
                    ];
                }
            }
        }

        if (empty($displayName)) {
            $errors[] = 'Display name is required.';
        }

        if (empty($errors)) {
            $uname = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email ?: $user['email'])[0])));
            if (empty($uname)) $uname = 'user_' . $userId;

            // 1. Sync user_portfolios
            $dataPortfolio = [
                'user_id'              => $userId,
                'username'             => $uname,
                'display_name'         => $displayName,
                'job_title'            => $jobTitle,
                'headline'             => $headline,
                'bio'                  => $bio,
                'template_style'       => $templateStyle,
                'phone'                => $phone,
                'email'                => $email,
                'company'              => $company,
                'location'             => $location,
                'website'              => $website,
                'avatar_url'           => $avatarUrl,
                'skills_json'          => json_encode($skillsArray),
                'services_json'        => json_encode($servicesList),
                'portfolio_items_json' => json_encode($projectsList),
                'social_links_json'    => json_encode($socials),
                'is_public'            => 1,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            if ($portfolio) {
                db()->update('user_portfolios', $dataPortfolio, 'id = ?', [$portfolio['id']]);
            } else {
                $dataPortfolio['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_portfolios', $dataPortfolio);
            }

            // 2. Sync user_vcards
            $dataVcard = [
                'user_id'          => $userId,
                'username'         => $uname,
                'headline'         => $headline,
                'bio'              => $bio,
                'template'         => $templateStyle,
                'phone'            => $phone,
                'whatsapp'         => $whatsapp,
                'company'          => $company,
                'job_title'        => $jobTitle,
                'location'         => $location,
                'website'          => $website ?: (BASE_URL . '/portfolio.php?u=' . urlencode($uname)),
                'linkedin'         => $socials['linkedin'] ?? null,
                'twitter'          => $socials['twitter'] ?? null,
                'github'           => $socials['github'] ?? null,
                'instagram'        => $socials['instagram'] ?? null,
                'skills_json'      => json_encode($skillsArray),
                'projects_json'    => json_encode($projectsList),
                'is_public'        => 1,
                'updated_at'       => date('Y-m-d H:i:s'),
            ];

            if ($vcardRecord) {
                db()->update('user_vcards', $dataVcard, 'id = ?', [$vcardRecord['id']]);
            } else {
                $dataVcard['created_at'] = date('Y-m-d H:i:s');
                db()->insert('user_vcards', $dataVcard);
            }

            // 3. Update users table
            db()->update('users', [
                'company'  => $company,
                'headline' => $headline,
                'bio'      => $bio,
            ], 'id = ?', [$userId]);

            $success = '✨ Portfolio, Digital NFC vCard, and Contact settings saved and published successfully!';
            $portfolio   = db()->fetchOne("SELECT * FROM user_portfolios WHERE user_id = ?", [$userId]);
            $vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ?", [$userId]);
        }
    }
}

$pageTitle = 'Portfolio & Digital vCard Studio — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'portfolio-builder';
require_once __DIR__ . '/includes/app_header.php';

$curSkills    = json_decode($portfolio['skills_json'] ?? '[]', true) ?: ['Meta Ads', 'TikTok Ads', 'UGC Creative', 'ROAS Scaling', 'Copywriting'];
$curServices  = json_decode($portfolio['services_json'] ?? '[]', true) ?: [
    ['title' => 'Full Omnichannel Ad Scaling', 'price' => '$2,500/month', 'description' => 'Directing Meta and TikTok ad budgets for fast-growing brands.'],
    ['title' => 'Viral UGC Creative Testing Package', 'price' => '$650/package', 'description' => 'End-to-end creative testing, scripts, and video iteration.']
];
$curProjects  = json_decode($portfolio['portfolio_items_json'] ?? '[]', true) ?: [
    ['title' => 'DTC Fashion Brand Scaling', 'metric' => '4.8x ROAS', 'description' => 'Scaled monthly spend from $10k to $110k at 4.8x blended ROAS.', 'link' => 'https://adsbooster.net']
];
$curSocials   = json_decode($portfolio['social_links_json'] ?? '[]', true) ?: [];
$curTemplate  = $portfolio['template_style'] ?? 'dark_luxury';

$usernameSlug = $portfolio['username'] ?? $vcardRecord['username'] ?? (string)$userId;
$shareUrl     = BASE_URL . '/portfolio.php?u=' . urlencode($usernameSlug);
$vcardWebUrl  = BASE_URL . '/vcard.php?u=' . urlencode($usernameSlug);
$vcardFileUrl = BASE_URL . '/vcard-download.php?u=' . urlencode($usernameSlug);
?>

<div class="container-fluid px-0">

    <!-- Top Action Bar -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-3 py-1 font-size-11 fw-bold" style="background: rgba(200,255,0,0.12); color: var(--accent); border: 1px solid rgba(200,255,0,0.3);">
                    <i class="ph-bold ph-shield-check me-1"></i> Multi-Format Showcase Studio
                </span>
                <span class="text-white-50 font-size-12">Public Portfolio &bull; Digital NFC vCard &bull; RFC 6350 .vcf</span>
            </div>
            <h1 class="page-title text-white fw-bold mb-1">
                <i class="ph-bold ph-address-book text-accent me-2"></i> Digital vCard &amp; Portfolio Studio
            </h1>
            <p class="text-muted mb-0 font-size-14">
                Design your luxury public showcase, configure your interactive NFC digital business card, and sync your resume in one place.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13 d-flex align-items-center gap-1">
                <i class="ph-bold ph-wifi-high text-accent"></i> Virtual NFC Card
            </a>
            <a href="<?= e($vcardFileUrl) ?>" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13 d-flex align-items-center gap-1">
                <i class="ph-bold ph-download-simple"></i> Download .vcf
            </a>
            <a href="<?= e($shareUrl) ?>" target="_blank" class="btn btn-accent rounded-pill px-4 py-2 font-size-13 text-dark fw-bold d-flex align-items-center gap-1">
                <i class="ph-bold ph-arrow-square-out"></i> Live Portfolio
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="ph-bold ph-check-circle font-size-20"></i>
                <span><?= e($success) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= e($shareUrl) ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3 py-1 font-size-12 fw-bold text-dark">
                    View Portfolio <i class="ph ph-arrow-square-out ms-1"></i>
                </a>
                <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 py-1 font-size-12">
                    Open Digital vCard <i class="ph ph-address-book ms-1"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger rounded-4 p-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Dedicated URL & Sharing Bar -->
    <div class="card-dark rounded-4 p-4 mb-4 border d-flex align-items-center justify-content-between flex-wrap gap-3" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:14px;background:rgba(200,255,0,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;">
                <i class="ph-bold ph-link font-size-24"></i>
            </div>
            <div>
                <span class="font-size-12 d-block text-white-50">Your Dedicated Public Showcase &amp; vCard Link</span>
                <span class="fw-bold font-size-15 font-monospace" style="color:var(--accent);"><?= e($shareUrl) ?></span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" onclick="navigator.clipboard.writeText('<?= e($shareUrl) ?>'); alert('Portfolio link copied to clipboard!');" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13">
                <i class="ph ph-copy me-1"></i> Copy Portfolio URL
            </button>
            <button type="button" onclick="navigator.clipboard.writeText('<?= e($vcardWebUrl) ?>'); alert('Digital NFC vCard link copied to clipboard!');" class="btn btn-outline-light rounded-pill px-3 py-2 font-size-13">
                <i class="ph ph-share-network me-1"></i> Copy NFC vCard URL
            </button>
        </div>
    </div>

    <!-- Main Workspace: Form & Studio -->
    <div class="row g-4">

        <!-- Left Column: AI Resume Quick Parser & Tools -->
        <div class="col-lg-4">
            <!-- AI Resume Auto-Populator Card -->
            <div class="card-dark rounded-4 p-4 mb-4 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge rounded-pill px-3 py-1 font-size-11 fw-bold" style="background: rgba(200,255,0,0.15); color: var(--accent); border: 1px solid rgba(200,255,0,0.3);">
                        <i class="ph-bold ph-magic-wand me-1"></i> Groq AI Powered
                    </span>
                    <a href="<?= BASE_URL ?>/resume-analyser.php" class="text-white-50 font-size-12 text-decoration-none hover:text-white">
                        Full ATS Tool &rarr;
                    </a>
                </div>

                <h3 class="h5 text-white fw-bold mb-2">Instant Resume Import</h3>
                <p class="font-size-13 text-white-50 mb-3" style="line-height: 1.5;">
                    Upload your resume (.PDF, .DOCX, .TXT) or paste text. Our AI auto-populates your name, headline, skills, and portfolio case studies.
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="parse_resume">

                    <div class="mb-3">
                        <label class="form-label font-size-12 text-white fw-semibold">Upload Resume File</label>
                        <input type="file" name="resume_file" accept=".pdf,.docx,.txt" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:8px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 text-white fw-semibold">Or Paste Resume Text</label>
                        <textarea name="resume_text" rows="4" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" placeholder="Paste resume, LinkedIn summary, or experience..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-accent rounded-pill w-100 py-2 fw-bold text-dark font-size-13 d-flex align-items-center justify-content-center gap-1">
                        <i class="ph-bold ph-sparkle"></i> Auto-Generate Portfolio &amp; vCard
                    </button>
                </form>
            </div>

            <!-- Mini Live Digital NFC Card Preview -->
            <div class="card-dark rounded-4 p-4 border text-center" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-white-50 font-size-12 fw-semibold">Interactive Card Preview</span>
                    <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="font-size-12 text-accent text-decoration-none">
                        Open Fullscreen <i class="ph ph-arrow-square-out ms-1"></i>
                    </a>
                </div>

                <!-- Card Graphic Preview -->
                <div id="miniNfcCardPreview" class="rounded-4 p-3 mb-3 text-start position-relative overflow-hidden" style="background: linear-gradient(135deg, #131d2e 0%, #080c14 100%); border: 1.5px solid rgba(200,255,0,0.35); box-shadow: 0 10px 25px rgba(0,0,0,0.5); transition: all 0.3s ease;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div style="width:32px;height:24px;border-radius:4px;background:linear-gradient(135deg, #ffd700, #b8860b);border:1px solid rgba(255,255,255,0.4);"></div>
                        <i id="miniNfcWave" class="ph-bold ph-wifi-high text-accent font-size-18" style="transition: color 0.3s ease;"></i>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div id="miniNfcAvatarRing" class="rounded-circle overflow-hidden flex-shrink-0" style="width:40px;height:40px;border:1.5px solid var(--accent);background:rgba(200,255,0,0.1);display:flex;align-items:center;justify-content:center;font-weight:bold;color:var(--accent);transition: all 0.3s ease;">
                            <?= strtoupper(substr($portfolio['display_name'] ?? $user['full_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <div class="text-white fw-bold font-size-13 text-truncate" style="max-width: 170px;">
                                <?= e($portfolio['display_name'] ?? $user['full_name']) ?>
                            </div>
                            <div id="miniNfcRole" class="font-size-11" style="color:var(--accent);transition: color 0.3s ease;"><?= e($portfolio['job_title'] ?? 'Growth Marketer') ?></div>
                        </div>
                    </div>
                    <div class="font-size-11 text-white-50 pt-2 border-top border-secondary border-opacity-20 d-flex justify-content-between">
                        <span><i class="ph ph-map-pin me-1"></i> <?= e($portfolio['location'] ?? 'Remote') ?></span>
                        <span id="miniNfcBadge" class="text-white-75 fw-semibold" style="transition: color 0.3s ease;">NFC Active</span>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="btn btn-outline-light rounded-pill w-100 py-2 font-size-12">
                        <i class="ph-bold ph-qr-code me-1"></i> Test Flip &amp; QR
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column: Comprehensive Portfolio & vCard Editor -->
        <div class="col-lg-8">
            <div class="card-dark rounded-4 p-4 p-md-5 border" style="background: #0d1524 !important; border-color: rgba(255,255,255,0.08) !important;">
                
                <h3 class="h4 text-white fw-bold mb-1">Portfolio &amp; Digital vCard Settings</h3>
                <p class="text-white-50 font-size-13 mb-4">
                    Customize your design theme, identity, case studies, services, and contact links. All changes apply live to your public portfolio and digital vCard.
                </p>

                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_portfolio">

                    <!-- 1. Luxury Design Themes Selector -->
                    <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-15">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-bold text-white mb-0 font-size-14">
                                <i class="ph-bold ph-palette text-accent me-1"></i> Choose Luxury Design Theme
                            </label>
                            <span class="font-size-11 text-white-50">Click any card to preview &amp; select</span>
                        </div>

                        <style>
                            .theme-select-card {
                                background: #080c14;
                                border: 2px solid rgba(255, 255, 255, 0.1);
                                border-radius: 14px;
                                padding: 18px 12px;
                                text-align: center;
                                cursor: pointer;
                                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                                user-select: none;
                                position: relative;
                                height: 100%;
                            }
                            .theme-select-card:hover {
                                transform: translateY(-3px);
                                border-color: rgba(255, 255, 255, 0.35);
                            }
                            .theme-select-card.active {
                                background: rgba(255, 255, 255, 0.05) !important;
                            }
                            .theme-select-card[data-theme="dark_luxury"].active {
                                border-color: #c8ff00 !important;
                                box-shadow: 0 0 22px rgba(200, 255, 0, 0.35) !important;
                            }
                            .theme-select-card[data-theme="creative_neon"].active {
                                border-color: #a855f7 !important;
                                box-shadow: 0 0 22px rgba(168, 85, 247, 0.4) !important;
                            }
                            .theme-select-card[data-theme="executive_minimal"].active {
                                border-color: #38bdf8 !important;
                                box-shadow: 0 0 22px rgba(56, 189, 248, 0.35) !important;
                            }
                            .theme-select-card[data-theme="minimalist_aurora"].active {
                                border-color: #10b981 !important;
                                box-shadow: 0 0 22px rgba(16, 185, 129, 0.35) !important;
                            }
                        </style>

                        <div class="row g-3">
                            <!-- Theme 1: Dark Luxury -->
                            <div class="col-6 col-md-3">
                                <div class="theme-select-card <?= $curTemplate === 'dark_luxury' ? 'active' : '' ?>"
                                     data-theme="dark_luxury"
                                     onclick="selectTheme('dark_luxury')">
                                    <input type="radio" name="template_style" id="theme_dark_luxury" value="dark_luxury" <?= $curTemplate === 'dark_luxury' ? 'checked' : '' ?> style="position:absolute;opacity:0;pointer-events:none;">
                                    <div class="rounded-circle mx-auto mb-2" style="width:28px;height:28px;background:#c8ff00;box-shadow:0 0 12px rgba(200,255,0,0.6);"></div>
                                    <span class="text-white fw-bold font-size-13 d-block">Dark Luxury</span>
                                    <span class="text-white-50 font-size-10">Obsidian &amp; Lime</span>
                                </div>
                            </div>

                            <!-- Theme 2: Cyber Neon -->
                            <div class="col-6 col-md-3">
                                <div class="theme-select-card <?= $curTemplate === 'creative_neon' ? 'active' : '' ?>"
                                     data-theme="creative_neon"
                                     onclick="selectTheme('creative_neon')">
                                    <input type="radio" name="template_style" id="theme_creative_neon" value="creative_neon" <?= $curTemplate === 'creative_neon' ? 'checked' : '' ?> style="position:absolute;opacity:0;pointer-events:none;">
                                    <div class="rounded-circle mx-auto mb-2" style="width:28px;height:28px;background:linear-gradient(135deg, #a855f7, #06b6d4);box-shadow:0 0 12px rgba(168,85,247,0.6);"></div>
                                    <span class="text-white fw-bold font-size-13 d-block">Cyber Neon</span>
                                    <span class="text-white-50 font-size-10">Purple &amp; Cyan</span>
                                </div>
                            </div>

                            <!-- Theme 3: Executive Titanium -->
                            <div class="col-6 col-md-3">
                                <div class="theme-select-card <?= $curTemplate === 'executive_minimal' ? 'active' : '' ?>"
                                     data-theme="executive_minimal"
                                     onclick="selectTheme('executive_minimal')">
                                    <input type="radio" name="template_style" id="theme_executive_minimal" value="executive_minimal" <?= $curTemplate === 'executive_minimal' ? 'checked' : '' ?> style="position:absolute;opacity:0;pointer-events:none;">
                                    <div class="rounded-circle mx-auto mb-2" style="width:28px;height:28px;background:#38bdf8;box-shadow:0 0 12px rgba(56,189,248,0.6);"></div>
                                    <span class="text-white fw-bold font-size-13 d-block">Titanium</span>
                                    <span class="text-white-50 font-size-10">Slate &amp; Sky</span>
                                </div>
                            </div>

                            <!-- Theme 4: Minimalist Aurora -->
                            <div class="col-6 col-md-3">
                                <div class="theme-select-card <?= $curTemplate === 'minimalist_aurora' ? 'active' : '' ?>"
                                     data-theme="minimalist_aurora"
                                     onclick="selectTheme('minimalist_aurora')">
                                    <input type="radio" name="template_style" id="theme_minimalist_aurora" value="minimalist_aurora" <?= $curTemplate === 'minimalist_aurora' ? 'checked' : '' ?> style="position:absolute;opacity:0;pointer-events:none;">
                                    <div class="rounded-circle mx-auto mb-2" style="width:28px;height:28px;background:#10b981;box-shadow:0 0 12px rgba(16,185,129,0.6);"></div>
                                    <span class="text-white fw-bold font-size-13 d-block">Aurora Glass</span>
                                    <span class="text-white-50 font-size-10">Emerald &amp; Charcoal</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Personal & Professional Identity -->
                    <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-15">
                        <h4 class="h6 text-white fw-bold mb-3">
                            <i class="ph-bold ph-user text-accent me-1"></i> Personal Identity &amp; Headline
                        </h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label font-size-13 text-white fw-semibold">Display Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="display_name" required class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['display_name'] ?? $user['full_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-size-13 text-white fw-semibold">Professional Role / Job Title</label>
                                <input type="text" name="job_title" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['job_title'] ?? 'Performance Media Buyer & Growth Director') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label font-size-13 text-white fw-semibold">High-Impact Positioning Headline (1 sentence)</label>
                                <input type="text" name="headline" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['headline'] ?? 'Scaling omnichannel ad campaigns across Meta, TikTok, and Google Ads with creative testing.') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-size-13 text-white fw-semibold">Company / Agency Name</label>
                                <input type="text" name="company" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['company'] ?? $user['company'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-size-13 text-white fw-semibold">Location / Base Timezone</label>
                                <input type="text" name="location" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['location'] ?? 'Worldwide / Remote') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label font-size-13 text-white fw-semibold">Avatar Image URL (or path)</label>
                                <input type="text" name="avatar_url" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" placeholder="https://example.com/avatar.jpg" value="<?= htmlspecialchars($portfolio['avatar_url'] ?? $user['avatar'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label font-size-13 text-white fw-semibold">Professional Bio &amp; Executive Summary</label>
                                <textarea name="bio" rows="4" class="form-control font-size-13" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:12px;line-height:1.6;"><?= htmlspecialchars($portfolio['bio'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Core Skills & Competencies -->
                    <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-15">
                        <h4 class="h6 text-white fw-bold mb-2">
                            <i class="ph-bold ph-cpu text-accent me-1"></i> Core Skills &amp; Marketing Platforms
                        </h4>
                        <p class="font-size-12 text-white-50 mb-2">Separate skills with commas. Click suggested tags below to quick-add:</p>
                        <input type="text" name="skills" id="skillsInputField" class="form-control mb-2" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars(implode(', ', $curSkills)) ?>">
                        
                        <!-- Quick Skill Chips -->
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach (['Meta Ads', 'TikTok Ads', 'Google Ads', 'Shopify Plus', 'UGC Creative', 'Copywriting', 'ROAS Scaling', 'Conversion Rate Optimization (CRO)', 'Klaviyo', 'Triple Whale'] as $suggested): ?>
                                <button type="button" onclick="addSkillTag('<?= addslashes($suggested) ?>')" class="btn btn-sm btn-outline-secondary rounded-pill font-size-11 px-2 py-1 text-white-75">
                                    + <?= $suggested ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 4. Featured Projects & Case Studies Repeater -->
                    <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-15">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h4 class="h6 text-white fw-bold mb-0">
                                    <i class="ph-bold ph-trophy text-accent me-1"></i> Proven Case Studies &amp; Projects
                                </h4>
                                <span class="font-size-12 text-white-50">Showcase past revenue growth, ROAS metrics, or campaign milestones.</span>
                            </div>
                            <button type="button" onclick="addProjectRow()" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-12">
                                <i class="ph ph-plus me-1"></i> Add Case Study
                            </button>
                        </div>

                        <div id="projectsRepeaterContainer">
                            <?php foreach ($curProjects as $idx => $proj): ?>
                                <div class="p-3 mb-3 rounded-3 project-row position-relative" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);">
                                    <button type="button" onclick="this.closest('.project-row').remove()" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle p-1" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;" title="Remove Project">
                                        <i class="ph ph-x"></i>
                                    </button>
                                    <div class="row g-2">
                                        <div class="col-md-7">
                                            <label class="form-label font-size-11 text-white-50 mb-1">Project / Campaign Title</label>
                                            <input type="text" name="project_title[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" value="<?= htmlspecialchars($proj['title'] ?? '') ?>" placeholder="e.g. DTC Apparel Scale">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label font-size-11 text-white-50 mb-1">Highlight Metric Badge</label>
                                            <input type="text" name="project_metric[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" value="<?= htmlspecialchars($proj['metric'] ?? '') ?>" placeholder="e.g. 4.8x ROAS / +$120k GMV">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label font-size-11 text-white-50 mb-1">Description / Results</label>
                                            <input type="text" name="project_description[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" value="<?= htmlspecialchars($proj['description'] ?? '') ?>" placeholder="Brief 1-sentence accomplishment summary">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 5. Services & Packages Repeater -->
                    <div class="mb-4 pb-3 border-bottom border-secondary border-opacity-15">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h4 class="h6 text-white fw-bold mb-0">
                                    <i class="ph-bold ph-package text-accent me-1"></i> Service Packages &amp; Rates
                                </h4>
                                <span class="font-size-12 text-white-50">List your monthly retainer packages or fixed price sprint offerings.</span>
                            </div>
                            <button type="button" onclick="addServiceRow()" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-12">
                                <i class="ph ph-plus me-1"></i> Add Service
                            </button>
                        </div>

                        <div id="servicesRepeaterContainer">
                            <?php foreach ($curServices as $idx => $srv): ?>
                                <div class="p-3 mb-3 rounded-3 service-row position-relative" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);">
                                    <button type="button" onclick="this.closest('.service-row').remove()" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle p-1" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;" title="Remove Service">
                                        <i class="ph ph-x"></i>
                                    </button>
                                    <div class="row g-2">
                                        <div class="col-md-7">
                                            <label class="form-label font-size-11 text-white-50 mb-1">Service Offering Name</label>
                                            <input type="text" name="service_title[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" value="<?= htmlspecialchars($srv['title'] ?? '') ?>" placeholder="e.g. Omnichannel Growth Retainer">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label font-size-11 text-white-50 mb-1">Pricing / Rate</label>
                                            <input type="text" name="service_price[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" value="<?= htmlspecialchars($srv['price'] ?? '') ?>" placeholder="e.g. $2,500/month or $500/sprint">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label font-size-11 text-white-50 mb-1">Deliverables / Scope</label>
                                            <input type="text" name="service_description[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" value="<?= htmlspecialchars($srv['description'] ?? '') ?>" placeholder="e.g. Weekly creative briefs, media management, attribution">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 6. Contact Details & Social Networks -->
                    <div class="mb-4">
                        <h4 class="h6 text-white fw-bold mb-3">
                            <i class="ph-bold ph-phone-call text-accent me-1"></i> Contact Details &amp; Social Links
                        </h4>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label font-size-13 text-white fw-semibold">Public Email</label>
                                <input type="email" name="email" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['email'] ?? $user['email']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-13 text-white fw-semibold">Direct Phone</label>
                                <input type="text" name="phone" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($portfolio['phone'] ?? $user['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-13 text-white fw-semibold">WhatsApp Number</label>
                                <input type="text" name="whatsapp" class="form-control" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;padding:10px;" value="<?= htmlspecialchars($vcardRecord['whatsapp'] ?? $portfolio['phone'] ?? $user['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label font-size-12 text-white-75">LinkedIn Profile URL</label>
                                <input type="url" name="social_linkedin" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="https://linkedin.com/in/..." value="<?= htmlspecialchars($curSocials['linkedin'] ?? $vcardRecord['linkedin'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-12 text-white-75">Twitter / X Profile URL</label>
                                <input type="url" name="social_twitter" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="https://x.com/..." value="<?= htmlspecialchars($curSocials['twitter'] ?? $vcardRecord['twitter'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-12 text-white-75">Instagram Profile URL</label>
                                <input type="url" name="social_instagram" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($curSocials['instagram'] ?? $vcardRecord['instagram'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-12 text-white-75">GitHub Profile URL</label>
                                <input type="url" name="social_github" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="https://github.com/..." value="<?= htmlspecialchars($curSocials['github'] ?? $vcardRecord['github'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-12 text-white-75">Website / Blog URL</label>
                                <input type="url" name="website" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="https://example.com" value="<?= htmlspecialchars($portfolio['website'] ?? $vcardRecord['website'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label font-size-12 text-white-75">YouTube / Channel URL</label>
                                <input type="url" name="social_youtube" class="form-control font-size-12" style="background:#080c14 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="https://youtube.com/..." value="<?= htmlspecialchars($curSocials['youtube'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top border-secondary border-opacity-15 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <button type="submit" class="btn btn-accent rounded-pill px-5 py-3 fw-bold text-dark font-size-15 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-floppy-disk font-size-18"></i> Save &amp; Publish Everything
                        </button>
                        <a href="<?= e($shareUrl) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-3 font-size-14">
                            <i class="ph ph-eye me-1"></i> Preview Live Showcase
                        </a>
                    </div>

                </form>

            </div>
        </div>

    </div>

</div>

<script>
function addSkillTag(tag) {
    const field = document.getElementById('skillsInputField');
    let current = field.value.split(',').map(s => s.trim()).filter(Boolean);
    if (!current.includes(tag)) {
        current.push(tag);
        field.value = current.join(', ');
    }
}

function addProjectRow() {
    const container = document.getElementById('projectsRepeaterContainer');
    const html = `
    <div class="p-3 mb-3 rounded-3 project-row position-relative" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);">
        <button type="button" onclick="this.closest('.project-row').remove()" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle p-1" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;" title="Remove Project">
            <i class="ph ph-x"></i>
        </button>
        <div class="row g-2">
            <div class="col-md-7">
                <label class="form-label font-size-11 text-white-50 mb-1">Project / Campaign Title</label>
                <input type="text" name="project_title[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="e.g. Black Friday Campaign Scaling">
            </div>
            <div class="col-md-5">
                <label class="form-label font-size-11 text-white-50 mb-1">Highlight Metric Badge</label>
                <input type="text" name="project_metric[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="e.g. 5.2x ROAS / $240k Sales">
            </div>
            <div class="col-12">
                <label class="form-label font-size-11 text-white-50 mb-1">Description / Results</label>
                <input type="text" name="project_description[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="Brief result summary">
            </div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function addServiceRow() {
    const container = document.getElementById('servicesRepeaterContainer');
    const html = `
    <div class="p-3 mb-3 rounded-3 service-row position-relative" style="background:#080c14;border:1px solid rgba(255,255,255,0.1);">
        <button type="button" onclick="this.closest('.service-row').remove()" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle p-1" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;" title="Remove Service">
            <i class="ph ph-x"></i>
        </button>
        <div class="row g-2">
            <div class="col-md-7">
                <label class="form-label font-size-11 text-white-50 mb-1">Service Offering Name</label>
                <input type="text" name="service_title[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="e.g. TikTok Paid Media Sprint">
            </div>
            <div class="col-md-5">
                <label class="form-label font-size-11 text-white-50 mb-1">Pricing / Rate</label>
                <input type="text" name="service_price[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="e.g. $1,800/month">
            </div>
            <div class="col-12">
                <label class="form-label font-size-11 text-white-50 mb-1">Deliverables / Scope</label>
                <input type="text" name="service_description[]" class="form-control form-control-sm" style="background:#0d1524 !important;border:1px solid rgba(255,255,255,0.15) !important;color:#fff !important;" placeholder="Deliverable details">
            </div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function selectTheme(themeName) {
    // 1. Check corresponding radio input
    const radio = document.querySelector(`input[name="template_style"][value="${themeName}"]`);
    if (radio) {
        radio.checked = true;
    }

    // 2. Toggle active styles on all cards
    document.querySelectorAll('.theme-select-card').forEach(c => {
        c.classList.remove('active');
    });
    const activeCard = document.querySelector(`.theme-select-card[data-theme="${themeName}"]`);
    if (activeCard) {
        activeCard.classList.add('active');
    }

    // 3. Dynamically update the mini card preview on the left panel
    const miniCard = document.getElementById('miniNfcCardPreview');
    const miniChip = document.getElementById('miniNfcWave');
    const miniBorder = document.getElementById('miniNfcAvatarRing');
    const miniRole = document.getElementById('miniNfcRole');
    const miniBadge = document.getElementById('miniNfcBadge');

    if (miniCard) {
        if (themeName === 'creative_neon') {
            miniCard.style.background = 'linear-gradient(135deg, #1f113a 0%, #0d061c 100%)';
            miniCard.style.borderColor = 'rgba(168, 85, 247, 0.45)';
            if (miniChip) miniChip.style.color = '#a855f7';
            if (miniBorder) miniBorder.style.borderColor = '#a855f7';
            if (miniRole) miniRole.style.color = '#a855f7';
            if (miniBadge) { miniBadge.style.color = '#a855f7'; miniBadge.innerText = 'Cyber Neon'; }
        } else if (themeName === 'executive_minimal') {
            miniCard.style.background = 'linear-gradient(135deg, #131c2d 0%, #0c121e 100%)';
            miniCard.style.borderColor = 'rgba(56, 189, 248, 0.45)';
            if (miniChip) miniChip.style.color = '#38bdf8';
            if (miniBorder) miniBorder.style.borderColor = '#38bdf8';
            if (miniRole) miniRole.style.color = '#38bdf8';
            if (miniBadge) { miniBadge.style.color = '#38bdf8'; miniBadge.innerText = 'Executive Titanium'; }
        } else if (themeName === 'minimalist_aurora') {
            miniCard.style.background = 'linear-gradient(135deg, #0e2720 0%, #071612 100%)';
            miniCard.style.borderColor = 'rgba(16, 185, 129, 0.45)';
            if (miniChip) miniChip.style.color = '#10b981';
            if (miniBorder) miniBorder.style.borderColor = '#10b981';
            if (miniRole) miniRole.style.color = '#10b981';
            if (miniBadge) { miniBadge.style.color = '#10b981'; miniBadge.innerText = 'Aurora Glass'; }
        } else {
            miniCard.style.background = 'linear-gradient(135deg, #131d2e 0%, #080c14 100%)';
            miniCard.style.borderColor = 'rgba(200, 255, 0, 0.35)';
            if (miniChip) miniChip.style.color = '#c8ff00';
            if (miniBorder) miniBorder.style.borderColor = '#c8ff00';
            if (miniRole) miniRole.style.color = '#c8ff00';
            if (miniBadge) { miniBadge.style.color = '#c8ff00'; miniBadge.innerText = 'Dark Luxury'; }
        }
    }
}
window.updateThemePreview = selectTheme;
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
