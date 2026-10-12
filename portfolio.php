<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';

$identifier = trim((string)($_GET['u'] ?? ''));
$targetId   = (int)($_GET['id'] ?? 0);
$currentUserId = is_logged_in() ? current_user_id() : 0;

$portfolio = null;
if ($identifier !== '') {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.username = ? OR u.email = ? LIMIT 1",
        [$identifier, $identifier]
    );
} elseif ($targetId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$targetId]
    );
} elseif ($currentUserId > 0) {
    $portfolio = db()->fetchOne(
        "SELECT p.*, u.full_name as user_full_name, u.avatar as user_avatar, u.email as user_email, u.phone as user_phone, u.company as user_company
         FROM user_portfolios p 
         JOIN users u ON u.id = p.user_id 
         WHERE p.user_id = ? LIMIT 1",
        [$currentUserId]
    );
}

// Fallback to base user details if portfolio not yet explicitly saved
if (!$portfolio) {
    $lookupUser = null;
    if ($targetId > 0) {
        $lookupUser = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$targetId]);
    } elseif ($identifier !== '') {
        $lookupUser = db()->fetchOne("SELECT * FROM users WHERE email = ? OR id = ?", [$identifier, (int)$identifier]);
    } elseif ($currentUserId > 0) {
        $lookupUser = current_user();
    }

    if ($lookupUser) {
        $portfolio = [
            'id'             => 0,
            'user_id'        => (int)$lookupUser['id'],
            'username'       => strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $lookupUser['email'])[0]))),
            'display_name'   => $lookupUser['full_name'] ?: 'Verified Creator',
            'job_title'      => 'Advertising & Growth Specialist',
            'headline'       => $lookupUser['headline'] ?: 'Scaling performance ad campaigns across Meta, TikTok, and Google Ads.',
            'bio'            => $lookupUser['bio'] ?: 'Performance-focused digital marketer with deep expertise in full-funnel media buying, AI-driven creative testing, and ROAS optimization.',
            'template_style' => 'dark_luxury',
            'phone'          => $lookupUser['phone'] ?? '+1 (555) 019-2831',
            'email'          => $lookupUser['email'],
            'company'        => $lookupUser['company'] ?? 'Adsbooster.net Partner Network',
            'location'       => 'Worldwide / Remote',
            'website'        => '',
            'skills_json'    => json_encode(['Meta Ads', 'TikTok Ads', 'UGC Video', 'Copywriting', 'Conversion Rate Optimization (CRO)', 'Google Analytics']),
            'services_json'  => json_encode([
                ['title' => 'Full Omnichannel Ad Scaling Retainer', 'price' => '$2,500/month', 'description' => 'Managing paid ad budgets across Meta, TikTok, and Google.'],
                ['title' => 'Viral UGC Creative Testing Package', 'price' => '$650/package', 'description' => 'Creative strategy, hook testing, and video iterations.'],
            ]),
            'portfolio_items_json' => json_encode([
                ['title' => 'D2C Ecommerce Scale (Nova Luxe)', 'metric' => '4.82x ROAS', 'description' => 'Scaled monthly revenue to $184k with creative iteration.'],
                ['title' => 'B2B SaaS Lead Generation', 'metric' => '-41% CPA', 'description' => 'Restructured search keywords and high-intent landing page copy.']
            ]),
            'avatar_url'     => $lookupUser['avatar'] ?? '',
            'views_count'    => 12,
            'is_public'      => 1,
        ];
    }
}

if (!$portfolio) {
    http_response_code(404);
    die('Portfolio not found.');
}

// Increment views
if (!empty($portfolio['id'])) {
    try {
        db()->query("UPDATE user_portfolios SET views_count = views_count + 1 WHERE id = ?", [$portfolio['id']]);
    } catch (\Throwable $e) {}
}

$pUserId = (int)$portfolio['user_id'];
$vcardRecord = db()->fetchOne("SELECT * FROM user_vcards WHERE user_id = ? LIMIT 1", [$pUserId]);

$isOwner  = $currentUserId > 0 && $pUserId === $currentUserId;
$template = $portfolio['template_style'] ?? 'dark_luxury';

$skills   = json_decode($portfolio['skills_json'] ?? '[]', true) ?: [];
$services = json_decode($portfolio['services_json'] ?? '[]', true) ?: [];
$projects = json_decode($portfolio['portfolio_items_json'] ?? '[]', true) ?: [];
$socials  = json_decode($portfolio['social_links_json'] ?? '[]', true) ?: [];

// Fallback social profiles from user_vcards
if (empty($socials['linkedin']) && !empty($vcardRecord['linkedin']))   $socials['linkedin']  = $vcardRecord['linkedin'];
if (empty($socials['twitter']) && !empty($vcardRecord['twitter']))     $socials['twitter']   = $vcardRecord['twitter'];
if (empty($socials['github']) && !empty($vcardRecord['github']))       $socials['github']    = $vcardRecord['github'];
if (empty($socials['instagram']) && !empty($vcardRecord['instagram'])) $socials['instagram'] = $vcardRecord['instagram'];

$usernameSlug = $portfolio['username'] ?: (string)$portfolio['user_id'];
$vcfUrl       = BASE_URL . '/vcard-download.php?u=' . urlencode($usernameSlug);
$vcardWebUrl  = BASE_URL . '/vcard.php?u=' . urlencode($usernameSlug);
$shareUrl     = BASE_URL . '/portfolio.php?u=' . urlencode($usernameSlug);
$qrCodeUrl    = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=10&data=' . rawurlencode($vcfUrl);

$avatar = $portfolio['avatar_url'] ?: ($portfolio['user_avatar'] ?? '');
$cleanPhone = preg_replace('/[^0-9]/', '', (string)($vcardRecord['whatsapp'] ?? $portfolio['phone'] ?? ''));

$pageTitle = e($portfolio['display_name']) . ' — Portfolio & Digital vCard — ' . get_setting('site_name', 'Adsbooster.net');
$pageDescription = e($portfolio['headline'] ?? $portfolio['bio'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<!-- Template Customization Stylesheet -->
<style>
  <?php if ($template === 'creative_neon'): ?>
    .portfolio-hero { background: linear-gradient(135deg, #090314 0%, #15092a 50%, #061124 100%) !important; }
    .portfolio-card { background: rgba(26, 16, 48, 0.75) !important; border-color: rgba(168, 85, 247, 0.35) !important; backdrop-filter: blur(16px); }
    .text-theme-accent { color: #a855f7 !important; }
    .badge-theme-accent { background: rgba(168, 85, 247, 0.15) !important; color: #a855f7 !important; border: 1px solid rgba(168, 85, 247, 0.4) !important; }
    .btn-theme-accent { background: linear-gradient(135deg, #a855f7 0%, #06b6d4 100%) !important; color: #fff !important; border: 0 !important; font-weight: 700; }
    .nfc-widget-card { background: linear-gradient(135deg, rgba(32, 18, 62, 0.95), rgba(13, 6, 30, 0.98)) !important; border-color: rgba(168, 85, 247, 0.45) !important; }
  <?php elseif ($template === 'executive_minimal'): ?>
    .portfolio-hero { background: #0c1017 !important; }
    .portfolio-card { background: #131924 !important; border-color: rgba(255, 255, 255, 0.1) !important; backdrop-filter: blur(12px); }
    .text-theme-accent { color: #38bdf8 !important; }
    .badge-theme-accent { background: rgba(56, 189, 248, 0.15) !important; color: #38bdf8 !important; border: 1px solid rgba(56, 189, 248, 0.4) !important; }
    .btn-theme-accent { background: #38bdf8 !important; color: #080c14 !important; border: 0 !important; font-weight: 700; }
    .nfc-widget-card { background: linear-gradient(135deg, rgba(19, 28, 44, 0.95), rgba(11, 16, 26, 0.98)) !important; border-color: rgba(56, 189, 248, 0.4) !important; }
  <?php elseif ($template === 'minimalist_aurora'): ?>
    .portfolio-hero { background: #071512 !important; }
    .portfolio-card { background: #0d211b !important; border-color: rgba(16, 185, 129, 0.3) !important; backdrop-filter: blur(14px); }
    .text-theme-accent { color: #10b981 !important; }
    .badge-theme-accent { background: rgba(16, 185, 129, 0.15) !important; color: #10b981 !important; border: 1px solid rgba(16, 185, 129, 0.4) !important; }
    .btn-theme-accent { background: #10b981 !important; color: #080c14 !important; border: 0 !important; font-weight: 700; }
    .nfc-widget-card { background: linear-gradient(135deg, rgba(13, 33, 27, 0.95), rgba(7, 21, 18, 0.98)) !important; border-color: rgba(16, 185, 129, 0.4) !important; }
  <?php else: /* dark_luxury */ ?>
    .portfolio-hero { background: #080c14 !important; }
    .portfolio-card { background: #0d1524 !important; border-color: rgba(255, 255, 255, 0.08) !important; backdrop-filter: blur(12px); }
    .text-theme-accent { color: #c8ff00 !important; }
    .badge-theme-accent { background: rgba(200, 255, 0, 0.15) !important; color: #c8ff00 !important; border: 1px solid rgba(200, 255, 0, 0.35) !important; }
    .btn-theme-accent { background: #c8ff00 !important; color: #080c14 !important; border: 0 !important; font-weight: 700; }
    .nfc-widget-card { background: linear-gradient(135deg, rgba(19, 29, 46, 0.95), rgba(8, 12, 20, 0.98)) !important; border-color: rgba(200, 255, 0, 0.35) !important; }
  <?php endif; ?>

  .emv-chip-mini {
    width: 36px;
    height: 26px;
    background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #aa820a 100%);
    border-radius: 5px;
    border: 1px solid rgba(255,255,255,0.4);
    position: relative;
    box-shadow: inset 0 1px 2px rgba(255,255,255,0.5), 0 2px 4px rgba(0,0,0,0.4);
  }
  .emv-chip-mini::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    width: 100%;
    height: 1px;
    background: rgba(0,0,0,0.3);
  }
  .emv-chip-mini::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 1px;
    height: 100%;
    background: rgba(0,0,0,0.3);
  }

  .hover-lift {
    transition: transform 0.25s ease, box-shadow 0.25s ease;
  }
  .hover-lift:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.4);
  }
</style>

<section class="section-padding portfolio-hero" style="padding-top: 140px !important; padding-bottom: 90px; min-height: 90vh;">
    <div class="container max-w-1100-px">

        <!-- Owner Toolbar Banner -->
        <?php if ($isOwner): ?>
            <div class="alert alert-info bg-card border border-main-600 border-opacity-30 rounded-4 p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3" style="background:#0d1524;">
                <div class="d-flex align-items-center gap-2">
                    <i class="ph-bold ph-user-circle text-theme-accent font-size-22"></i>
                    <div>
                        <span class="text-white fw-bold d-block font-size-14">Your Public Portfolio &amp; Digital vCard</span>
                        <span class="text-white-50 font-size-12">Public URL: <?= e($shareUrl) ?></span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= BASE_URL ?>/portfolio-builder.php" class="btn btn-sm btn-main-600 text-dark fw-bold rounded-pill px-3 py-1 font-size-13">
                        <i class="ph ph-paint-brush me-1"></i> Edit Portfolio &amp; Templates
                    </a>
                    <a href="<?= BASE_URL ?>/resume-analyser.php" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-13">
                        <i class="ph ph-file-text me-1"></i> ATS Analyser
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Profile Header Card -->
        <div class="card portfolio-card rounded-5 p-4 p-md-5 mb-4 shadow-lg border">
            <div class="row align-items-center g-4">
                <!-- Avatar -->
                <div class="col-md-auto text-center text-md-start">
                    <div class="rounded-circle overflow-hidden d-inline-flex align-items-center justify-content-center border border-2 border-secondary border-opacity-30" style="width: 120px; height: 120px; background: rgba(200,255,0,0.1); box-shadow: 0 0 20px rgba(0,0,0,0.5);">
                        <?php if (!empty($avatar)): ?>
                            <img src="<?= e($avatar) ?>" alt="<?= e($portfolio['display_name']) ?>" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <span class="text-theme-accent fw-bold font-size-36">
                                <?= strtoupper(substr($portfolio['display_name'], 0, 2)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Details -->
                <div class="col-md">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h1 class="h2 text-white fw-bold mb-0"><?= e($portfolio['display_name']) ?></h1>
                        <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-30 rounded-pill px-3 py-1 font-size-12 fw-bold">
                            <i class="ph-fill ph-seal-check me-1"></i> Verified Creator
                        </span>
                        <span class="badge bg-dark border border-secondary border-opacity-25 text-white-50 rounded-pill px-2 py-1 font-size-11">
                            <i class="ph ph-eye me-1"></i> <?= number_format((int)$portfolio['views_count']) ?> views
                        </span>
                    </div>

                    <h3 class="h5 text-theme-accent fw-semibold mb-2"><?= e($portfolio['job_title']) ?></h3>
                    
                    <p class="text-white-75 font-size-15 mb-3" style="line-height: 1.6;">
                        <?= e($portfolio['headline']) ?>
                    </p>

                    <!-- Contact chips -->
                    <div class="d-flex flex-wrap gap-3 font-size-13 text-white-50">
                        <?php if (!empty($portfolio['company'])): ?>
                            <div><i class="ph ph-buildings text-theme-accent me-1"></i> <?= e($portfolio['company']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($portfolio['location'])): ?>
                            <div><i class="ph ph-map-pin text-theme-accent me-1"></i> <?= e($portfolio['location']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($portfolio['email'])): ?>
                            <div><i class="ph ph-envelope-simple text-theme-accent me-1"></i> <?= e($portfolio['email']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action CTA Toolbar -->
                <div class="col-md-auto d-flex flex-column gap-2 text-center text-md-end w-100 w-md-auto">
                    <!-- RFC vCard Download (.vcf) -->
                    <a href="<?= e($vcfUrl) ?>" class="btn btn-theme-accent rounded-pill px-4 py-2 font-size-14 d-flex align-items-center justify-content-center gap-2">
                        <i class="ph-bold ph-address-book"></i> Download Digital vCard (.vcf)
                    </a>

                    <!-- Dedicated Virtual NFC Card Link -->
                    <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-14 d-flex align-items-center justify-content-center gap-2">
                        <i class="ph-bold ph-wifi-high text-theme-accent"></i> View Virtual NFC Card
                    </a>

                    <!-- Direct P2P Message -->
                    <a href="<?= BASE_URL ?>/p2p-chat.php?user=<?= $portfolio['user_id'] ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2 font-size-14 d-flex align-items-center justify-content-center gap-2 text-white-75">
                        <i class="ph ph-chat-teardrop-dots"></i> Message on Platform
                    </a>

                    <!-- WhatsApp if available -->
                    <?php if (!empty($cleanPhone)): ?>
                        <a href="https://wa.me/<?= e($cleanPhone) ?>" target="_blank" class="btn btn-outline-success rounded-pill px-4 py-2 font-size-13 d-flex align-items-center justify-content-center gap-2">
                            <i class="ph ph-whatsapp-logo"></i> WhatsApp Chat
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Embedded Interactive Digital NFC Card Section -->
        <div class="card portfolio-card rounded-5 p-4 p-md-5 mb-5 border">
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div>
                    <h3 class="h4 text-white fw-bold mb-1 d-flex align-items-center gap-2">
                        <i class="ph-bold ph-credit-card text-theme-accent"></i> Interactive Virtual Business Card
                    </h3>
                    <p class="text-white-50 font-size-13 mb-0">
                        Scan the live QR code with any smartphone camera to save <?= e($portfolio['display_name']) ?>'s contact card instantly.
                    </p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 py-2 font-size-12" data-bs-toggle="modal" data-bs-target="#qrScanModal">
                    <i class="ph-bold ph-qr-code me-1 text-theme-accent"></i> Fullscreen QR Code
                </button>
            </div>

            <!-- Virtual NFC Card Row -->
            <div class="row align-items-center g-4">
                <!-- Card Visual Simulation -->
                <div class="col-lg-6">
                    <div class="nfc-widget-card rounded-4 p-4 border position-relative overflow-hidden shadow-lg hover-lift" style="min-height: 240px; cursor: pointer;" onclick="window.open('<?= e($vcardWebUrl) ?>', '_blank')">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="emv-chip-mini"></div>
                                <i class="ph-bold ph-wifi-high text-theme-accent font-size-20" title="Contactless NFC"></i>
                            </div>
                            <span class="badge rounded-pill px-3 py-1 font-size-10 fw-bold badge-theme-accent">
                                VERIFIED NFC CARD
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3 my-3">
                            <div class="rounded-circle overflow-hidden flex-shrink-0" style="width: 56px; height: 56px; border: 2px solid var(--accent, #c8ff00); background: rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:center; font-weight:bold; color:var(--accent,#c8ff00);">
                                <?php if (!empty($avatar)): ?>
                                    <img src="<?= e($avatar) ?>" alt="<?= e($portfolio['display_name']) ?>" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <?= strtoupper(substr($portfolio['display_name'], 0, 2)) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4 class="text-white fw-bold mb-0 font-size-16"><?= e($portfolio['display_name']) ?></h4>
                                <div class="font-size-13 text-theme-accent fw-semibold"><?= e($portfolio['job_title']) ?></div>
                                <?php if (!empty($portfolio['company'])): ?>
                                    <div class="font-size-11 text-white-50"><i class="ph ph-buildings me-1"></i> <?= e($portfolio['company']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary border-opacity-20 font-size-11 text-white-50">
                            <div><i class="ph ph-map-pin me-1"></i> <?= e($portfolio['location']) ?></div>
                            <div class="text-white-75 fw-semibold d-flex align-items-center gap-1">
                                <span>Tap to Open Interactive Card</span> &rarr;
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instant QR Code Quick Scan Widget -->
                <div class="col-lg-6">
                    <div class="p-4 rounded-4 text-center border" style="background:#080c14; border-color: rgba(255,255,255,0.08) !important;">
                        <div class="row align-items-center g-3">
                            <div class="col-sm-auto text-center">
                                <div class="bg-white p-2 rounded-4 shadow d-inline-block" style="width: 130px; height: 130px;">
                                    <img src="<?= e($qrCodeUrl) ?>" alt="vCard QR Code" class="w-100 h-100 object-fit-contain">
                                </div>
                            </div>
                            <div class="col-sm text-sm-start text-center">
                                <h5 class="text-white fw-bold font-size-15 mb-1">Instant Phone Camera Scan</h5>
                                <p class="text-white-50 font-size-12 mb-3">
                                    Aim your iPhone or Android camera at the QR code to immediately download <?= e($portfolio['display_name']) ?>'s contact card into your phone address book.
                                </p>
                                <div class="d-flex gap-2 justify-content-sm-start justify-content-center flex-wrap">
                                    <a href="<?= e($vcfUrl) ?>" class="btn btn-sm btn-theme-accent rounded-pill px-3 py-1 font-size-12">
                                        <i class="ph-bold ph-download-simple me-1"></i> Download .vcf
                                    </a>
                                    <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-12">
                                        <i class="ph ph-arrows-clockwise me-1"></i> Flip Virtual Card
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bio & Overview Section -->
        <div class="card portfolio-card rounded-5 p-4 p-md-5 mb-5 border">
            <h3 class="h4 text-white fw-bold mb-3 d-flex align-items-center gap-2">
                <i class="ph ph-user-focus text-theme-accent"></i> Professional Background
            </h3>
            <div class="text-white-75 font-size-15" style="line-height: 1.8;">
                <?= nl2br(e($portfolio['bio'])) ?>
            </div>

            <!-- Skills & Tools -->
            <?php if (!empty($skills)): ?>
                <div class="mt-4 pt-4 border-top border-secondary border-opacity-10">
                    <h5 class="text-white fw-bold font-size-15 mb-3">Core Skills &amp; Platforms</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($skills as $sk): ?>
                            <span class="badge bg-dark border border-secondary border-opacity-25 text-white-75 rounded-pill px-3 py-2 font-size-12">
                                <i class="ph ph-check text-theme-accent me-1"></i> <?= e($sk) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Featured Projects / Portfolio Items -->
        <?php if (!empty($projects)): ?>
            <div class="mb-5">
                <h3 class="h4 text-white fw-bold mb-4 d-flex align-items-center gap-2">
                    <i class="ph ph-trophy text-theme-accent"></i> Proven Case Studies &amp; Projects
                </h3>
                <div class="row g-4">
                    <?php foreach ($projects as $proj): ?>
                        <div class="col-md-6">
                            <div class="card portfolio-card rounded-4 p-4 h-100 border hover-lift">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h4 class="h5 text-white fw-bold mb-0"><?= e($proj['title'] ?? 'Campaign Case Study') ?></h4>
                                    <?php if (!empty($proj['metric'])): ?>
                                        <span class="badge btn-theme-accent rounded-pill px-3 py-1 font-size-12">
                                            <?= e($proj['metric']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted font-size-14 mb-3" style="line-height: 1.6;">
                                    <?= e($proj['description'] ?? '') ?>
                                </p>
                                <?php if (!empty($proj['link'])): ?>
                                    <div class="mt-auto pt-2 border-top border-secondary border-opacity-10">
                                        <a href="<?= e($proj['link']) ?>" target="_blank" class="text-theme-accent font-size-12 fw-semibold text-decoration-none d-inline-flex align-items-center gap-1">
                                            View Project Results <i class="ph ph-arrow-up-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Services & Packages -->
        <?php if (!empty($services)): ?>
            <div class="card portfolio-card rounded-5 p-4 p-md-5 mb-5 border">
                <h3 class="h4 text-white fw-bold mb-4 d-flex align-items-center gap-2">
                    <i class="ph ph-sparkle text-theme-accent"></i> Service Packages &amp; Rates
                </h3>
                <div class="row g-3">
                    <?php foreach ($services as $srv): ?>
                        <div class="col-md-6">
                            <div class="p-4 bg-dark border border-secondary border-opacity-15 rounded-4 h-100 d-flex flex-column justify-content-between">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-white fw-bold font-size-15"><?= e($srv['title'] ?? 'Service Offering') ?></span>
                                    <span class="text-theme-accent fw-bold font-size-15"><?= e($srv['price'] ?? 'Inquire') ?></span>
                                </div>
                                <?php if (!empty($srv['description'])): ?>
                                    <p class="text-white-50 font-size-13 mb-3" style="line-height: 1.5;">
                                        <?= e($srv['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/p2p-chat.php?user=<?= $portfolio['user_id'] ?>" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 font-size-12 align-self-start">
                                    Inquire Package &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Social Media Profiles Bar -->
        <?php if (!empty($socials)): ?>
            <div class="card portfolio-card rounded-4 p-4 mb-5 border text-center">
                <span class="font-size-13 text-white-50 mb-3 d-block">Connect with <?= e($portfolio['display_name']) ?> across Social Platforms</span>
                <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                    <?php if (!empty($socials['linkedin'])): ?>
                        <a href="<?= e($socials['linkedin']) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-linkedin-logo font-size-18 text-info"></i> LinkedIn
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socials['twitter'])): ?>
                        <a href="<?= e($socials['twitter']) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-x-logo font-size-18 text-white"></i> Twitter / X
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socials['instagram'])): ?>
                        <a href="<?= e($socials['instagram']) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-instagram-logo font-size-18 text-danger"></i> Instagram
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socials['github'])): ?>
                        <a href="<?= e($socials['github']) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-github-logo font-size-18 text-white"></i> GitHub
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socials['youtube'])): ?>
                        <a href="<?= e($socials['youtube']) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13 d-flex align-items-center gap-2">
                            <i class="ph-bold ph-youtube-logo font-size-18 text-danger"></i> YouTube
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Bottom Share & Contact Callout -->
        <div class="text-center py-4 border-top border-secondary border-opacity-10">
            <p class="text-white-50 font-size-14 mb-3">Want to collaborate or hire <?= e($portfolio['display_name']) ?>?</p>
            <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                <a href="<?= BASE_URL ?>/p2p-chat.php?user=<?= $portfolio['user_id'] ?>" class="btn btn-theme-accent rounded-pill px-5 py-3 font-size-14 fw-bold">
                    <i class="ph-bold ph-chat-teardrop-dots me-1"></i> Start Direct Conversation
                </a>
                <a href="<?= e($vcfUrl) ?>" class="btn btn-outline-light rounded-pill px-4 py-3 font-size-14">
                    <i class="ph ph-download-simple me-1"></i> Save to Contacts (.vcf)
                </a>
                <a href="<?= e($vcardWebUrl) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-4 py-3 font-size-14">
                    <i class="ph-bold ph-wifi-high text-theme-accent me-1"></i> View Virtual NFC Card
                </a>
            </div>
        </div>

    </div>
</section>

<!-- Fullscreen QR Code Modal -->
<div class="modal fade" id="qrScanModal" tabindex="-1" aria-labelledby="qrScanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-5 p-4 text-center border" style="background: #0d1524; border-color: rgba(255,255,255,0.15);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-white fw-bold w-100" id="qrScanModalLabel">
                    <i class="ph-bold ph-qr-code text-theme-accent me-1"></i> Scan Digital vCard
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="bg-white p-3 rounded-4 shadow d-inline-block mb-3" style="width: 220px; height: 220px;">
                    <img src="<?= e($qrCodeUrl) ?>" alt="vCard QR Code" class="w-100 h-100 object-fit-contain">
                </div>
                <h6 class="text-white fw-bold mb-1"><?= e($portfolio['display_name']) ?></h6>
                <div class="font-size-13 text-theme-accent mb-2"><?= e($portfolio['job_title']) ?></div>
                <p class="font-size-12 text-white-50 mb-0">
                    Open your camera app on iPhone or Android to instantly save this contact card or share it via NFC.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <a href="<?= e($vcfUrl) ?>" class="btn btn-theme-accent rounded-pill px-4 py-2 font-size-13">
                    <i class="ph-bold ph-download-simple me-1"></i> Download .vcf
                </a>
                <button type="button" class="btn btn-outline-light rounded-pill px-4 py-2 font-size-13" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
