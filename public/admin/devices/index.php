<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/devices/manager.php';

start_session();
require_role('system_admin');

$admin_id    = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action    = $_POST['action'] ?? '';
        $device_id = (int) ($_POST['device_id'] ?? 0);

        if ($action === 'approve' && $device_id > 0) {
            $result = approve_device($device_id, $admin_id);
            if ($result['ok']) {
                $flash_ok = 'Device approved.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'deny' && $device_id > 0) {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                $flash_error = 'A denial reason is required.';
            } else {
                deny_device($device_id, $admin_id, $reason);
                $flash_ok = 'Device denied.';
            }
        }

        header('Location: /admin/devices/');
        exit;
    }
}

$pending = get_pending_devices();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Device Approvals — CFLAG DMR</title>
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
            <p class="eyebrow">Admin</p>
            <h1>Device Approvals</h1>

            <?php if (empty($pending)): ?>
            <p class="muted" style="font-size:0.9rem;">No pending device registrations.</p>
            <?php else: ?>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Callsign</th>
                            <th>DMR ID</th>
                            <th>Type</th>
                            <th>Hardware</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $d): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($d['display_name'] ?: $d['username'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="muted" style="font-size:0.8rem;display:block;">
                                    <?= htmlspecialchars($d['email'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="callsign"><?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($d['hardware_desc'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(substr($d['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-start;padding-top:0.5rem;">
                                <form method="post">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="nav-link" style="font-size:0.85rem;min-height:44px;">Approve</button>
                                </form>
                                <form method="post" style="display:flex;flex-direction:column;gap:0.35rem;">
                                    <input type="hidden" name="action" value="deny">
                                    <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <textarea name="reason" rows="2" placeholder="Denial reason (required)"
                                              style="font-size:0.8rem;padding:0.3rem 0.5rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:4px;width:160px;resize:vertical;"
                                              required></textarea>
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;background:#450a0a;color:#fca5a5;">Deny</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <p style="margin-top:1rem;">
                <a href="/admin/" class="nav-link">← Dashboard</a>
            </p>
        </section>

    </main>
</body>
</html>
