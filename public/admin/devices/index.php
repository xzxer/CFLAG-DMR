<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/devices/manager.php';

start_session();
require_role('system_admin');

$admin_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action    = $_POST['action'] ?? '';
        $device_id = (int) ($_POST['device_id'] ?? 0);

        if ($action === 'approve' && $device_id > 0) {
            $result = approve_device($device_id, $admin_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Device approved.' : $result['error'];
        } elseif ($action === 'deny' && $device_id > 0) {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                $_SESSION['_flash_error'] = 'A denial reason is required.';
            } else {
                deny_device($device_id, $admin_id, $reason);
                $_SESSION['_flash_ok'] = 'Device denied.';
            }
        }
    }
    header('Location: /admin/devices/');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$pending = get_pending_devices();

$page_title = 'Device Approvals';
$active_nav = 'admin-devices';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-single">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Pending Device Registrations</span>
            <span class="panel-subtitle"><?= count($pending) ?> pending</span>
        </div>
        <div class="panel-body">
            <?php if (empty($pending)): ?>
            <div class="empty-state"><strong>No pending registrations</strong></div>
            <?php else: ?>
            <?php foreach ($pending as $d): ?>
            <div style="border:1px solid var(--border-2);border-radius:var(--radius);padding:0.875rem;margin-bottom:0.75rem;">
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:0.5rem;">
                    <a href="/admin/users/view.php?id=<?= (int)$d['user_id'] ?>"
                       style="font-weight:600;color:var(--text);">
                        <?= htmlspecialchars($d['display_name'] ?: $d['username'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars($d['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="col-ts" style="margin-left:auto;">
                        <?= htmlspecialchars(substr($d['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.625rem;">
                    <span style="font-weight:700;"><?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge badge-gray"><?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($d['hardware_desc']): ?>
                    <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars($d['hardware_desc'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                    <form method="post">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                    </form>
                    <form method="post" style="display:flex;gap:0.375rem;align-items:flex-end;">
                        <input type="hidden" name="action" value="deny">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text" class="form-input" name="reason"
                               placeholder="Denial reason (required)" required style="width:220px;">
                        <button type="submit" class="btn btn-danger btn-sm">Deny</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
