<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/devices/manager.php';
require_once $root . '/app/talkgroups/manager.php';
require_once $root . '/app/config/settings.php';
require_once $root . '/app/config/peer_auth_generator.php';
require_once $root . '/app/config/routing_generator.php';

start_session();
require_login();

$user    = current_user();
$user_id = (int) $_SESSION['user_id'];

if (in_array($user['moderation_state'] ?? '', ['suspended', 'banned'], true)) {
    redirect('/login.php');
}

$reopen_id = 0; // device ID to reopen edit modal after redirect

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action    = $_POST['action'] ?? '';
        $device_id = (int) ($_POST['device_id'] ?? 0);

        if ($action === 'register') {
            $result = register_device($user_id, $_POST);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok']
                ? 'Device submitted for approval.'
                : $result['error'];

        } elseif ($action === 'edit_details' && $device_id > 0) {
            $result = update_device_details($device_id, $user_id, $_POST['callsign'] ?? '', $_POST['hardware_desc'] ?? '');
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Device updated.' : $result['error'];
            if ($result['ok']) $_SESSION['_reopen_edit'] = $device_id;

        } elseif ($action === 'edit_features' && $device_id > 0) {
            $ok = update_device_features(
                $device_id, $user_id,
                isset($_POST['tg_rewrite_enabled']),
                $_POST['tg_rewrite_prefix'] ?? ''
            );
            $_SESSION[$ok ? '_flash_ok' : '_flash_error'] = $ok ? 'Features saved.' : 'Could not save features.';
            if ($ok) $_SESSION['_reopen_edit'] = $device_id;

        } elseif ($action === 'edit_ssid' && $device_id > 0) {
            $result = update_device_ssid($device_id, $user_id, (int) ($_POST['ssid_suffix'] ?? 0));
            if ($result['ok']) {
                generate_peer_auth_json();
                generate_bridge_routes_json();
                $_SESSION['_flash_ok'] = 'SSID suffix updated. New peer ID: ' . $result['peer_id'];
                $_SESSION['_reopen_edit'] = $device_id;
            } else {
                $_SESSION['_flash_error'] = $result['error'];
                $_SESSION['_reopen_edit'] = $device_id;
            }

        } elseif ($action === 'edit_location' && $device_id > 0) {
            $result = update_device_location($device_id, $user_id, [
                'lat'          => $_POST['lat']          ?? '',
                'lon'          => $_POST['lon']          ?? '',
                'rx_freq'      => $_POST['rx_freq']      ?? '',
                'tx_freq'      => $_POST['tx_freq']      ?? '',
                'tx_power'     => $_POST['tx_power']     ?? '',
                'height_m'     => $_POST['height_m']     ?? '',
                'location_desc'=> $_POST['location_desc']?? '',
                'station_desc' => $_POST['station_desc'] ?? '',
                'station_url'  => $_POST['station_url']  ?? '',
            ]);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Location saved.' : $result['error'];
            if ($result['ok']) $_SESSION['_reopen_edit'] = $device_id;

        } elseif ($action === 'delete' && $device_id > 0) {
            $ok = delete_device($device_id, $user_id);
            $_SESSION[$ok ? '_flash_ok' : '_flash_error'] = $ok ? 'Device removed.' : 'Could not remove device.';

        } elseif ($action === 'add_sub' && $device_id > 0) {
            $result = add_subscription($device_id, (int)($_POST['talkgroup_id']??0), (int)($_POST['timeslot']??1), $user_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Subscription added.' : $result['error'];

        } elseif ($action === 'remove_sub' && $device_id > 0) {
            remove_subscription($device_id, (int)($_POST['talkgroup_id']??0), (int)($_POST['timeslot']??1), $user_id);
            $_SESSION['_flash_ok'] = 'Subscription removed.';

        } elseif ($action === 'request_join' && $device_id > 0) {
            $result = submit_join_request($device_id, (int)($_POST['talkgroup_id']??0), $user_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Access request submitted.' : $result['error'];
        }

        $redirect = '/user/devices.php';
        if ($reopen_id = (int)($_SESSION['_reopen_edit'] ?? 0)) {
            $redirect .= '?edit=' . $reopen_id;
            unset($_SESSION['_reopen_edit']);
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);
$auto_edit   = (int) ($_GET['edit'] ?? 0);

$devices         = get_user_devices($user_id);
$open_talkgroups = get_public_talkgroups();
$user_base_dmr   = (int) ($user['dmr_id'] ?? 0);
$master_settings = get_master_settings();
$dmr_server_host = $master_settings ? ($master_settings['public_address'] ?: '') : '';
$dmr_server_port = $master_settings ? (int) $master_settings['port'] : 62031;

function dev_status_badge(string $status): string
{
    return match ($status) {
        'approved' => '<span class="badge badge-active">Approved</span>',
        'pending'  => '<span class="badge badge-amber">Pending</span>',
        'denied'   => '<span class="badge badge-red">Denied</span>',
        default    => '<span class="badge badge-gray">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
}

$page_title = 'My Devices';
$active_nav = 'my-devices';
require_once $root . '/app/views/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside" style="flex:1;min-height:0;">

    <!-- Device list -->
    <div class="panel" style="flex:1;min-height:0;display:flex;flex-direction:column;">
        <div class="panel-header">
            <span class="panel-title">Registered Devices</span>
            <span style="font-size:0.75rem;color:var(--text-3);"><?= count($devices) ?> device<?= count($devices) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="panel-body" style="overflow-y:auto;">
            <?php if (empty($devices)): ?>
            <div class="empty-state">
                <strong>No devices yet</strong>
                <span>Register a hotspot or repeater using the form →</span>
            </div>
            <?php else: ?>
            <?php foreach ($devices as $d): ?>
            <div style="border:1px solid var(--border-2);border-radius:var(--radius);padding:0.875rem 1rem;margin-bottom:0.75rem;">

                <!-- Header row -->
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:0.75rem;">
                    <span style="font-weight:700;font-size:1rem;color:var(--accent-text);font-family:monospace;">
                        <?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="badge badge-gray col-mono" style="font-size:0.7rem;">
                        <?php if ($d['device_type'] === 'hotspot' && $d['peer_id']): ?>
                            <?= htmlspecialchars((string)$d['peer_id'], ENT_QUOTES, 'UTF-8') ?>
                            <span style="opacity:0.55;">(<?= (string)$d['dmr_id'] ?>+<?= str_pad((string)($d['ssid_suffix']??0),2,'0',STR_PAD_LEFT) ?>)</span>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$d['dmr_id'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </span>
                    <span style="font-size:0.72rem;color:var(--text-3);"><?= htmlspecialchars(ucfirst($d['device_type']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?= dev_status_badge($d['status']) ?>
                    <?php if ($d['status'] === 'denied' && $d['denied_reason'] !== null): ?>
                    <span style="font-size:0.71rem;color:var(--red);"><?= htmlspecialchars($d['denied_reason'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <div style="margin-left:auto;display:flex;gap:0.375rem;">
                        <?php if ($d['status'] === 'approved' && $d['device_passphrase']): ?>
                        <button type="button" class="btn btn-ghost btn-xs"
                                onclick="openConfigModal(
                                    <?= htmlspecialchars(json_encode($d['callsign']),ENT_QUOTES,'UTF-8') ?>,
                                    <?= (int)($d['peer_id'] ?: $d['dmr_id']) ?>,
                                    <?= htmlspecialchars(json_encode($d['device_passphrase']),ENT_QUOTES,'UTF-8') ?>,
                                    <?= $d['tg_rewrite_enabled'] ? 'true' : 'false' ?>,
                                    <?= htmlspecialchars(json_encode($d['tg_rewrite_prefix']??''),ENT_QUOTES,'UTF-8') ?>
                                )">Get Config</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary btn-xs"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)">
                            Edit
                        </button>
                    </div>
                </div>

                <?php if ($d['status'] === 'approved' && $d['device_passphrase']): ?>
                <div style="display:flex;align-items:center;gap:0.875rem;padding:0.4rem 0.625rem;background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;margin-bottom:0.75rem;font-size:0.78rem;">
                    <span style="color:var(--text-3);">Passphrase</span>
                    <code style="color:var(--accent-text);letter-spacing:0.04em;font-size:0.82rem;">
                        <?= htmlspecialchars($d['device_passphrase'], ENT_QUOTES, 'UTF-8') ?>
                    </code>
                </div>
                <?php endif; ?>

                <?php if ($d['status'] === 'approved'): ?>
                    <?php $subs = get_subscriptions_for_device((int)$d['id']); ?>
                    <span class="form-section-label" style="display:block;margin-bottom:0.375rem;">Talkgroup Subscriptions</span>

                    <?php if (!empty($subs)): ?>
                    <div class="lh-table-wrap" style="margin-bottom:0.5rem;">
                    <table class="data-table">
                        <thead><tr><th>TG Name</th><th>TGID</th><th>Slot</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($subs as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['tg_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="col-mono" style="color:var(--accent-text);"><?= (int)$sub['tgid'] ?></td>
                                <td class="col-mono">TS<?= (int)$sub['timeslot'] ?></td>
                                <td class="col-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_sub">
                                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                        <input type="hidden" name="talkgroup_id" value="<?= (int)$sub['talkgroup_id'] ?>">
                                        <input type="hidden" name="timeslot" value="<?= (int)$sub['timeslot'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                                        <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php else: ?>
                    <p style="font-size:0.72rem;color:var(--text-3);margin-bottom:0.5rem;">No subscriptions yet.</p>
                    <?php endif; ?>

                    <?php if (!empty($open_talkgroups)): ?>
                    <form method="post" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
                        <input type="hidden" name="action" value="add_sub">
                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                        <select name="talkgroup_id" class="form-select" style="flex:1;min-width:160px;">
                            <?php foreach ($open_talkgroups as $tg): ?>
                            <option value="<?= (int)$tg['id'] ?>">TG <?= (int)$tg['tgid'] ?> — <?= htmlspecialchars($tg['name'],ENT_QUOTES,'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="timeslot" class="form-select" style="width:70px;">
                            <option value="1">TS1</option>
                            <option value="2">TS2</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-xs">Subscribe</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                <p style="font-size:0.72rem;color:var(--text-3);">Talkgroup subscriptions available after approval.</p>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Register aside -->
    <div class="col-stack" style="align-self:start;">
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Register Device</span></div>
            <div class="panel-body">
                <div style="display:flex;gap:0;border:1px solid var(--border-2);border-radius:var(--radius);overflow:hidden;margin-bottom:1rem;">
                    <button type="button" id="toggle-hotspot" onclick="setDeviceType('hotspot')"
                            style="flex:1;border-radius:0;border:none;font-size:0.8rem;padding:0.4rem 0.5rem;cursor:pointer;background:var(--accent);color:#fff;">
                        Hotspot
                    </button>
                    <button type="button" id="toggle-repeater" onclick="setDeviceType('repeater')"
                            style="flex:1;border-radius:0;border:none;font-size:0.8rem;padding:0.4rem 0.5rem;cursor:pointer;background:none;color:var(--text-2);">
                        Repeater
                    </button>
                </div>
                <form method="post" id="register-form">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                    <input type="hidden" name="device_type" id="reg-device-type" value="hotspot">

                    <div class="form-group">
                        <label for="reg_callsign">Callsign</label>
                        <input type="text" id="reg_callsign" name="callsign" maxlength="16" required
                               placeholder="e.g. W7ABC" style="text-transform:uppercase;max-width:160px;">
                    </div>

                    <div id="hotspot-fields">
                        <?php if ($user_base_dmr >= 1000000): ?>
                        <div class="form-group">
                            <label>Your base DMR ID</label>
                            <code style="display:block;font-size:0.9rem;color:var(--accent-text);padding:0.25rem 0;"><?= (int)$user_base_dmr ?></code>
                            <p class="form-hint">From your account.</p>
                        </div>
                        <div class="form-group">
                            <label for="reg_ssid">SSID Suffix</label>
                            <select id="reg_ssid" name="ssid_suffix" onchange="updatePeerId()" style="width:80px;">
                                <?php for ($s = 1; $s <= 99; $s++): ?>
                                <option value="<?= $s ?>"><?= str_pad((string)$s,2,'0',STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Connection ID</label>
                            <div id="peer_id_preview" style="font-size:0.95rem;font-weight:700;color:var(--accent-text);font-family:monospace;padding:0.25rem 0;">
                                <?= ($user_base_dmr >= 1000000) ? (string)($user_base_dmr * 100 + 1) : '—' ?>
                            </div>
                            <p class="form-hint">Enter this in your hotspot's DMR ID field.</p>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-error" style="font-size:0.78rem;">No base DMR ID — contact an admin.</div>
                        <?php endif; ?>
                    </div>

                    <div id="repeater-fields" style="display:none;">
                        <div class="form-group">
                            <label for="reg_dmr_id">Repeater DMR ID</label>
                            <input type="text" id="reg_dmr_id" name="dmr_id" maxlength="7"
                                   placeholder="7-digit ID" style="max-width:160px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reg_hardware">Hardware <span style="color:var(--text-3);font-weight:normal;">(optional)</span></label>
                        <input type="text" id="reg_hardware" name="hardware_desc" maxlength="255"
                               placeholder="e.g. OpenSPOT4 Pro" style="max-width:240px;">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="reg-submit-btn">Register Hotspot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════ EDIT MODAL -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:1rem;">
<div style="background:var(--bg-surface);border:1px solid var(--border-2);border-radius:var(--radius);width:min(720px,96vw);max-height:90vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,0.5);">

    <!-- Modal header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1.125rem;border-bottom:1px solid var(--border-1);flex-shrink:0;">
        <div>
            <span id="em-title" style="font-weight:700;font-size:1rem;font-family:monospace;color:var(--accent-text);margin-right:0.5rem;"></span>
            <span id="em-badge" style="font-size:0.72rem;color:var(--text-3);"></span>
        </div>
        <button type="button" onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;color:var(--text-3);font-size:1.3rem;line-height:1;padding:0 0.25rem;">&times;</button>
    </div>

    <!-- Tabs -->
    <div style="display:flex;border-bottom:1px solid var(--border-1);flex-shrink:0;padding:0 1.125rem;">
        <button type="button" class="em-tab em-tab-active" onclick="switchTab('device')" id="tab-device"
                style="padding:0.625rem 0.875rem;font-size:0.82rem;border:none;border-bottom:2px solid var(--accent);background:none;cursor:pointer;color:var(--text-1);margin-bottom:-1px;">
            Device
        </button>
        <button type="button" class="em-tab" onclick="switchTab('features')" id="tab-features"
                style="padding:0.625rem 0.875rem;font-size:0.82rem;border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;color:var(--text-3);margin-bottom:-1px;">
            Features
        </button>
        <button type="button" class="em-tab" onclick="switchTab('location')" id="tab-location"
                style="padding:0.625rem 0.875rem;font-size:0.82rem;border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;color:var(--text-3);margin-bottom:-1px;">
            Location &amp; Station
        </button>
    </div>

    <!-- Scrollable content -->
    <div style="flex:1;overflow-y:auto;">

        <!-- ─── Tab: Device ─── -->
        <div id="em-tab-device" style="padding:1.125rem;">
            <form method="post" id="em-form-details">
                <input type="hidden" name="action" value="edit_details">
                <input type="hidden" name="device_id" id="em-device-id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">

                <div class="form-row" style="gap:0.75rem;margin-bottom:1rem;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.75rem;">Callsign</label>
                        <input type="text" name="callsign" id="em-callsign" maxlength="16" required
                               style="width:130px;text-transform:uppercase;font-family:monospace;font-weight:700;">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;">
                        <label style="font-size:0.75rem;">Hardware</label>
                        <input type="text" name="hardware_desc" id="em-hardware" maxlength="255"
                               placeholder="e.g. OpenSPOT4 Pro">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save Device Info</button>
            </form>

            <!-- SSID suffix (hotspot only) -->
            <div id="em-ssid-section" style="display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border-1);">
                <span class="form-section-label">SSID Suffix</span>
                <form method="post" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-top:0.5rem;">
                    <input type="hidden" name="action" value="edit_ssid">
                    <input type="hidden" name="device_id" id="em-ssid-device-id">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                    <select name="ssid_suffix" id="em-ssid-select" style="width:75px;">
                        <?php for ($s = 1; $s <= 99; $s++): ?>
                        <option value="<?= $s ?>"><?= str_pad((string)$s,2,'0',STR_PAD_LEFT) ?></option>
                        <?php endfor; ?>
                    </select>
                    <span style="font-size:0.78rem;color:var(--text-3);">
                        Peer ID → <code id="em-peer-preview" style="color:var(--accent-text);"></code>
                    </span>
                    <button type="submit" class="btn btn-secondary btn-sm">Update Suffix</button>
                    <p class="form-hint" style="width:100%;margin:0.25rem 0 0;">
                        Changing the suffix changes your connection ID — update your hotspot's DMR ID to match.
                    </p>
                </form>
            </div>

            <!-- Delete -->
            <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border-1);">
                <form method="post" onsubmit="return confirm('Permanently remove this device?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="device_id" id="em-delete-device-id">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Remove Device</button>
                </form>
            </div>
        </div>

        <!-- ─── Tab: Features ─── -->
        <div id="em-tab-features" style="display:none;padding:1.125rem;">
            <form method="post" id="em-form-features">
                <input type="hidden" name="action" value="edit_features">
                <input type="hidden" name="device_id" id="em-feat-device-id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">

                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                    <label style="position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0;">
                        <input type="checkbox" name="tg_rewrite_enabled" value="1" id="em-tgr-chk"
                               onchange="onEmTgrToggle()" style="opacity:0;width:0;height:0;position:absolute;">
                        <span id="em-tgr-track" style="position:absolute;inset:0;background:var(--border-2);border-radius:22px;cursor:pointer;transition:background .15s;">
                            <span id="em-tgr-thumb" style="position:absolute;left:3px;top:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:left .15s;"></span>
                        </span>
                    </label>
                    <span style="font-size:0.88rem;font-weight:600;">TG Rewrite</span>
                    <span style="font-size:0.75rem;color:var(--text-3);">Enables network-side talkgroup number translation</span>
                </div>

                <div id="em-prefix-section" style="display:none;">
                    <p style="font-size:0.78rem;color:var(--text-3);margin:0 0 0.625rem;">
                        Select the network prefix assigned to this hotspot. All rewrite rules are generated automatically.
                    </p>
                    <div id="em-prefix-btns" style="display:flex;gap:0.375rem;flex-wrap:wrap;margin-bottom:1rem;">
                        <?php foreach ([1,2,3,4,5,7,8,9] as $pv): ?>
                        <button type="button" data-p="<?= $pv ?>" onclick="selectEmPrefix(<?= $pv ?>)"
                                style="width:38px;height:38px;font-size:0.9rem;font-weight:700;border:1px solid var(--border-2);border-radius:var(--radius);cursor:pointer;background:none;color:var(--text-2);">
                            <?= $pv ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="tg_rewrite_prefix" id="em-prefix-val">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Save Features</button>
            </form>
        </div>

        <!-- ─── Tab: Location & Station ─── -->
        <div id="em-tab-location" style="display:none;padding:1.125rem;">
            <form method="post" id="em-form-location">
                <input type="hidden" name="action" value="edit_location">
                <input type="hidden" name="device_id" id="em-loc-device-id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">

                <!-- RPTC status banner -->
                <div id="em-rptc-banner" style="display:none;background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;padding:0.5rem 0.75rem;font-size:0.75rem;color:var(--text-3);margin-bottom:0.875rem;">
                    <span style="color:var(--accent-text);">&#x25CF;</span>
                    Live data last received from hotspot: <span id="em-rptc-ts" style="font-weight:600;"></span>
                </div>

                <!-- Map -->
                <div id="em-map" style="height:280px;border-radius:var(--radius);overflow:hidden;margin-bottom:0.875rem;background:var(--bg-base);border:1px solid var(--border-1);"></div>

                <!-- Address search -->
                <div style="display:flex;gap:0.5rem;margin-bottom:0.875rem;">
                    <input type="text" id="em-addr-search" placeholder="Search address or city…" style="flex:1;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="geocodeAddress()">Search</button>
                </div>

                <!-- Lat / Lon -->
                <div class="form-row" style="gap:0.75rem;margin-bottom:0.875rem;">
                    <div class="form-group" style="margin:0;flex:1;">
                        <label style="font-size:0.72rem;">Latitude</label>
                        <input type="text" name="lat" id="em-lat" placeholder="e.g. 28.80055" style="font-family:monospace;">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;">
                        <label style="font-size:0.72rem;">Longitude</label>
                        <input type="text" name="lon" id="em-lon" placeholder="e.g. -81.27312" style="font-family:monospace;">
                    </div>
                </div>
                <p class="form-hint" style="margin-bottom:0.875rem;">Click the map to place a pin, or search an address. Coordinates are stored precisely.</p>

                <div class="form-divider"></div>

                <!-- Station info -->
                <span class="form-section-label">Station Info <span style="color:var(--text-3);font-weight:normal;">(auto-filled when hotspot connects)</span></span>
                <div class="form-row" style="gap:0.75rem;margin:0.625rem 0;">
                    <div class="form-group" style="margin:0;flex:1;">
                        <label style="font-size:0.72rem;">RX Frequency (Hz)</label>
                        <input type="text" name="rx_freq" id="em-rx-freq" placeholder="e.g. 145190000" style="font-family:monospace;">
                    </div>
                    <div class="form-group" style="margin:0;flex:1;">
                        <label style="font-size:0.72rem;">TX Frequency (Hz)</label>
                        <input type="text" name="tx_freq" id="em-tx-freq" placeholder="e.g. 145790000" style="font-family:monospace;">
                    </div>
                    <div class="form-group" style="margin:0;width:90px;">
                        <label style="font-size:0.72rem;">Power (W)</label>
                        <input type="number" name="tx_power" id="em-power" min="0" max="100" style="width:100%;">
                    </div>
                    <div class="form-group" style="margin:0;width:90px;">
                        <label style="font-size:0.72rem;">Height (m)</label>
                        <input type="number" name="height_m" id="em-height" min="0" style="width:100%;">
                    </div>
                </div>
                <div class="form-group">
                    <label style="font-size:0.72rem;">Location Description</label>
                    <input type="text" name="location_desc" id="em-loc-desc" maxlength="255"
                           placeholder="e.g. Chattanooga, TN">
                </div>
                <div class="form-group">
                    <label style="font-size:0.72rem;">Station Description</label>
                    <input type="text" name="station_desc" id="em-sta-desc" maxlength="255"
                           placeholder="e.g. W4LMC Home Hotspot">
                </div>
                <div class="form-group">
                    <label style="font-size:0.72rem;">URL</label>
                    <input type="text" name="station_url" id="em-sta-url" maxlength="512"
                           placeholder="e.g. https://www.qrz.com/db/W4LMC">
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Save Location</button>
            </form>
        </div>

    </div><!-- /.scroll -->
</div>
</div>

<!-- ═══════════════════════════════════════════════════════ CONFIG MODAL -->
<div id="config-modal" style="display:none;position:fixed;inset:0;z-index:1001;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;">
    <div style="background:var(--bg-surface);border:1px solid var(--border-2);border-radius:var(--radius);width:min(580px,94vw);max-height:88vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.4);">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 1rem;border-bottom:1px solid var(--border-1);">
            <span style="font-weight:600;font-size:0.95rem;">DMR Network Config</span>
            <button type="button" onclick="closeConfigModal()" style="background:none;border:none;cursor:pointer;color:var(--text-3);font-size:1.2rem;line-height:1;padding:0 0.25rem;">&times;</button>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;padding:0.75rem 1rem;align-items:center;border-bottom:1px solid var(--border-1);">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="font-size:0.75rem;color:var(--text-3);">Format</span>
                <div style="display:flex;border:1px solid var(--border-2);border-radius:var(--radius);overflow:hidden;">
                    <button type="button" id="cfg-fmt-wpsd" onclick="setConfigFormat('wpsd')"
                            style="padding:0.28rem 0.7rem;font-size:0.75rem;border:none;cursor:pointer;background:var(--accent);color:#fff;">WPSD</button>
                    <button type="button" id="cfg-fmt-pistar" onclick="setConfigFormat('pistar')"
                            style="padding:0.28rem 0.7rem;font-size:0.75rem;border:none;cursor:pointer;background:none;color:var(--text-2);">Pi-Star / MMDVMHost</button>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="font-size:0.75rem;color:var(--text-3);">TG Rewrite</span>
                <label style="position:relative;display:inline-block;width:36px;height:20px;flex-shrink:0;">
                    <input type="checkbox" id="cfg-tgr-enabled" onchange="onCfgTgrToggle()" style="opacity:0;width:0;height:0;position:absolute;">
                    <span id="cfg-tgr-track" style="position:absolute;inset:0;background:var(--border-2);border-radius:20px;cursor:pointer;transition:background .15s;">
                        <span id="cfg-tgr-thumb" style="position:absolute;left:2px;top:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:left .15s;"></span>
                    </span>
                </label>
            </div>
        </div>
        <div id="cfg-prefix-row" style="display:none;padding:0.625rem 1rem;border-bottom:1px solid var(--border-1);background:var(--bg-base);">
            <div style="display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
                <span style="font-size:0.75rem;color:var(--text-3);">Prefix</span>
                <div id="cfg-prefix-btns" style="display:flex;gap:0.25rem;">
                    <?php foreach ([1,2,3,4,5,7,8,9] as $pv): ?>
                    <button type="button" data-p="<?= $pv ?>" onclick="selectCfgPrefix(<?= $pv ?>)"
                            style="width:28px;height:28px;font-size:0.82rem;font-weight:700;border:1px solid var(--border-2);border-radius:4px;cursor:pointer;background:none;color:var(--text-2);"><?= $pv ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div style="position:relative;flex:1;min-height:0;margin:0.75rem 1rem;">
            <button type="button" onclick="copyConfig()"
                    style="position:absolute;top:0.5rem;right:0.5rem;z-index:1;background:var(--bg-base);border:1px solid var(--border-2);border-radius:4px;padding:0.25rem 0.5rem;cursor:pointer;font-size:0.72rem;color:var(--text-2);display:flex;align-items:center;gap:0.3rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span id="cfg-copy-label">Copy</span>
            </button>
            <pre id="cfg-output" style="margin:0;padding:0.875rem 2.75rem 0.875rem 0.875rem;background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;font-size:0.78rem;line-height:1.7;overflow:auto;max-height:360px;white-space:pre;color:var(--text-1);"></pre>
        </div>
        <div style="padding:0 1rem 0.875rem;">
            <p style="font-size:0.71rem;color:var(--text-3);margin:0;">
                WPSD: paste into <em>Advanced &rsaquo; DMR Networks &rsaquo; Custom</em>.
                Pi-Star: Expert Editor &rsaquo; /etc/mmdvmhost &rsaquo; [DMR Network].
            </p>
        </div>
    </div>
</div>

<script>
var baseDmr = <?= (int)$user_base_dmr ?>;
var _cfgCallsign='', _cfgPeerId=0, _cfgPass='', _cfgFmt='wpsd', _cfgPrefix=0;
var _cfgHost = <?= json_encode($dmr_server_host) ?>;
var _cfgPort = <?= (int)$dmr_server_port ?>;
var _cfgNetName = 'CFLAG';

// ─── Register form ───────────────────────────────────────
function setDeviceType(type) {
    document.getElementById('reg-device-type').value = type;
    var hs = document.getElementById('hotspot-fields');
    var rp = document.getElementById('repeater-fields');
    var btn = document.getElementById('reg-submit-btn');
    var bhs = document.getElementById('toggle-hotspot');
    var brp = document.getElementById('toggle-repeater');
    if (type === 'hotspot') {
        hs.style.display=''; rp.style.display='none';
        document.getElementById('reg_dmr_id').removeAttribute('required');
        btn.textContent='Register Hotspot';
        bhs.style.background='var(--accent)'; bhs.style.color='#fff';
        brp.style.background='none'; brp.style.color='var(--text-2)';
    } else {
        hs.style.display='none'; rp.style.display='';
        document.getElementById('reg_dmr_id').setAttribute('required','required');
        btn.textContent='Register Repeater';
        brp.style.background='var(--accent)'; brp.style.color='#fff';
        bhs.style.background='none'; bhs.style.color='var(--text-2)';
    }
}
function updatePeerId() {
    var s = parseInt(document.getElementById('reg_ssid').value, 10);
    var el = document.getElementById('peer_id_preview');
    if (el) el.textContent = baseDmr >= 1000000 ? (baseDmr*100+s).toString() : '—';
}

// ─── Edit modal ──────────────────────────────────────────
var _emMap = null, _emMarker = null, _emBaseDmr = 0;

function openEditModal(d) {
    // Populate all fields from device object
    document.getElementById('em-title').textContent = d.callsign;
    document.getElementById('em-badge').textContent  = d.device_type + (d.ssid_suffix ? ' · suffix '+String(d.ssid_suffix).padStart(2,'0') : '');

    ['em-device-id','em-ssid-device-id','em-feat-device-id',
     'em-loc-device-id','em-delete-device-id'].forEach(function(id){
        document.getElementById(id).value = d.id;
    });

    document.getElementById('em-callsign').value  = d.callsign || '';
    document.getElementById('em-hardware').value  = d.hardware_desc || '';

    // SSID suffix (hotspot only)
    var ssidSec = document.getElementById('em-ssid-section');
    if (d.device_type === 'hotspot' && d.dmr_id) {
        ssidSec.style.display = 'block';
        _emBaseDmr = parseInt(d.dmr_id, 10);
        var sel = document.getElementById('em-ssid-select');
        sel.value = parseInt(d.ssid_suffix||'1', 10);
        updateEmPeerPreview();
    } else {
        ssidSec.style.display = 'none';
    }

    // Features tab
    var tgrChk = document.getElementById('em-tgr-chk');
    tgrChk.checked = !!parseInt(d.tg_rewrite_enabled||'0', 10);
    syncEmTgrToggle();
    var storedP = parseInt(d.tg_rewrite_prefix||'0', 10);
    document.getElementById('em-prefix-section').style.display = tgrChk.checked ? 'block' : 'none';
    var validPs = [1,2,3,4,5,7,8,9];
    if (storedP > 0 && validPs.indexOf(storedP) >= 0) {
        selectEmPrefix(storedP, true);
    } else {
        document.querySelectorAll('#em-prefix-btns button').forEach(function(b){
            b.style.background='none'; b.style.color='var(--text-2)'; b.style.borderColor='var(--border-2)';
        });
        document.getElementById('em-prefix-val').value = '';
    }

    // Location tab
    document.getElementById('em-lat').value      = d.lat   || '';
    document.getElementById('em-lon').value      = d.lon   || '';
    document.getElementById('em-rx-freq').value  = d.rx_freq  || '';
    document.getElementById('em-tx-freq').value  = d.tx_freq  || '';
    document.getElementById('em-power').value    = d.tx_power || '';
    document.getElementById('em-height').value   = d.height_m || '';
    document.getElementById('em-loc-desc').value = d.location_desc || '';
    document.getElementById('em-sta-desc').value = d.station_desc  || '';
    document.getElementById('em-sta-url').value  = d.station_url   || '';

    var banner = document.getElementById('em-rptc-banner');
    if (d.rptc_updated_at) {
        banner.style.display = 'block';
        document.getElementById('em-rptc-ts').textContent = d.rptc_updated_at;
    } else {
        banner.style.display = 'none';
    }

    switchTab('device');
    document.getElementById('edit-modal').style.display = 'flex';
    document.addEventListener('keydown', _emEscHandler);
}

function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
    document.removeEventListener('keydown', _emEscHandler);
}
function _emEscHandler(e) { if (e.key === 'Escape') closeEditModal(); }
document.getElementById('edit-modal').addEventListener('click', function(e){
    if (e.target === this) closeEditModal();
});

function switchTab(name) {
    ['device','features','location'].forEach(function(t) {
        document.getElementById('em-tab-'+t).style.display     = t===name ? 'block' : 'none';
        var btn = document.getElementById('tab-'+t);
        btn.style.borderBottomColor = t===name ? 'var(--accent)' : 'transparent';
        btn.style.color = t===name ? 'var(--text-1)' : 'var(--text-3)';
    });
    if (name === 'location') initEmMap();
}

function updateEmPeerPreview() {
    var s = parseInt(document.getElementById('em-ssid-select').value, 10);
    document.getElementById('em-peer-preview').textContent = (_emBaseDmr * 100 + s).toString();
}
document.getElementById('em-ssid-select').addEventListener('change', updateEmPeerPreview);

function syncEmTgrToggle() {
    var on = document.getElementById('em-tgr-chk').checked;
    document.getElementById('em-tgr-track').style.background = on ? 'var(--accent)' : 'var(--border-2)';
    document.getElementById('em-tgr-thumb').style.left = on ? '21px' : '3px';
}
function onEmTgrToggle() {
    syncEmTgrToggle();
    document.getElementById('em-prefix-section').style.display =
        document.getElementById('em-tgr-chk').checked ? 'block' : 'none';
}

function selectEmPrefix(p, silent) {
    document.getElementById('em-prefix-val').value = p;
    document.querySelectorAll('#em-prefix-btns button').forEach(function(b) {
        var active = parseInt(b.getAttribute('data-p'),10) === p;
        b.style.background  = active ? 'var(--accent)' : 'none';
        b.style.color       = active ? '#fff' : 'var(--text-2)';
        b.style.borderColor = active ? 'var(--accent)' : 'var(--border-2)';
    });
}

// Leaflet map
function initEmMap() {
    if (_emMap) { _emMap.invalidateSize(); return; }
    var lat = parseFloat(document.getElementById('em-lat').value) || 39.5;
    var lon = parseFloat(document.getElementById('em-lon').value) || -98.35;
    var zoom = document.getElementById('em-lat').value ? 12 : 4;

    _emMap = L.map('em-map').setView([lat, lon], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors', maxZoom: 19
    }).addTo(_emMap);

    if (document.getElementById('em-lat').value) {
        _emMarker = L.marker([lat, lon], {draggable: true}).addTo(_emMap);
        _emMarker.on('dragend', function(e) { setEmCoords(e.target.getLatLng()); });
    }

    _emMap.on('click', function(e) {
        if (_emMarker) {
            _emMarker.setLatLng(e.latlng);
        } else {
            _emMarker = L.marker(e.latlng, {draggable: true}).addTo(_emMap);
            _emMarker.on('dragend', function(ev) { setEmCoords(ev.target.getLatLng()); });
        }
        setEmCoords(e.latlng);
    });
}

function setEmCoords(latlng) {
    document.getElementById('em-lat').value = latlng.lat.toFixed(6);
    document.getElementById('em-lon').value = latlng.lng.toFixed(6);
}

function geocodeAddress() {
    var q = document.getElementById('em-addr-search').value.trim();
    if (!q) return;
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.length) { alert('Address not found.'); return; }
            var lat = parseFloat(data[0].lat), lon = parseFloat(data[0].lon);
            document.getElementById('em-lat').value = lat.toFixed(6);
            document.getElementById('em-lon').value = lon.toFixed(6);
            if (!_emMap) { initEmMap(); return; }
            _emMap.setView([lat, lon], 13);
            if (_emMarker) {
                _emMarker.setLatLng([lat, lon]);
            } else {
                _emMarker = L.marker([lat, lon], {draggable: true}).addTo(_emMap);
                _emMarker.on('dragend', function(e) { setEmCoords(e.target.getLatLng()); });
            }
        })
        .catch(function() { alert('Geocoding failed — check your connection.'); });
}

// ─── Config modal ─────────────────────────────────────────
function openConfigModal(callsign, peerId, passphrase, tgrEnabled, tgrPrefix) {
    _cfgCallsign=callsign; _cfgPeerId=peerId; _cfgPass=passphrase; _cfgFmt='wpsd'; _cfgPrefix=0;
    var chk = document.getElementById('cfg-tgr-enabled');
    chk.checked = !!tgrEnabled;
    syncCfgTgrToggle();
    document.getElementById('cfg-prefix-row').style.display = tgrEnabled ? 'block' : 'none';
    var validPs=[1,2,3,4,5,7,8,9], stored=parseInt(tgrPrefix,10);
    if (!isNaN(stored) && validPs.indexOf(stored)>=0) selectCfgPrefix(stored, true);
    else document.querySelectorAll('#cfg-prefix-btns button').forEach(function(b){
        b.style.background='none'; b.style.color='var(--text-2)'; b.style.borderColor='var(--border-2)';
    });
    setConfigFormat('wpsd');
    document.getElementById('config-modal').style.display='flex';
    document.addEventListener('keydown', _cfgEscHandler);
}
function closeConfigModal() {
    document.getElementById('config-modal').style.display='none';
    document.removeEventListener('keydown', _cfgEscHandler);
}
function _cfgEscHandler(e) { if (e.key==='Escape') closeConfigModal(); }
document.getElementById('config-modal').addEventListener('click', function(e){ if(e.target===this) closeConfigModal(); });

function syncCfgTgrToggle() {
    var on = document.getElementById('cfg-tgr-enabled').checked;
    document.getElementById('cfg-tgr-track').style.background = on ? 'var(--accent)' : 'var(--border-2)';
    document.getElementById('cfg-tgr-thumb').style.left = on ? '18px' : '2px';
}
function onCfgTgrToggle() {
    syncCfgTgrToggle();
    document.getElementById('cfg-prefix-row').style.display =
        document.getElementById('cfg-tgr-enabled').checked ? 'block' : 'none';
    updateConfigOutput();
}

function selectCfgPrefix(p, silent) {
    _cfgPrefix=p;
    document.querySelectorAll('#cfg-prefix-btns button').forEach(function(b){
        var a=parseInt(b.getAttribute('data-p'),10)===p;
        b.style.background=a?'var(--accent)':'none';
        b.style.color=a?'#fff':'var(--text-2)';
        b.style.borderColor=a?'var(--accent)':'var(--border-2)';
    });
    if (!silent) updateConfigOutput();
}

function setConfigFormat(fmt) {
    _cfgFmt=fmt;
    var bw=document.getElementById('cfg-fmt-wpsd'), bp=document.getElementById('cfg-fmt-pistar');
    if (fmt==='wpsd'){ bw.style.background='var(--accent)'; bw.style.color='#fff'; bp.style.background='none'; bp.style.color='var(--text-2)'; }
    else { bp.style.background='var(--accent)'; bp.style.color='#fff'; bw.style.background='none'; bw.style.color='var(--text-2)'; }
    updateConfigOutput();
}
function updateConfigOutput() {
    document.getElementById('cfg-output').textContent = buildConfig(_cfgFmt);
    document.getElementById('cfg-copy-label').textContent = 'Copy';
}

function buildRewrites(p) {
    var base=p*1000000, pcBase=p*10000+4000, typeV=base+9990;
    return [
        'TGRewrite0=2,11,2,9,1',
        'TGRewrite1=1,'+(base+1)+',1,1,999999',
        'TGRewrite2=2,'+(base+1)+',2,1,999999',
        'TGRewrite3=1,100,1,100,1','TGRewrite4=2,100,2,100,1',
        'TGRewrite5=1,9,1,9,1','TGRewrite6=2,9,2,9,1',
        'PCRewrite0=2,'+pcBase+',2,4000,1001',
        'PCRewrite1=1,'+(base+1)+',1,1,999999',
        'PCRewrite2=2,'+(base+1)+',2,1,999999',
        'TypeRewrite1=1,'+typeV+',1,9990',
        'TypeRewrite2=2,'+typeV+',2,9990',
        'SrcRewrite1=1,1,1,'+(base+1)+',999999',
        'SrcRewrite2=2,1,2,'+(base+1)+',999999',
    ].join('\n');
}

function buildConfig(fmt) {
    var host=_cfgHost||'(set Public Address in Admin > Master Settings)';
    var tgrOn=document.getElementById('cfg-tgr-enabled').checked;
    var rewrites = tgrOn && _cfgPrefix>0 ? '\n'+buildRewrites(_cfgPrefix)
                 : tgrOn ? '\n# Select a prefix above'
                 : '';
    if (fmt==='wpsd') {
        var lines=['[DMR Network Custom]','Enabled=1','Location=0','Debug=0',
            'Id='+_cfgPeerId];
        if (tgrOn) lines.push('WPSD_AutoRewrites=1');
        lines.push('Name='+_cfgNetName,'Address='+host,'Port='+_cfgPort,'Password="'+_cfgPass+'"');
        if (rewrites) lines.push(rewrites.trim());
        return lines.join('\n');
    } else {
        var out='[General]\nCallsign='+_cfgCallsign+'\nId='+_cfgPeerId
               +'\n\n[DMR Network]\nEnable=1\nAddress='+host+'\nPort='+_cfgPort
               +'\nLocal=0\nPassword='+_cfgPass+'\nOptions=\nDebug=0';
        if (rewrites) out += '\n'+rewrites.trim();
        return out;
    }
}

function copyConfig() {
    var text=document.getElementById('cfg-output').textContent;
    var label=document.getElementById('cfg-copy-label');
    if (navigator.clipboard&&navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){
            label.textContent='Copied!'; setTimeout(function(){label.textContent='Copy';},2000);
        });
    } else {
        var ta=document.createElement('textarea'); ta.value=text;
        ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        label.textContent='Copied!'; setTimeout(function(){label.textContent='Copy';},2000);
    }
}

// Auto-open edit modal if redirected back with ?edit= param
<?php if ($auto_edit > 0): ?>
(function() {
    var devices = <?= json_encode(array_values($devices)) ?>;
    for (var i=0; i<devices.length; i++) {
        if (parseInt(devices[i].id,10) === <?= $auto_edit ?>) {
            openEditModal(devices[i]); break;
        }
    }
})();
<?php endif; ?>
</script>

<?php require_once $root . '/app/views/footer.php'; ?>
