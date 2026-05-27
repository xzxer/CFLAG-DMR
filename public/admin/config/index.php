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
    } elseif (($_POST['action'] ?? '') === 'apply') {
        $result = apply_hblink_config($actor_id);
        if ($result['ok']) {
            $_SESSION['_flash_ok'] = 'Config applied and HBLink restarted successfully at ' . date('H:i:s') . '.';
        } else {
            $_SESSION['_flash_error'] = 'Apply failed: ' . ($result['error'] ?? 'Unknown error.');
        }
    }
    header('Location: /admin/config/');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$history        = get_generation_history(5);
$hblink_status  = get_hblink_status();

// Diff preview: compare pending generated config against last stored generation
$show_preview = isset($_GET['preview']);
$preview_diff = null;
$preview_msg  = null;
if ($show_preview) {
    $settings = get_master_settings();
    if ($settings !== null) {
        require_once $root . '/app/config/openbridge.php';
        require_once $root . '/app/devices/manager.php';
        $pending_text = _render_config_text(
            $settings,
            get_whitelist_eligible_dmr_ids(),
            get_openbridge_connections(true)
        );
        $stmt_last = get_db()->prepare(
            'SELECT config_text FROM config_generation_history ORDER BY generated_at DESC LIMIT 1'
        );
        $stmt_last->execute();
        $last_row = $stmt_last->fetch();
        if ($last_row === false) {
            $preview_msg = 'No previous generation to compare against.';
        } elseif (trim($last_row['config_text']) === trim($pending_text)) {
            $preview_msg = 'No changes pending — the generated config matches the last stored generation.';
        } else {
            $preview_diff = _compute_diff($last_row['config_text'], $pending_text);
        }
    } else {
        $preview_msg = 'Master settings are not configured — cannot generate preview.';
    }
}

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
                <span class="panel-title">HBLink Controls</span>
                <div class="panel-actions">
                    <?php
                    $status_label = match($hblink_status) {
                        'running'    => 'Running',
                        'restarting' => 'Restarting',
                        'stopped',
                        'exited'     => 'Stopped',
                        default      => 'Unknown',
                    };
                    $status_class = match($hblink_status) {
                        'running'    => 'badge-green',
                        'restarting' => 'badge-amber',
                        'stopped',
                        'exited'     => 'badge-red',
                        default      => 'badge-gray',
                    };
                    ?>
                    <span class="badge <?= $status_class ?>">HBLink: <?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <div class="panel-body">
                <p style="color:var(--text-2);font-size:0.85rem;margin-bottom:1rem;">
                    <strong>Generate</strong> writes the config from the current DB state without restarting.
                    <strong>Apply &amp; Restart</strong> generates, backs up the current config, writes the new one, and restarts HBLink.
                </p>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <form method="post" action="/admin/config/">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="generate">
                        <button type="submit" class="btn btn-secondary">Generate Config</button>
                    </form>
                    <form method="post" action="/admin/config/" onsubmit="return confirm('Apply the current DB config and restart HBLink now? This will briefly disconnect active peers.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="apply">
                        <button type="submit" class="btn btn-primary">Apply &amp; Restart</button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($show_preview): ?>
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Pending Changes Preview</span>
                <div class="panel-actions">
                    <a href="/admin/config/" class="btn btn-ghost btn-xs">Close</a>
                </div>
            </div>
            <div class="panel-body">
                <?php if ($preview_msg !== null): ?>
                    <p style="color:var(--text-2);font-size:0.85rem;"><?= htmlspecialchars($preview_msg, ENT_QUOTES, 'UTF-8') ?></p>
                <?php elseif ($preview_diff !== null): ?>
                    <div class="code-viewer" style="max-height:380px;overflow-y:auto;"><?php
                        foreach (explode("\n", $preview_diff) as $line) {
                            $esc = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                            if (str_starts_with($line, '- ')) {
                                echo '<span style="color:var(--red);">' . $esc . '</span>' . "\n";
                            } elseif (str_starts_with($line, '+ ')) {
                                echo '<span style="color:var(--green);">' . $esc . '</span>' . "\n";
                            } else {
                                echo $esc . "\n";
                            }
                        }
                    ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

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
                    <a href="/admin/config/?preview=1" class="btn btn-ghost" style="justify-content:flex-start;">Preview Changes</a>
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
