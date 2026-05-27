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

        } elseif ($action === 'update_dmr_id') {
            $result = update_dmr_id($user_id, $_POST['dmr_id'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'DMR ID saved.' : $result['error'];

        } elseif ($action === 'change_password') {
            $result = change_password(
                $user_id,
                $_POST['current_password'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['confirm_password'] ?? ''
            );
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Password changed.' : $result['error'];

        } elseif ($action === 'request_email_change') {
            $result = request_email_change($user_id, $_POST['new_email'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Verification email sent.' : $result['error'];

        } elseif ($action === 'cancel_email_change') {
            cancel_email_change($user_id);
            $_SESSION['_flash_ok'] = 'Email change cancelled.';

        } elseif ($action === 'request_callsign') {
            $result = submit_callsign_request($user_id, $_POST['new_callsign'] ?? '', $_POST['explanation'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Callsign update request submitted.' : $result['error'];

        } elseif ($action === 'update_extended') {
            $result = update_extended_profile($user_id, $_POST);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Profile saved.' : $result['error'];
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

    <!-- Left: editable forms -->
    <div class="col-stack">

        <!-- Radio identity: display name, callsign, DMR ID, extended -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Radio Identity</span></div>
            <div class="panel-body">

                <!-- Display name inline -->
                <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;margin-bottom:1.25rem;">
                    <input type="hidden" name="action" value="update_name">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="margin:0;flex:1;">
                        <label for="display_name" style="font-size:0.78rem;">Display Name</label>
                        <input type="text" id="display_name" name="display_name" maxlength="128" required
                               value="<?= htmlspecialchars($profile['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-xs" style="margin-bottom:1px;">Save</button>
                </form>

                <div class="form-divider"></div>

                <!-- Callsign row -->
                <div style="margin-bottom:1rem;">
                    <span class="form-section-label">Callsign</span>
                    <?php if ($profile['callsign'] ?? null): ?>
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
                        <span style="font-size:1.1rem;font-weight:700;color:var(--accent-text);font-family:monospace;">
                            <?= htmlspecialchars($profile['callsign'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span style="font-size:0.72rem;color:var(--text-3);">Use the form below to request a change</span>
                    </div>
                    <?php else: ?>
                    <p style="font-size:0.78rem;color:var(--text-3);margin-bottom:0.5rem;">No callsign on file — submit a request below.</p>
                    <?php endif; ?>

                    <?php if ($open_cs_request !== null): ?>
                    <div style="background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;padding:0.5rem 0.75rem;font-size:0.78rem;">
                        Pending request for <strong><?= htmlspecialchars($open_cs_request['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span style="color:var(--text-3);margin-left:0.5rem;"><?= htmlspecialchars($open_cs_request['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php else: ?>
                    <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="request_callsign">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group" style="margin:0;">
                            <label for="new_callsign" style="font-size:0.72rem;">New Callsign</label>
                            <input type="text" id="new_callsign" name="new_callsign" maxlength="10" required
                                   placeholder="e.g. W7XYZ" style="width:120px;text-transform:uppercase;">
                        </div>
                        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                            <label for="cs_explanation" style="font-size:0.72rem;">Reason (optional)</label>
                            <input type="text" id="cs_explanation" name="explanation" maxlength="255"
                                   placeholder="e.g. Vanity call granted by FCC">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-xs" style="margin-bottom:1px;">Request Update</button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="form-divider"></div>

                <!-- DMR ID row -->
                <div style="margin-bottom:1rem;">
                    <span class="form-section-label">Base DMR ID</span>
                    <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;margin-top:0.375rem;">
                        <input type="hidden" name="action" value="update_dmr_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group" style="margin:0;">
                            <label for="dmr_id" style="font-size:0.72rem;">7-Digit DMR ID</label>
                            <input type="text" id="dmr_id" name="dmr_id" maxlength="7"
                                   placeholder="e.g. 3130123" style="width:130px;font-family:monospace;"
                                   pattern="[0-9]{7}"
                                   value="<?= htmlspecialchars((string)($profile['dmr_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-xs" style="margin-bottom:1px;">Save</button>
                        <p class="form-hint" style="width:100%;margin:0.25rem 0 0;">Your RadioID.net registration number. Used as the base ID for hotspot registration.</p>
                    </form>
                </div>

                <div class="form-divider"></div>

                <!-- Extended profile -->
                <form method="post">
                    <input type="hidden" name="action" value="update_extended">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <span class="form-section-label">Name &amp; Location</span>
                    <div class="form-row" style="gap:0.75rem;margin-bottom:0.75rem;">
                        <div class="form-group" style="margin:0;flex:1;min-width:0;">
                            <label for="first_name" style="font-size:0.72rem;">First Name</label>
                            <input type="text" id="first_name" name="first_name" maxlength="64"
                                   value="<?= htmlspecialchars($profile['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group" style="margin:0;flex:1;min-width:0;">
                            <label for="last_name" style="font-size:0.72rem;">Last Name</label>
                            <input type="text" id="last_name" name="last_name" maxlength="64"
                                   value="<?= htmlspecialchars($profile['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label for="grid_square" style="font-size:0.72rem;">Grid Square</label>
                            <input type="text" id="grid_square" name="grid_square" maxlength="8"
                                   placeholder="e.g. EM75" style="width:100px;text-transform:uppercase;"
                                   value="<?= htmlspecialchars($profile['grid_square'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio" style="font-size:0.72rem;">Bio <span style="color:var(--text-3);font-weight:normal;">(visible to logged-in users)</span></label>
                        <textarea id="bio" name="bio" maxlength="500" rows="3"
                                  style="resize:vertical;"><?= htmlspecialchars($profile['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="phone" style="font-size:0.72rem;">Phone <span style="color:var(--text-3);font-weight:normal;">(admin-visible only)</span></label>
                        <input type="text" id="phone" name="phone" maxlength="32" style="max-width:200px;"
                               value="<?= htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0.375rem;margin-bottom:1rem;">
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.82rem;font-weight:normal;">
                            <input type="checkbox" name="show_name_publicly" value="1"
                                   <?= !empty($profile['show_name_publicly']) ? 'checked' : '' ?>>
                            Show my name to other logged-in users
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.82rem;font-weight:normal;">
                            <input type="checkbox" name="show_in_directory" value="1"
                                   <?= !isset($profile['show_in_directory']) || $profile['show_in_directory'] ? 'checked' : '' ?>>
                            Show me in the user directory
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
                    </div>
                </form>

            </div>
        </div>

        <!-- Security -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Security</span></div>
            <div class="panel-body">

                <!-- Email -->
                <span class="form-section-label">Email Address</span>
                <?php if ($pending_email !== null): ?>
                <div style="background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;padding:0.5rem 0.75rem;font-size:0.78rem;margin-bottom:0.75rem;">
                    Verification pending for <strong><?= htmlspecialchars($pending_email['new_email'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span style="color:var(--text-3);margin-left:0.25rem;">— expires <?= htmlspecialchars($pending_email['expires_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <form method="post" style="margin-bottom:1rem;">
                    <input type="hidden" name="action" value="cancel_email_change">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-danger btn-xs">Cancel Change</button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.25rem;">
                    <input type="hidden" name="action" value="request_email_change">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                        <label for="new_email" style="font-size:0.72rem;">New Email Address</label>
                        <input type="email" id="new_email" name="new_email" required placeholder="new@example.com">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-xs" style="margin-bottom:1px;">
                        <?= $pending_email !== null ? 'Re-send' : 'Request Change' ?>
                    </button>
                </form>

                <div class="form-divider"></div>

                <!-- Password -->
                <span class="form-section-label">Password</span>
                <form method="post">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-row" style="gap:0.75rem;">
                        <div class="form-group" style="flex:1;min-width:0;">
                            <label for="current_password" style="font-size:0.72rem;">Current Password</label>
                            <input type="password" id="current_password" name="current_password"
                                   required autocomplete="current-password">
                        </div>
                        <div class="form-group" style="flex:1;min-width:0;">
                            <label for="new_password" style="font-size:0.72rem;">New Password <span style="color:var(--text-3);font-weight:normal;">(min 12 chars)</span></label>
                            <input type="password" id="new_password" name="new_password"
                                   required minlength="12" autocomplete="new-password">
                        </div>
                        <div class="form-group" style="flex:1;min-width:0;">
                            <label for="confirm_password" style="font-size:0.72rem;">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   required minlength="12" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary btn-sm">Change Password</button>
                    </div>
                </form>

            </div>
        </div>

    </div><!-- /.col-stack left -->

    <!-- Right: Account overview + callsign history -->
    <div class="col-stack" style="align-self:start;">

        <!-- Account overview -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Account Info</span></div>
            <div class="panel-body pad-none">
                <?php if ($profile): ?>
                <div class="field-list">
                    <div class="field-row">
                        <span class="field-key">Username</span>
                        <span class="field-val col-mono"><?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Callsign</span>
                        <span class="field-val" style="font-weight:700;font-size:1.05rem;font-family:monospace;color:var(--accent-text);">
                            <?= htmlspecialchars($profile['callsign'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">DMR ID</span>
                        <span class="field-val col-mono" style="color:var(--accent-text);">
                            <?= htmlspecialchars($profile['dmr_id'] ? (string)$profile['dmr_id'] : '—', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Email</span>
                        <span class="field-val" style="color:var(--text-3);word-break:break-all;"><?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Status</span>
                        <span class="field-val"><?= moderation_badge($profile['moderation_state']) ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Member Since</span>
                        <span class="field-val" style="color:var(--text-3);">
                            <?= htmlspecialchars(date('F j, Y', strtotime($profile['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <?php if ($profile['first_name'] ?? null): ?>
                    <div class="field-row">
                        <span class="field-key">Name</span>
                        <span class="field-val">
                            <?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($profile['grid_square'] ?? null): ?>
                    <div class="field-row">
                        <span class="field-key">Grid</span>
                        <span class="field-val col-mono"><?= htmlspecialchars($profile['grid_square'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Callsign history -->
        <?php if (!empty($cs_history)): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Callsign Requests</span></div>
            <div class="panel-body pad-none">
                <table class="data-table">
                    <thead><tr><th>Callsign</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($cs_history as $req): ?>
                        <tr>
                            <td style="font-weight:700;font-family:monospace;"><?= htmlspecialchars($req['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($req['status'] === 'approved'): ?>
                                <span class="badge badge-active">Approved</span>
                                <?php elseif ($req['status'] === 'denied'): ?>
                                <span class="badge badge-red">Denied</span>
                                <?php else: ?>
                                <span class="badge badge-amber">Pending</span>
                                <?php endif; ?>
                                <?php if ($req['review_notes'] ?? ''): ?>
                                <br><span style="font-size:0.7rem;color:var(--text-3);"><?= htmlspecialchars($req['review_notes'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="col-ts"><?= htmlspecialchars($req['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.col-stack right -->

</div><!-- /.layout-primary-aside -->

<?php require_once $root . '/app/views/footer.php'; ?>
