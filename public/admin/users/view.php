<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/auth/roles.php';
require_once dirname(__DIR__, 3) . '/app/email/mailer.php';

start_session();
require_role('admin');

$db        = get_db();
$actor_id  = (int) $_SESSION['user_id'];
$user_id   = (int) ($_GET['id'] ?? 0);

if ($user_id === 0) {
    redirect('/admin/users/');
}

$stmt = $db->prepare(
    'SELECT id, username, callsign, email, display_name, dmr_id,
            moderation_state, mute_expires_at, tier, last_login_at, created_at,
            email_verified_at
     FROM users WHERE id = ?'
);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user === false) {
    http_response_code(404);
    exit('User not found.');
}

$flash_error   = '';
$flash_success = '';
$is_system_admin = user_has_role($actor_id, 'system_admin');

/* ── POST handler ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'assign_role': {
                    $role_name = trim($_POST['role_name'] ?? '');
                    if ($role_name === '') {
                        throw new \RuntimeException('No role selected.');
                    }
                    assign_role($actor_id, $user_id, $role_name);
                    $flash_success = 'Role assigned successfully.';
                    break;
                }
                case 'revoke_role': {
                    $role_name = trim($_POST['role_name'] ?? '');
                    if ($role_name === '') {
                        throw new \RuntimeException('No role specified.');
                    }
                    revoke_role($actor_id, $user_id, $role_name);
                    $flash_success = 'Role revoked successfully.';
                    break;
                }
                case 'mute_network': {
                    if (!user_has_role($actor_id, 'moderator')) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $duration = (int) ($_POST['duration_hours'] ?? 0);
                    $reason   = trim($_POST['reason'] ?? '');
                    if ($duration <= 0 || !in_array($duration, [1, 3, 6, 24], true)) {
                        throw new \RuntimeException('Invalid duration.');
                    }
                    if ($reason === '') {
                        throw new \RuntimeException('Reason is required.');
                    }
                    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
                    $db->prepare(
                        'UPDATE users SET moderation_state = "muted_on_network",
                                          mute_expires_at = ?
                         WHERE id = ?'
                    )->execute([$expires_at, $user_id]);
                    log_mod_action($actor_id, $user_id, 'muted_on_network', $reason, $duration, $expires_at);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', [
                            'target_user_id' => $user_id,
                            'new_state'      => 'muted_on_network',
                            'dmr_id'         => (int) $user['dmr_id'],
                        ]);
                    }
                    $flash_success = 'User muted on network.';
                    break;
                }
                case 'lift_mute': {
                    if (!user_has_role($actor_id, 'moderator')) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $reason = trim($_POST['reason'] ?? 'Mute lifted by moderator.');
                    $db->prepare(
                        'UPDATE users SET moderation_state = "active", mute_expires_at = NULL WHERE id = ?'
                    )->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'activated', $reason);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', [
                            'target_user_id' => $user_id,
                            'new_state'      => 'active',
                            'dmr_id'         => (int) $user['dmr_id'],
                        ]);
                    }
                    $flash_success = 'Network mute lifted.';
                    break;
                }
                case 'suspend': {
                    if (!user_has_role($actor_id, 'admin')) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $reason = trim($_POST['reason'] ?? '');
                    if ($reason === '') {
                        throw new \RuntimeException('Reason is required.');
                    }
                    $db->prepare(
                        'UPDATE users SET moderation_state = "suspended", mute_expires_at = NULL WHERE id = ?'
                    )->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'suspended', $reason);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', [
                            'target_user_id' => $user_id,
                            'new_state'      => 'suspended',
                            'dmr_id'         => (int) $user['dmr_id'],
                        ]);
                    }
                    $flash_success = 'User suspended.';
                    break;
                }
                case 'lift_suspend': {
                    if (!user_has_role($actor_id, 'admin')) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $reason = trim($_POST['reason'] ?? 'Suspension lifted.');
                    $db->prepare(
                        'UPDATE users SET moderation_state = "active", mute_expires_at = NULL WHERE id = ?'
                    )->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'activated', $reason);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', [
                            'target_user_id' => $user_id,
                            'new_state'      => 'active',
                            'dmr_id'         => (int) $user['dmr_id'],
                        ]);
                    }
                    $flash_success = 'Suspension lifted.';
                    break;
                }
                case 'ban': {
                    if (!user_has_role($actor_id, 'admin')) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $reason = trim($_POST['reason'] ?? '');
                    if ($reason === '') {
                        throw new \RuntimeException('Reason is required.');
                    }
                    $db->prepare(
                        'UPDATE users SET moderation_state = "banned", mute_expires_at = NULL WHERE id = ?'
                    )->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'banned', $reason);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', [
                            'target_user_id' => $user_id,
                            'new_state'      => 'banned',
                            'dmr_id'         => (int) $user['dmr_id'],
                        ]);
                    }
                    $flash_success = 'User banned.';
                    break;
                }
                case 'reverse_ban': {
                    if (!user_has_role($actor_id, 'system_admin')) {
                        throw new \RuntimeException('Only a system admin may reverse a ban.');
                    }
                    $reason = trim($_POST['reason'] ?? 'Ban reversed by system admin.');
                    $db->prepare(
                        'UPDATE users SET moderation_state = "active", mute_expires_at = NULL WHERE id = ?'
                    )->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'activated', $reason);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', [
                            'target_user_id' => $user_id,
                            'new_state'      => 'active',
                            'dmr_id'         => (int) $user['dmr_id'],
                        ]);
                    }
                    $flash_success = 'Ban reversed.';
                    break;
                }
                case 'manual_verify': {
                    if (!$is_system_admin) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')
                       ->execute([$user_id]);
                    log_audit_action($actor_id, 'account_verified', 'user', $user_id, ['method' => 'manual']);
                    $flash_success = 'Account manually verified.';
                    break;
                }
                case 'resend_verify': {
                    if (!$is_system_admin) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    $db->prepare(
                        'UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
                    )->execute([$user_id]);
                    $token = bin2hex(random_bytes(32));
                    $db->prepare(
                        'INSERT INTO email_verifications (user_id, token, expires_at)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
                    )->execute([$user_id, $token]);
                    send_verification_email($user['email'], $user['display_name'], $token);
                    log_audit_action($actor_id, 'verification_resent', 'user', $user_id);
                    $flash_success = 'Verification email resent.';
                    break;
                }
                case 'delete_user': {
                    if (!$is_system_admin) {
                        throw new \RuntimeException('Insufficient privileges.');
                    }
                    if ($user_id === $actor_id) {
                        throw new \RuntimeException('You cannot delete your own account.');
                    }
                    log_audit_action($actor_id, 'account_deleted', 'user', $user_id, ['username' => $user['username']]);
                    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
                    redirect('/admin/users/');
                    break;
                }
                default:
                    $flash_error = 'Unknown action.';
            }
        } catch (\RuntimeException $e) {
            $flash_error = $e->getMessage();
        } catch (\InvalidArgumentException $e) {
            $flash_error = $e->getMessage();
        }

        // Reload user record after any state change
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

/* ── View data ────────────────────────────────────────────────── */
$user_roles   = get_user_roles($user_id);
$actor_level  = actor_max_level($actor_id);
$is_admin     = user_has_role($actor_id, 'admin');
$is_moderator = user_has_role($actor_id, 'moderator');

$all_roles = $db->query('SELECT name, display_name FROM roles ORDER BY sort_order')->fetchAll();

$state       = $user['moderation_state'];
$state_badge = [
    'active'           => 'badge-active',
    'muted_on_network' => 'badge-muted',
    'suspended'        => 'badge-suspended',
    'banned'           => 'badge-banned',
];
$state_label = [
    'active'           => 'Active',
    'muted_on_network' => 'Muted on Network',
    'suspended'        => 'Suspended',
    'banned'           => 'Banned',
];
$role_badge = [
    'system_admin' => 'badge-system-admin',
    'admin'        => 'badge-admin',
    'moderator'    => 'badge-moderator',
    'user'         => 'badge-user',
];

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?> — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page" style="align-items: start; padding: 2rem;">
        <section class="card" style="width: min(720px, 100%);">
            <p class="eyebrow">CFLAG DMR · User Management</p>
            <h1><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></h1>

            <p style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.9rem; margin-bottom: 1.5rem;">
                <a href="/admin/users/" class="nav-link">← User List</a>
                <a href="/admin/" class="nav-link">Dashboard</a>
            </p>

            <?php if ($flash_error !== ''): ?>
                <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($flash_success !== ''): ?>
                <div class="alert-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <!-- ── User Details ───────────────────────────────── -->
            <div class="field-row">
                <span class="field-label">Username</span>
                <span class="field-value"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Display Name</span>
                <span class="field-value"><?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Email</span>
                <span class="field-value"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">DMR ID</span>
                <span class="field-value"><?= $user['dmr_id'] !== null ? (int) $user['dmr_id'] : '—' ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Tier</span>
                <span class="field-value"><?= htmlspecialchars($user['tier'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Member Since</span>
                <span class="field-value muted"><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Last Login</span>
                <span class="field-value muted">
                    <?= $user['last_login_at']
                        ? htmlspecialchars($user['last_login_at'], ENT_QUOTES, 'UTF-8')
                        : '—' ?>
                </span>
            </div>
            <div class="field-row">
                <span class="field-label">Verification</span>
                <span class="field-value">
                    <?php if ($user['email_verified_at'] !== null): ?>
                        <span class="muted" style="font-size:0.85rem;">
                            <?= htmlspecialchars($user['email_verified_at'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-unverified">Not Verified</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($is_system_admin && $user['email_verified_at'] === null): ?>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="manual_verify">
                    <button type="submit" class="btn btn-sm btn-secondary">Manually Verify</button>
                </form>
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="resend_verify">
                    <button type="submit" class="btn btn-sm btn-secondary">Resend Verification Email</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($is_system_admin): ?>
            <div style="margin-bottom: 1rem;">
                <form method="post" action=""
                      onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($user['username']), ENT_QUOTES, 'UTF-8') ?>? This cannot be undone.')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <button type="submit" class="btn btn-sm btn-danger">Delete Account</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── Roles ─────────────────────────────────────── -->
            <?php if ($is_system_admin): ?>
            <h2 class="section-title">Roles</h2>

            <?php if (empty($user_roles)): ?>
                <p class="muted" style="font-size:0.9rem;">No roles assigned.</p>
            <?php else: ?>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                    <?php foreach ($user_roles as $role): ?>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action"    value="revoke_role">
                            <input type="hidden" name="role_name" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit"
                                    class="badge <?= htmlspecialchars($role_badge[$role] ?? 'badge-user', ENT_QUOTES, 'UTF-8') ?>"
                                    style="cursor:pointer; border:none; padding: 0.3rem 0.7rem;"
                                    title="Click to revoke">
                                <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?> ✕
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            $assignable = array_filter(
                $all_roles,
                fn($r) => !in_array($r['name'], $user_roles, true)
                       && (ROLE_HIERARCHY[$r['name']] ?? 0) <= $actor_level
            );
            ?>
            <?php if (!empty($assignable)): ?>
            <form method="post" action="" class="action-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action"    value="assign_role">
                <div class="form-group">
                    <label for="role_name">Assign Role</label>
                    <select id="role_name" name="role_name">
                        <?php foreach ($assignable as $r): ?>
                            <option value="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-secondary">Assign</button>
            </form>
            <?php endif; ?>
            <?php endif; /* is_system_admin */ ?>

            <!-- ── Moderation ────────────────────────────────── -->
            <?php if ($is_moderator): ?>
            <h2 class="section-title">Moderation</h2>

            <div class="field-row" style="margin-bottom: 1rem;">
                <span class="field-label">Status</span>
                <span class="badge <?= htmlspecialchars($state_badge[$state] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($state_label[$state] ?? $state, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($state === 'muted_on_network' && $user['mute_expires_at']): ?>
                    <span class="muted" style="font-size:0.8rem;">
                        until <?= htmlspecialchars($user['mute_expires_at'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($state === 'active'): ?>

                <!-- Mute on network (moderator+) -->
                <form method="post" action="" class="action-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="mute_network">
                    <div class="form-group">
                        <label for="duration_hours">Mute Duration</label>
                        <select id="duration_hours" name="duration_hours">
                            <option value="1">1 hour</option>
                            <option value="3">3 hours</option>
                            <option value="6">6 hours</option>
                            <option value="24">24 hours</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mute_reason">Reason</label>
                        <input type="text" id="mute_reason" name="reason" required placeholder="Required">
                    </div>
                    <button type="submit" class="btn btn-sm btn-warn">Mute on Network</button>
                </form>

                <?php if ($is_admin): ?>
                <!-- Suspend (admin+) -->
                <form method="post" action="" class="action-form" style="margin-top:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="suspend">
                    <div class="form-group">
                        <label for="suspend_reason">Suspend — Reason</label>
                        <input type="text" id="suspend_reason" name="reason" required placeholder="Required">
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Suspend this user?')">Suspend</button>
                </form>

                <!-- Ban (admin+) -->
                <form method="post" action="" class="action-form" style="margin-top:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="ban">
                    <div class="form-group">
                        <label for="ban_reason">Permanent Ban — Reason</label>
                        <input type="text" id="ban_reason" name="reason" required placeholder="Required">
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Permanently ban this user? This requires a system admin to reverse.')">Ban</button>
                </form>
                <?php endif; ?>

            <?php elseif ($state === 'muted_on_network'): ?>

                <!-- Lift mute (moderator+) -->
                <form method="post" action="" class="action-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="lift_mute">
                    <input type="hidden" name="reason" value="Mute lifted by moderator.">
                    <button type="submit" class="btn btn-sm btn-secondary">Lift Mute</button>
                </form>
                <?php if ($is_admin): ?>
                <form method="post" action="" class="action-form" style="margin-top:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="suspend">
                    <div class="form-group">
                        <label for="suspend_from_mute_reason">Escalate to Suspension — Reason</label>
                        <input type="text" id="suspend_from_mute_reason" name="reason" required placeholder="Required">
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Suspend this user?')">Suspend</button>
                </form>
                <?php endif; ?>

            <?php elseif ($state === 'suspended'): ?>

                <?php if ($is_admin): ?>
                <!-- Lift suspension (admin+) -->
                <form method="post" action="" class="action-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="lift_suspend">
                    <input type="hidden" name="reason" value="Suspension lifted by admin.">
                    <button type="submit" class="btn btn-sm btn-secondary">Lift Suspension</button>
                </form>
                <form method="post" action="" class="action-form" style="margin-top:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="ban">
                    <div class="form-group">
                        <label for="ban_from_suspend_reason">Escalate to Permanent Ban — Reason</label>
                        <input type="text" id="ban_from_suspend_reason" name="reason" required placeholder="Required">
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Permanently ban this user?')">Ban</button>
                </form>
                <?php endif; ?>

            <?php elseif ($state === 'banned'): ?>

                <?php if ($is_system_admin): ?>
                <!-- Reverse ban (system_admin only) -->
                <form method="post" action="" class="action-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="reverse_ban">
                    <input type="hidden" name="reason" value="Ban reversed by system admin.">
                    <button type="submit" class="btn btn-sm btn-secondary"
                            onclick="return confirm('Reverse this permanent ban?')">Reverse Ban</button>
                </form>
                <?php else: ?>
                <p class="muted" style="font-size:0.85rem;">Only a system admin may reverse a permanent ban.</p>
                <?php endif; ?>

            <?php endif; ?>
            <?php endif; /* is_moderator */ ?>

        </section>
    </main>
</body>
</html>
