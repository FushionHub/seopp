<?php
declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/modules/auto_buyer_engine.php';

if (!is_logged_in()) {
    set_flash('error', 'Please log in to access the AI Media Buyer.');
    redirect(url('login'));
}

$pageTitle = 'Autonomous AI Media Buyer & Anti-Burn Kill-Switch — Adsbooster.net';
$user = current_user();
$userId = current_user_id();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid security token.');
        redirect(url('ai-media-buyer'));
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_rule') {
        AutoBuyerEngine::saveRule($userId, $_POST);
        set_flash('success', 'AI Media Buyer rule saved successfully.');
        redirect(url('ai-media-buyer'));
    }

    if ($action === 'delete_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        AutoBuyerEngine::deleteRule($userId, $ruleId);
        set_flash('success', 'Rule deleted successfully.');
        redirect(url('ai-media-buyer'));
    }

    if ($action === 'load_default_template') {
        $templates = AutoBuyerEngine::getDefaultRulesTemplate();
        foreach ($templates as $tmpl) {
            AutoBuyerEngine::saveRule($userId, $tmpl);
        }
        set_flash('success', 'Standard Anti-Burn & Growth rules loaded into your account!');
        redirect(url('ai-media-buyer'));
    }

    if ($action === 'run_now') {
        $results = AutoBuyerEngine::evaluateAllRules($userId);
        $cnt = count($results);
        set_flash('success', "Evaluation completed! Triggered {$cnt} automated protection actions.");
        redirect(url('ai-media-buyer'));
    }
}

// Fetch active user campaigns
$campaigns = db()->fetchAll("SELECT * FROM ad_campaigns WHERE user_id = ? ORDER BY id DESC", [$userId]);
$rules = db()->fetchAll("SELECT * FROM ai_media_buyer_rules WHERE user_id = ? ORDER BY id DESC", [$userId]);
$logs = db()->fetchAll("SELECT * FROM ai_media_buyer_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$userId]);

require_once __DIR__ . '/includes/app_header.php';
?>

<div class="content-body">
  <div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-shield-warning me-1"></i> Anti-Burn Shield Active
          </span>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
            <i class="ph ph-cpu me-1"></i> Autonomous 24/7 Agent
          </span>
        </div>
        <h2 class="h3 fw-bold mb-1">Autonomous AI Media Buyer & Kill-Switch</h2>
        <p class="text-secondary mb-0">Protects your ad spend while you sleep. Auto-pauses fatigue ads, scales profitable winners (+20%), and alerts in real-time.</p>
      </div>
      <div class="d-flex gap-2">
        <form method="POST" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="run_now">
          <button type="submit" class="btn btn-outline-primary">
            <i class="ph ph-play-circle me-1"></i> Evaluate Rules Now
          </button>
        </form>
        <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modal-new-rule">
          <i class="ph ph-plus-circle me-1"></i> Create Protection Rule
        </button>
      </div>
    </div>

    <!-- Live Telemetry KPI Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Active Rules</span>
            <i class="ph ph-sliders text-primary fs-5"></i>
          </div>
          <h3 class="fw-bold text-white mb-0"><?= count($rules) ?></h3>
          <small class="text-success"><i class="ph ph-check-circle me-1"></i> Monitoring 24/7</small>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Monitored Campaigns</span>
            <i class="ph ph-megaphone text-info fs-5"></i>
          </div>
          <h3 class="fw-bold text-white mb-0"><?= count($campaigns) ?></h3>
          <small class="text-secondary">Across Meta, TikTok, Google</small>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Auto-Interventions</span>
            <i class="ph ph-lightning text-warning fs-5"></i>
          </div>
          <h3 class="fw-bold text-white mb-0"><?= count($logs) ?></h3>
          <small class="text-warning">Budget saved & scaled</small>
        </div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card border-0 bg-surface-card rounded-4 p-3 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small fw-semibold">Anti-Burn Status</span>
            <i class="ph ph-shield-check text-success fs-5"></i>
          </div>
          <h3 class="fw-bold text-success mb-0">ARMED</h3>
          <small class="text-secondary">Latency: &lt; 60s per check</small>
        </div>
      </div>
    </div>

    <!-- Active Rules & Template Quick-Load -->
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="card border-0 bg-surface-card rounded-4 p-4 mb-4 shadow-sm">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Configured Anti-Burn & Auto-Pilot Rules</h5>
            <?php if (empty($rules)): ?>
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="load_default_template">
              <button type="submit" class="btn btn-sm btn-outline-warning">
                <i class="ph ph-magic-wand me-1"></i> 1-Click Load Best Practice Rules
              </button>
            </form>
            <?php endif; ?>
          </div>

          <?php if (empty($rules)): ?>
          <div class="text-center py-5 border border-dashed rounded-4 border-secondary border-opacity-25">
            <i class="ph ph-shield-check fs-1 text-secondary mb-2 d-block"></i>
            <h6 class="fw-semibold text-white">No custom rules configured yet</h6>
            <p class="text-secondary small mb-3">Load standard rules or create your custom CPA/ROAS kill switches.</p>
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="load_default_template">
              <button type="submit" class="btn btn-primary fw-bold">
                <i class="ph ph-sparkle me-1"></i> Load Default Anti-Burn Rules
              </button>
            </form>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
              <thead>
                <tr class="text-secondary small">
                  <th>Rule Name</th>
                  <th>Trigger Condition</th>
                  <th>Autonomous Action</th>
                  <th>Status</th>
                  <th>Last Triggered</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rules as $r): ?>
                <tr>
                  <td class="fw-semibold text-white"><?= e($r['rule_name']) ?></td>
                  <td>
                    <span class="badge bg-secondary bg-opacity-25 text-white font-monospace">
                      <?= strtoupper(e($r['condition_metric'])) ?> <?= e($r['condition_operator']) ?> <?= (float)$r['condition_value'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-primary-subtle text-primary">
                      <?= ucwords(str_replace('_', ' ', e($r['action_type']))) ?>
                      <?= (float)$r['action_value'] > 0 ? " (" . (float)$r['action_value'] . "%)" : "" ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= $r['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                      <?= $r['is_active'] ? 'Active' : 'Disabled' ?>
                    </span>
                  </td>
                  <td class="small text-secondary">
                    <?= !empty($r['last_triggered_at']) ? date('M d, H:i', strtotime($r['last_triggered_at'])) : 'Never' ?>
                  </td>
                  <td class="text-end">
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_rule">
                      <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2">
                        <i class="ph ph-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- Campaign Health & Fatigue Radar -->
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm">
          <h5 class="fw-bold mb-3">Live Campaign Fatigue Radar</h5>
          <?php if (empty($campaigns)): ?>
          <p class="text-secondary small mb-0">No active campaigns found. <a href="<?= url('create-ad') ?>" class="text-primary">Launch a campaign</a> to begin monitoring.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
              <thead>
                <tr class="text-secondary small">
                  <th>Campaign Name</th>
                  <th>Budget</th>
                  <th>Est. CTR / CPA</th>
                  <th>Fatigue Risk</th>
                  <th>Quick Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($campaigns as $camp): 
                  $fatigue = AutoBuyerEngine::predictFatigue((int)$camp['id']);
                  $metrics = AutoBuyerEngine::getCampaignCalculatedMetrics((int)$camp['id']);
                ?>
                <tr>
                  <td>
                    <span class="fw-semibold text-white d-block"><?= e($camp['campaign_name']) ?></span>
                    <small class="text-secondary text-capitalize"><?= e($camp['campaign_type'] ?? 'Meta') ?></small>
                  </td>
                  <td class="fw-bold text-white">$<?= number_format((float)$camp['budget_amount'], 2) ?></td>
                  <td class="small">
                    <span class="text-info"><?= $metrics['ctr'] ?>% CTR</span> &bull; 
                    <span class="text-warning">$<?= $metrics['cpa'] ?> CPA</span>
                  </td>
                  <td>
                    <span class="badge <?= $fatigue['badge_class'] ?> rounded-pill">
                      <?= $fatigue['risk_level'] ?> (<?= $fatigue['fatigue_score'] ?>%)
                    </span>
                  </td>
                  <td>
                    <a href="<?= url('ugc-video-studio') ?>" class="btn btn-sm btn-outline-primary py-1 px-2">
                      <i class="ph ph-arrows-clockwise me-1"></i> AI Refresh
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right Column: Auto-Buyer Logs -->
      <div class="col-lg-4">
        <div class="card border-0 bg-surface-card rounded-4 p-4 shadow-sm mb-4">
          <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="ph ph-clock-counter-clockwise text-primary"></i> Real-Time Interventions
          </h5>
          <?php if (empty($logs)): ?>
          <div class="text-center py-4 text-secondary small">
            <i class="ph ph-check-circle fs-2 text-success mb-2 d-block"></i>
            No protective actions needed yet. All campaigns operating normally.
          </div>
          <?php else: ?>
          <div class="timeline-list">
            <?php foreach ($logs as $lg): ?>
            <div class="border-start border-primary ps-3 pb-3 position-relative">
              <span class="position-absolute top-0 start-0 translate-middle p-1 bg-primary rounded-circle" style="width: 8px; height: 8px;"></span>
              <div class="small fw-semibold text-white mb-1"><?= ucwords(str_replace('_', ' ', e($lg['action_taken']))) ?></div>
              <p class="text-secondary small mb-1"><?= e($lg['details']) ?></p>
              <span class="text-secondary" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($lg['created_at'])) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: New Rule -->
<div class="modal fade" id="modal-new-rule" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-surface-card text-white border border-secondary border-opacity-25 rounded-4">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_rule">
        <div class="modal-header border-secondary border-opacity-25">
          <h5 class="modal-title fw-bold">Create Autonomous Rule</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary">Rule Name</label>
            <input type="text" name="rule_name" class="form-control" placeholder="e.g. Pause ad if CPA exceeds $35" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small text-secondary">Metric</label>
              <select name="condition_metric" class="form-select">
                <option value="cpa">Cost Per Acquisition (CPA)</option>
                <option value="ctr">Click-Through Rate (CTR %)</option>
                <option value="roas">Return on Ad Spend (ROAS)</option>
                <option value="spend_without_conv">Spend Without Conversion ($)</option>
                <option value="frequency">Ad Frequency</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small text-secondary">Operator</label>
              <select name="condition_operator" class="form-select">
                <option value=">">&gt;</option>
                <option value=">=">&gt;=</option>
                <option value="<">&lt;</option>
                <option value="<=">&lt;=</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small text-secondary">Threshold</label>
              <input type="number" step="0.01" name="condition_value" class="form-control" placeholder="25.00" required>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label small text-secondary">Autonomous Action</label>
              <select name="action_type" class="form-select">
                <option value="pause_campaign">🚨 Emergency Pause Campaign</option>
                <option value="scale_budget_pct">⚡ Auto-Scale Budget (+%)</option>
                <option value="reduce_budget_pct">📉 Reduce Budget (-%)</option>
                <option value="trigger_creative_refresh">🔄 Trigger AI Creative Refresh</option>
                <option value="notify_only">📢 Email & Alert Only</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-secondary">Adjustment %</label>
              <input type="number" step="1" name="action_value" class="form-control" placeholder="20" value="0">
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary border-opacity-25">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Arm Rule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
