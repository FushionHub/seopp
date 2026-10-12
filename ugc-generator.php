<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/modules/ai_engine.php';
require_once __DIR__ . '/modules/social_post_engine.php';

$userId    = current_user_id();
$userData  = current_user();
$aiCredits = (int)($userData['ai_credits'] ?? 0);

$pageTitle = 'AI Studio — Create Images & Videos — ' . get_setting('site_name', 'Adsbooster.net');
$_appPage  = 'ugc-generator';

$errors      = [];
$success     = '';
$generatedUgc = null;

$activeTab = $_GET['tab'] ?? 'generate_image';
if (!in_array($activeTab, ['generate_image', 'image_editor', 'creative_gallery'], true)) {
    $activeTab = 'generate_image';
}

// Handle UGC Image Generation or Regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['generate_ugc_image', 'regenerate_variation'], true)) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $productName    = trim($_POST['product_name'] ?? '');
        $ugcStyle       = trim($_POST['ugc_style'] ?? 'selfie_hold');
        $creatorPersona = trim($_POST['creator_persona'] ?? 'gen_z');
        $aspectRatio    = trim($_POST['aspect_ratio'] ?? '1:1');
        $targetAudience = trim($_POST['target_audience'] ?? '');
        $headline       = trim($_POST['headline'] ?? '');
        $badgeText      = trim($_POST['badge_text'] ?? '⭐⭐⭐⭐⭐ Top Rated');

        // If regenerating, use a new random seed
        $seed = ($_POST['action'] === 'regenerate_variation') ? random_int(100000, 999999) : (int)($_POST['seed'] ?? random_int(100000, 999999));

        if ($productName === '') {
            $errors[] = 'Type your product name first — that is all we need to start.';
        } else {
            $res = AIEngine::generateUGCImage([
                'product_name'    => $productName,
                'ugc_style'       => $ugcStyle,
                'creator_persona' => $creatorPersona,
                'aspect_ratio'    => $aspectRatio,
                'target_audience' => $targetAudience,
                'headline'        => $headline,
                'badge_text'      => $badgeText,
                'seed'            => $seed,
            ], $userId);

            if ($res['success']) {
                $generatedUgc = $res['data'];
                $aiCredits    = $res['credits_remaining'];
                $activeTab    = 'generate_image';
                $success      = ($_POST['action'] === 'regenerate_variation')
                    ? 'Here is a fresh variation of your creative.'
                    : ($res['message'] ?? 'Your image is ready.');
            } else {
                $errors[]   = $res['message'];
                $aiCredits  = $res['credits_remaining'];
            }
        }
    }
}

// Fetch user's recent UGC image creatives
$recentCreatives = db()->fetchAll(
    "SELECT id, prompt, result_output, credits_used, created_at
     FROM ai_creatives
     WHERE user_id = ? AND type = 'image'
     ORDER BY created_at DESC LIMIT 12",
    [$userId]
);

require_once __DIR__ . '/includes/app_header.php';

// Shared option metadata — used by the chip controls below.
$styleOptions = [
    'selfie_hold'       => ['Selfie with product', 'A real person holding it, facing the camera. Highest click rate.'],
    'unboxing'          => ['Unboxing shot', 'The product opened on a table, packaging in view.'],
    'lifestyle_kitchen' => ['At home', 'A candid moment in a kitchen or living room.'],
    'influencer_desk'   => ['Creator desk', 'A content creator reviewing it at their setup.'],
    'before_after'      => ['Before & after', 'A side-by-side transformation. Great for results claims.'],
    'flatlay'           => ['Flat lay', 'Top-down editorial layout on a clean background.'],
];
$personaOptions = [
    'gen_z'           => ['Lexi', 'Gen-Z, energetic, relatable'],
    'saas_founder'    => ['Marcus', 'Founder, professional'],
    'fitness_athlete' => ['Jordan', 'Fitness & wellness'],
    'homemaker'       => ['Maya', 'Busy parent, everyday life'],
    'tech_reviewer'   => ['Alex', 'Tech & gadgets'],
    'luxury_model'    => ['Elena', 'High-end, editorial'],
];
$aspectOptions = [
    '1:1'  => ['Square', 'Feed post'],
    '9:16' => ['Vertical', 'Reels & TikTok'],
    '16:9' => ['Wide', 'YouTube & banners'],
];
$badgeOptions = [
    '⭐⭐⭐⭐⭐ 4.9/5 Rating (1,240+ Reviews)',
    '🔥 50% OFF TODAY ONLY',
    '✅ 100% Verified Customer',
    '🏆 #1 Bestseller in 2026',
    '🚚 Free Express Shipping Worldwide',
];

$curStyle    = (string)($_POST['ugc_style'] ?? ($generatedUgc['ugc_style'] ?? 'selfie_hold'));
$curPersona  = (string)($_POST['creator_persona'] ?? ($generatedUgc['creator_persona'] ?? 'gen_z'));
$curAspect   = (string)($_POST['aspect_ratio'] ?? ($generatedUgc['aspect_ratio'] ?? '1:1'));
$curBadge    = (string)($_POST['badge_text'] ?? ($generatedUgc['badge_text'] ?? $badgeOptions[0]));
$curProduct  = (string)($_POST['product_name'] ?? ($generatedUgc['product_name'] ?? ''));
$curAudience = (string)($_POST['target_audience'] ?? '');
$curHeadline = (string)($_POST['headline'] ?? ($generatedUgc['hook_headline'] ?? ''));
?>

<style>
/* ── AI Studio workspace ───────────────────────────────────── */
.st{display:grid;grid-template-columns:212px minmax(300px,380px) 1fr;gap:16px;align-items:start;min-height:70vh}
@media (max-width:1199px){.st{grid-template-columns:1fr}}
.st-rail,.st-panel,.st-stage{background:#0b111c;border:1px solid rgba(255,255,255,.07);border-radius:20px}
.st-rail{padding:14px;position:sticky;top:88px}
.st-panel{padding:20px;max-height:calc(100vh - 120px);overflow:auto}
.st-stage{padding:20px;min-height:520px;display:flex;flex-direction:column}
.st-nav{display:flex;flex-direction:column;gap:4px}
.st-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;color:#94a3b8;font-size:14px;font-weight:600;text-decoration:none;transition:all .15s}
.st-nav a:hover{background:rgba(255,255,255,.05);color:#fff}
.st-nav a.on{background:rgba(200,255,0,.12);color:#c8ff00}
.st-nav a i{font-size:18px;width:20px;text-align:center}
.st-lbl{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin:18px 0 8px}
.st-lbl:first-child{margin-top:0}
.st-help{font-size:12px;color:#64748b;margin-top:5px;line-height:1.45}
.st-in{width:100%;background:#080c14;border:1px solid rgba(255,255,255,.1);color:#fff;border-radius:12px;padding:12px 14px;font-size:14px;transition:border-color .15s}
.st-in:focus{outline:none;border-color:#c8ff00;box-shadow:0 0 0 3px rgba(200,255,0,.15)}
.st-chips{display:flex;flex-wrap:wrap;gap:6px}
.st-chip{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);color:#cbd5e1;border-radius:999px;padding:7px 12px;font-size:12.5px;font-weight:600;cursor:pointer;transition:all .15s;text-align:left}
.st-chip:hover{border-color:rgba(200,255,0,.5);color:#fff}
.st-chip.on{background:rgba(200,255,0,.15);border-color:#c8ff00;color:#c8ff00}
.st-opt{display:block;width:100%;text-align:left;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:10px 12px;color:#cbd5e1;cursor:pointer;transition:all .15s;margin-bottom:6px}
.st-opt:hover{border-color:rgba(200,255,0,.45);background:rgba(255,255,255,.06)}
.st-opt.on{border-color:#c8ff00;background:rgba(200,255,0,.1)}
.st-opt b{display:block;color:#fff;font-size:13px;font-weight:700}
.st-opt span{display:block;color:#64748b;font-size:11.5px;margin-top:2px}
.st-opt.on span{color:#a3b18a}
.st-go{width:100%;margin-top:22px;background:#c8ff00;color:#0b111c;border:none;border-radius:14px;padding:15px;font-size:15px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:transform .12s,box-shadow .12s}
.st-go:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(200,255,0,.25)}
.st-go:disabled{opacity:.55;cursor:wait;transform:none}
.st-stagehead{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.st-canvaswrap{flex:1;display:flex;align-items:center;justify-content:center;background:
  radial-gradient(circle at 50% 40%,rgba(200,255,0,.05),transparent 60%),
  repeating-conic-gradient(rgba(255,255,255,.025) 0% 25%,transparent 0% 50%) 0 0/22px 22px;
  border:1px solid rgba(255,255,255,.06);border-radius:16px;min-height:420px;padding:18px;position:relative}
.st-img{max-width:100%;max-height:460px;border-radius:12px;display:block;box-shadow:0 20px 60px rgba(0,0,0,.55)}
.st-empty{text-align:center;max-width:360px}
.st-empty i{font-size:44px;color:#c8ff00;opacity:.75}
.st-empty h4{color:#fff;font-weight:800;font-size:19px;margin:14px 0 8px}
.st-empty p{color:#94a3b8;font-size:14px;line-height:1.6;margin:0}
.st-acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,.07)}
.st-act{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:9px 15px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,.14);color:#e2e8f0;background:rgba(255,255,255,.03);transition:all .15s}
.st-act:hover{background:rgba(255,255,255,.09);color:#fff}
.st-act.pri{background:#c8ff00;border-color:#c8ff00;color:#0b111c;font-weight:800}
.st-badge{position:absolute;top:14px;left:14px;background:#c8ff00;color:#0b111c;font-size:12px;font-weight:800;padding:5px 12px;border-radius:999px;z-index:2}
.st-cap{position:absolute;left:0;right:0;bottom:0;padding:34px 16px 14px;background:linear-gradient(transparent,rgba(0,0,0,.9));border-radius:0 0 12px 12px;z-index:2}
.st-cap b{display:block;color:#fff;font-size:15px;text-shadow:0 2px 6px rgba(0,0,0,.8)}
.st-cap span{display:block;color:#cbd5e1;font-size:12.5px;margin-top:3px;text-shadow:0 1px 4px rgba(0,0,0,.8)}
.st-lib{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:14px}
.st-card{background:#0b111c;border:1px solid rgba(255,255,255,.07);border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
.st-card img{width:100%;height:172px;object-fit:cover;background:#000;display:block}
.st-card .bd{padding:12px;flex:1}
.st-card h5{margin:0 0 4px;color:#fff;font-size:13.5px;font-weight:700}
.st-card p{margin:0;color:#94a3b8;font-size:12px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.st-card .ft{display:flex;flex-wrap:wrap;gap:5px;padding:10px 12px;border-top:1px solid rgba(255,255,255,.06)}
.st-card .ft a{font-size:11.5px;padding:5px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.12);color:#cbd5e1;text-decoration:none}
.st-card .ft a:hover{background:rgba(255,255,255,.08);color:#fff}
.st-card .ft a.pri{background:#c8ff00;border-color:#c8ff00;color:#0b111c;font-weight:700}
.st-alert{border-radius:14px;padding:13px 16px;font-size:13.5px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-start}
.st-alert.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}
.st-alert.bad{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.st-alert ul{margin:0;padding-left:16px}
.st-skel{width:100%;max-width:340px;aspect-ratio:9/16;border-radius:14px;background:linear-gradient(100deg,#0f172a 30%,#1e293b 50%,#0f172a 70%);background-size:200% 100%;animation:stsh 1.4s infinite}
@keyframes stsh{0%{background-position:200% 0}100%{background-position:-200% 0}}
.st-ranges{display:grid;gap:12px}
.st-range{display:grid;gap:6px}
.st-range .hd{display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;font-weight:600}
.st-range input{width:100%;accent-color:#c8ff00}
</style>

<div class="w-100">

  <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
      <h1 class="page-title mb-1"><i class="ph-bold ph-sparkle text-accent me-2"></i>AI Studio</h1>
      <p class="page-subtitle mb-0">Describe your product, pick a look, and get ad-ready images and videos in one place.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span style="background:#0d1524;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:8px 14px;font-size:13px;color:#94a3b8;">
        Credits <b style="color:#c8ff00;font-size:15px;"><?= number_format($aiCredits) ?></b>
      </span>
      <a href="<?= url('ugc-video-studio') ?>" class="st-act"><i class="ph ph-film"></i> Video Studio</a>
      <a href="<?= url('post-creator') ?>" class="st-act"><i class="ph ph-share-network"></i> Post</a>
      <a href="<?= url('create-ad') ?>" class="st-act pri"><i class="ph ph-megaphone"></i> Launch Ad</a>
    </div>
  </div>

  <div class="st">

    <!-- ── Left rail: where am I? ─────────────────────────── -->
    <nav class="st-rail">
      <div class="st-lbl">Studio</div>
      <div class="st-nav">
        <a href="?tab=generate_image" class="<?= $activeTab === 'generate_image' ? 'on' : '' ?>">
          <i class="ph ph-image-square"></i> Create image
        </a>
        <a href="?tab=image_editor" class="<?= $activeTab === 'image_editor' ? 'on' : '' ?>">
          <i class="ph ph-sliders-horizontal"></i> Edit image
        </a>
        <a href="?tab=creative_gallery" class="<?= $activeTab === 'creative_gallery' ? 'on' : '' ?>">
          <i class="ph ph-squares-four"></i> My images
        </a>
        <a href="<?= url('ugc-video-studio') ?>">
          <i class="ph ph-film"></i> Create video
        </a>
      </div>
      <div class="st-lbl">Try an example</div>
      <div class="st-nav" id="flowInspirationChips">
        <a href="#" onclick="applyFlowPreset('serum');return false;"><i class="ph ph-drop"></i> Skincare serum</a>
        <a href="#" onclick="applyFlowPreset('earbuds');return false;"><i class="ph ph-headphones"></i> Wireless earbuds</a>
        <a href="#" onclick="applyFlowPreset('energy');return false;"><i class="ph ph-lightning"></i> Energy drink</a>
        <a href="#" onclick="applyFlowPreset('sneakers');return false;"><i class="ph ph-sneaker-move"></i> Running shoes</a>
        <a href="#" onclick="applyFlowPreset('saas');return false;"><i class="ph ph-desktop"></i> Software app</a>
        <a href="#" onclick="applyFlowPreset('coffee');return false;"><i class="ph ph-coffee"></i> Cold brew</a>
      </div>
    </nav>

    <!-- ── Middle: the controls ───────────────────────────── -->
    <section class="st-panel">

      <?php if ($success): ?>
        <div class="st-alert ok"><i class="ph ph-check-circle" style="font-size:18px"></i><span><?= e($success) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="st-alert bad"><i class="ph ph-warning-circle" style="font-size:18px"></i>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($activeTab === 'generate_image'): ?>
        <form method="POST" id="ugcGenForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="generate_ugc_image">

          <div class="st-lbl">1 · What are you selling?</div>
          <input type="text" id="ugcProductName" name="product_name" class="st-in" required
                 placeholder="e.g. Luxe Radiance Facial Serum" value="<?= e($curProduct) ?>">
          <p class="st-help">The product, brand or service name. Example: “AquaBottle Pro”.</p>

          <div class="st-lbl">2 · What shape do you need?</div>
          <div class="st-chips">
            <?php foreach ($aspectOptions as $val => [$label, $use]): ?>
              <label class="st-chip <?= $curAspect === $val ? 'on' : '' ?>" data-aspect="<?= e($val) ?>">
                <input type="radio" name="aspect_ratio" value="<?= e($val) ?>" class="d-none" <?= $curAspect === $val ? 'checked' : '' ?>>
                <?= e($val) ?> · <?= e($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="st-help">Square for feed posts, vertical for Reels and TikTok, wide for YouTube.</p>

          <div class="st-lbl">3 · Where is the photo taken?</div>
          <?php foreach ($styleOptions as $val => [$label, $desc]): ?>
            <label class="st-opt <?= $curStyle === $val ? 'on' : '' ?>">
              <input type="radio" name="ugc_style" value="<?= e($val) ?>" class="d-none" <?= $curStyle === $val ? 'checked' : '' ?>>
              <b><?= e($label) ?></b><span><?= e($desc) ?></span>
            </label>
          <?php endforeach; ?>

          <div class="st-lbl">4 · Who is in the photo?</div>
          <div class="st-chips">
            <?php foreach ($personaOptions as $val => [$label, $desc]): ?>
              <label class="st-chip <?= $curPersona === $val ? 'on' : '' ?>">
                <input type="radio" name="creator_persona" value="<?= e($val) ?>" class="d-none" <?= $curPersona === $val ? 'checked' : '' ?>>
                <?= e($label) ?> · <?= e($desc) ?>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="st-lbl">5 · Optional extras</div>
          <input type="text" id="ugcAudience" name="target_audience" class="st-in" style="margin-bottom:10px"
                 placeholder="Who is it for? e.g. busy students who need focus" value="<?= e($curAudience) ?>">
          <input type="text" id="ugcHeadline" name="headline" class="st-in"
                 placeholder="Text overlay, e.g. My skin in 14 days" value="<?= e($curHeadline) ?>">
          <p class="st-help">Leave these blank if you are not sure — we will work without them.</p>

          <div class="st-lbl">Trust badge (optional)</div>
          <select id="ugcBadgeSelect" name="badge_text" class="st-in">
            <?php foreach ($badgeOptions as $b): ?>
              <option value="<?= e($b) ?>" <?= $curBadge === $b ? 'selected' : '' ?>><?= e($b) ?></option>
            <?php endforeach; ?>
          </select>

          <button type="submit" class="st-go" id="stGenerateBtn">
            <i class="ph-bold ph-sparkle"></i> <span>Generate image</span>
          </button>
          <p class="st-help" style="text-align:center;margin-top:10px">Takes about 10 seconds. Uses 5 credits.</p>
        </form>

      <?php elseif ($activeTab === 'image_editor'):
        $editorImg      = (string)($_GET['img'] ?? ($generatedUgc['image_url'] ?? 'https://images.unsplash.com/photo-1557838923-2985c318be48?auto=format&fit=crop&w=1200&q=80'));
        $editorHeadline = (string)($_GET['headline'] ?? '🔥 Special Limited Time Offer!');
      ?>
        <div class="st-lbl">Brightness</div>
        <div class="st-ranges">
          <div class="st-range">
            <div class="hd"><span>Brightness</span><span id="brightnessVal">100%</span></div>
            <input type="range" id="filterBrightness" min="50" max="150" value="100" oninput="applyCanvasFilters()">
          </div>
          <div class="st-range">
            <div class="hd"><span>Contrast</span><span id="contrastVal">100%</span></div>
            <input type="range" id="filterContrast" min="50" max="150" value="100" oninput="applyCanvasFilters()">
          </div>
          <div class="st-range">
            <div class="hd"><span>Colour intensity</span><span id="saturationVal">100%</span></div>
            <input type="range" id="filterSaturation" min="0" max="200" value="100" oninput="applyCanvasFilters()">
          </div>
        </div>

        <div class="st-lbl">Quick looks</div>
        <div class="st-chips">
          <button type="button" class="st-chip" onclick="setColorPreset('normal')">Original</button>
          <button type="button" class="st-chip" onclick="setColorPreset('vibrant')">Vibrant</button>
          <button type="button" class="st-chip" onclick="setColorPreset('warm')">Warm</button>
          <button type="button" class="st-chip" onclick="setColorPreset('bw')">Black &amp; white</button>
        </div>

        <div class="st-lbl">Trust badge</div>
        <select id="editorBadgeSelect" class="st-in" onchange="renderCanvas()">
          <option value="">None</option>
          <option value="⭐⭐⭐⭐⭐ 4.9/5 Rating" selected>⭐⭐⭐⭐⭐ 4.9/5 Rating</option>
          <option value="🔥 50% OFF TODAY">🔥 50% OFF TODAY</option>
          <option value="✅ 100% Verified Buyer">✅ 100% Verified Buyer</option>
          <option value="🏆 Best Seller 2026">🏆 Best Seller 2026</option>
        </select>

        <div class="st-lbl">Headline text at the bottom</div>
        <input type="text" id="editorHeadlineInput" class="st-in" value="<?= e($editorHeadline) ?>" oninput="renderCanvas()">

        <button type="button" class="st-go" onclick="downloadEditedCanvas()">
          <i class="ph-bold ph-download-simple"></i> <span>Download PNG</span>
        </button>
        <button type="button" class="st-act" style="width:100%;justify-content:center;margin-top:10px" onclick="resetEditor()">
          Reset everything
        </button>

      <?php else: ?>
        <div class="st-lbl">Your library</div>
        <p class="st-help" style="margin:0 0 14px">
          Everything you have created is saved here automatically. Pick one to edit, post, or turn into an ad.
        </p>
        <a href="?tab=generate_image" class="st-act pri" style="width:100%;justify-content:center">
          <i class="ph ph-plus"></i> Create a new image
        </a>
      <?php endif; ?>
    </section>

    <!-- ── Right: the stage ───────────────────────────────── -->
    <section class="st-stage">
      <?php if ($activeTab === 'generate_image'): ?>
        <div class="st-stagehead">
          <span style="color:#fff;font-weight:700;font-size:15px">Preview</span>
          <?php if ($generatedUgc): ?>
            <span style="color:#64748b;font-size:12px">
              <?= e($generatedUgc['aspect_ratio']) ?> · <?= (int)$generatedUgc['width'] ?>×<?= (int)$generatedUgc['height'] ?> · seed <?= e((string)$generatedUgc['seed']) ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="st-canvaswrap" id="stStage">
          <?php if ($generatedUgc): ?>
            <div style="position:relative;display:inline-block;max-width:100%">
              <?php if (!empty($generatedUgc['badge_text'])): ?>
                <span class="st-badge"><?= e($generatedUgc['badge_text']) ?></span>
              <?php endif; ?>
              <img id="activeUgcImage" class="st-img" src="<?= e($generatedUgc['image_url']) ?>" alt="Generated ad image">
              <div class="st-cap">
                <b><?= e($generatedUgc['hook_headline']) ?></b>
                <span><?= e($generatedUgc['ad_copy']) ?></span>
              </div>
            </div>
          <?php else: ?>
            <div class="st-empty">
              <i class="ph ph-image"></i>
              <h4>Your image appears here</h4>
              <p>Type your product name on the left, or tap one of the examples in the side menu to fill everything in for you.</p>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($generatedUgc): ?>
          <div class="st-acts">
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="regenerate_variation">
              <input type="hidden" name="product_name" value="<?= e($generatedUgc['product_name']) ?>">
              <input type="hidden" name="ugc_style" value="<?= e($generatedUgc['ugc_style']) ?>">
              <input type="hidden" name="creator_persona" value="<?= e($generatedUgc['creator_persona']) ?>">
              <input type="hidden" name="aspect_ratio" value="<?= e($generatedUgc['aspect_ratio']) ?>">
              <input type="hidden" name="target_audience" value="<?= e($curAudience) ?>">
              <input type="hidden" name="headline" value="<?= e($generatedUgc['hook_headline']) ?>">
              <input type="hidden" name="badge_text" value="<?= e($generatedUgc['badge_text']) ?>">
              <button type="submit" class="st-act"><i class="ph ph-arrows-clockwise"></i> Try another version</button>
            </form>
            <a href="?tab=image_editor&img=<?= urlencode($generatedUgc['image_url']) ?>&headline=<?= urlencode($generatedUgc['hook_headline']) ?>" class="st-act"><i class="ph ph-sliders-horizontal"></i> Edit</a>
            <a href="<?= e($generatedUgc['image_url']) ?>" target="_blank" rel="noopener" download="adsbooster-ugc-<?= time() ?>.png" class="st-act"><i class="ph ph-download-simple"></i> Download</a>
            <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($generatedUgc['image_url']) ?>&caption=<?= urlencode($generatedUgc['hook_headline'] . "\n\n" . $generatedUgc['ad_copy']) ?>" class="st-act"><i class="ph ph-paper-plane-tilt"></i> Post to social</a>
            <a href="<?= url('create-ad') ?>?image_url=<?= urlencode($generatedUgc['image_url']) ?>&headline=<?= urlencode($generatedUgc['hook_headline']) ?>&primary_text=<?= urlencode($generatedUgc['ad_copy']) ?>" class="st-act pri"><i class="ph ph-rocket-launch"></i> Run as an ad</a>
          </div>
        <?php endif; ?>

      <?php elseif ($activeTab === 'image_editor'): ?>
        <div class="st-stagehead">
          <span style="color:#fff;font-weight:700;font-size:15px">Editing</span>
          <span style="color:#64748b;font-size:12px">Changes update live · download when you are happy</span>
        </div>
        <div class="st-canvaswrap">
          <canvas id="creativeCanvas" class="st-img" style="max-height:520px"></canvas>
        </div>

      <?php else: ?>
        <div class="st-stagehead">
          <span style="color:#fff;font-weight:700;font-size:15px">My images</span>
          <span style="color:#64748b;font-size:12px"><?= count($recentCreatives) ?> saved</span>
        </div>
        <?php if (!empty($recentCreatives)): ?>
          <div class="st-lib">
            <?php foreach ($recentCreatives as $c):
              $data    = json_decode($c['result_output'] ?? '{}', true) ?: [];
              $img     = (string)($data['image_url'] ?? '');
              $title   = (string)($data['product_name'] ?? 'Image creative');
              $headline= (string)($data['hook_headline'] ?? ($c['prompt'] ?? ''));
            ?>
              <div class="st-card">
                <?php if ($img): ?>
                  <img src="<?= e($img) ?>" alt="<?= e($title) ?>" loading="lazy">
                <?php else: ?>
                  <div style="height:172px;display:flex;align-items:center;justify-content:center;color:#334155;font-size:30px"><i class="ph ph-image"></i></div>
                <?php endif; ?>
                <div class="bd">
                  <h5><?= e($title) ?></h5>
                  <p><?= e($headline) ?></p>
                  <span style="font-size:11px;color:#64748b"><?= date('M d, Y', strtotime($c['created_at'])) ?> · <?= (int)$c['credits_used'] ?> credits</span>
                </div>
                <?php if ($img): ?>
                  <div class="ft">
                    <a href="?tab=image_editor&img=<?= urlencode($img) ?>&headline=<?= urlencode($headline) ?>">Edit</a>
                    <a href="<?= url('post-creator') ?>?media_url=<?= urlencode($img) ?>&caption=<?= urlencode($headline) ?>">Post</a>
                    <a class="pri" href="<?= url('create-ad') ?>?image_url=<?= urlencode($img) ?>&headline=<?= urlencode($headline) ?>&primary_text=<?= urlencode($data['ad_copy'] ?? '') ?>">Run ad</a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="st-canvaswrap">
            <div class="st-empty">
              <i class="ph ph-images"></i>
              <h4>Nothing saved yet</h4>
              <p>Images you create are kept here automatically so you can reuse them any time. Start with an example from the left menu.</p>
              <a href="?tab=generate_image" class="st-act pri" style="margin-top:16px"><i class="ph ph-sparkle"></i> Create your first image</a>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
/* ── Chip / option selection (delegated, works for any group) ── */
document.addEventListener('click', function (e) {
  const chip = e.target.closest('.st-chip, .st-opt');
  if (!chip || !chip.closest('form')) return;
  const input = chip.querySelector('input[type=radio]');
  if (!input) return;
  const name = input.name;
  chip.closest('form').querySelectorAll('input[name="' + name + '"]').forEach(r => {
    const lab = r.closest('.st-chip, .st-opt');
    if (lab) lab.classList.remove('on');
  });
  chip.classList.add('on');
});

/* ── Loading state on generate ── */
const genForm = document.getElementById('ugcGenForm');
if (genForm) {
  genForm.addEventListener('submit', function () {
    const btn = document.getElementById('stGenerateBtn');
    if (btn) { btn.disabled = true; btn.querySelector('span').textContent = 'Generating…'; }
  });
}

/* ── One-click example presets ── */
function applyFlowPreset(type) {
  const presets = {
    serum:   { name:"Luxe Botanical Glow Serum (50ml)", style:"selfie_hold", persona:"luxury_model", aspect:"1:1", audience:"Women 22-45 looking for simple daily hydration", headline:"Glass skin without ten complicated steps", badge:"⭐⭐⭐⭐⭐ 4.9/5 Rating (1,240+ Reviews)" },
    earbuds: { name:"AuraWave ANC Pro Wireless Earbuds", style:"influencer_desk", persona:"tech_reviewer", aspect:"9:16", audience:"Remote workers who need quiet focus", headline:"I replaced my $300 headphones with these", badge:"🏆 #1 Bestseller in 2026" },
    energy:  { name:"VoltClean Organic Energy Drink", style:"selfie_hold", persona:"fitness_athlete", aspect:"9:16", audience:"Students and athletes who need steady focus", headline:"Zero sugar, zero crash", badge:"🔥 50% OFF TODAY ONLY" },
    sneakers:{ name:"StrideZero Cloud-Knit Running Shoes", style:"unboxing", persona:"gen_z", aspect:"1:1", audience:"Runners and city commuters", headline:"20,000 steps and no sore feet", badge:"✅ 100% Verified Customer" },
    saas:    { name:"AdFlow AI Ad Scaling Suite", style:"influencer_desk", persona:"saas_founder", aspect:"16:9", audience:"Online store owners and media buyers", headline:"Scaling ads without wasting budget", badge:"🏆 #1 Bestseller in 2026" },
    coffee:  { name:"VelvetRoast Micro-Batch Cold Brew", style:"lifestyle_kitchen", persona:"homemaker", aspect:"1:1", audience:"Coffee drinkers who want a smoother cup", headline:"The smoothest morning brew you will try", badge:"🚚 Free Express Shipping Worldwide" }
  };
  const p = presets[type];
  const form = document.getElementById('ugcGenForm');
  if (!p || !form) return;

  const setRadio = (name, val) => {
    const r = form.querySelector('input[name="' + name + '"][value="' + val + '"]');
    if (!r) return;
    r.checked = true;
    form.querySelectorAll('input[name="' + name + '"]').forEach(x => {
      const lab = x.closest('.st-chip, .st-opt');
      if (lab) lab.classList.toggle('on', x.checked);
    });
  };

  const n = document.getElementById('ugcProductName'); if (n) n.value = p.name;
  const a = document.getElementById('ugcAudience');   if (a) a.value = p.audience;
  const h = document.getElementById('ugcHeadline');   if (h) h.value = p.headline;
  const b = document.getElementById('ugcBadgeSelect');if (b) b.value = p.badge;
  setRadio('ugc_style', p.style);
  setRadio('creator_persona', p.persona);
  setRadio('aspect_ratio', p.aspect);

  if (n) { n.focus(); n.scrollIntoView({behavior:'smooth', block:'center'}); }
}

/* ── Image editor canvas ── */
const canvas = document.getElementById('creativeCanvas');
if (canvas) {
  const ctx = canvas.getContext('2d');
  const sourceImg = new Image();
  sourceImg.crossOrigin = 'anonymous';
  sourceImg.src = <?= json_encode((string)($editorImg ?? '')) ?>;

  let filters = { brightness:100, contrast:100, saturation:100, sepia:0, grayscale:0 };

  sourceImg.onload = function () {
    canvas.width  = sourceImg.naturalWidth  || 1024;
    canvas.height = sourceImg.naturalHeight || 1024;
    renderCanvas();
  };
  sourceImg.onerror = function () {
    const wrap = canvas.parentElement;
    canvas.style.display = 'none';
    const msg = document.createElement('div');
    msg.className = 'st-empty';
    msg.innerHTML = '<i class="ph ph-image"></i><h4>Could not load that image</h4><p>Generate a new image in the Create tab, then press Edit.</p>';
    wrap.appendChild(msg);
  };

  window.applyCanvasFilters = function () {
    filters.brightness = parseInt(document.getElementById('filterBrightness').value, 10);
    filters.contrast   = parseInt(document.getElementById('filterContrast').value, 10);
    filters.saturation = parseInt(document.getElementById('filterSaturation').value, 10);
    document.getElementById('brightnessVal').innerText = filters.brightness + '%';
    document.getElementById('contrastVal').innerText   = filters.contrast + '%';
    document.getElementById('saturationVal').innerText = filters.saturation + '%';
    renderCanvas();
  };

  window.setColorPreset = function (preset) {
    const map = {
      normal:   { brightness:100, contrast:100, saturation:100, sepia:0,  grayscale:0 },
      vibrant:  { brightness:105, contrast:115, saturation:140, sepia:0,  grayscale:0 },
      warm:     { brightness:102, contrast:105, saturation:110, sepia:25, grayscale:0 },
      bw:       { brightness:105, contrast:120, saturation:0,   sepia:0,  grayscale:100 }
    };
    filters = map[preset] || map.normal;
    document.getElementById('filterBrightness').value = filters.brightness;
    document.getElementById('filterContrast').value   = filters.contrast;
    document.getElementById('filterSaturation').value = filters.saturation;
    applyCanvasFilters();
  };

  window.renderCanvas = function () {
    if (!sourceImg.complete || !canvas.width) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.filter = 'brightness(' + filters.brightness + '%) contrast(' + filters.contrast +
                 '%) saturate(' + filters.saturation + '%) sepia(' + filters.sepia +
                 '%) grayscale(' + filters.grayscale + '%)';
    ctx.drawImage(sourceImg, 0, 0, canvas.width, canvas.height);
    ctx.filter = 'none';

    const badge = document.getElementById('editorBadgeSelect').value;
    if (badge) {
      ctx.save();
      ctx.font = 'bold 24px Inter, system-ui, sans-serif';
      const tw = ctx.measureText(badge).width;
      ctx.fillStyle = '#c8ff00';
      ctx.beginPath();
      if (ctx.roundRect) { ctx.roundRect(32, 48, tw + 48, 48, 24); }
      else { ctx.rect(32, 48, tw + 48, 48); }
      ctx.fill();
      ctx.fillStyle = '#0b111c';
      ctx.fillText(badge, 56, 81);
      ctx.restore();
    }

    const headline = document.getElementById('editorHeadlineInput').value;
    if (headline) {
      ctx.save();
      const barH = 110, barY = canvas.height - barH;
      const grad = ctx.createLinearGradient(0, barY - 40, 0, canvas.height);
      grad.addColorStop(0, 'rgba(0,0,0,0)');
      grad.addColorStop(1, 'rgba(0,0,0,0.85)');
      ctx.fillStyle = grad;
      ctx.fillRect(0, barY - 40, canvas.width, barH + 40);
      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 30px Inter, system-ui, sans-serif';
      ctx.shadowColor = 'rgba(0,0,0,0.9)';
      ctx.shadowBlur = 8;
      const maxW = canvas.width - 64;
      let line = '', y = canvas.height - 45;
      ctx.textBaseline = 'alphabetic';
      for (const word of String(headline).split(/\s+/)) {
        const test = line ? line + ' ' + word : word;
        if (ctx.measureText(test).width > maxW && line) { ctx.fillText(line, 32, y); y -= 36; line = word; }
        else line = test;
      }
      if (line) ctx.fillText(line, 32, y);
      ctx.restore();
    }
  };

  window.downloadEditedCanvas = function () {
    const link = document.createElement('a');
    link.download = 'adsbooster-edited-creative-' + Date.now() + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  };

  window.resetEditor = function () { setColorPreset('normal'); };
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
