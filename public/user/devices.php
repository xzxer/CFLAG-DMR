<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/devices/manager.php';

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
        }

        header('Location: /user/devices.php');
        exit;
    }
}

$devices = get_user_devices($user_id);

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
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Callsign</th>
                            <th>DMR ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Hardware</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $d): ?>
                        <tr>
                            <td class="callsign"><?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= status_badge($d['status']) ?>
                                <?php if ($d['status'] === 'denied' && $d['denied_reason'] !== null): ?>
                                <span class="muted" style="font-size:0.8rem;display:block;">
                                    <?= htmlspecialchars($d['denied_reason'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline-flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                                    <input type="hidden" name="action" value="edit_hw">
                                    <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="text" name="hardware_desc" value="<?= htmlspecialchars($d['hardware_desc'], ENT_QUOTES, 'UTF-8') ?>"
                                           style="font-size:0.85rem;padding:0.2rem 0.4rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:4px;min-width:140px;">
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;padding:0.25rem 0.6rem;">Save</button>
                                </form>
                            </td>
                            <td><?= htmlspecialchars(substr($d['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Remove this device?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;padding:0.25rem 0.6rem;background:#450a0a;color:#fca5a5;">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
