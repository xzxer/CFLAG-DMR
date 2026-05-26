<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('system_admin');

$actor_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'generate') {
        $result = generate_hblink_config($actor_id);
        if ($result['ok']) {
            $_SESSION['_flash_ok'] = $result['changed']
                ? 'Config generated and written (changes detected).'
                : 'Config generated — no changes since last generation.';
        } else {
            $_SESSION['_flash_error'] = $result['error'] ?? 'Config generation failed.';
        }
    }
    header('Location: /admin/config/');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$history = get_generation_history(5);

$page_title = 'Network Config';
$active_nav = 'admin-config';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside">

    <div class="col-stack">

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Generate Config</span>
            </div>
            <div class="panel-body">
                <p style="color:var(--text-2);font-size:0.85rem;margin-bottom:1rem;">
                    Reads master settings, all approved device IDs, and enabled OpenBridge connections from the database
                    and writes the config file to the server. The previous file is replaced atomically.
                </p>
                <form method="post" action="/admin/config/">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="generate">
                    <button type="submit" class="btn btn-primary">Generate Config Now</button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Recent Generations</span>
                <div class="panel-actions">
                    <a href="/admin/config/history.php" class="btn btn-ghost btn-xs">Full History →</a>
                </div>
            </div>
            <div class="panel-body pad-none">
                <?php if (empty($history)): ?>
                <div class="empty-state"><strong>No config generated yet</strong></div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Generated</th>
                            <th>By</th>
                            <th>Result</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                        <tr>
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

    <div class="col-stack" style="align-self:start;">

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Config Sections</span>
            </div>
            <div class="panel-body">
                <nav style="display:flex;flex-direction:column;gap:0.375rem;">
                    <a href="/admin/config/master.php" class="btn btn-secondary" style="justify-content:flex-start;">Master Settings</a>
                    <a href="/admin/config/openbridge.php" class="btn btn-secondary" style="justify-content:flex-start;">OpenBridge Connections</a>
                    <a href="/admin/config/history.php" class="btn btn-ghost" style="justify-content:flex-start;">Generation History</a>
                </nav>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">About</span>
            </div>
            <div class="panel-body">
                <p style="color:var(--text-2);font-size:0.82rem;line-height:1.6;">
                    Generate the HBLink config file from the current database state —
                    master settings, approved peers, and OpenBridge connections.
                    The whitelist (REG_ACL) is derived from approved device DMR IDs.
                </p>
            </div>
        </div>

    </div>

</div>

<?php require_once $root . '/app/views/footer.php'; ?>
