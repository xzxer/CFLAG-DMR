<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/settings.php';

start_session();
require_role('system_admin');

$actor_id    = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $result = save_master_settings($actor_id, $_POST);
        if ($result['ok']) {
            $flash_ok = 'Master settings saved.';
        } else {
            $flash_error = $result['error'];
        }
    }
    header('Location: /admin/config/master.php');
    exit;
}

$settings = get_master_settings();
$v = $settings ?? [
    'bind_address'   => '0.0.0.0',
    'port'           => 62031,
    'passphrase'     => 'passphrase',
    'report_address' => '127.0.0.1',
    'report_port'    => 4321,
    'ping_time'      => 5,
    'max_missed'     => 3,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Master Settings — CFLAG DMR</title>
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
        <div class="card" style="background:#7f1d1d;color:#fecaca;margin-bottom:1rem;padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section class="card">
            <p class="eyebrow">Network Config</p>
            <h1>Master Server Settings</h1>
            <p class="muted">These values populate the <code>[MASTER]</code>, <code>[GLOBAL]</code>, and <code>[REPORTS]</code> sections of the generated HBLink config.</p>
            <p style="margin-top:1.25rem;">
                <a href="/admin/config/" class="nav-link">&#8592; Config</a>
            </p>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <form method="post" action="/admin/config/master.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="bind_address">Bind Address</label>
                    <input type="text" id="bind_address" name="bind_address" required maxlength="45"
                           value="<?= htmlspecialchars((string) $v['bind_address'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">IP the HBLink master listens on (e.g. 0.0.0.0)</p>
                </div>

                <div class="form-group">
                    <label for="port">Port</label>
                    <input type="number" id="port" name="port" required min="1" max="65535"
                           value="<?= (int) $v['port'] ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">UDP port for peer connections (default 62031)</p>
                </div>

                <div class="form-group">
                    <label for="passphrase">Passphrase</label>
                    <input type="text" id="passphrase" name="passphrase" required maxlength="15"
                           value="<?= htmlspecialchars((string) $v['passphrase'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">Shared passphrase for peer auth — 1–15 characters</p>
                </div>

                <hr style="border:none;border-top:1px solid #374151;margin:1.5rem 0;">

                <div class="form-group">
                    <label for="report_address">Report Address</label>
                    <input type="text" id="report_address" name="report_address" required maxlength="45"
                           value="<?= htmlspecialchars((string) $v['report_address'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">Address HBMonv2 connects to for reports (default 127.0.0.1)</p>
                </div>

                <div class="form-group">
                    <label for="report_port">Report Port</label>
                    <input type="number" id="report_port" name="report_port" required min="1" max="65535"
                           value="<?= (int) $v['report_port'] ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">TCP port for HBMonv2 reporting (default 4321)</p>
                </div>

                <hr style="border:none;border-top:1px solid #374151;margin:1.5rem 0;">

                <div class="form-group">
                    <label for="ping_time">Ping Time (seconds)</label>
                    <input type="number" id="ping_time" name="ping_time" required min="1"
                           value="<?= (int) $v['ping_time'] ?>">
                </div>

                <div class="form-group">
                    <label for="max_missed">Max Missed Pings</label>
                    <input type="number" id="max_missed" name="max_missed" required min="1"
                           value="<?= (int) $v['max_missed'] ?>">
                </div>

                <div style="margin-top:1.5rem;">
                    <button type="submit" class="btn">Save Settings</button>
                </div>
            </form>
        </section>

    </main>
</body>
</html>
