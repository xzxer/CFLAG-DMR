<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/openbridge.php';

start_session();
require_role('system_admin');

$actor_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
        header('Location: /admin/config/openbridge.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $result = save_openbridge_connection($actor_id, $_POST);
        $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'OpenBridge connection saved.' : $result['error'];

    } elseif ($action === 'toggle') {
        $id      = (int)($_POST['id'] ?? 0);
        $enabled = (bool)((int)($_POST['enabled'] ?? 0));
        if ($id > 0) {
            toggle_openbridge_connection($id, !$enabled);
            $_SESSION['_flash_ok'] = 'Connection ' . ($enabled ? 'disabled' : 'enabled') . '.';
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && delete_openbridge_connection($id)) {
            $_SESSION['_flash_ok'] = 'Connection deleted.';
        } else {
            $_SESSION['_flash_error'] = 'Could not delete connection.';
        }
    }

    header('Location: /admin/config/openbridge.php');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$edit_id   = (int)($_GET['edit'] ?? 0);
$edit_conn = $edit_id > 0 ? get_openbridge_connection($edit_id) : null;

$connections = get_openbridge_connections();

$page_title = 'OpenBridge Connections';
$active_nav = 'admin-config';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside">

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">OpenBridge Connections</span>
            <span class="panel-subtitle"><?= count($connections) ?> configured</span>
            <div class="panel-actions">
                <a href="/admin/config/" class="btn btn-ghost btn-xs">← Config</a>
            </div>
        </div>
        <div class="panel-body pad-none">
            <?php if (empty($connections)): ?>
            <div class="empty-state">
                <strong>No OpenBridge connections configured</strong>
                <p>Add a connection using the form on the right.</p>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Remote</th>
                        <th>Port</th>
                        <th>Network ID</th>
                        <th>Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $conn): ?>
                    <tr>
                        <td style="font-weight:600;"><?= htmlspecialchars($conn['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-mono"><?= htmlspecialchars($conn['remote_address'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-mono"><?= (int)$conn['port'] ?></td>
                        <td class="col-mono"><?= (int)$conn['network_id'] ?></td>
                        <td>
                            <?= $conn['enabled']
                                ? '<span class="badge badge-active">Enabled</span>'
                                : '<span class="badge badge-gray">Disabled</span>' ?>
                        </td>
                        <td class="col-actions">
                            <div class="action-row" style="justify-content:flex-end;">
                                <a href="/admin/config/openbridge.php?edit=<?= (int)$conn['id'] ?>"
                                   class="btn btn-ghost btn-xs">Edit</a>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$conn['id'] ?>">
                                    <input type="hidden" name="enabled" value="<?= (int)$conn['enabled'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-xs">
                                        <?= $conn['enabled'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>

                                <form method="post" style="display:inline;"
                                      onsubmit="return confirm('Delete this connection?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$conn['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" style="align-self:start;">
        <div class="panel-header">
            <span class="panel-title"><?= $edit_conn ? 'Edit Connection' : 'New Connection' ?></span>
            <?php if ($edit_conn): ?>
            <div class="panel-actions">
                <a href="/admin/config/openbridge.php" class="btn btn-ghost btn-xs">Cancel</a>
            </div>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if ($edit_conn): ?>
            <p style="color:var(--text-2);font-size:0.82rem;margin-bottom:1rem;">
                Editing: <strong><?= htmlspecialchars($edit_conn['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
            <?php else: ?>
            <p style="color:var(--text-2);font-size:0.82rem;margin-bottom:1rem;">
                Each enabled connection generates an <code>[OPENBRIDGE-name]</code> section in the HBLink config.
            </p>
            <?php endif; ?>

            <form method="post" action="/admin/config/openbridge.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($edit_conn): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_conn['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="ob_name">Name</label>
                    <input type="text" id="ob_name" name="name" required maxlength="64"
                           value="<?= htmlspecialchars((string)($edit_conn['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">Used as section name: <code>[OPENBRIDGE-NAME]</code></p>
                </div>

                <div class="form-group">
                    <label for="ob_remote">Remote Address</label>
                    <input type="text" id="ob_remote" name="remote_address" required maxlength="45"
                           value="<?= htmlspecialchars((string)($edit_conn['remote_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="ob_port">Port</label>
                    <input type="number" id="ob_port" name="port" required min="1" max="65535"
                           value="<?= (int)($edit_conn['port'] ?? 62035) ?>">
                </div>

                <div class="form-group">
                    <label for="ob_passphrase">Passphrase</label>
                    <input type="text" id="ob_passphrase" name="passphrase" required maxlength="15"
                           value="<?= htmlspecialchars((string)($edit_conn['passphrase'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">1–15 characters</p>
                </div>

                <div class="form-group">
                    <label for="ob_network_id">Network ID</label>
                    <input type="number" id="ob_network_id" name="network_id" required min="1"
                           value="<?= (int)($edit_conn['network_id'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="enabled" value="1"
                               <?= ($edit_conn ? $edit_conn['enabled'] : 1) ? 'checked' : '' ?>>
                        Enabled
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_conn ? 'Save Changes' : 'Add Connection' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once $root . '/app/views/footer.php'; ?>
