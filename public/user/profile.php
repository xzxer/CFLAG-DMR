<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/profile/manager.php';
require_once $root . '/app/profile/email_change.php';
require_once $root . '/app/profile/callsign.php';

start_session();
require_login();

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_name') {
            $result = update_display_name($user_id, $_POST['display_name'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Display name updated.' : $result['error'];

        } elseif ($action === 'change_password') {
            $result = change_password(
                $user_id,
                $_POST['current_password'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['confirm_password'] ?? ''
            );
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Password changed successfully.' : $result['error'];

        } elseif ($action === 'request_email_change') {
            $result = request_email_change($user_id, $_POST['new_email'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Verification email sent to new address.' : $result['error'];

        } elseif ($action === 'cancel_email_change') {
            cancel_email_change($user_id);
            $_SESSION['_flash_ok'] = 'Email change request cancelled.';

        } elseif ($action === 'request_callsign') {
            $result = submit_callsign_request($user_id, $_POST['new_callsign'] ?? '', $_POST['explanation'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Callsign update request submitted.' : $result['error'];
        }

        header('Location: /user/profile.php');
        exit;
    }
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null;  unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null;  unset($_SESSION['_flash_error']);

$profile         = get_profile($user_id);
$pending_email   = get_pending_email_change($user_id);
$open_cs_request = get_open_callsign_request($user_id);
$cs_history      = get_user_callsign_requests($user_id);

function moderation_badge(string $state): string
{
    return match ($state) {
        'active'           => '<span class="badge badge-active">Active</span>',
        'suspended'        => '<span class="badge badge-amber">Suspended</span>',
        'banned'           => '<span class="badge badge-red">Banned</span>',
        'muted_on_network' => '<span class="badge badge-amber">Muted</span>',
        default            => '<span class="badge badge-gray">' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</span>',
    };
}

$page_title = 'My Profile';
$active_nav = 'my-profile';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside" style="flex:1;min-height:0;">

    <!-- Left: Settings forms -->
    <div class="col-stack">

        <!-- Display name -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Display Name</span></div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_name">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="display_name">Name</label>
                        <input type="text" id="display_name" name="display_name"
                               maxlength="128" required
                               value="<?= htmlspecialchars($profile['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Change Password</span></div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password"
                               required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password"
                               required minlength="12" autocomplete="new-password">
                        <p class="form-hint">Minimum 12 characters</p>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               required minlength="12" autocomplete="new-password">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Email -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Email Address</span></div>
            <div class="panel-body">
                <?php if ($pending_email !== null): ?>
                <div class="alert alert-info" style="margin-bottom:0.75rem;">
                    Verification pending for: <strong><?= htmlspecialchars($pending_email['new_email'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <br><span style="font-size:0.72rem;opacity:0.7;">Expires: <?= htmlspecialchars($pending_email['expires_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <form method="post" style="margin-bottom:1rem;">
                    <input type="hidden" name="action" value="cancel_email_change">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Cancel Email Change</button>
                </form>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="request_email_change">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="new_email">New Email Address</label>
                        <input type="email" id="new_email" name="new_email"
                               required placeholder="new@example.com">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <?= $pending_email !== null ? 'Request New Change' : 'Request Email Change' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /.col-stack -->

    <!-- Right: Profile info + callsign -->
    <div class="col-stack">

        <!-- Profile overview -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Account Info</span></div>
            <div class="panel-body">
                <?php if ($profile): ?>
                <div class="field-list">
                    <div class="field-row">
                        <span class="field-key">Username</span>
                        <span class="field-val"><?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Callsign</span>
                        <span class="field-val" style="font-weight:700;font-size:1rem;">
                            <?= htmlspecialchars($profile['callsign'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Email</span>
                        <span class="field-val muted"><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Status</span>
                        <span class="field-val"><?= moderation_badge($profile['moderation_state']) ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Member Since</span>
                        <span class="field-val muted">
                            <?= htmlspecialchars(date('F j, Y', strtotime($profile['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Callsign update -->
        <div class="panel" style="flex:1;min-height:0;">
            <div class="panel-header"><span class="panel-title">Callsign Update</span></div>
            <div class="panel-body">
                <?php if ($open_cs_request !== null): ?>
                <div class="alert alert-info" style="margin-bottom:0.75rem;">
                    Pending request for:
                    <strong><?= htmlspecialchars($open_cs_request['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <br><span style="font-size:0.72rem;opacity:0.7;">Submitted: <?= htmlspecialchars($open_cs_request['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="request_callsign">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="new_callsign">New Callsign</label>
                        <input type="text" id="new_callsign" name="new_callsign"
                               maxlength="10" required placeholder="e.g. W7XYZ"
                               style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label for="explanation">Reason (optional)</label>
                        <input type="text" id="explanation" name="explanation"
                               maxlength="255" placeholder="e.g. Vanity call granted by FCC">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Request Update</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (!empty($cs_history)): ?>
                <div class="form-divider"></div>
                <span class="form-section-label">Request History</span>
                <table class="data-table">
                    <thead>
                        <tr><th>Callsign</th><th>Status</th><th>Notes</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cs_history as $req): ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars($req['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($req['status'] === 'approved'): ?>
                                <span class="badge badge-active">Approved</span>
                                <?php elseif ($req['status'] === 'denied'): ?>
                                <span class="badge badge-red">Denied</span>
                                <?php else: ?>
                                <span class="badge badge-amber">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-ts"><?= htmlspecialchars($req['review_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-ts"><?= htmlspecialchars($req['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.col-stack -->

</div><!-- /.layout-primary-aside -->

<?php require_once $root . '/app/views/footer.php'; ?>
