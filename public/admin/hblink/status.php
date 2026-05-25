<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';

start_session();
require_role('system_admin');

$status = get_hblink_status();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HBLink Status — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">HBLink</p>
            <h1>Process Status</h1>

            <?php if ($status['config_drifted']): ?>
            <div class="drift-warning">
                &#9888; Config file has been modified since HBLink was last started.
            </div>
            <?php endif; ?>

            <div class="field-row" style="margin-bottom:1rem;">
                <span class="field-label">Status</span>
                <span class="field-value">
                    <?php if ($status['running']): ?>
                    <span class="badge badge-active">Running</span>
                    <?php else: ?>
                    <span class="badge badge-banned">Stopped</span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($status['running']): ?>

            <div class="field-row">
                <span class="field-label">PID</span>
                <span class="field-value"><?= htmlspecialchars((string) $status['pid'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="field-row">
                <span class="field-label">Uptime</span>
                <span class="field-value">
                    <?= htmlspecialchars(
                        $status['uptime_seconds'] !== null
                            ? format_uptime($status['uptime_seconds'])
                            : 'Unknown',
                        ENT_QUOTES, 'UTF-8'
                    ) ?>
                </span>
            </div>

            <div class="field-row">
                <span class="field-label">Started</span>
                <span class="field-value">
                    <?= htmlspecialchars(
                        $status['started_at'] !== null
                            ? date('Y-m-d H:i:s', $status['started_at'])
                            : 'Unknown',
                        ENT_QUOTES, 'UTF-8'
                    ) ?>
                </span>
            </div>

            <?php endif; ?>

            <p style="margin-top:1.5rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
                <a href="/admin/" class="nav-link">&#8592; Dashboard</a>
                <a href="/admin/hblink/config.php" class="nav-link">Config File</a>
                <a href="/admin/hblink/rules.php" class="nav-link">Rules File</a>
            </p>
        </section>
    </main>
</body>
</html>
