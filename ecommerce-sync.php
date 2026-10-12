<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/ecommerce_sync.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access E-Commerce 1-Click Sync.');
    redirect(url('login'));
}

$pageTitle = 'Shopify & WooCommerce 1-Click Sync — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

$latestPack = null;

// Handle Store Connection and Auto-Pack Generation
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('ecommerce-sync'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'connect_store') {
        $sType = $_POST['store_type'] ?? 'shopify';
        $sName = trim($_POST['store_name'] ?? 'My Store');
        $sUrl = trim($_POST['store_url'] ?? 'https://myshopifystore.com');

        EcommerceSyncEngine::connectAndSyncStore($userId, $sType, $sName, $sUrl);
        set_flash('success', "Connected {$sName}! Products imported automatically.");
        redirect(url('ecommerce-sync'));
    }

    if ($action === 'generate_autopack') {
        $prodId = (int)($_POST['product_id'] ?? 0);
        try {
            $latestPack = EcommerceSyncEngine::generateAutoPack($userId, $prodId);
            set_flash('success', "1-Click Campaign Auto-Pack generated for {$latestPack['product']['product_title']}!");
        } catch (Exception $e) {
            set_flash('error', $e->getMessage());
        }
    }
}

// Fetch user stores and products
$stores = db()->fetchAll("SELECT * FROM ecommerce_stores WHERE user_id = ? ORDER BY id DESC", [$userId]);
$products = db()->fetchAll("SELECT p.*, s.store_name, s.store_type FROM ecommerce_products p JOIN ecommerce_stores s ON p.store_id = s.id WHERE p.user_id = ? ORDER BY p.id DESC", [$userId]);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-shopping-cart me-1"></i> E-Commerce Bridge V2.0
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-sparkle me-1"></i> 60-Second Ad Launch
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Shopify & WooCommerce 1-Click Sync</h2>
        <p class="text-secondary mb-0">Import products, reviews, and images from your store instantly. Synthesize 3 UGC creatives, 3 ad angles, and a 14-day posting calendar in 1 click.</p>
      </div>
      <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modal-connect-store">
        <i class="ph ph-plugs-connected me-1"></i> Connect New Store
      </button>
    </div>

    <?php if (empty($stores)): ?>
    <!-- No Stores Banner -->
    <div class="card border-0 bg-surface-card rounded-4 p-5 text-center shadow-sm mb-4">
      <div class="mx-auto mb-3 p-3 rounded-circle bg-primary bg-opacity-10 text-primary d-inline-block">
        <i class="ph ph-storefront fs-1"></i>
      </div>
      <h4 class="fw-bold text-white mb-2">Connect Your Online Store</h4>
      <p class="text-secondary small mb-4" style="max-width: 500px; margin: 0 auto;">
        Sync your Shopify or WooCommerce catalog to automate your entire advertising and social posting workflow in seconds.
      </p>
      <div>
        <button type="button" class="btn btn-primary px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modal-connect-store">
          <i class="ph ph-plus me-1"></i> Connect Shopify / WooCommerce
        </button>
      </div>
    </div>
    <?php else: ?>

    <!-- Connected Products Grid -->
    <div class="row g-4 mb-4">
      <div class="col-12">
        <h4 class="fw-bold mb-3 d-flex align-items-center gap-2">
          <i class="ph ph-package text-primary"></i> Imported Product Catalog
        </h4>
      </div>

      <?php foreach ($products as $prod): ?>
      <div class="col-lg-4 col-md-6">
        <div class="card border-0 bg-surface-card rounded-4 overflow-hidden h-100 shadow-sm d-flex flex-column justify-content-between">
          <div>
            <div class="ratio ratio-16x9 bg-black position-relative">
              <img src="<?= BASE_URL ?>/<?= e($prod['image_url'] ?? 'assets/images/thumbs/about-five-img1.png') ?>" class="w-100 h-100 object-fit-cover">
              <span class="badge bg-black bg-opacity-75 text-white position-absolute top-0 start-0 m-2 border border-secondary border-opacity-50">
                <i class="ph ph-tag me-1"></i> <?= e($prod['store_name']) ?>
              </span>
              <span class="badge bg-primary text-black position-absolute top-0 end-0 m-2 fw-bold">
                $<?= number_format((float)$prod['price'], 2) ?>
              </span>
            </div>
            <div class="p-3">
              <div class="d-flex align-items-center gap-1 text-warning small mb-1">
                <i class="ph-fill ph-star"></i>
                <span class="fw-bold"><?= (float)$prod['rating'] ?></span>
                <span class="text-secondary">(<?= (int)$prod['reviews_count'] ?> reviews)</span>
              </div>
              <h6 class="fw-bold text-white mb-2"><?= e($prod['product_title']) ?></h6>
              <p class="text-secondary small mb-0 line-clamp-2"><?= e($prod['description']) ?></p>
            </div>
          </div>
          <div class="p-3 pt-0 d-flex flex-column gap-2">
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="generate_autopack">
              <input type="hidden" name="product_id" value="<?= $prod['id'] ?>">
              <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                <i class="ph ph-sparkle me-1"></i> 1-Click Campaign Auto-Pack
              </button>
            </form>
            <a href="<?= url('ugc-video-studio') ?>" class="btn btn-sm btn-outline-secondary w-100">
              <i class="ph ph-video-camera me-1"></i> Generate AI Video Ad
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Display Generated Auto-Pack -->
    <?php if ($latestPack): ?>
    <div class="card border-0 bg-surface-card rounded-4 p-4 mb-4 shadow-sm">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
          <i class="ph ph-lightning text-warning"></i> Generated Campaign Auto-Pack: <?= e($latestPack['product']['product_title']) ?>
        </h4>
        <div class="d-flex gap-2">
          <a href="<?= url('ugc-video-studio') ?>" class="btn btn-sm btn-outline-primary fw-bold">
            <i class="ph ph-video-camera me-1"></i> Create Video Ad
          </a>
          <a href="<?= url('post-creator') ?>" class="btn btn-sm btn-primary fw-bold">
            <i class="ph ph-calendar-plus me-1"></i> Schedule All 14 Posts
          </a>
        </div>
      </div>

      <!-- 1. 3 UGC Creatives -->
      <h6 class="fw-bold text-primary mb-2">1. 3 AI UGC Visual Assets</h6>
      <div class="row g-3 mb-4">
        <?php foreach ($latestPack['ugc_images'] as $img): ?>
        <div class="col-md-4">
          <div class="p-2 rounded-3 border border-secondary border-opacity-25 bg-dark">
            <div class="ratio ratio-16x9 rounded-2 overflow-hidden mb-2">
              <img src="<?= BASE_URL ?>/<?= e($img['url']) ?>" class="w-100 h-100 object-fit-cover">
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-white fw-semibold"><?= e($img['title']) ?></small>
              <span class="badge bg-warning text-black small fw-bold"><?= e($img['badge']) ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 2. 3 Copy Variations -->
      <h6 class="fw-bold text-primary mb-2">2. 3 Psychological Ad Angles</h6>
      <div class="row g-3 mb-4">
        <?php foreach ($latestPack['copy_variations'] as $cp): ?>
        <div class="col-md-4">
          <div class="p-3 rounded-3 border border-secondary border-opacity-25 bg-dark h-100 d-flex flex-column justify-content-between">
            <div>
              <span class="badge bg-primary-subtle text-primary mb-2"><?= e($cp['angle']) ?></span>
              <h6 class="fw-bold text-white small mb-1"><?= e($cp['headline']) ?></h6>
              <p class="text-secondary small mb-2"><?= e($cp['primary_text']) ?></p>
            </div>
            <a href="<?= url('create-ad', ['headline' => $cp['headline'], 'text' => $cp['primary_text']]) ?>" class="btn btn-sm btn-outline-primary mt-2">
              <i class="ph ph-rocket-launch me-1"></i> Launch Ad Angle
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- 3. 14-Day Calendar Feed -->
      <h6 class="fw-bold text-primary mb-2">3. 14-Day Social Posting Calendar</h6>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle small mb-0">
          <thead>
            <tr class="text-secondary">
              <th>Day</th>
              <th>Date & Time</th>
              <th>Platform</th>
              <th>Format</th>
              <th>Suggested Caption</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($latestPack['posting_calendar_14d'] as $cal): ?>
            <tr>
              <td class="fw-bold text-white"><?= e($cal['day']) ?></td>
              <td class="text-info"><?= date('M d, H:i', strtotime($cal['date'])) ?></td>
              <td class="text-capitalize"><?= e($cal['platform']) ?></td>
              <td><span class="badge bg-secondary bg-opacity-25 text-white"><?= e($cal['post_type']) ?></span></td>
              <td class="text-secondary text-truncate" style="max-width: 300px;"><?= e($cal['caption']) ?></td>
              <td class="text-end">
                <a href="<?= url('post-creator') ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                  Edit
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal: Connect Store -->
<div class="modal fade" id="modal-connect-store" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-surface-card text-white border border-secondary border-opacity-25 rounded-4">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="connect_store">
        <div class="modal-header border-secondary border-opacity-25">
          <h5 class="modal-title fw-bold">Connect Online Store</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary">Store Platform</label>
            <select name="store_type" class="form-select">
              <option value="shopify">Shopify Store</option>
              <option value="woocommerce">WooCommerce (WordPress)</option>
              <option value="custom">Custom E-Commerce Domain</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Store Name</label>
            <input type="text" name="store_name" class="form-control" placeholder="e.g. Lumina Glow Cosmetics" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary">Store URL</label>
            <input type="url" name="store_url" class="form-control" placeholder="https://yourstore.myshopify.com" required>
          </div>
        </div>
        <div class="modal-footer border-secondary border-opacity-25">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Sync & Ingest Products</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
