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
    if (preg_match('/^(\s*[^;#\s][^:=]*?)\s*([=:])\s*(.*)/s', $line, $m)) {
        $key = $m[1];
        $sep = $m[2];
        $val = rtrim($m[3]);
        if (preg_match('/PASSPHRASE|PASSWORD|SECRET/i', trim($key))) {
            $key_esc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $val_esc = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            return $key_esc . $sep . ' '
                 . '<span class="masked-value" data-val="' . $val_esc . '" data-visible="0">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</span>'
                 . ' <button type="button" class="btn btn-secondary btn-xs" style="vertical-align:middle;" onclick="toggleMask(this)">Show</button>';
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

$page_title = 'HBLink Config';
$active_nav = 'admin-hblink';
require_once $root . '/app/views/header.php';
?>

<div class="layout-single">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Config File</span>
            <div class="panel-actions">
                <a href="/admin/hblink/rules.php" class="btn btn-ghost btn-xs">Rules</a>
                <a href="/admin/hblink/status.php" class="btn btn-ghost btn-xs">Status</a>
            </div>
        </div>
        <div class="panel-body">

            <?php if ($status['config_drifted']): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;">
                &#9888; Config file has been modified since HBLink was last started.
            </div>
            <?php endif; ?>

            <?php if ($file['error'] !== null): ?>
            <div class="alert alert-error"><?= htmlspecialchars($file['error'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>

            <p style="font-size:0.8rem;color:var(--text-3);margin-bottom:0.875rem;">
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

        </div>
    </div>
</div>

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

<?php require_once $root . '/app/views/footer.php'; ?>
