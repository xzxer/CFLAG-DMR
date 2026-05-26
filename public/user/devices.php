<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/devices/manager.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_login();

$user    = current_user();
$user_id = (int) $_SESSION['user_id'];

if (in_array($user['moderation_state'] ?? '', ['suspended', 'banned'], true)) {
    redirect('/login.php');
}

$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'register') {
            $result = register_device($user_id, $_POST);
            if ($result['ok']) {
                $flash_ok = 'Device submitted for approval.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'edit_hw') {
            $device_id = (int) ($_POST['device_id'] ?? 0);
            if ($device_id > 0 && update_device_hardware($device_id, $user_id, $_POST['hardware_desc'] ?? '')) {
                $flash_ok = 'Hardware description updated.';
            } else {
                $flash_error = 'Could not update device.';
            }

        } elseif ($action === 'delete') {
            $device_id = (int) ($_POST['device_id'] ?? 0);
            if ($device_id > 0 && delete_device($device_id, $user_id)) {
                $flash_ok = 'Device removed.';
            } else {
                $flash_error = 'Could not remove device.';
            }

        } elseif ($action === 'add_sub') {
            $device_id   = (int) ($_POST['device_id'] ?? 0);
            $talkgroup_id = (int) ($_POST['talkgroup_id'] ?? 0);
            $timeslot    = (int) ($_POST['timeslot'] ?? 1);
            $result = add_subscription($device_id, $talkgroup_id, $timeslot, $user_id);
            if ($result['ok']) {
                $flash_ok = 'Subscription added.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'remove_sub') {
            $device_id    = (int) ($_POST['device_id'] ?? 0);
            $talkgroup_id = (int) ($_POST['talkgroup_id'] ?? 0);
            $timeslot     = (int) ($_POST['timeslot'] ?? 1);
            remove_subscription($device_id, $talkgroup_id, $timeslot, $user_id);
            $flash_ok = 'Subscription removed.';

        } elseif ($action === 'request_join') {
            $device_id    = (int) ($_POST['device_id'] ?? 0);
            $talkgroup_id = (int) ($_POST['talkgroup_id'] ?? 0);
            $result = submit_join_request($device_id, $talkgroup_id, $user_id);
            if ($result['ok']) {
                $flash_ok = 'Access request submitted.';
            } else {
                $flash_error = $result['error'];
            }
        }

        header('Location: /user/devices.php');
        exit;
    }
}

$devices          = get_user_devices($user_id);
$open_talkgroups  = get_public_talkgroups();

function status_badge(string $status): string
{
    return match ($status) {
        'approved' => '<span class="badge badge-active">Approved</span>',
        'pending'  => '<span class="badge badge-pending">Pending</span>',
        'denied'   => '<span class="badge badge-banned">Denied</span>',
        default    => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
    };
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Devices — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">

        <?php if ($flash_ok !== null): ?>
        <div class="card" style="background:#14532d; color:#bbf7d0; margin-bottom:1rem; padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if ($flash_error !== null): ?>
        <div class="card" style="background:#450a0a; color:#fca5a5; margin-bottom:1rem; padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section class="card">
            <p class="eyebrow">My Account</p>
            <h1>My Devices</h1>

            <?php if (empty($devices)): ?>
            <p class="muted" style="font-size:0.9rem;">No devices registered yet.</p>
            <?php else: ?>
            <?php foreach ($devices as $d): ?>
            <div style="border:1px solid #334155;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:baseline;margin-bottom:0.75rem;">
                    <span class="callsign" style="font-size:1rem;"><?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="muted" style="font-size:0.85rem;"><?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="muted" style="font-size:0.85rem;"><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?= status_badge($d['status']) ?>
                    <?php if ($d['status'] === 'denied' && $d['denied_reason'] !== null): ?>
                    <span class="muted" style="font-size:0.8rem;"><?= htmlspecialchars($d['denied_reason'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:0.5rem;">
                    <form method="post" style="display:inline-flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="edit_hw">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text" name="hardware_desc" value="<?= htmlspecialchars($d['hardware_desc'], ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Hardware description"
                               style="font-size:0.85rem;padding:0.2rem 0.4rem;background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:4px;min-width:160px;">
                        <button type="submit" class="nav-link" style="font-size:0.8rem;padding:0.2rem 0.5rem;min-height:44px;">Save</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Remove this device?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="nav-link" style="font-size:0.8rem;padding:0.2rem 0.5rem;min-height:44px;background:#450a0a;color:#fca5a5;">Remove</button>
                    </form>
                </div>

                <?php if ($d['status'] === 'approved'): ?>
                <?php $subs = get_subscriptions_for_device((int)$d['id']); ?>
                <p class="section-title" style="margin-top:0.75rem;">Talkgroup Subscriptions</p>

                <?php if (!empty($subs)): ?>
                <div class="lh-table-wrap" style="margin-bottom:0.75rem;">
                    <table class="lh-table">
                        <thead><tr><th>TG Name</th><th>TGID</th><th>Slot</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($subs as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['tg_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="color:#60a5fa;font-weight:700;"><?= htmlspecialchars((string)$sub['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>TS<?= htmlspecialchars((string)$sub['timeslot'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_sub">
                                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                        <input type="hidden" name="talkgroup_id" value="<?= (int)$sub['talkgroup_id'] ?>">
                                        <input type="hidden" name="timeslot" value="<?= (int)$sub['timeslot'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="nav-link" style="font-size:0.8rem;padding:0.2rem 0.5rem;min-height:44px;background:#450a0a;color:#fca5a5;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="muted" style="font-size:0.85rem;margin-bottom:0.75rem;">No talkgroup subscriptions.</p>
                <?php endif; ?>

                <?php if (!empty($open_talkgroups)): ?>
                <form method="post" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                    <input type="hidden" name="action" value="add_sub">
                    <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="flex:1 1 180px;margin-bottom:0;">
                        <label>Open Talkgroup</label>
                        <select name="talkgroup_id">
                            <?php foreach ($open_talkgroups as $tg): ?>
                            <option value="<?= (int)$tg['id'] ?>">
                                TG <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 80px;margin-bottom:0;">
                        <label>Timeslot</label>
                        <select name="timeslot">
                            <option value="1">TS1</option>
                            <option value="2">TS2</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm" style="margin-bottom:0;">Subscribe</button>
                </form>
                <?php else: ?>
                <p class="muted" style="font-size:0.85rem;">No open talkgroups available yet.</p>
                <?php endif; ?>

                <?php else: ?>
                <p class="muted" style="font-size:0.85rem;margin-top:0.5rem;">Talkgroup subscriptions available after approval.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Register</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:1rem;">Add a Device</h1>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="callsign">Callsign</label>
                    <input type="text" id="callsign" name="callsign" maxlength="16" required
                           placeholder="e.g. W7ABC"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>

                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="dmr_id">DMR ID</label>
                    <input type="text" id="dmr_id" name="dmr_id" maxlength="7" required
                           placeholder="7-digit ID e.g. 3171234"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>

                <div class="field-row" style="margin-bottom:0.75rem;">
                    <label class="field-label" for="device_type">Type</label>
                    <select id="device_type" name="device_type"
                            style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                        <option value="hotspot">Hotspot</option>
                        <option value="repeater">Repeater</option>
                    </select>
                </div>

                <div class="field-row" style="margin-bottom:1rem;">
                    <label class="field-label" for="hardware_desc">Hardware (optional)</label>
                    <input type="text" id="hardware_desc" name="hardware_desc" maxlength="255"
                           placeholder="e.g. OpenSPOT4 Pro"
                           style="width:100%;padding:0.5rem 0.75rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:6px;font-size:0.95rem;">
                </div>

                <button type="submit" class="nav-link" style="min-height:44px;padding:0.5rem 1.25rem;">Register Device</button>
            </form>
        </section>

        <p style="margin-top:1.5rem;">
            <a href="/user/" class="nav-link">← Dashboard</a>
        </p>

    </main>
</body>
</html>
