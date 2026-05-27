<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/settings.php';

start_session();
require_role('system_admin');

$actor_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $result = save_master_settings($actor_id, $_POST);
        $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Master settings saved.' : $result['error'];
    }
    header('Location: /admin/config/master.php');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$settings = get_master_settings();
$v = $settings ?? [
    'bind_address'   => '0.0.0.0',
    'public_address' => '',
    'port'           => 62031,
    'passphrase'     => 'passphrase',
    'report_address' => '127.0.0.1',
    'report_port'    => 4321,
    'ping_time'      => 5,
    'max_missed'     => 3,
];

$page_title = 'Master Settings';
$active_nav = 'admin-config';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-single">
    <div class="panel" style="align-self:start;max-width:560px;">
        <div class="panel-header">
            <span class="panel-title">Master Server Settings</span>
            <div class="panel-actions">
                <a href="/admin/config/" class="btn btn-ghost btn-xs">← Config</a>
            </div>
        </div>
        <div class="panel-body">
            <p style="color:var(--text-2);font-size:0.82rem;margin-bottom:1.25rem;">
                These values populate the <code>[MASTER]</code>, <code>[GLOBAL]</code>, and <code>[REPORTS]</code> sections of the generated HBLink config.
            </p>

            <form method="post" action="/admin/config/master.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="public_address">Public Address</label>
                    <input type="text" id="public_address" name="public_address" required maxlength="253"
                           value="<?= htmlspecialchars((string)$v['public_address'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. dmr.cflag.net or 203.0.113.10">
                    <p class="form-hint">Hostname or IP that hotspots and repeaters connect to. Used in generated device configs.</p>
                </div>

                <div class="form-group">
                    <label for="bind_address">Bind Address</label>
                    <input type="text" id="bind_address" name="bind_address" required maxlength="45"
                           value="<?= htmlspecialchars((string)$v['bind_address'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">Local IP the HBLink master process binds to (e.g. 0.0.0.0)</p>
                </div>

                <div class="form-group">
                    <label for="port">Port</label>
                    <input type="number" id="port" name="port" required min="1" max="65535"
                           value="<?= (int)$v['port'] ?>">
                    <p class="form-hint">UDP port for peer connections (default 62031)</p>
                </div>

                <div class="form-group">
                    <label for="passphrase">Passphrase</label>
                    <input type="text" id="passphrase" name="passphrase" required maxlength="15"
                           value="<?= htmlspecialchars((string)$v['passphrase'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">Shared passphrase for peer auth — 1–15 characters</p>
                </div>

                <hr style="border:none;border-top:1px solid var(--border-1);margin:1.25rem 0;">

                <div class="form-group">
                    <label for="report_address">Report Address</label>
                    <input type="text" id="report_address" name="report_address" required maxlength="45"
                           value="<?= htmlspecialchars((string)$v['report_address'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">Address HBMonv2 connects to for reports (default 127.0.0.1)</p>
                </div>

                <div class="form-group">
                    <label for="report_port">Report Port</label>
                    <input type="number" id="report_port" name="report_port" required min="1" max="65535"
                           value="<?= (int)$v['report_port'] ?>">
                    <p class="form-hint">TCP port for HBMonv2 reporting (default 4321)</p>
                </div>

                <hr style="border:none;border-top:1px solid var(--border-1);margin:1.25rem 0;">

                <div class="form-group">
                    <label for="ping_time">Ping Time (seconds)</label>
                    <input type="number" id="ping_time" name="ping_time" required min="1"
                           value="<?= (int)$v['ping_time'] ?>">
                </div>

                <div class="form-group">
                    <label for="max_missed">Max Missed Pings</label>
                    <input type="number" id="max_missed" name="max_missed" required min="1"
                           value="<?= (int)$v['max_missed'] ?>">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <a href="/admin/config/" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
