<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ad_spy_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the Competitor Ad Spy Engine.');
    redirect(url('login'));
}

$pageTitle = 'Competitor Ad Spy & Reverse-Engineering Engine — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

$latestAnalysis = null;

// Handle Form Analysis Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('ad-spy'));
    }

    $compName = trim($_POST['competitor_name'] ?? '');
    $compUrl = trim($_POST['competitor_url'] ?? '');
    $platform = trim($_POST['platform'] ?? 'meta');

    if (!empty($compUrl) || !empty($compName)) {
        $latestAnalysis = AdSpyEngine::analyzeCompetitor($userId, $compName, $compUrl, $platform);
        set_flash('success', "Competitor brief generated for '" . $latestAnalysis['competitor_name'] . "' - 5 counter-creative angles ready.");
    }
}

// Fetch historical analyses
$history = db()->fetchAll("SELECT * FROM competitor_ad_analyses WHERE user_id = ? ORDER BY id DESC LIMIT 10", [$userId]);
if (!$latestAnalysis && !empty($history)) {
    $row = $history[0];
    $latestAnalysis = [
        'id' => $row['id'],
        'competitor_name' => $row['competitor_name'],
        'competitor_url' => $row['competitor_url'],
        'platform' => $row['platform'],
        'scraped_ads_count' => $row['scraped_ads_count'],
        'extracted_hooks' => json_decode($row['extracted_hooks'] ?? '[]', true),
        'extracted_angles' => json_decode($row['extracted_angles'] ?? '[]', true),
        'target_audience_insights' => json_decode($row['target_audience_insights'] ?? '[]', true),
        'counter_creatives' => json_decode($row['counter_creatives'] ?? '[]', true)
    ];
}

// Older rows stored a random "scraped_ads_count". Recompute from the brief
// that is actually stored so the badge always describes real content.
if (is_array($latestAnalysis)) {
    $latestAnalysis['scraped_ads_count'] =
        count($latestAnalysis['extracted_hooks'] ?? [])
        + count($latestAnalysis['extracted_angles'] ?? [])
        + count($latestAnalysis['counter_creatives'] ?? []);
}

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-detective me-1"></i> Intelligence Suite V2.0
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-lightning me-1"></i> Steal & Improve Engine
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Competitor Ad Spy & Reverse-Engineering</h2>
        <p class="text-secondary mb-0">Deconstruct your competitor's winning hooks, formats, and value propositions. Launch superior counter-campaigns in 1 click.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= url('ugc-video-studio') ?>" class="btn btn-outline-primary">
          <i class="ph ph-video-camera me-1"></i> AI Video Studio
        </a>
        <a href="<?= url('create-ad') ?>" class="btn btn-primary fw-bold">
          <i class="ph ph-rocket-launch me-1"></i> Ad Creator
        </a>
      </div>
    </div>

    <!-- Search / Spy Input Box -->
    <div class="card border-0 bg-surface-card rounded-4 p-4 mb-4 shadow-sm">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label small text-secondary fw-semibold">Competitor Brand Name</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary border-opacity-25 text-secondary"><i class="ph ph-buildings"></i></span>
              <input type="text" name="competitor_name" class="form-control" placeholder="e.g. Gymshark, Notion, Shopify" required>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-secondary fw-semibold">Website URL or Social Handle</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary border-opacity-25 text-secondary"><i class="ph ph-globe"></i></span>
              <input type="text" name="competitor_url" class="form-control" placeholder="https://competitor.com or @handle">
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-secondary fw-semibold">Network Focus</label>
            <select name="platform" class="form-select">
              <option value="meta">Meta (FB & IG)</option>
              <option value="tiktok">TikTok Ads</option>
              <option value="google">Google Ads</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
              <i class="ph ph-magnifying-glass me-1"></i> Spy & Analyze
            </button>
          </div>
        </div>
      </form>
    </div>

    <?php if ($latestAnalysis): ?>
    <!-- Intelligence Overview -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm h-100">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><?= e($latestAnalysis['competitor_name']) ?></h5>
            <span class="badge bg-primary text-black fw-bold"><?= (int)$latestAnalysis['scraped_ads_count'] ?> insights generated</span>
          </div>

          <div class="alert alert-secondary border-0 bg-dark bg-opacity-25 small py-2 px-3 mb-3">
            <i class="ph ph-info"></i>
            Written brief generated from proven-response templates. This tool does
            <strong>not</strong> scrape the Meta Ad Library or TikTok Creative Center,
            so the figures below describe this brief — not observed ad performance.
          </div>

          <div class="mb-3">
            <small class="text-secondary d-block mb-1">Recommended angle focus:</small>
            <?php foreach ($latestAnalysis['extracted_angles'] ?? [] as $ang): ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between small text-white mb-1">
                <span><?= e($ang['angle']) ?></span>
                <span class="text-primary fw-bold"><?= (int)$ang['share_pct'] ?>%</span>
              </div>
              <div class="progress bg-dark" style="height: 6px;">
                <div class="progress-bar bg-primary" style="width: <?= (int)$ang['share_pct'] ?>%;"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="border-top border-secondary border-opacity-25 pt-3">
            <small class="text-secondary d-block mb-1">Suggested format mix (guidance, not measured):</small>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($latestAnalysis['target_audience_insights']['format_split'] ?? [] as $fmt => $pct): ?>
              <span class="badge bg-dark border border-secondary border-opacity-25 text-white">
                <?= e($fmt) ?>: <strong class="text-info"><?= e($pct) ?></strong>
              </span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Extracted Hooks -->
      <div class="col-lg-8">
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm h-100">
          <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="ph ph-anchor text-warning"></i> Hook Templates
          </h5>
          <div class="row g-3">
            <?php foreach ($latestAnalysis['extracted_hooks'] ?? [] as $idx => $hk): ?>
            <div class="col-md-6">
              <div class="p-3 rounded-3 border border-secondary border-opacity-25 bg-dark bg-opacity-50">
                <span class="badge bg-secondary bg-opacity-25 text-warning small mb-2">Hook Angle #<?= $idx + 1 ?></span>
                <p class="text-white small mb-0 fw-medium">"<?= e($hk) ?>"</p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- 5 "Steal & Improve" Counter-Creatives -->
    <div class="mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
          <i class="ph ph-sparkle text-primary"></i> 5 "Steal & Improve" Counter-Campaigns
        </h4>
        <span class="text-secondary small">1-Click Launch into Ad Creator or Video Studio</span>
      </div>

      <div class="row g-3">
        <?php foreach ($latestAnalysis['counter_creatives'] ?? [] as $cIdx => $cc): ?>
        <div class="col-lg-4 col-md-6">
          <div class="card border-0 bg-surface-card rounded-4 p-4 h-100 shadow-sm d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-primary-subtle text-primary fw-bold small"><?= e($cc['title']) ?></span>
              </div>
              <h6 class="fw-bold text-white mb-2"><?= e($cc['headline']) ?></h6>
              <div class="p-2 rounded-2 bg-dark bg-opacity-50 border border-secondary border-opacity-25 mb-2 small text-warning">
                <strong>Hook:</strong> "<?= e($cc['hook']) ?>"
              </div>
              <p class="text-secondary small mb-3"><?= e($cc['body']) ?></p>
            </div>
            <div class="d-flex flex-column gap-2">
              <a href="<?= url('create-ad', ['headline' => $cc['headline'], 'text' => $cc['hook'] . ' ' . $cc['body']]) ?>" class="btn btn-sm btn-primary w-100 fw-bold py-2">
                <i class="ph ph-rocket-launch me-1"></i> Deploy Counter-Ad Now
              </a>
              <a href="<?= url('ugc-video-studio') ?>" class="btn btn-sm btn-outline-secondary w-100 py-1 small">
                <i class="ph ph-video-camera me-1"></i> Generate Talking Video for this Angle
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
