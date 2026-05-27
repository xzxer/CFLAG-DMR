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
                        <button type="button" class="btn btn-secondary btn-xs"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>)">
                            Edit / Config
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

<!-- ═══════════════════════════════════════════════════════ EDIT + CONFIG MODAL -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:1rem;">
<div style="background:var(--bg-surface);border:1px solid var(--border-2);border-radius:var(--radius);width:min(1060px,96vw);height:min(800px,90vh);display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,0.5);">

    <!-- Modal header -->
    <div style="display:flex;align-items:center;gap:1rem;padding:0.875rem 1.25rem;border-bottom:1px solid var(--border-1);flex-shrink:0;">
        <div style="flex:1;min-width:0;display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
            <span id="em-title" style="font-size:1.05rem;font-weight:800;font-family:monospace;color:var(--accent-text);"></span>
            <span id="em-status-badge"></span>
            <span id="em-badge" style="font-size:0.75rem;color:var(--text-3);"></span>
        </div>
        <!-- Connection ID — prominent -->
        <div style="flex-shrink:0;padding:0 1rem;border-left:1px solid var(--border-1);text-align:right;">
            <div style="font-size:0.6rem;color:var(--text-3);text-transform:uppercase;letter-spacing:0.07em;margin-bottom:0.15rem;">Connection ID</div>
            <div id="em-peer-id-display" style="font-size:1.25rem;font-weight:800;font-family:monospace;color:var(--accent-text);line-height:1;"></div>
        </div>
        <button type="button" onclick="closeEditModal()" style="background:none;border:none;cursor:pointer;color:var(--text-3);font-size:1.3rem;line-height:1;padding:0 0.25rem;flex-shrink:0;">&times;</button>
    </div>

    <!-- Body: left (tabs) + right (live config) -->
    <div style="display:flex;flex:1;min-height:0;overflow:hidden;">

        <!-- Left panel: tabs + content -->
        <div style="display:flex;flex-direction:column;flex:1;min-width:0;overflow:hidden;">

            <!-- Tabs -->
            <div style="display:flex;border-bottom:1px solid var(--border-1);flex-shrink:0;padding:0 1.125rem;">
                <button type="button" onclick="switchTab('device')" id="tab-device"
                        style="padding:0.625rem 0.875rem;font-size:0.82rem;border:none;border-bottom:2px solid var(--accent);background:none;cursor:pointer;color:var(--text-1);margin-bottom:-1px;">
                    Device
                </button>
                <button type="button" onclick="switchTab('features')" id="tab-features"
                        style="padding:0.625rem 0.875rem;font-size:0.82rem;border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;color:var(--text-3);margin-bottom:-1px;">
                    Features
                </button>
                <button type="button" onclick="switchTab('location')" id="tab-location"
                        style="padding:0.625rem 0.875rem;font-size:0.82rem;border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;color:var(--text-3);margin-bottom:-1px;">
                    Location &amp; Station
                </button>
            </div>

            <!-- Scrollable tab content -->
            <div style="flex:1;overflow-y:auto;">

                <!-- ─── Tab: Device ─── -->
                <div id="em-tab-device" style="padding:1.25rem;">
                    <form method="post" id="em-form-details">
                        <input type="hidden" name="action" value="edit_details">
                        <input type="hidden" name="device_id" id="em-device-id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                        <div class="form-group" style="margin-bottom:0.875rem;">
                            <label>Callsign</label>
                            <input type="text" name="callsign" id="em-callsign" maxlength="16" required
                                   style="width:160px;text-transform:uppercase;font-family:monospace;font-weight:700;font-size:0.95rem;"
                                   oninput="updateConfigOutput()">
                        </div>
                        <div class="form-group" style="margin-bottom:1rem;">
                            <label>Hardware <span style="color:var(--text-3);font-weight:normal;">(optional)</span></label>
                            <input type="text" name="hardware_desc" id="em-hardware" maxlength="255"
                                   placeholder="e.g. OpenSPOT4 Pro">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Save Device Info</button>
                    </form>

                    <!-- Passphrase display -->
                    <div id="em-passphrase-row" style="display:none;margin-top:1.25rem;padding:0.625rem 0.875rem;background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;">
                        <span style="font-size:0.7rem;color:var(--text-3);display:block;margin-bottom:0.2rem;text-transform:uppercase;letter-spacing:0.06em;">Passphrase</span>
                        <code id="em-passphrase-val" style="color:var(--accent-text);letter-spacing:0.07em;font-size:0.95rem;font-weight:700;"></code>
                    </div>

                    <!-- SSID suffix (hotspot only) -->
                    <div id="em-ssid-section" style="display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border-1);">
                        <span class="form-section-label">SSID Suffix</span>
                        <form method="post" style="margin-top:0.625rem;">
                            <input type="hidden" name="action" value="edit_ssid">
                            <input type="hidden" name="device_id" id="em-ssid-device-id">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">
                            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;">
                                <select name="ssid_suffix" id="em-ssid-select" style="width:80px;font-size:0.95rem;">
                                    <?php for ($s = 1; $s <= 99; $s++): ?>
                                    <option value="<?= $s ?>"><?= str_pad((string)$s,2,'0',STR_PAD_LEFT) ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div>
                                    <div style="font-size:0.68rem;color:var(--text-3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.1rem;">New Connection ID</div>
                                    <code id="em-peer-preview" style="font-size:1rem;font-weight:700;color:var(--accent-text);font-family:monospace;"></code>
                                </div>
                                <button type="submit" class="btn btn-secondary btn-sm">Update Suffix</button>
                            </div>
                            <p class="form-hint">Changing the suffix changes your connection ID — update your hotspot to match.</p>
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
                <div id="em-tab-features" style="display:none;padding:1.25rem;">
                    <form method="post" id="em-form-features">
                        <input type="hidden" name="action" value="edit_features">
                        <input type="hidden" name="device_id" id="em-feat-device-id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">

                        <div style="display:flex;align-items:center;gap:1rem;padding:0.875rem;background:var(--bg-base);border:1px solid var(--border-1);border-radius:var(--radius);margin-bottom:1.25rem;">
                            <label style="position:relative;display:inline-block;width:46px;height:26px;flex-shrink:0;cursor:pointer;">
                                <input type="checkbox" name="tg_rewrite_enabled" value="1" id="em-tgr-chk"
                                       onchange="onEmTgrToggle()" style="opacity:0;width:0;height:0;position:absolute;">
                                <span id="em-tgr-track" style="position:absolute;inset:0;background:var(--border-2);border-radius:26px;transition:background .15s;">
                                    <span id="em-tgr-thumb" style="position:absolute;left:3px;top:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:left .15s;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></span>
                                </span>
                            </label>
                            <div>
                                <div style="font-size:0.9rem;font-weight:600;margin-bottom:0.1rem;">TG Rewrite</div>
                                <div style="font-size:0.75rem;color:var(--text-3);">Enables network-side talkgroup number translation</div>
                            </div>
                        </div>

                        <div id="em-prefix-section" style="display:none;">
                            <span class="form-section-label" style="display:block;margin-bottom:0.5rem;">Network Prefix</span>
                            <p style="font-size:0.78rem;color:var(--text-3);margin:0 0 0.75rem;">
                                Select the prefix for this hotspot — all 14 rewrite rules generate automatically in the config panel.
                            </p>
                            <div id="em-prefix-btns" style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                                <?php foreach ([1,2,3,4,5,7,8,9] as $pv): ?>
                                <button type="button" data-p="<?= $pv ?>" onclick="selectEmPrefix(<?= $pv ?>)"
                                        style="width:46px;height:46px;font-size:1rem;font-weight:700;border:1px solid var(--border-2);border-radius:var(--radius);cursor:pointer;background:none;color:var(--text-2);">
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
                <div id="em-tab-location" style="display:none;padding:1.25rem;">
                    <form method="post" id="em-form-location">
                        <input type="hidden" name="action" value="edit_location">
                        <input type="hidden" name="device_id" id="em-loc-device-id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>">

                        <div id="em-rptc-banner" style="display:none;background:var(--bg-base);border:1px solid var(--border-1);border-radius:4px;padding:0.5rem 0.75rem;font-size:0.75rem;color:var(--text-3);margin-bottom:0.875rem;">
                            <span style="color:var(--accent-text);">&#x25CF;</span>
                            Live data last received: <span id="em-rptc-ts" style="font-weight:600;"></span>
                        </div>

                        <div id="em-map" style="height:260px;border-radius:var(--radius);overflow:hidden;margin-bottom:0.875rem;background:var(--bg-base);border:1px solid var(--border-1);"></div>

                        <div style="display:flex;gap:0.5rem;margin-bottom:0.875rem;">
                            <input type="text" id="em-addr-search" placeholder="Search address or city…" style="flex:1;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="geocodeAddress()">Search</button>
                        </div>

                        <div class="form-row" style="gap:0.75rem;margin-bottom:0.5rem;">
                            <div class="form-group" style="margin:0;flex:1;">
                                <label>Latitude</label>
                                <input type="text" name="lat" id="em-lat" placeholder="e.g. 28.80055" style="font-family:monospace;">
                            </div>
                            <div class="form-group" style="margin:0;flex:1;">
                                <label>Longitude</label>
                                <input type="text" name="lon" id="em-lon" placeholder="e.g. -81.27312" style="font-family:monospace;">
                            </div>
                        </div>
                        <p class="form-hint" style="margin-bottom:0.875rem;">Click the map to place a pin, or search an address.</p>

                        <div class="form-divider"></div>
                        <span class="form-section-label" style="display:block;margin-bottom:0.625rem;">Station Info <span style="font-weight:normal;color:var(--text-3);">(auto-filled when hotspot connects)</span></span>

                        <div class="form-row" style="gap:0.75rem;margin-bottom:0.75rem;">
                            <div class="form-group" style="margin:0;flex:1;">
                                <label>RX Frequency (Hz)</label>
                                <input type="text" name="rx_freq" id="em-rx-freq" placeholder="e.g. 145190000" style="font-family:monospace;">
                            </div>
                            <div class="form-group" style="margin:0;flex:1;">
                                <label>TX Frequency (Hz)</label>
                                <input type="text" name="tx_freq" id="em-tx-freq" placeholder="e.g. 145790000" style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="form-row" style="gap:0.75rem;margin-bottom:0.75rem;">
                            <div class="form-group" style="margin:0;width:120px;">
                                <label>Power (W)</label>
                                <input type="number" name="tx_power" id="em-power" min="0" max="100">
                            </div>
                            <div class="form-group" style="margin:0;width:120px;">
                                <label>Height (m)</label>
                                <input type="number" name="height_m" id="em-height" min="0">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Location Description</label>
                            <input type="text" name="location_desc" id="em-loc-desc" maxlength="255" placeholder="e.g. Chattanooga, TN">
                        </div>
                        <div class="form-group">
                            <label>Station Description</label>
                            <input type="text" name="station_desc" id="em-sta-desc" maxlength="255" placeholder="e.g. W4LMC Home Hotspot">
                        </div>
                        <div class="form-group">
                            <label>URL</label>
                            <input type="text" name="station_url" id="em-sta-url" maxlength="512" placeholder="e.g. https://www.qrz.com/db/W4LMC">
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">Save Location</button>
                    </form>
                </div>

            </div><!-- /.scroll -->
        </div><!-- /.left -->

        <!-- Right panel: live config -->
        <div style="width:340px;flex-shrink:0;display:flex;flex-direction:column;border-left:1px solid var(--border-1);background:var(--bg-base);">

            <!-- Config panel header + format toggle -->
            <div style="padding:0.625rem 0.875rem;border-bottom:1px solid var(--border-1);display:flex;align-items:center;gap:0.625rem;flex-shrink:0;">
                <span style="font-size:0.78rem;font-weight:600;color:var(--text-2);flex:1;">Device Config</span>
                <div style="display:flex;border:1px solid var(--border-2);border-radius:var(--radius);overflow:hidden;">
                    <button type="button" id="cfg-fmt-wpsd" onclick="setConfigFormat('wpsd')"
                            style="padding:0.22rem 0.6rem;font-size:0.72rem;font-weight:600;border:none;cursor:pointer;background:var(--accent);color:#fff;">WPSD</button>
                    <button type="button" id="cfg-fmt-pistar" onclick="setConfigFormat('pistar')"
                            style="padding:0.22rem 0.6rem;font-size:0.72rem;border:none;cursor:pointer;background:none;color:var(--text-2);">Pi-Star</button>
                </div>
            </div>

            <!-- Config output -->
            <div style="flex:1;overflow-y:auto;position:relative;padding:0.75rem;">
                <button type="button" onclick="copyConfig()"
                        style="position:absolute;top:1rem;right:1rem;z-index:1;background:var(--bg-surface);border:1px solid var(--border-2);border-radius:4px;padding:0.2rem 0.5rem;cursor:pointer;font-size:0.7rem;color:var(--text-2);display:flex;align-items:center;gap:0.25rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span id="cfg-copy-label">Copy</span>
                </button>
                <pre id="cfg-output" style="margin:0;padding:0.75rem 2.5rem 0.75rem 0.75rem;background:var(--bg-surface);border:1px solid var(--border-1);border-radius:4px;font-size:0.72rem;line-height:1.75;white-space:pre;color:var(--text-1);min-height:80px;overflow-x:auto;"></pre>
            </div>

            <!-- Footer hint -->
            <div style="padding:0.5rem 0.875rem;border-top:1px solid var(--border-1);flex-shrink:0;">
                <p style="font-size:0.67rem;color:var(--text-3);margin:0;line-height:1.5;">
                    WPSD: Advanced &rsaquo; DMR Networks &rsaquo; Custom.<br>
                    Pi-Star: Expert Editor &rsaquo; /etc/mmdvmhost &rsaquo; [DMR Network].
                </p>
            </div>
        </div><!-- /.right -->

    </div><!-- /.body -->
</div>
</div>

<script>
var baseDmr   = <?= (int)$user_base_dmr ?>;
var _cfgHost  = <?= json_encode($dmr_server_host) ?>;
var _cfgPort  = <?= (int)$dmr_server_port ?>;
var _cfgNetName = 'CFLAG';

// Edit modal state (drives live config panel)
var _emPass = '', _emPeerId = 0, _emBaseDmr = 0, _emCfgFmt = 'wpsd';
var _emMap = null, _emMarker = null;

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
function openEditModal(d) {
    var statusLabels = {approved:'<span class="badge badge-active">Approved</span>',pending:'<span class="badge badge-amber">Pending</span>',denied:'<span class="badge badge-red">Denied</span>'};
    document.getElementById('em-title').textContent       = d.callsign;
    document.getElementById('em-status-badge').innerHTML  = statusLabels[d.status] || '';
    document.getElementById('em-badge').textContent       = d.device_type + (d.ssid_suffix ? ' · suffix '+String(d.ssid_suffix).padStart(2,'0') : '');

    ['em-device-id','em-ssid-device-id','em-feat-device-id',
     'em-loc-device-id','em-delete-device-id'].forEach(function(id){
        document.getElementById(id).value = d.id;
    });

    // Device tab
    _emPass = d.device_passphrase || '';
    document.getElementById('em-callsign').value = d.callsign || '';
    document.getElementById('em-hardware').value = d.hardware_desc || '';

    var passRow = document.getElementById('em-passphrase-row');
    if (_emPass) {
        passRow.style.display = 'block';
        document.getElementById('em-passphrase-val').textContent = _emPass;
    } else {
        passRow.style.display = 'none';
    }

    // SSID / peer ID
    if (d.device_type === 'hotspot' && d.dmr_id) {
        document.getElementById('em-ssid-section').style.display = 'block';
        _emBaseDmr = parseInt(d.dmr_id, 10);
        document.getElementById('em-ssid-select').value = parseInt(d.ssid_suffix||'1', 10);
        _emPeerId = _emBaseDmr * 100 + parseInt(d.ssid_suffix||'1', 10);
        updateEmPeerPreview();
    } else {
        document.getElementById('em-ssid-section').style.display = 'none';
        _emBaseDmr = 0;
        _emPeerId = parseInt(d.peer_id||d.dmr_id||'0', 10);
    }
    document.getElementById('em-peer-id-display').textContent = _emPeerId > 0 ? _emPeerId.toString() : '—';

    // Features tab
    var tgrChk = document.getElementById('em-tgr-chk');
    tgrChk.checked = !!parseInt(d.tg_rewrite_enabled||'0', 10);
    syncEmTgrToggle();
    document.getElementById('em-prefix-section').style.display = tgrChk.checked ? 'block' : 'none';
    var storedP = parseInt(d.tg_rewrite_prefix||'0', 10);
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
    document.getElementById('em-lat').value      = d.lat          || '';
    document.getElementById('em-lon').value      = d.lon          || '';
    document.getElementById('em-rx-freq').value  = d.rx_freq      || '';
    document.getElementById('em-tx-freq').value  = d.tx_freq      || '';
    document.getElementById('em-power').value    = d.tx_power     || '';
    document.getElementById('em-height').value   = d.height_m     || '';
    document.getElementById('em-loc-desc').value = d.location_desc|| '';
    document.getElementById('em-sta-desc').value = d.station_desc || '';
    document.getElementById('em-sta-url').value  = d.station_url  || '';
    var banner = document.getElementById('em-rptc-banner');
    if (d.rptc_updated_at) {
        banner.style.display = 'block';
        document.getElementById('em-rptc-ts').textContent = d.rptc_updated_at;
    } else {
        banner.style.display = 'none';
    }

    // Reset map so it reinitialises for new device coords
    _emMap = null; _emMarker = null;

    setConfigFormat('wpsd');
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
        document.getElementById('em-tab-'+t).style.display = t===name ? 'block' : 'none';
        var btn = document.getElementById('tab-'+t);
        btn.style.borderBottomColor = t===name ? 'var(--accent)' : 'transparent';
        btn.style.color = t===name ? 'var(--text-1)' : 'var(--text-3)';
    });
    if (name === 'location') initEmMap();
}

function updateEmPeerPreview() {
    var s = parseInt(document.getElementById('em-ssid-select').value, 10);
    _emPeerId = _emBaseDmr * 100 + s;
    document.getElementById('em-peer-preview').textContent = _emPeerId.toString();
    document.getElementById('em-peer-id-display').textContent = _emPeerId.toString();
    updateConfigOutput();
}
document.getElementById('em-ssid-select').addEventListener('change', updateEmPeerPreview);

function syncEmTgrToggle() {
    var on = document.getElementById('em-tgr-chk').checked;
    document.getElementById('em-tgr-track').style.background = on ? 'var(--accent)' : 'var(--border-2)';
    document.getElementById('em-tgr-thumb').style.left = on ? '23px' : '3px';
}
function onEmTgrToggle() {
    syncEmTgrToggle();
    document.getElementById('em-prefix-section').style.display =
        document.getElementById('em-tgr-chk').checked ? 'block' : 'none';
    updateConfigOutput();
}

function selectEmPrefix(p, silent) {
    document.getElementById('em-prefix-val').value = p;
    document.querySelectorAll('#em-prefix-btns button').forEach(function(b) {
        var active = parseInt(b.getAttribute('data-p'),10) === p;
        b.style.background  = active ? 'var(--accent)' : 'none';
        b.style.color       = active ? '#fff' : 'var(--text-2)';
        b.style.borderColor = active ? 'var(--accent)' : 'var(--border-2)';
    });
    if (!silent) updateConfigOutput();
}

// ─── Config panel (live, inside edit modal) ───────────────
function setConfigFormat(fmt) {
    _emCfgFmt = fmt;
    var bw = document.getElementById('cfg-fmt-wpsd'), bp = document.getElementById('cfg-fmt-pistar');
    bw.style.background = fmt==='wpsd' ? 'var(--accent)' : 'none';
    bw.style.color      = fmt==='wpsd' ? '#fff' : 'var(--text-2)';
    bp.style.background = fmt==='pistar' ? 'var(--accent)' : 'none';
    bp.style.color      = fmt==='pistar' ? '#fff' : 'var(--text-2)';
    updateConfigOutput();
}
function updateConfigOutput() {
    var el = document.getElementById('cfg-output');
    if (el) { el.textContent = buildConfig(_emCfgFmt); document.getElementById('cfg-copy-label').textContent = 'Copy'; }
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
    if (!_emPass) return '# Device must be approved before\n# a config is available.';
    var callsign = document.getElementById('em-callsign').value || '—';
    var host = _cfgHost || '(set Public Address in Admin › Master Settings)';
    var tgrOn = document.getElementById('em-tgr-chk').checked;
    var prefix = parseInt(document.getElementById('em-prefix-val').value || '0', 10);
    var rewrites = tgrOn && prefix > 0 ? '\n'+buildRewrites(prefix) : tgrOn ? '\n# Select a prefix above' : '';
    if (fmt === 'wpsd') {
        var lines = ['[DMR Network Custom]','Enabled=1','Location=0','Debug=0','Id='+_emPeerId];
        if (tgrOn) lines.push('WPSD_AutoRewrites=1');
        lines.push('Name='+_cfgNetName,'Address='+host,'Port='+_cfgPort,'Password="'+_emPass+'"');
        if (rewrites) lines.push(rewrites.trim());
        return lines.join('\n');
    } else {
        var out = '[General]\nCallsign='+callsign+'\nId='+_emPeerId
                +'\n\n[DMR Network]\nEnable=1\nAddress='+host+'\nPort='+_cfgPort
                +'\nLocal=0\nPassword='+_emPass+'\nOptions=\nDebug=0';
        if (rewrites) out += '\n'+rewrites.trim();
        return out;
    }
}
function copyConfig() {
    var text = document.getElementById('cfg-output').textContent;
    var label = document.getElementById('cfg-copy-label');
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function(){ label.textContent='Copied!'; setTimeout(function(){label.textContent='Copy';},2000); });
    } else {
        var ta = document.createElement('textarea'); ta.value=text; ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        label.textContent='Copied!'; setTimeout(function(){label.textContent='Copy';},2000);
    }
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
