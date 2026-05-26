<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('system_admin');

$detail_id = (int)($_GET['id'] ?? 0);

if ($detail_id > 0) {
    $detail = get_generation_detail($detail_id);
    if ($detail === null) {
        header('Location: /admin/config/history.php');
        exit;
    }
} else {
    $detail  = null;
    $history = get_generation_history(50);
}

$page_title = $detail !== null ? 'Generation Detail' : 'Generation History';
$active_nav = 'admin-config';
require_once $root . '/app/views/header.php';
?>

<?php if ($detail !== null): ?>

<div class="layout-single">

    <div class="panel" style="align-self:start;">
        <div class="panel-header">
            <span class="panel-title">Generation Detail</span>
            <div class="panel-actions">
                <a href="/admin/config/history.php" class="btn btn-ghost btn-xs">← History</a>
                <a href="/admin/config/" class="btn btn-ghost btn-xs">Config</a>
            </div>
        </div>
        <div class="panel-body">
            <div class="field-list">
                <div class="field-row">
                    <span class="field-key">Generated</span>
                    <span class="field-val col-ts"><?= htmlspecialchars($detail['generated_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-key">By</span>
                    <span class="field-val"><?= htmlspecialchars($detail['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-key">Result</span>
                    <span class="field-val">
                        <?= $detail['changed']
                            ? '<span class="badge badge-amber">Changed</span>'
                            : '<span class="badge badge-gray">No change</span>' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($detail['diff_text'] !== null && $detail['diff_text'] !== ''): ?>
    <div class="panel" style="align-self:start;margin-top:1rem;">
        <div class="panel-header">
            <span class="panel-title">Changes From Previous</span>
        </div>
        <div class="panel-body">
            <div class="code-viewer" style="max-height:400px;overflow-y:auto;"><?php
                foreach (explode("\n", $detail['diff_text']) as $line) {
                    $escaped = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                    if (str_starts_with($line, '- ')) {
                        echo '<span style="color:var(--red);">' . $escaped . '</span>' . "\n";
                    } elseif (str_starts_with($line, '+ ')) {
                        echo '<span style="color:var(--green);">' . $escaped . '</span>' . "\n";
                    } else {
                        echo $escaped . "\n";
                    }
                }
            ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel" style="align-self:start;margin-top:1rem;">
        <div class="panel-header">
            <span class="panel-title">Full Config Text</span>
        </div>
        <div class="panel-body">
            <div class="code-viewer" style="max-height:500px;overflow-y:auto;"><?= htmlspecialchars($detail['config_text'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

</div>

<?php else: ?>

<div class="layout-table-page">

    <div class="panel-compact" style="flex-shrink:0;">
        <div class="filter-bar">
            <span style="font-weight:600;color:var(--text);">Generation History</span>
            <div style="margin-left:auto;">
                <a href="/admin/config/" class="btn btn-ghost btn-sm">← Config</a>
            </div>
        </div>
    </div>

    <div class="panel" style="flex:1;min-height:0;">
        <div class="panel-body pad-none">
            <?php if (empty($history)): ?>
            <div class="empty-state"><strong>No generations recorded yet</strong></div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Generated</th>
                        <th>By</th>
                        <th>Result</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                    <tr>
                        <td class="col-mono" style="color:var(--text-3);"><?= (int)$row['id'] ?></td>
                        <td class="col-ts"><?= htmlspecialchars($row['generated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= $row['changed']
                                ? '<span class="badge badge-amber">Changed</span>'
                                : '<span class="badge badge-gray">No change</span>' ?>
                        </td>
                        <td class="col-actions">
                            <a href="/admin/config/history.php?id=<?= (int)$row['id'] ?>" class="btn btn-ghost btn-xs">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php endif; ?>

<?php require_once $root . '/app/views/footer.php'; ?>
