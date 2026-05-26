<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';
require_once $root . '/app/lastheard/reader.php';
require_once $root . '/app/devices/manager.php';
require_once $root . '/app/talkgroups/manager.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('admin');

$name                 = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
$is_sysadmin          = user_has_role((int) $_SESSION['user_id'], 'system_admin');
$hblink_status        = $is_sysadmin ? get_hblink_status()                    : null;
$lh_result            = $is_sysadmin ? load_lastheard(5)                      : null;
$pending_device_count = $is_sysadmin ? count(get_pending_devices())           : 0;
$pending_tg_count     = $is_sysadmin ? count(get_pending_talkgroup_requests()) : 0;
$last_gen             = $is_sysadmin ? (get_generation_history(1)[0] ?? null)  : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Welcome, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="muted">Admin dashboard — manage users, devices, talkgroups, and server configuration.</p>
            <p style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                <?php if (user_has_role((int) $_SESSION['user_id'], 'system_admin')): ?>
                    <a href="/admin/users/" class="nav-link">User Management</a>
                <?php endif; ?>
                <a href="/" class="nav-link">← Site Dashboard</a>
                <a href="/logout.php" class="nav-link">Log out</a>
            </p>
        </section>

        <?php if ($is_sysadmin): ?>
        <section class="card" style="margin-top: 1.5rem;">
            <p class="eyebrow">Devices</p>
            <h1 style="font-size: clamp(1.1rem, 2vw, 1.4rem); margin-bottom: 0.75rem;">Pending Approvals</h1>
            <?php if ($pending_device_count > 0): ?>
            <p style="font-size:0.95rem;">
                <span class="badge badge-pending"><?= $pending_device_count ?> pending</span>
            </p>
            <?php else: ?>
            <p class="muted" style="font-size:0.9rem;">No pending device registrations.</p>
            <?php endif; ?>
            <p style="margin-top: 0.75rem;">
                <a href="/admin/devices/" class="nav-link">Device Approvals &#8594;</a>
            </p>
        </section>

        <section class="card" style="margin-top: 1.5rem;">
            <p class="eyebrow">Talkgroups</p>
            <h1 style="font-size: clamp(1.1rem, 2vw, 1.4rem); margin-bottom: 0.75rem;">Management</h1>
            <?php if ($pending_tg_count > 0): ?>
            <p style="font-size:0.95rem;">
                <span class="badge badge-pending"><?= $pending_tg_count ?> pending</span> talkgroup requests
            </p>
            <?php else: ?>
            <p class="muted" style="font-size:0.9rem;">No pending talkgroup requests.</p>
            <?php endif; ?>
            <p style="margin-top: 0.75rem; display:flex; gap:1.25rem; flex-wrap:wrap;">
                <a href="/admin/talkgroups/" class="nav-link">Talkgroup Catalog &#8594;</a>
                <?php if ($pending_tg_count > 0): ?>
                <a href="/admin/talkgroups/requests.php" class="nav-link">Review Requests &#8594;</a>
                <?php endif; ?>
            </p>
        </section>
        <?php endif; ?>

        <?php if ($lh_result !== null): ?>
        <section class="card" style="margin-top: 1.5rem;">
            <p class="eyebrow">Activity</p>
            <h1 style="font-size: clamp(1.1rem, 2vw, 1.4rem); margin-bottom: 0.75rem;">Last Heard</h1>
            <?php if ($lh_result['error'] !== null || empty($lh_result['rows'])): ?>
            <p class="muted" style="font-size:0.9rem;">No activity recorded yet.</p>
            <?php else: ?>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr><th>Callsign</th><th>Talkgroup</th><th>Date / Time</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lh_result['rows'] as $row): ?>
                        <tr>
                            <td class="callsign"><?= htmlspecialchars($row['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : 'TG '.$row['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <p style="margin-top: 0.75rem;">
                <a href="/last-heard.php" class="nav-link">View Full Log &#8594;</a>
            </p>
        </section>
        <?php endif; ?>

        <?php if ($last_gen !== null): ?>
        <section class="card" style="margin-top: 1.5rem;">
            <p class="eyebrow">Network Config</p>
            <h1 style="font-size: clamp(1.1rem, 2vw, 1.4rem); margin-bottom: 0.75rem;">HBLink Config Generation</h1>
            <div class="field-row">
                <span class="field-label">Last Generated</span>
                <span class="field-value muted" style="font-size:0.9rem;"><?= htmlspecialchars($last_gen['generated_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Result</span>
                <span class="field-value">
                    <?php if ($last_gen['changed']): ?>
                    <span class="badge badge-pending">Changed</span>
                    <?php else: ?>
                    <span class="badge" style="background:#374151;color:#d1d5db;">No change</span>
                    <?php endif; ?>
                </span>
            </div>
            <p style="margin-top: 1rem;">
                <a href="/admin/config/" class="nav-link">Manage Config &#8594;</a>
            </p>
        </section>
        <?php elseif ($is_sysadmin): ?>
        <section class="card" style="margin-top: 1.5rem;">
            <p class="eyebrow">Network Config</p>
            <h1 style="font-size: clamp(1.1rem, 2vw, 1.4rem); margin-bottom: 0.75rem;">HBLink Config Generation</h1>
            <p class="muted" style="font-size:0.9rem;">No config has been generated yet.</p>
            <p style="margin-top: 0.75rem;">
                <a href="/admin/config/" class="nav-link">Generate Config &#8594;</a>
            </p>
        </section>
        <?php endif; ?>

        <?php if ($hblink_status !== null): ?>
        <section class="card" style="margin-top: 1.5rem;">
            <p class="eyebrow">HBLink</p>
            <h1 style="font-size: clamp(1.1rem, 2vw, 1.4rem); margin-bottom: 0.75rem;">Server Status</h1>

            <?php if ($hblink_status['config_drifted']): ?>
            <div class="drift-warning" style="font-size:0.9rem;">
                &#9888; Config modified since last start
            </div>
            <?php endif; ?>

            <div class="field-row">
                <span class="field-label">Engine</span>
                <span class="field-value">
                    <?php if ($hblink_status['running']): ?>
                    <span class="badge badge-active">Running</span>
                    <?php else: ?>
                    <span class="badge badge-banned">Stopped</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($hblink_status['running'] && $hblink_status['uptime_seconds'] !== null): ?>
            <div class="field-row">
                <span class="field-label">Uptime</span>
                <span class="field-value muted" style="font-size:0.9rem;">
                    <?= htmlspecialchars(format_uptime($hblink_status['uptime_seconds']), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <?php endif; ?>

            <p style="margin-top: 1rem; display: flex; gap: 1.25rem; flex-wrap: wrap; font-size: 0.9rem;">
                <a href="/admin/hblink/config.php" class="nav-link">Config File</a>
                <a href="/admin/hblink/rules.php" class="nav-link">Rules File</a>
                <a href="/admin/hblink/status.php" class="nav-link">Process Status</a>
            </p>
        </section>
        <?php endif; ?>

    </main>
</body>
</html>
