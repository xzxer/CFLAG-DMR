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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'register') {
            $result = register_device($user_id, $_POST);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Device submitted for approval.' : $result['error'];

        } elseif ($action === 'edit_hw') {
            $device_id = (int) ($_POST['device_id'] ?? 0);
            $ok = $device_id > 0 && update_device_hardware($device_id, $user_id, $_POST['hardware_desc'] ?? '');
            $_SESSION[$ok ? '_flash_ok' : '_flash_error'] = $ok ? 'Hardware description updated.' : 'Could not update device.';

        } elseif ($action === 'delete') {
            $device_id = (int) ($_POST['device_id'] ?? 0);
            $ok = $device_id > 0 && delete_device($device_id, $user_id);
            $_SESSION[$ok ? '_flash_ok' : '_flash_error'] = $ok ? 'Device removed.' : 'Could not remove device.';

        } elseif ($action === 'add_sub') {
            $device_id    = (int) ($_POST['device_id'] ?? 0);
            $talkgroup_id = (int) ($_POST['talkgroup_id'] ?? 0);
            $timeslot     = (int) ($_POST['timeslot'] ?? 1);
            $result = add_subscription($device_id, $talkgroup_id, $timeslot, $user_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Subscription added.' : $result['error'];

        } elseif ($action === 'remove_sub') {
            $device_id    = (int) ($_POST['device_id'] ?? 0);
            $talkgroup_id = (int) ($_POST['talkgroup_id'] ?? 0);
            $timeslot     = (int) ($_POST['timeslot'] ?? 1);
            remove_subscription($device_id, $talkgroup_id, $timeslot, $user_id);
            $_SESSION['_flash_ok'] = 'Subscription removed.';

        } elseif ($action === 'request_join') {
            $device_id    = (int) ($_POST['device_id'] ?? 0);
            $talkgroup_id = (int) ($_POST['talkgroup_id'] ?? 0);
            $result = submit_join_request($device_id, $talkgroup_id, $user_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Access request submitted.' : $result['error'];
        }

        header('Location: /user/devices.php');
        exit;
    }
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$devices         = get_user_devices($user_id);
$open_talkgroups = get_public_talkgroups();

function dev_status_badge(string $status): string
{
    return match ($status) {
        'approved' => '<span class="badge badge-active">Approved</span>',
        'pending'  => '<span class="badge badge-amber">Pending</span>',
        'denied'   => '<span class="badge badge-red">Denied</span>',
        default    => '<span class="badge badge-gray">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
}

$page_title = 'My Devices';
$active_nav = 'my-devices';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside" style="flex:1;min-height:0;">

    <!-- Main: device list -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Registered Devices</span>
            <span class="panel-subtitle"><?= count($devices) ?> device<?= count($devices) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="panel-body">
            <?php if (empty($devices)): ?>
            <div class="empty-state">
                <strong>No devices yet</strong>
                <span>Register a hotspot or repeater using the form →</span>
            </div>
            <?php else: ?>
            <?php foreach ($devices as $d): ?>
            <div style="border:1px solid var(--border-2);border-radius:var(--radius);padding:0.875rem;margin-bottom:0.75rem;">

                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:0.625rem;">
                    <span style="font-weight:700;font-size:0.95rem;"><?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge badge-gray"><?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?= dev_status_badge($d['status']) ?>
                    <?php if ($d['status'] === 'denied' && $d['denied_reason'] !== null): ?>
                    <span style="font-size:0.72rem;color:var(--red);"><?= htmlspecialchars($d['denied_reason'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.5rem;">
                    <form method="post" style="display:inline-flex;gap:0.375rem;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="edit_hw">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="text" class="form-input" name="hardware_desc"
                               value="<?= htmlspecialchars($d['hardware_desc'], ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Hardware description"
                               style="width:180px;">
                        <button type="submit" class="btn btn-secondary btn-xs">Save</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Remove this device?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                    </form>
                </div>

                <?php if ($d['status'] === 'approved'): ?>
                    <?php $subs = get_subscriptions_for_device((int)$d['id']); ?>
                    <span class="form-section-label" style="display:block;margin-top:0.625rem;">Talkgroup Subscriptions</span>

                    <?php if (!empty($subs)): ?>
                    <table class="data-table" style="margin-bottom:0.5rem;">
                        <thead><tr><th>TG Name</th><th>TGID</th><th>Slot</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($subs as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['tg_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="col-mono" style="color:var(--accent-text);"><?= htmlspecialchars((string)$sub['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="col-mono">TS<?= htmlspecialchars((string)$sub['timeslot'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="col-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_sub">
                                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                        <input type="hidden" name="talkgroup_id" value="<?= (int)$sub['talkgroup_id'] ?>">
                                        <input type="hidden" name="timeslot" value="<?= (int)$sub['timeslot'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="font-size:0.72rem;color:var(--text-3);margin-bottom:0.5rem;">No talkgroup subscriptions.</p>
                    <?php endif; ?>

                    <?php if (!empty($open_talkgroups)): ?>
                    <form method="post" class="filter-bar" style="flex-wrap:wrap;">
                        <input type="hidden" name="action" value="add_sub">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <label>Talkgroup</label>
                        <select class="form-select" name="talkgroup_id" style="flex:1 1 160px;">
                            <?php foreach ($open_talkgroups as $tg): ?>
                            <option value="<?= (int)$tg['id'] ?>">
                                TG <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Slot</label>
                        <select class="form-select" name="timeslot" style="width:70px;">
                            <option value="1">TS1</option>
                            <option value="2">TS2</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-xs">Subscribe</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                <p style="font-size:0.72rem;color:var(--text-3);margin-top:0.25rem;">Talkgroup subscriptions available after approval.</p>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aside: register form -->
    <div class="panel" style="align-self:start;">
        <div class="panel-header"><span class="panel-title">Register a Device</span></div>
        <div class="panel-body">
            <form method="post">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="callsign">Callsign</label>
                    <input type="text" id="callsign" name="callsign"
                           maxlength="16" required placeholder="e.g. W7ABC">
                </div>
                <div class="form-group">
                    <label for="dmr_id">DMR ID</label>
                    <input type="text" id="dmr_id" name="dmr_id"
                           maxlength="7" required placeholder="7-digit ID">
                </div>
                <div class="form-group">
                    <label for="device_type">Type</label>
                    <select id="device_type" name="device_type" class="form-select">
                        <option value="hotspot">Hotspot</option>
                        <option value="repeater">Repeater</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="hardware_desc">Hardware (optional)</label>
                    <input type="text" id="hardware_desc" name="hardware_desc"
                           maxlength="255" placeholder="e.g. OpenSPOT4 Pro">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Register Device</button>
                </div>
            </form>
        </div>
    </div>

</div><!-- /.layout-primary-aside -->

<?php require_once $root . '/app/views/footer.php'; ?>
