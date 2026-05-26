<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/profile/callsign.php';

start_session();
require_role('system_admin');

$actor_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request.';
    } else {
        $action     = $_POST['action'] ?? '';
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');

        if ($action === 'approve' && $request_id > 0) {
            $result = approve_callsign_request($request_id, $actor_id, $notes);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Callsign request approved.' : $result['error'];
        } elseif ($action === 'deny' && $request_id > 0) {
            if ($notes === '') {
                $_SESSION['_flash_error'] = 'Please provide a reason for denial.';
            } else {
                $result = deny_callsign_request($request_id, $actor_id, $notes);
                $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Callsign request denied.' : $result['error'];
            }
        }
    }
    header('Location: /admin/users/callsign-requests.php');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$pending = get_pending_callsign_requests();

$page_title = 'Callsign Requests';
$active_nav = 'admin-users';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-single">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Pending Callsign Requests</span>
            <div class="panel-actions">
                <a href="/admin/users/" class="btn btn-ghost btn-xs">← Users</a>
            </div>
        </div>
        <div class="panel-body">
            <?php if (empty($pending)): ?>
            <div class="empty-state">
                <strong>No pending requests</strong>
            </div>
            <?php else: ?>
            <?php foreach ($pending as $req): ?>
            <div style="border:1px solid var(--border-2);border-radius:var(--radius);padding:0.875rem;margin-bottom:0.75rem;">

                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:0.5rem;">
                    <a href="/admin/users/view.php?id=<?= (int)$req['user_id'] ?>"
                       style="font-weight:600;color:var(--text);">
                        <?= htmlspecialchars($req['username'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars($req['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="col-ts" style="margin-left:auto;">
                        <?= htmlspecialchars($req['created_at'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div style="margin-bottom:0.625rem;display:flex;align-items:center;gap:0.5rem;">
                    <?php if ($req['old_callsign'] !== null): ?>
                    <span style="font-weight:700;"><?= htmlspecialchars($req['old_callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span style="color:var(--text-3);">&rarr;</span>
                    <?php endif; ?>
                    <span style="font-weight:700;color:var(--accent-text);">
                        <?= htmlspecialchars($req['requested_callsign'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <?php if (!empty($req['explanation'])): ?>
                <p style="font-size:0.78rem;color:var(--text-2);margin-bottom:0.625rem;">
                    <?= htmlspecialchars($req['explanation'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>

                <form method="post" class="filter-bar">
                    <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label>Notes</label>
                    <input type="text" class="form-input" name="notes" maxlength="255"
                           placeholder="Required for denial" style="flex:1;">
                    <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">Approve</button>
                    <button type="submit" name="action" value="deny" class="btn btn-danger btn-sm"
                            onclick="return confirm('Deny this callsign request?')">Deny</button>
                </form>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
