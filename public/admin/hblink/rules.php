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

$page_title = 'HBLink Rules';
$active_nav = 'admin-hblink';
require_once $root . '/app/views/header.php';
?>

<div class="layout-single">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Rules File</span>
            <div class="panel-actions">
                <a href="/admin/hblink/config.php" class="btn btn-ghost btn-xs">Config</a>
                <a href="/admin/hblink/status.php" class="btn btn-ghost btn-xs">Status</a>
            </div>
        </div>
        <div class="panel-body">

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
                    <li><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
