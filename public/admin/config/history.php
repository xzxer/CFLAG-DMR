<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('system_admin');

$actor_id  = (int) $_SESSION['user_id'];
$flash_ok  = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_err = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

// ── POST: download or rollback ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
        header('Location: /admin/config/history.php');
        exit;
    }

    $post_action = $_POST['post_action'] ?? '';
    $post_id     = (int) ($_POST['generation_id'] ?? 0);

    if ($post_action === 'download' && $post_id > 0) {
        $record = get_config_generation_by_id($post_id);
        if ($record === null) {
            $_SESSION['_flash_error'] = "Generation #{$post_id} not found.";
            header('Location: /admin/config/history.php');
            exit;
        }
        $filename = 'hblink-generation-' . preg_replace('/[^0-9]/', '', $record['generated_at']) . '.cfg';
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($record['config_text']));
        echo $record['config_text'];
        exit;
    }

    if ($post_action === 'rollback' && $post_id > 0) {
        $result = rollback_to_generation($post_id, $actor_id);
        if ($result['ok']) {
            $_SESSION['_flash_ok'] = 'Rolled back to generation #' . $post_id . '. New generation #' . $result['new_generation_id'] . ' applied successfully.';
        } else {
            $_SESSION['_flash_error'] = 'Rollback failed: ' . ($result['error'] ?? 'Unknown error.');
        }
        header('Location: /admin/config/history.php');
        exit;
    }

    header('Location: /admin/config/history.php');
    exit;
}

// ── GET: running config modal ─────────────────────────────────────────────
$view_running = isset($_GET['view']) && $_GET['view'] === 'running';

// ── GET: detail or list ───────────────────────────────────────────────────
$detail_id = (int) ($_GET['id'] ?? 0);
$per_page  = 20;
$page      = max(1, (int) ($_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;

if ($detail_id > 0) {
    $detail = get_generation_detail($detail_id);
    if ($detail === null) {
        header('Location: /admin/config/history.php');
        exit;
    }
    $detail_source = $detail['rolled_back_from_id'] ? get_config_generation_by_id((int) $detail['rolled_back_from_id']) : null;
} else {
    $detail  = null;
    $total   = (int) get_db()->query('SELECT COUNT(*) FROM config_generation_history')->fetchColumn();
    $pages   = max(1, (int) ceil($total / $per_page));
    $history = get_generation_history($per_page, $offset);
}

$running_config = $view_running ? get_running_hblink_config() : null;

$page_title = $detail !== null ? 'Generation Detail' : 'Generation History';
$active_nav = 'admin-config';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):  ?><div class="alert alert-success" style="margin:0.75rem 0;"><?= htmlspecialchars($flash_ok,  ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_err): ?><div class="alert alert-error"   style="margin:0.75rem 0;"><?= htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<?php if ($running_config !== null): ?>
<!-- Running config modal overlay -->
<div style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;display:flex;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius);width:min(820px,100%);max-height:90vh;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;padding:0.75rem 1rem;border-bottom:1px solid var(--border);">
            <strong style="flex:1;">Running HBLink Config</strong>
            <a href="/admin/config/history.php<?= $page > 1 ? '?page=' . $page : '' ?>" class="btn btn-ghost btn-sm">Close</a>
        </div>
        <div style="flex:1;overflow-y:auto;padding:0;min-height:0;">
            <div class="code-viewer" style="border-radius:0;max-height:none;border:none;"><?= htmlspecialchars($running_config, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</div>
<?php elseif ($view_running): ?>
<div class="alert alert-error" style="margin:0.75rem 0;">Could not read running config file. Check HBLINK_CONFIG_PATH and file permissions.</div>
<?php endif; ?>

<?php if ($detail !== null): ?>

<div class="layout-single">

    <div class="panel" style="align-self:start;">
        <div class="panel-header">
            <span class="panel-title">Generation #<?= (int) $detail['id'] ?></span>
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
                    <span class="field-key">Changed</span>
                    <span class="field-val">
                        <?= $detail['changed']
                            ? '<span class="badge badge-amber">Changed</span>'
                            : '<span class="badge badge-gray">No change</span>' ?>
                    </span>
                </div>
                <?php if ($detail['rolled_back_from_id']): ?>
                <div class="field-row">
                    <span class="field-key">Rollback Source</span>
                    <span class="field-val">
                        <a href="/admin/config/history.php?id=<?= (int) $detail['rolled_back_from_id'] ?>" class="btn btn-ghost btn-xs">
                            #<?= (int) $detail['rolled_back_from_id'] ?><?= $detail_source ? ' &mdash; ' . htmlspecialchars($detail_source['generated_at'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($detail['applied']): ?>
                <div class="field-row">
                    <span class="field-key">Applied At</span>
                    <span class="field-val col-ts"><?= htmlspecialchars($detail['applied_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-key">Apply Result</span>
                    <span class="field-val">
                        <?php if ($detail['apply_success'] === null): ?>
                            <span class="badge badge-gray">Pending</span>
                        <?php elseif ($detail['apply_success']): ?>
                            <span class="badge badge-green">Success</span>
                        <?php else: ?>
                            <span class="badge badge-red">Failed</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($detail['apply_error'])): ?>
                <div class="field-row" style="align-items:flex-start;">
                    <span class="field-key">Error</span>
                    <span class="field-val" style="color:var(--red);font-size:0.82rem;"><?= htmlspecialchars($detail['apply_error'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($detail['backup_path'])): ?>
                <div class="field-row">
                    <span class="field-key">Backup</span>
                    <span class="field-val col-mono" style="font-size:0.78rem;"><?= htmlspecialchars($detail['backup_path'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="panel-footer" style="gap:0.5rem;display:flex;flex-wrap:wrap;">
            <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="post_action" value="download">
                <input type="hidden" name="generation_id" value="<?= (int) $detail['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Download .cfg</button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Roll back to generation #<?= (int) $detail['id'] ?>? This will overwrite the live config and restart HBLink.')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="post_action" value="rollback">
                <input type="hidden" name="generation_id" value="<?= (int) $detail['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">Rollback to This</button>
            </form>
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
            <div style="margin-left:auto;display:flex;gap:0.5rem;">
                <a href="/admin/config/history.php?view=running" class="btn btn-ghost btn-sm">Running Config</a>
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
                        <th>Changed</th>
                        <th>Applied</th>
                        <th>Apply Result</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                    <tr>
                        <td class="col-mono" style="color:var(--text-3);"><?= (int) $row['id'] ?></td>
                        <td class="col-ts"><?= htmlspecialchars($row['generated_at'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($row['rolled_back_from_id']): ?>
                            <br><span style="font-size:0.68rem;color:var(--text-3);">rollback ← #<?= (int) $row['rolled_back_from_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= $row['changed']
                                ? '<span class="badge badge-amber">Changed</span>'
                                : '<span class="badge badge-gray">No change</span>' ?>
                        </td>
                        <td>
                            <?= $row['applied']
                                ? '<span class="badge badge-blue">Applied</span>'
                                : '<span class="badge badge-gray">Not applied</span>' ?>
                        </td>
                        <td>
                            <?php if ($row['applied']): ?>
                                <?php if ($row['apply_success'] === null): ?>
                                    <span class="badge badge-gray">Pending</span>
                                <?php elseif ($row['apply_success']): ?>
                                    <span class="badge badge-green">Success</span>
                                <?php else: ?>
                                    <span class="badge badge-red" title="<?= htmlspecialchars($row['apply_error'] ?? '', ENT_QUOTES, 'UTF-8') ?>">Failed</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-3);">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-actions">
                            <a href="/admin/config/history.php?id=<?= (int) $row['id'] ?>" class="btn btn-ghost btn-xs">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php if ($pages > 1): ?>
        <div class="panel-footer">
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&larr;</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?>">&rarr;</a>
                <?php endif; ?>
            </div>
            <span style="font-size:0.72rem;color:var(--text-3);">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> records)</span>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php endif; ?>

<?php require_once $root . '/app/views/footer.php'; ?>
