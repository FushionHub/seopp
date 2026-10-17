<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'Social Media & Publishing Hub';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF security token.');
    } else {
        $act = $_POST['act'] ?? '';
        $postId = (int)($_POST['post_id'] ?? 0);
        $accId  = (int)($_POST['account_id'] ?? 0);

        if ($act === 'force_publish' && $postId > 0) {
            db()->update('scheduled_posts', [
                'status' => 'published',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id=?', [$postId]);
            set_flash('success', "Post #{$postId} forcefully published to social feed.");
        } elseif ($act === 'cancel_post' && $postId > 0) {
            db()->update('scheduled_posts', ['status' => 'cancelled'], 'id=?', [$postId]);
            set_flash('success', "Post #{$postId} cancelled.");
        } elseif ($act === 'delete_post' && $postId > 0) {
            db()->delete('scheduled_posts', 'id=?', [$postId]);
            set_flash('success', "Scheduled post #{$postId} removed.");
        } elseif ($act === 'disconnect_account' && $accId > 0) {
            db()->delete('connected_accounts', 'id=?', [$accId]);
            set_flash('success', "Connected social account #{$accId} unlinked.");
        }
    }
    redirect(url('admin/social-media'));
}

// KPI Metrics
$totalAccounts  = db()->count('connected_accounts');
$queuedPosts    = db()->count('scheduled_posts', "status='scheduled'");
$publishedPosts = db()->count('scheduled_posts', "status='published'");

$tab = $_GET['tab'] ?? 'posts';
$filterStatus = $_GET['status'] ?? '';
$filterPlatform = $_GET['platform'] ?? '';

// Posts query
$postWhere = '1=1';
$postParams = [];
if ($filterStatus && in_array($filterStatus, ['scheduled', 'published', 'failed', 'cancelled'])) {
    $postWhere .= " AND p.status = ?";
    $postParams[] = $filterStatus;
}
if ($filterPlatform) {
    $postWhere .= " AND p.platform = ?";
    $postParams[] = $filterPlatform;
}

$posts = db()->fetchAll(
    "SELECT p.*, u.full_name, u.email 
     FROM scheduled_posts p
     LEFT JOIN users u ON u.id = p.user_id
     WHERE {$postWhere}
     ORDER BY p.scheduled_time DESC LIMIT 50",
    $postParams
);

// Accounts query
$accounts = db()->fetchAll(
    "SELECT a.*, u.full_name, u.email 
     FROM connected_accounts a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC LIMIT 50"
);

require_once __DIR__ . '/sidebar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon blue"><i class="ph-bold ph-share-network"></i></div>
            <div>
                <div class="card-title">Connected Accounts</div>
                <div class="card-value"><?= number_format($totalAccounts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon yellow"><i class="ph-bold ph-clock"></i></div>
            <div>
                <div class="card-title">Queued Posts</div>
                <div class="card-value"><?= number_format($queuedPosts) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-card d-flex align-items-center gap-3">
            <div class="card-icon green"><i class="ph-bold ph-check-circle"></i></div>
            <div>
                <div class="card-title">Published Posts</div>
                <div class="card-value"><?= number_format($publishedPosts) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2">
        <a href="?tab=posts" class="btn-admin <?= $tab === 'posts' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-calendar-check"></i> Post Pipeline (<?= $queuedPosts + $publishedPosts ?>)
        </a>
        <a href="?tab=accounts" class="btn-admin <?= $tab === 'accounts' ? 'btn-admin-green' : 'btn-admin-outline' ?>">
            <i class="ph-bold ph-users"></i> Linked Channels (<?= $totalAccounts ?>)
        </a>
    </div>
</div>

<?php if ($tab === 'posts'): ?>
    <div class="admin-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <h6 class="text-white fw-bold mb-0">Scheduled & Published Post Pipeline</h6>
            <div class="d-flex gap-2">
                <a href="?tab=posts" class="btn-admin btn-admin-outline <?= !$filterStatus ? 'active' : '' ?>">All</a>
                <a href="?tab=posts&status=scheduled" class="btn-admin btn-admin-outline <?= $filterStatus === 'scheduled' ? 'active' : '' ?>">Scheduled</a>
                <a href="?tab=posts&status=published" class="btn-admin btn-admin-outline <?= $filterStatus === 'published' ? 'active' : '' ?>">Published</a>
                <a href="?tab=posts&status=failed" class="btn-admin btn-admin-outline <?= $filterStatus === 'failed' ? 'active' : '' ?>">Failed</a>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <p class="text-muted py-4 text-center">No posts found matching filter criteria.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Platform & Type</th>
                            <th>Caption & Content</th>
                            <th>Author</th>
                            <th>Schedule Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-white text-capitalize">
                                        <i class="ph-bold ph-share"></i> <?= htmlspecialchars($p['platform'] ?? 'social') ?>
                                    </div>
                                    <span class="badge bg-dark border border-secondary text-light small">
                                        <?= strtoupper($p['media_type'] ?? 'TEXT') ?>
                                    </span>
                                </td>
                                <td style="max-width: 320px;">
                                    <div class="small text-white text-truncate" title="<?= htmlspecialchars($p['caption'] ?? '') ?>">
                                        <?= htmlspecialchars(substr($p['caption'] ?? '', 0, 90)) ?>...
                                    </div>
                                    <?php if (!empty($p['media_url'])): ?>
                                        <div class="small text-info mt-1">
                                            <i class="ph-bold ph-image"></i> Attachment attached
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($p['full_name'] ?? 'System') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($p['email'] ?? '') ?></div>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y H:i', strtotime($p['scheduled_time'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $stCls = [
                                        'published' => 'active',
                                        'scheduled' => 'in_progress',
                                        'failed' => 'rejected',
                                        'cancelled' => 'inactive'
                                    ];
                                    $cls = $stCls[$p['status']] ?? 'pending';
                                    ?>
                                    <span class="badge-status <?= $cls ?>"><?= ucfirst($p['status']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($p['status'] === 'scheduled'): ?>
                                            <form method="POST" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="force_publish">
                                                <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-admin btn-admin-green" title="Force Publish Now">
                                                    <i class="ph-bold ph-paper-plane-right"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this scheduled post?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn-admin btn-admin-red" title="Delete">
                                                <i class="ph-bold ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'accounts'): ?>
    <div class="admin-card">
        <h6 class="text-white fw-bold mb-3">Connected User Accounts</h6>
        <?php if (empty($accounts)): ?>
            <p class="text-muted py-4 text-center">No connected social accounts found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Account Name</th>
                            <th>Account Owner</th>
                            <th>Platform UID</th>
                            <th>Connected At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $a): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark border border-primary text-primary text-uppercase">
                                        <?= htmlspecialchars($a['platform']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-white fw-semibold"><?= htmlspecialchars($a['account_name'] ?? 'Social Profile') ?></div>
                                </td>
                                <td>
                                    <div class="text-white small"><?= htmlspecialchars($a['full_name'] ?? 'User') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($a['email'] ?? '') ?></div>
                                </td>
                                <td class="text-muted font-monospace small">
                                    <?= htmlspecialchars($a['platform_user_id'] ?? 'N/A') ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($a['created_at'])) ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke and unlink this social account?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="act" value="disconnect_account">
                                        <input type="hidden" name="account_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn-admin btn-admin-red" title="Disconnect">
                                            <i class="ph-bold ph-plugs-connected"></i> Unlink
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
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
