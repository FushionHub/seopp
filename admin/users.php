<?php declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../config/site_setting_and_config.php';

$pageTitle = 'User Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Invalid CSRF token.');
    } else {
        $act   = $_POST['act'] ?? '';
        $uId   = (int)($_POST['user_id'] ?? 0);

        if ($uId > 0) {
            $user = db()->fetchOne("SELECT * FROM users WHERE id=?", [$uId]);
            if (!$user) {
                set_flash('error', 'User not found.');
            } else {
                switch ($act) {
                    case 'topup':
                        $amount = (float)($_POST['amount'] ?? 0);
                        if ($amount > 0) {
                            db()->update('users', ['ad_credits' => $user['ad_credits'] + $amount], 'id=?', [$uId]);
                            set_flash('success', "Added {$amount} credits to user #{$uId}.");
                        }
                        break;
                    case 'suspend':
                        db()->update('users', ['status' => 'suspended'], 'id=?', [$uId]);
                        set_flash('success', "User #{$uId} suspended.");
                        break;
                    case 'activate':
                        db()->update('users', ['status' => 'active'], 'id=?', [$uId]);
                        set_flash('success', "User #{$uId} activated.");
                        break;
                    case 'verify_seller':
                        $newVal = $user['is_verified_seller'] ? 0 : 1;
                        db()->update('users', ['is_verified_seller' => $newVal], 'id=?', [$uId]);
                        set_flash('success', "Seller verification " . ($newVal ? 'enabled' : 'disabled') . " for user #{$uId}.");
                        break;
                    case 'upgrade_plan':
                        $planId = (int)($_POST['plan_id'] ?? 0);
                        db()->update('users', ['plan_id' => $planId > 0 ? $planId : null], 'id=?', [$uId]);
                        set_flash('success', "Plan updated for user #{$uId}.");
                        break;
                }
            }
        }
    }
    redirect(BASE_URL . '/admin/users.php');
}

$search = trim($_GET['q'] ?? '');
$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$users = db()->fetchAll(
    "SELECT u.*, p.name as plan_name FROM users u 
     LEFT JOIN pricing_plans p ON p.id = u.plan_id 
     WHERE {$where} ORDER BY u.created_at DESC LIMIT 100",
    $params
);
$plans = db()->fetchAll("SELECT id, name FROM pricing_plans WHERE status='active' ORDER BY name");

require_once __DIR__ . '/sidebar.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <form method="GET" class="d-flex gap-2" style="flex:1;max-width:400px;">
        <input type="text" name="q" class="form-control-admin flex-grow-1" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-admin btn-admin-green"><i class="ph-bold ph-magnifying-glass"></i> Search</button>
        <?php if ($search): ?>
            <a href="?" class="btn-admin btn-admin-outline">Clear</a>
        <?php endif; ?>
    </form>
    <div class="text-muted" style="font-size:0.85rem;"><?= count($users) ?> user(s) found</div>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr><th>User</th><th>Role</th><th>Credits</th><th>Plan</th><th>Seller</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar"><?= strtoupper(substr($u['full_name']??'A',0,1)) ?></div>
                            <div>
                                <div class="text-white fw-medium"><?= htmlspecialchars($u['full_name'] ?? 'N/A') ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-capitalize"><?= $u['role'] ?></td>
                    <td class="text-white"><?= number_format((float)$u['ad_credits']) ?></td>
                    <td><?= htmlspecialchars($u['plan_name'] ?? 'Free') ?></td>
                    <td>
                        <?php if ($u['is_verified_seller']): ?>
                            <span class="badge-status active">Verified</span>
                        <?php else: ?>
                            <span class="badge-status inactive">No</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status <?= $u['status'] ?>"><?= $u['status'] ?></span></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <button class="btn-admin btn-admin-outline" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['id'] ?>">
                            <i class="ph-bold ph-gear"></i>
                        </button>
                    </td>
                </tr>

                <div class="modal fade" id="userModal<?= $u['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content" style="background:var(--admin-card);border:1px solid var(--admin-border);">
                            <div class="modal-header" style="border-bottom:1px solid var(--admin-border);">
                                <h6 class="text-white fw-bold">Manage: <?= htmlspecialchars($u['full_name'] ?? $u['email']) ?></h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-label-admin">Top Up Ad Credits</div>
                                        <form method="POST" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="topup">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="number" name="amount" class="form-control-admin flex-grow-1" placeholder="Amount" min="1" required>
                                            <button type="submit" class="btn-admin btn-admin-green">Add</button>
                                        </form>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-label-admin">Upgrade Plan</div>
                                        <form method="POST" class="d-flex gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="upgrade_plan">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="plan_id" class="form-select-admin flex-grow-1">
                                                <option value="0">Free (No Plan)</option>
                                                <?php foreach ($plans as $p): ?>
                                                    <option value="<?= $p['id'] ?>" <?= (int)$u['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-admin btn-admin-blue">Update</button>
                                        </form>
                                    </div>
                                    <div class="col-12 d-flex gap-2 flex-wrap">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <form method="POST" onsubmit="return confirm('Suspend this user?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="suspend">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn-admin btn-admin-red"><i class="ph-bold ph-lock"></i> Suspend</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="act" value="activate">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn-admin btn-admin-green"><i class="ph-bold ph-check"></i> Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="act" value="verify_seller">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button class="btn-admin btn-admin-yellow">
                                                <i class="ph-bold ph-seal-check"></i>
                                                <?= $u['is_verified_seller'] ? 'Unverify Seller' : 'Verify Seller' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
