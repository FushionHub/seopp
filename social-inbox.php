<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/social_inbox_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the Omnichannel Social Inbox.');
    redirect(url('login'));
}

$pageTitle = 'Omnichannel Social Inbox & AI Sales Auto-Closer — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

// Handle AJAX AI Auto-Closer trigger
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('social-inbox'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'auto_close') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        try {
            $res = SocialInboxEngine::triggerAutoCloser($userId, $msgId);
            set_flash('success', "AI Sales Auto-Closer replied to customer with promo link and 15% discount code!");
        } catch (Exception $e) {
            set_flash('error', $e->getMessage());
        }
        redirect(url('social-inbox'));
    }
}

$messages = SocialInboxEngine::getInboxMessages($userId);
$unreadCount = count(array_filter($messages, fn($m) => $m['status'] === 'unread'));

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-chats-circle me-1"></i> Unified Omnichannel Hub
          </span>
          <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-lightning me-1"></i> AI Instant Sales Closer (&lt; 10s)
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Omnichannel Social Inbox & AI Closer</h2>
        <p class="text-secondary mb-0">Manage comments, DMs, and WhatsApp inquiries across Meta, TikTok, and Instagram in one unified dashboard. Convert high-intent leads instantly with AI.</p>
      </div>
    </div>

    <!-- Inbox UI Container -->
    <div class="card border-0 bg-surface-card rounded-4 p-0 shadow-sm overflow-hidden mb-4">
      <div class="row g-0">
        <!-- Left Column: Channel Filter Tabs -->
        <div class="col-lg-4 border-end border-secondary border-opacity-25 p-3">
          <div class="d-flex justify-content-between align-items-center mb-3 px-2">
            <h5 class="fw-bold mb-0 text-white">Conversations</h5>
            <span class="badge bg-primary text-black fw-bold"><?= $unreadCount ?> Unread</span>
          </div>

          <!-- Channel Filter Pills -->
          <div class="d-flex gap-1 mb-3 overflow-x-auto pb-1">
            <button class="btn btn-sm btn-primary rounded-pill px-3 py-1">All</button>
            <button class="btn btn-sm btn-dark rounded-pill px-3 py-1 text-secondary">Instagram</button>
            <button class="btn btn-sm btn-dark rounded-pill px-3 py-1 text-secondary">TikTok</button>
            <button class="btn btn-sm btn-dark rounded-pill px-3 py-1 text-secondary">WhatsApp</button>
          </div>

          <!-- Message List -->
          <div class="list-group list-group-flush gap-2">
            <?php foreach ($messages as $idx => $m): ?>
            <div class="p-3 rounded-3 border border-secondary border-opacity-25 bg-dark bg-opacity-50 <?= $idx === 0 ? 'border-primary' : '' ?>">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-secondary bg-opacity-25 text-capitalize text-info small">
                    <?= e($m['platform']) ?>
                  </span>
                  <?php if ($m['sentiment'] === 'high_intent_buy'): ?>
                  <span class="badge bg-danger-subtle text-danger small">🔥 High Intent</span>
                  <?php endif; ?>
                </div>
                <small class="text-secondary"><?= date('H:i', strtotime($m['received_at'])) ?></small>
              </div>
              <h6 class="fw-bold text-white small mb-1"><?= e($m['sender_name']) ?></h6>
              <p class="text-secondary small mb-2 text-truncate"><?= e($m['message_text']) ?></p>
              
              <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-25">
                <span class="badge <?= $m['status'] === 'replied' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                  <?= $m['status'] === 'replied' ? '✓ AI Auto-Closed' : 'Pending Response' ?>
                </span>
                <?php if ($m['status'] !== 'replied'): ?>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="auto_close">
                  <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-primary py-1 px-2 fw-bold">
                    <i class="ph ph-magic-wand me-1"></i> AI Auto-Close
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Right Column: Conversation Detail & Reply View -->
        <div class="col-lg-8 p-4 d-flex flex-column justify-content-between">
          <div>
            <!-- Active Conversation Header -->
            <div class="d-flex justify-content-between align-items-center pb-3 border-bottom border-secondary border-opacity-25 mb-4">
              <div class="d-flex align-items-center gap-3">
                <img src="<?= BASE_URL ?>/<?= e($messages[0]['sender_avatar'] ?? 'assets/images/thumbs/team-details-img3.png') ?>" class="rounded-circle object-fit-cover" width="48" height="48">
                <div>
                  <h5 class="fw-bold text-white mb-0"><?= e($messages[0]['sender_name']) ?></h5>
                  <span class="badge bg-secondary bg-opacity-25 text-capitalize text-info small">
                    <?= e($messages[0]['platform']) ?> &bull; <?= e($messages[0]['message_type']) ?>
                  </span>
                </div>
              </div>
              <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold">
                🔥 High Purchase Intent Detected
              </span>
            </div>

            <!-- Chat Bubble Flow -->
            <div class="chat-flow mb-4">
              <!-- Customer Message Bubble -->
              <div class="d-flex gap-3 mb-3">
                <img src="<?= BASE_URL ?>/<?= e($messages[0]['sender_avatar'] ?? 'assets/images/thumbs/team-details-img3.png') ?>" class="rounded-circle object-fit-cover" width="36" height="36">
                <div class="p-3 rounded-4 bg-dark border border-secondary border-opacity-25 text-white" style="max-width: 80%;">
                  <p class="mb-0 small"><?= e($messages[0]['message_text']) ?></p>
                  <small class="text-secondary d-block mt-1" style="font-size: 0.7rem;"><?= date('M d, H:i', strtotime($messages[0]['received_at'])) ?></small>
                </div>
              </div>

              <!-- AI Auto-Reply Bubble (if replied) -->
              <?php if (!empty($messages[0]['auto_reply_text'])): ?>
              <div class="d-flex gap-3 mb-3 justify-content-end">
                <div class="p-3 rounded-4 bg-primary bg-opacity-15 border border-primary border-opacity-50 text-white" style="max-width: 80%;">
                  <div class="d-flex align-items-center gap-1 mb-1">
                    <i class="ph ph-sparkle text-primary small"></i>
                    <strong class="text-primary small">Adsbooster AI Auto-Closer:</strong>
                  </div>
                  <p class="mb-0 small"><?= e($messages[0]['auto_reply_text']) ?></p>
                  <small class="text-secondary d-block mt-1 text-end" style="font-size: 0.7rem;">Sent in 4.2s &bull; Promo FLASH15 attached</small>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Fast Reply Input Bar -->
          <div class="p-3 rounded-3 bg-dark border border-secondary border-opacity-25">
            <div class="d-flex gap-2 mb-2">
              <span class="badge bg-secondary bg-opacity-25 text-white small cursor-pointer" onclick="document.getElementById('inp-reply').value = 'Hey! Use promo code FLASH15 for 15% off today: <?= url('pricing-plan') ?>';">
                ⚡ Send Discount Code
              </span>
              <span class="badge bg-secondary bg-opacity-25 text-white small cursor-pointer" onclick="document.getElementById('inp-reply').value = 'Yes! We offer 100% free express worldwide shipping.';">
                📦 Free Shipping Info
              </span>
            </div>
            <div class="input-group">
              <input type="text" id="inp-reply" class="form-control bg-dark text-white border-secondary border-opacity-25" placeholder="Type a response or trigger 1-Click AI Auto-Closer...">
              <button class="btn btn-primary fw-bold" onclick="alert('Message sent to customer!');">
                <i class="ph ph-paper-plane-tilt me-1"></i> Send Reply
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
