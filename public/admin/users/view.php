<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/email/mailer.php';
require_once $root . '/app/devices/manager.php';

start_session();
require_role('admin');

$db       = get_db();
$actor_id = (int) $_SESSION['user_id'];
$user_id  = (int) ($_GET['id'] ?? 0);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
                case 'assign_role': {
                    $role_name = trim($_POST['role_name'] ?? '');
                    if ($role_name === '') throw new \RuntimeException('No role selected.');
                    assign_role($actor_id, $user_id, $role_name);
                    $flash_success = 'Role assigned successfully.';
                    break;
                }
                case 'revoke_role': {
                    $role_name = trim($_POST['role_name'] ?? '');
                    if ($role_name === '') throw new \RuntimeException('No role specified.');
                    revoke_role($actor_id, $user_id, $role_name);
                    $flash_success = 'Role revoked successfully.';
                    break;
                }
                case 'mute_network': {
                    if (!user_has_role($actor_id, 'moderator')) throw new \RuntimeException('Insufficient privileges.');
                    $duration = (int) ($_POST['duration_hours'] ?? 0);
                    $reason   = trim($_POST['reason'] ?? '');
                    if ($duration <= 0 || !in_array($duration, [1, 3, 6, 24], true)) throw new \RuntimeException('Invalid duration.');
                    if ($reason === '') throw new \RuntimeException('Reason is required.');
                    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
                    $db->prepare('UPDATE users SET moderation_state = "muted_on_network", mute_expires_at = ? WHERE id = ?')
                       ->execute([$expires_at, $user_id]);
                    log_mod_action($actor_id, $user_id, 'muted_on_network', $reason, $duration, $expires_at);
                    if ($user['dmr_id'] !== null) {
                        queue_network_regen($actor_id, 'moderation_state_change', ['target_user_id' => $user_id, 'new_state' => 'muted_on_network', 'dmr_id' => (int) $user['dmr_id']]);
                    }
                    $flash_success = 'User muted on network.';
                    break;
                }
                case 'lift_mute': {
                    if (!user_has_role($actor_id, 'moderator')) throw new \RuntimeException('Insufficient privileges.');
                    $reason = trim($_POST['reason'] ?? 'Mute lifted by moderator.');
                    $db->prepare('UPDATE users SET moderation_state = "active", mute_expires_at = NULL WHERE id = ?')->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'activated', $reason);
                    if ($user['dmr_id'] !== null) queue_network_regen($actor_id, 'moderation_state_change', ['target_user_id' => $user_id, 'new_state' => 'active', 'dmr_id' => (int) $user['dmr_id']]);
                    $flash_success = 'Network mute lifted.';
                    break;
                }
                case 'suspend': {
                    if (!user_has_role($actor_id, 'admin')) throw new \RuntimeException('Insufficient privileges.');
                    $reason = trim($_POST['reason'] ?? '');
                    if ($reason === '') throw new \RuntimeException('Reason is required.');
                    $db->prepare('UPDATE users SET moderation_state = "suspended", mute_expires_at = NULL WHERE id = ?')->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'suspended', $reason);
                    if ($user['dmr_id'] !== null) queue_network_regen($actor_id, 'moderation_state_change', ['target_user_id' => $user_id, 'new_state' => 'suspended', 'dmr_id' => (int) $user['dmr_id']]);
                    $flash_success = 'User suspended.';
                    break;
                }
                case 'lift_suspend': {
                    if (!user_has_role($actor_id, 'admin')) throw new \RuntimeException('Insufficient privileges.');
                    $reason = trim($_POST['reason'] ?? 'Suspension lifted.');
                    $db->prepare('UPDATE users SET moderation_state = "active", mute_expires_at = NULL WHERE id = ?')->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'activated', $reason);
                    if ($user['dmr_id'] !== null) queue_network_regen($actor_id, 'moderation_state_change', ['target_user_id' => $user_id, 'new_state' => 'active', 'dmr_id' => (int) $user['dmr_id']]);
                    $flash_success = 'Suspension lifted.';
                    break;
                }
                case 'ban': {
                    if (!user_has_role($actor_id, 'admin')) throw new \RuntimeException('Insufficient privileges.');
                    $reason = trim($_POST['reason'] ?? '');
                    if ($reason === '') throw new \RuntimeException('Reason is required.');
                    $db->prepare('UPDATE users SET moderation_state = "banned", mute_expires_at = NULL WHERE id = ?')->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'banned', $reason);
                    if ($user['dmr_id'] !== null) queue_network_regen($actor_id, 'moderation_state_change', ['target_user_id' => $user_id, 'new_state' => 'banned', 'dmr_id' => (int) $user['dmr_id']]);
                    $flash_success = 'User banned.';
                    break;
                }
                case 'reverse_ban': {
                    if (!$is_system_admin) throw new \RuntimeException('Only a system admin may reverse a ban.');
                    $reason = trim($_POST['reason'] ?? 'Ban reversed by system admin.');
                    $db->prepare('UPDATE users SET moderation_state = "active", mute_expires_at = NULL WHERE id = ?')->execute([$user_id]);
                    log_mod_action($actor_id, $user_id, 'activated', $reason);
                    if ($user['dmr_id'] !== null) queue_network_regen($actor_id, 'moderation_state_change', ['target_user_id' => $user_id, 'new_state' => 'active', 'dmr_id' => (int) $user['dmr_id']]);
                    $flash_success = 'Ban reversed.';
                    break;
                }
                case 'manual_verify': {
                    if (!$is_system_admin) throw new \RuntimeException('Insufficient privileges.');
                    $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$user_id]);
                    log_audit_action($actor_id, 'account_verified', 'user', $user_id, ['method' => 'manual']);
                    $flash_success = 'Account manually verified.';
                    break;
                }
                case 'resend_verify': {
                    if (!$is_system_admin) throw new \RuntimeException('Insufficient privileges.');
                    $db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$user_id]);
                    $token = bin2hex(random_bytes(32));
                    $db->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')->execute([$user_id, $token]);
                    send_verification_email($user['email'], $user['display_name'], $token);
                    log_audit_action($actor_id, 'verification_resent', 'user', $user_id);
                    $flash_success = 'Verification email resent.';
                    break;
                }
                case 'delete_user': {
                    if (!$is_system_admin) throw new \RuntimeException('Insufficient privileges.');
                    if ($user_id === $actor_id) throw new \RuntimeException('You cannot delete your own account.');
                    log_audit_action($actor_id, 'account_deleted', 'user', $user_id, ['username' => $user['username']]);
                    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
                    redirect('/admin/users/');
                    break;
                }
                default: $flash_error = 'Unknown action.';
            }
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $flash_error = $e->getMessage();
        }
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

$user_roles   = get_user_roles($user_id);
$actor_level  = actor_max_level($actor_id);
$is_admin     = user_has_role($actor_id, 'admin');
$is_moderator = user_has_role($actor_id, 'moderator');
$all_roles    = $db->query('SELECT name, display_name FROM roles ORDER BY sort_order')->fetchAll();
$user_devices = get_user_devices($user_id);

$stmt_ml = $db->prepare(
    'SELECT action, reason, duration_hours, expires_at, created_at,
            (SELECT username FROM users WHERE id = ml.actor_user_id) AS actor_username
     FROM mod_log ml WHERE ml.target_user_id = ?
     ORDER BY ml.created_at DESC LIMIT 20'
);
$stmt_ml->execute([$user_id]);
$mod_history = $stmt_ml->fetchAll();

$csrf = csrf_token();
$state = $user['moderation_state'];

$state_badge = ['active' => 'badge-active', 'muted_on_network' => 'badge-gray', 'suspended' => 'badge-amber', 'banned' => 'badge-red'];
$state_label = ['active' => 'Active', 'muted_on_network' => 'Muted on Network', 'suspended' => 'Suspended', 'banned' => 'Banned'];
$role_badge  = ['system_admin' => 'badge-system-admin', 'admin' => 'badge-admin', 'moderator' => 'badge-blue', 'user' => 'badge-gray'];

$page_title = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
$active_nav = 'admin-users';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_error !== ''):    ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error,   ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_success !== ''): ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside" style="flex:1;min-height:0;">

    <!-- Left: details + moderation + history -->
    <div class="col-stack">

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Account Info</span>
                <div class="panel-actions">
                    <a href="/admin/users/" class="btn btn-ghost btn-xs">← Users</a>
                </div>
            </div>
            <div class="panel-body">
                <div class="field-list">
                    <div class="field-row"><span class="field-key">Username</span><span class="field-val"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="field-row"><span class="field-key">Display Name</span><span class="field-val"><?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="field-row"><span class="field-key">Callsign</span><span class="field-val" style="font-weight:700;"><?= htmlspecialchars($user['callsign'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="field-row"><span class="field-key">Email</span><span class="field-val muted"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="field-row"><span class="field-key">DMR ID</span><span class="field-val col-mono"><?= $user['dmr_id'] !== null ? (int)$user['dmr_id'] : '—' ?></span></div>
                    <div class="field-row"><span class="field-key">Tier</span><span class="field-val"><?= htmlspecialchars($user['tier'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="field-row"><span class="field-key">Member Since</span><span class="field-val col-ts"><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="field-row"><span class="field-key">Last Login</span><span class="field-val col-ts"><?= $user['last_login_at'] ? htmlspecialchars($user['last_login_at'], ENT_QUOTES, 'UTF-8') : '—' ?></span></div>
                    <div class="field-row">
                        <span class="field-key">Verified</span>
                        <span class="field-val">
                            <?php if ($user['email_verified_at'] !== null): ?>
                            <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars($user['email_verified_at'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                            <span class="badge badge-unverified">Not Verified</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if ($is_system_admin && $user['email_verified_at'] === null): ?>
                <div class="action-row" style="margin-top:0.875rem;">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="manual_verify">
                        <button type="submit" class="btn btn-secondary btn-sm">Manually Verify</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="resend_verify">
                        <button type="submit" class="btn btn-secondary btn-sm">Resend Verification</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($is_system_admin): ?>
                <div style="margin-top:0.875rem;padding-top:0.75rem;border-top:1px solid var(--border);">
                    <form method="post"
                          onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($user['username']), ENT_QUOTES, 'UTF-8') ?>? This cannot be undone.')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <button type="submit" class="btn btn-danger btn-sm">Delete Account</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Devices -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Devices</span></div>
            <div class="panel-body pad-none">
                <?php if (empty($user_devices)): ?>
                <div class="empty-state"><strong>No devices registered</strong></div>
                <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Callsign</th><th>DMR ID</th><th>Type</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($user_devices as $d): ?>
                        <tr>
                            <td class="col-call"><?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-mono" style="color:var(--accent-text);"><?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($d['status'] === 'approved'): ?>
                                <span class="badge badge-active">Approved</span>
                                <?php elseif ($d['status'] === 'pending'): ?>
                                <span class="badge badge-amber">Pending</span>
                                <?php else: ?>
                                <span class="badge badge-red">Denied</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($mod_history)): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Moderation History</span></div>
            <div class="panel-body pad-none">
                <table class="data-table">
                    <thead><tr><th>Action</th><th>Reason</th><th>By</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($mod_history as $entry): ?>
                        <tr>
                            <td><?= htmlspecialchars($entry['action'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($entry['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-ts"><?= htmlspecialchars($entry['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-ts"><?= htmlspecialchars($entry['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.col-stack -->

    <!-- Right: roles + moderation actions -->
    <div class="col-stack">

        <?php if ($is_system_admin): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Roles</span></div>
            <div class="panel-body">
                <?php if (!empty($user_roles)): ?>
                <div class="action-row" style="flex-wrap:wrap;margin-bottom:0.75rem;">
                    <?php foreach ($user_roles as $role): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="revoke_role">
                        <input type="hidden" name="role_name" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="badge <?= htmlspecialchars($role_badge[$role] ?? 'badge-gray', ENT_QUOTES, 'UTF-8') ?>"
                                style="cursor:pointer;border:none;" title="Click to revoke">
                            <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?> ✕
                        </button>
                    </form>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="font-size:0.72rem;color:var(--text-3);margin-bottom:0.75rem;">No roles assigned.</p>
                <?php endif; ?>

                <?php
                $assignable = array_filter(
                    $all_roles,
                    fn($r) => !in_array($r['name'], $user_roles, true)
                           && (ROLE_HIERARCHY[$r['name']] ?? 0) <= $actor_level
                );
                ?>
                <?php if (!empty($assignable)): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="assign_role">
                    <div class="form-group">
                        <label for="role_name">Assign Role</label>
                        <select id="role_name" name="role_name" class="form-select">
                            <?php foreach ($assignable as $r): ?>
                            <option value="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($r['display_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary btn-sm">Assign</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($is_moderator): ?>
        <div class="panel" style="flex:1;min-height:0;">
            <div class="panel-header">
                <span class="panel-title">Moderation</span>
                <span class="badge <?= htmlspecialchars($state_badge[$state] ?? 'badge-gray', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($state_label[$state] ?? $state, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($state === 'muted_on_network' && $user['mute_expires_at']): ?>
                <span style="font-size:0.65rem;color:var(--text-3);">
                    until <?= htmlspecialchars($user['mute_expires_at'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <?php if ($state === 'active'): ?>

                <form method="post" style="margin-bottom:0.75rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="mute_network">
                    <div class="form-group">
                        <label>Mute Duration</label>
                        <select name="duration_hours" class="form-select">
                            <option value="1">1 hour</option>
                            <option value="3">3 hours</option>
                            <option value="6">6 hours</option>
                            <option value="24">24 hours</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <input type="text" name="reason" required placeholder="Required">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-warn btn-sm">Mute on Network</button>
                    </div>
                </form>

                <?php if ($is_admin): ?>
                <div class="form-divider"></div>
                <form method="post" style="margin-bottom:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="suspend">
                    <div class="form-group">
                        <label>Suspend — Reason</label>
                        <input type="text" name="reason" required placeholder="Required">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Suspend this user?')">Suspend</button>
                    </div>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="ban">
                    <div class="form-group">
                        <label>Permanent Ban — Reason</label>
                        <input type="text" name="reason" required placeholder="Required">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Permanently ban this user?')">Ban</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php elseif ($state === 'muted_on_network'): ?>
                <form method="post" style="margin-bottom:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="lift_mute">
                    <input type="hidden" name="reason" value="Mute lifted by moderator.">
                    <button type="submit" class="btn btn-secondary btn-sm">Lift Mute</button>
                </form>
                <?php if ($is_admin): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="suspend">
                    <div class="form-group">
                        <label>Escalate to Suspension — Reason</label>
                        <input type="text" name="reason" required placeholder="Required">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Suspend this user?')">Suspend</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php elseif ($state === 'suspended'): ?>
                <?php if ($is_admin): ?>
                <form method="post" style="margin-bottom:0.5rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="lift_suspend">
                    <input type="hidden" name="reason" value="Suspension lifted by admin.">
                    <button type="submit" class="btn btn-secondary btn-sm">Lift Suspension</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="ban">
                    <div class="form-group">
                        <label>Escalate to Permanent Ban — Reason</label>
                        <input type="text" name="reason" required placeholder="Required">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Permanently ban this user?')">Ban</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php elseif ($state === 'banned'): ?>
                <?php if ($is_system_admin): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="reverse_ban">
                    <input type="hidden" name="reason" value="Ban reversed by system admin.">
                    <button type="submit" class="btn btn-secondary btn-sm"
                            onclick="return confirm('Reverse this permanent ban?')">Reverse Ban</button>
                </form>
                <?php else: ?>
                <p style="font-size:0.72rem;color:var(--text-3);">Only a system admin may reverse a permanent ban.</p>
                <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.col-stack -->

</div><!-- /.layout-primary-aside -->

<?php require_once $root . '/app/views/footer.php'; ?>
