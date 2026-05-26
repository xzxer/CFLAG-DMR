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
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_name') {
            $result = update_display_name($user_id, $_POST['display_name'] ?? '');
            if ($result['ok']) {
                $flash_ok = 'Display name updated.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'change_password') {
            $result = change_password(
                $user_id,
                $_POST['current_password'] ?? '',
                $_POST['new_password'] ?? '',
                $_POST['confirm_password'] ?? ''
            );
            if ($result['ok']) {
                $flash_ok = 'Password changed successfully.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'request_email_change') {
            $result = request_email_change($user_id, $_POST['new_email'] ?? '');
            if ($result['ok']) {
                $flash_ok = 'Verification email sent to new address. Click the link to confirm.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'cancel_email_change') {
            cancel_email_change($user_id);
            $flash_ok = 'Email change request cancelled.';

        } elseif ($action === 'request_callsign') {
            $result = submit_callsign_request(
                $user_id,
                $_POST['new_callsign'] ?? '',
                $_POST['explanation'] ?? ''
            );
            if ($result['ok']) {
                $flash_ok = 'Callsign update request submitted for admin review.';
            } else {
                $flash_error = $result['error'];
            }
        }

        header('Location: /user/profile.php');
        exit;
    }
}

$profile         = get_profile($user_id);
$pending_email   = get_pending_email_change($user_id);
$open_cs_request = get_open_callsign_request($user_id);
$cs_history      = get_user_callsign_requests($user_id);

function moderation_badge(string $state): string
{
    return match ($state) {
        'active'              => '<span class="badge badge-active">Active</span>',
        'suspended'           => '<span class="badge badge-pending">Suspended</span>',
        'banned'              => '<span class="badge badge-banned">Banned</span>',
        'muted_on_network'    => '<span class="badge badge-pending">Muted on Network</span>',
        default               => htmlspecialchars($state, ENT_QUOTES, 'UTF-8'),
    };
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">

        <?php if ($flash_ok !== null): ?>
        <div class="card" style="background:#14532d;color:#bbf7d0;margin-bottom:1rem;padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <?php if ($flash_error !== null): ?>
        <div class="card" style="background:#450a0a;color:#fca5a5;margin-bottom:1rem;padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section class="card">
            <p class="eyebrow">My Account</p>
            <h1>My Profile</h1>

            <?php if ($profile): ?>
            <div class="field-row">
                <span class="field-label">Username</span>
                <span class="field-value muted"><?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Callsign</span>
                <span class="field-value callsign" style="font-size:1rem;">
                    <?= htmlspecialchars($profile['callsign'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <div class="field-row">
                <span class="field-label">Email</span>
                <span class="field-value muted" style="font-size:0.9rem;">
                    <?= htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <div class="field-row">
                <span class="field-label">Status</span>
                <span class="field-value"><?= moderation_badge($profile['moderation_state']) ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Member Since</span>
                <span class="field-value muted" style="font-size:0.9rem;">
                    <?= htmlspecialchars(date('F j, Y', strtotime($profile['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Settings</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:1rem;">Display Name</h1>
            <form method="post">
                <input type="hidden" name="action" value="update_name">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="display_name">Name</label>
                    <input type="text" id="display_name" name="display_name" maxlength="128" required
                           value="<?= htmlspecialchars($profile['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <button type="submit" class="nav-link" style="min-height:44px;padding:0.5rem 1.25rem;">Save</button>
            </form>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Security</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:1rem;">Change Password</h1>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required
                           autocomplete="current-password"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8"
                           autocomplete="new-password"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <div class="field-row" style="margin-bottom:1rem;">
                    <label class="field-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                           autocomplete="new-password"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <button type="submit" class="nav-link" style="min-height:44px;padding:0.5rem 1.25rem;">Change Password</button>
            </form>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Email</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Email Address</h1>

            <?php if ($pending_email !== null): ?>
            <div class="card" style="background:#1e3a5f;border:1px solid #3b82f6;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.9rem;">
                Verification pending for: <strong><?= htmlspecialchars($pending_email['new_email'], ENT_QUOTES, 'UTF-8') ?></strong>
                <br><span class="muted" style="font-size:0.8rem;">Expires: <?= htmlspecialchars($pending_email['expires_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <form method="post" style="margin-bottom:1rem;">
                <input type="hidden" name="action" value="cancel_email_change">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="nav-link" style="font-size:0.85rem;min-height:44px;background:#450a0a;color:#fca5a5;">Cancel Email Change</button>
            </form>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="request_email_change">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="new_email">New Email Address</label>
                    <input type="email" id="new_email" name="new_email" required
                           placeholder="new@example.com"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <button type="submit" class="nav-link" style="min-height:44px;padding:0.5rem 1.25rem;">
                    <?= $pending_email !== null ? 'Request New Change' : 'Request Email Change' ?>
                </button>
            </form>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Callsign</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Callsign Update</h1>

            <?php if ($open_cs_request !== null): ?>
            <div class="card" style="background:#1e3a5f;border:1px solid #3b82f6;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.9rem;">
                Pending request for callsign: <span class="callsign"><?= htmlspecialchars($open_cs_request['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                <br><span class="muted" style="font-size:0.8rem;">Submitted: <?= htmlspecialchars($open_cs_request['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="request_callsign">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="new_callsign">New Callsign</label>
                    <input type="text" id="new_callsign" name="new_callsign" maxlength="10" required
                           placeholder="e.g. W7XYZ"
                           style="text-transform:uppercase;width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <div class="field-row" style="margin-bottom:1rem;">
                    <label class="field-label" for="explanation">Reason (optional)</label>
                    <input type="text" id="explanation" name="explanation" maxlength="255"
                           placeholder="e.g. Vanity call granted by FCC"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>
                <button type="submit" class="nav-link" style="min-height:44px;padding:0.5rem 1.25rem;">Request Callsign Update</button>
            </form>
            <?php endif; ?>

            <?php if (!empty($cs_history)): ?>
            <p class="section-title" style="margin-top:1rem;">Request History</p>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead><tr><th>Requested</th><th>Status</th><th>Notes</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($cs_history as $req): ?>
                        <tr>
                            <td class="callsign"><?= htmlspecialchars($req['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($req['status'] === 'approved'): ?>
                                <span class="badge badge-active">Approved</span>
                                <?php elseif ($req['status'] === 'denied'): ?>
                                <span class="badge badge-banned">Denied</span>
                                <?php else: ?>
                                <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted" style="font-size:0.85rem;">
                                <?= htmlspecialchars($req['review_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="muted" style="font-size:0.85rem;">
                                <?= htmlspecialchars($req['created_at'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <p style="margin-top:1.5rem;">
            <a href="/" class="nav-link">&#8592; Dashboard</a>
        </p>

    </main>
</body>
</html>
