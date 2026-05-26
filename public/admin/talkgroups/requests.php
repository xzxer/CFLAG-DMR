<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_role('system_admin');

$admin_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action     = $_POST['action'] ?? '';
        $request_id = (int) ($_POST['request_id'] ?? 0);

        if ($action === 'approve' && $request_id > 0) {
            $final_tgid = validate_tgid($_POST['final_tgid'] ?? '');
            if ($final_tgid === false) {
                $_SESSION['_flash_error'] = 'Invalid final TGID.';
            } else {
                $tier   = $_POST['tier'] ?? 'admin';
                $result = approve_talkgroup_request($request_id, $admin_id, $final_tgid, $tier);
                $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Request approved and talkgroup created.' : $result['error'];
            }

        } elseif ($action === 'deny' && $request_id > 0) {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                $_SESSION['_flash_error'] = 'A denial reason is required.';
            } else {
                deny_talkgroup_request($request_id, $admin_id, $reason);
                $_SESSION['_flash_ok'] = 'Request denied.';
            }

        } elseif ($action === 'approve_upgrade' && $request_id > 0) {
            if (approve_ownership_upgrade($request_id, $admin_id)) {
                $_SESSION['_flash_ok'] = 'Ownership upgraded to user_full.';
            } else {
                $_SESSION['_flash_error'] = 'Upgrade request not found.';
            }

        } elseif ($action === 'deny_upgrade' && $request_id > 0) {
            deny_ownership_upgrade($request_id, $admin_id);
            $_SESSION['_flash_ok'] = 'Upgrade request denied.';
        }
    }
    header('Location: /admin/talkgroups/requests.php');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$pending_requests = get_pending_talkgroup_requests();
$pending_upgrades = get_pending_ownership_upgrades();

$page_title = 'Talkgroup Requests';
$active_nav = 'admin-talkgroups';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-single">

    <div class="panel" style="align-self:start;">
        <div class="panel-header">
            <span class="panel-title">New Talkgroup Requests</span>
            <span class="panel-subtitle"><?= count($pending_requests) ?> pending</span>
            <div class="panel-actions">
                <a href="/admin/talkgroups/" class="btn btn-ghost btn-xs">← Talkgroups</a>
            </div>
        </div>
        <div class="panel-body pad-none">
            <?php if (empty($pending_requests)): ?>
            <div class="empty-state"><strong>No pending talkgroup requests</strong></div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Proposed TGID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $r): ?>
                    <tr>
                        <td>
                            <a href="/admin/users/view.php?id=<?= (int)$r['user_id'] ?>" style="font-weight:600;color:var(--text);">
                                <?= htmlspecialchars($r['display_name'] ?: $r['username'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <div style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td class="col-mono" style="color:var(--accent-text);font-weight:700;">
                            <?= htmlspecialchars((string)$r['proposed_tgid'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td style="font-weight:600;"><?= htmlspecialchars($r['proposed_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge badge-gray"><?= htmlspecialchars(ucfirst($r['proposed_type']), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="col-ts" style="max-width:180px;white-space:normal;">
                            <?= htmlspecialchars($r['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="col-ts"><?= htmlspecialchars(substr($r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-actions">
                            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                <form method="post" style="display:flex;gap:0.375rem;flex-wrap:wrap;align-items:flex-end;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="text" class="form-input" name="final_tgid"
                                           value="<?= htmlspecialchars((string)$r['proposed_tgid'], ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="TGID" maxlength="8" required style="width:72px;">
                                    <select class="form-select" name="tier" style="width:110px;">
                                        <option value="user_partial">user_partial</option>
                                        <option value="user_full">user_full</option>
                                        <option value="admin" selected>admin</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-xs">Approve</button>
                                </form>
                                <form method="post" style="display:flex;gap:0.375rem;align-items:flex-end;">
                                    <input type="hidden" name="action" value="deny">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="text" class="form-input" name="reason"
                                           placeholder="Reason (required)" required style="width:160px;">
                                    <button type="submit" class="btn btn-danger btn-xs">Deny</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" style="align-self:start;margin-top:1rem;">
        <div class="panel-header">
            <span class="panel-title">Ownership Upgrade Requests</span>
            <span class="panel-subtitle"><?= count($pending_upgrades) ?> pending</span>
        </div>
        <div class="panel-body pad-none">
            <?php if (empty($pending_upgrades)): ?>
            <div class="empty-state"><strong>No pending upgrade requests</strong></div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Talkgroup</th>
                        <th>TGID</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_upgrades as $u): ?>
                    <tr>
                        <td>
                            <a href="/admin/users/view.php?id=<?= (int)$u['user_id'] ?>" style="font-weight:600;color:var(--text);">
                                <?= htmlspecialchars($u['display_name'] ?: $u['username'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <div style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td style="font-weight:600;"><?= htmlspecialchars($u['talkgroup_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-mono" style="color:var(--accent-text);font-weight:700;">
                            <?= htmlspecialchars((string)$u['tgid'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="col-ts" style="max-width:200px;white-space:normal;">
                            <?= htmlspecialchars($u['reason'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="col-ts"><?= htmlspecialchars(substr($u['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-actions">
                            <div class="action-row" style="justify-content:flex-end;">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="approve_upgrade">
                                    <input type="hidden" name="request_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-primary btn-xs">Approve</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="deny_upgrade">
                                    <input type="hidden" name="request_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">Deny</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once $root . '/app/views/footer.php'; ?>
