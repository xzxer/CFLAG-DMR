<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/openbridge.php';

start_session();
require_role('system_admin');

$actor_id    = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;
$edit_conn   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
        header('Location: /admin/config/openbridge.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $result = save_openbridge_connection($actor_id, $_POST);
        if ($result['ok']) {
            $flash_ok = 'OpenBridge connection saved.';
        } else {
            $flash_error = $result['error'];
        }

    } elseif ($action === 'toggle') {
        $id      = (int) ($_POST['id'] ?? 0);
        $enabled = (bool) ((int) ($_POST['enabled'] ?? 0));
        if ($id > 0) {
            toggle_openbridge_connection($id, !$enabled);
            $flash_ok = 'Connection ' . ($enabled ? 'disabled' : 'enabled') . '.';
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && delete_openbridge_connection($id)) {
            $flash_ok = 'Connection deleted.';
        } else {
            $flash_error = 'Could not delete connection.';
        }
    }

    header('Location: /admin/config/openbridge.php');
    exit;
}

$edit_id = (int) ($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $edit_conn = get_openbridge_connection($edit_id);
}

$connections = get_openbridge_connections();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>OpenBridge — CFLAG DMR</title>
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
            <h1>OpenBridge Connections</h1>
            <p class="muted">Each enabled connection generates an <code>[OPENBRIDGE-name]</code> section in the HBLink config.</p>
            <p style="margin-top:1.25rem;">
                <a href="/admin/config/" class="nav-link">&#8592; Config</a>
            </p>
        </section>

        <?php if (!empty($connections)): ?>
        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Connections</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Existing Connections</h1>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Remote</th>
                            <th>Port</th>
                            <th>Network ID</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($connections as $conn): ?>
                        <tr>
                            <td><?= htmlspecialchars($conn['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($conn['remote_address'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $conn['port'] ?></td>
                            <td><?= (int) $conn['network_id'] ?></td>
                            <td>
                                <?php if ($conn['enabled']): ?>
                                <span class="badge badge-active">Enabled</span>
                                <?php else: ?>
                                <span class="badge" style="background:#374151;color:#d1d5db;">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap; display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <a href="/admin/config/openbridge.php?edit=<?= (int) $conn['id'] ?>" class="nav-link" style="font-size:0.85rem;">Edit</a>

                                <form method="post" action="/admin/config/openbridge.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $conn['id'] ?>">
                                    <input type="hidden" name="enabled" value="<?= (int) $conn['enabled'] ?>">
                                    <button type="submit" class="btn" style="font-size:0.8rem;padding:0.2rem 0.6rem;background:#374151;">
                                        <?= $conn['enabled'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>

                                <form method="post" action="/admin/config/openbridge.php" style="display:inline;"
                                      onsubmit="return confirm('Delete this connection?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $conn['id'] ?>">
                                    <button type="submit" class="btn" style="font-size:0.8rem;padding:0.2rem 0.6rem;background:#7f1d1d;color:#fecaca;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow"><?= $edit_conn ? 'Edit' : 'Add' ?></p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">
                <?= $edit_conn ? 'Edit: ' . htmlspecialchars($edit_conn['name'], ENT_QUOTES, 'UTF-8') : 'New OpenBridge Connection' ?>
            </h1>

            <form method="post" action="/admin/config/openbridge.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($edit_conn): ?>
                <input type="hidden" name="id" value="<?= (int) $edit_conn['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="ob_name">Name</label>
                    <input type="text" id="ob_name" name="name" required maxlength="64"
                           value="<?= htmlspecialchars((string) ($edit_conn['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">Used as the config section name: <code>[OPENBRIDGE-NAME]</code></p>
                </div>

                <div class="form-group">
                    <label for="ob_remote_address">Remote Address</label>
                    <input type="text" id="ob_remote_address" name="remote_address" required maxlength="45"
                           value="<?= htmlspecialchars((string) ($edit_conn['remote_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="ob_port">Port</label>
                    <input type="number" id="ob_port" name="port" required min="1" max="65535"
                           value="<?= (int) ($edit_conn['port'] ?? 62035) ?>">
                </div>

                <div class="form-group">
                    <label for="ob_passphrase">Passphrase</label>
                    <input type="text" id="ob_passphrase" name="passphrase" required maxlength="15"
                           value="<?= htmlspecialchars((string) ($edit_conn['passphrase'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <p class="muted" style="font-size:0.8rem;margin-top:0.25rem;">1–15 characters</p>
                </div>

                <div class="form-group">
                    <label for="ob_network_id">Network ID</label>
                    <input type="number" id="ob_network_id" name="network_id" required min="1"
                           value="<?= (int) ($edit_conn['network_id'] ?? 0) ?>">
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="checkbox" name="enabled" value="1"
                               <?= ($edit_conn ? $edit_conn['enabled'] : 1) ? 'checked' : '' ?>>
                        Enabled
                    </label>
                </div>

                <div style="margin-top:1.5rem; display:flex; gap:1rem;">
                    <button type="submit" class="btn"><?= $edit_conn ? 'Save Changes' : 'Add Connection' ?></button>
                    <?php if ($edit_conn): ?>
                    <a href="/admin/config/openbridge.php" class="nav-link">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

    </main>
</body>
</html>
