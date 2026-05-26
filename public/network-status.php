<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';
require_once $root . '/app/lastheard/reader.php';

start_session();
require_login();

$user_id     = (int) $_SESSION['user_id'];
$is_sysadmin = user_has_role($user_id, 'system_admin');

$hblink_status = get_hblink_status();
$active_peers  = get_recently_active_dmr_ids(30);
$lh_result     = load_lastheard(10);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Network Status — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">

        <section class="card">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Network Status</h1>

            <?php if ($hblink_status['config_drifted'] && $is_sysadmin): ?>
            <div class="drift-warning" style="font-size:0.9rem;margin-bottom:1rem;">
                &#9888; Config modified since last HBLink start — consider regenerating config before reload
            </div>
            <?php endif; ?>

            <div class="field-row">
                <span class="field-label">Server</span>
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

            <?php if ($is_sysadmin): ?>
            <p style="margin-top:1rem; display:flex; gap:1.25rem; flex-wrap:wrap; font-size:0.9rem;">
                <a href="/admin/hblink/status.php" class="nav-link">Process Details</a>
                <a href="/admin/hblink/config.php" class="nav-link">Config File</a>
                <a href="/admin/hblink/rules.php" class="nav-link">Rules File</a>
                <?php if (is_dir($root . '/public/admin/config')): ?>
                <a href="/admin/config/" class="nav-link">Config Generator</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Devices</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">
                Recently Active
                <span class="muted" style="font-size:0.8rem;font-weight:400;"> (last 30 min)</span>
            </h1>
            <?php if (!empty($active_peers)): ?>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Callsign</th>
                            <th>DMR ID</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_peers as $peer): ?>
                        <tr>
                            <td class="callsign">
                                <?= htmlspecialchars($peer['callsign'] !== '' ? $peer['callsign'] : '—', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="color:#60a5fa;font-weight:700;">
                                <?= htmlspecialchars($peer['dmr_id'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="muted" style="font-size:0.85rem;">
                                <?= htmlspecialchars($peer['last_seen'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="muted" style="font-size:0.9rem;">No devices active in the last 30 minutes.</p>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Activity</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Recent Calls</h1>
            <?php if (!empty($lh_result['rows'])): ?>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr><th>Callsign</th><th>Talkgroup</th><th>Date / Time</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lh_result['rows'] as $row): ?>
                        <tr>
                            <td class="callsign"><?= htmlspecialchars($row['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : 'TG ' . $row['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="muted" style="font-size:0.9rem;">No recent activity.</p>
            <?php endif; ?>
            <p style="margin-top:0.75rem;">
                <a href="/last-heard.php" class="nav-link">View Full Log &#8594;</a>
            </p>
        </section>

        <p style="margin-top:1.5rem;">
            <a href="/" class="nav-link">&#8592; Dashboard</a>
        </p>

    </main>
</body>
</html>
