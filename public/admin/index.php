<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';

start_session();
require_role('admin');

$name          = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
$hblink_status = user_has_role((int) $_SESSION['user_id'], 'system_admin')
    ? get_hblink_status()
    : null;
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
            <p class="muted">You are logged in to the CFLAG DMR admin dashboard.</p>
            <p style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                <?php if (user_has_role((int) $_SESSION['user_id'], 'system_admin')): ?>
                    <a href="/admin/users/" class="nav-link">User Management</a>
                <?php endif; ?>
                <a href="/logout.php" class="nav-link">Log out</a>
            </p>
        </section>

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
