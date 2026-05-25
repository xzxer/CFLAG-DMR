<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/reader.php';

start_session();
require_role('system_admin');

$file = load_hblink_file('hblink_rules_path');

$lines = [];
if ($file['content'] !== null) {
    $normalized = str_replace("\r\n", "\n", $file['content']);
    $lines = explode("\n", $normalized);
    if (end($lines) === '') {
        array_pop($lines);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HBLink Rules — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page" style="align-items: flex-start; padding: 2rem 1rem;">
        <section class="card" style="width: min(960px, 100%);">
            <p class="eyebrow">HBLink</p>
            <h1>Rules File</h1>

            <?php if ($file['error'] !== null): ?>
            <div class="alert-error"><?= htmlspecialchars($file['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>

            <p class="muted" style="font-size:0.85rem; margin-bottom:1rem;">
                Last modified:
                <?= htmlspecialchars(
                    $file['modified_at'] !== null
                        ? date('Y-m-d H:i:s', $file['modified_at'])
                        : 'Unknown',
                    ENT_QUOTES, 'UTF-8'
                ) ?>
            </p>

            <div class="code-viewer">
                <ol>
                    <?php foreach ($lines as $line): ?>
                    <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <?php endif; ?>

            <p style="margin-top:1.5rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
                <a href="/admin/" class="nav-link">&#8592; Dashboard</a>
                <a href="/admin/hblink/config.php" class="nav-link">Config File</a>
                <a href="/admin/hblink/status.php" class="nav-link">Process Status</a>
            </p>
        </section>
    </main>
</body>
</html>
