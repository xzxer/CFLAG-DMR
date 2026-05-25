<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/reader.php';
require_once $root . '/app/hblink/process.php';

start_session();
require_role('system_admin');

$file   = load_hblink_file('hblink_cfg_path');
$status = get_hblink_status();

function render_config_line(string $line): string
{
    // Match KEY: VALUE or KEY = VALUE on non-comment lines
    if (preg_match('/^(\s*[^;#\s][^:=]*?)\s*([=:])\s*(.*)/s', $line, $m)) {
        $key = $m[1];
        $sep = $m[2];
        $val = rtrim($m[3]);
        if (preg_match('/PASSPHRASE|PASSWORD|SECRET/i', trim($key))) {
            $key_esc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $val_esc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            return $key_esc . $sep . ' '
                 . '<span class="masked-value" data-val="' . $val_esc . '" data-visible="0">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</span>'
                 . ' <button type="button" class="btn btn-sm btn-secondary" style="font-size:0.7rem;padding:0.15rem 0.6rem;min-height:44px;vertical-align:middle;" onclick="toggleMask(this)">Show</button>';
        }
    }
    return htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
}

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
    <title>HBLink Config — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page" style="align-items: flex-start; padding: 2rem 1rem;">
        <section class="card" style="width: min(960px, 100%);">
            <p class="eyebrow">HBLink</p>
            <h1>Config File</h1>

            <?php if ($status['config_drifted']): ?>
            <div class="drift-warning">
                &#9888; Config file has been modified since HBLink was last started.
            </div>
            <?php endif; ?>

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
                    <li><?= render_config_line($line) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <?php endif; ?>

            <p style="margin-top:1.5rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
                <a href="/admin/" class="nav-link">&#8592; Dashboard</a>
                <a href="/admin/hblink/rules.php" class="nav-link">Rules File</a>
                <a href="/admin/hblink/status.php" class="nav-link">Process Status</a>
            </p>
        </section>
    </main>

    <script>
    function toggleMask(btn) {
        var span = btn.previousElementSibling;
        if (span.dataset.visible === '1') {
            span.textContent = '••••••••';
            span.dataset.visible = '0';
            btn.textContent = 'Show';
        } else {
            span.textContent = span.dataset.val;
            span.dataset.visible = '1';
            btn.textContent = 'Hide';
        }
    }
    </script>
</body>
</html>
