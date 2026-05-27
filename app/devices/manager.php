<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';

function validate_dmr_id(string $raw): int|false
{
    $raw = trim($raw);
    if (!ctype_digit($raw)) return false;
    $id = (int) $raw;
    return ($id >= 1000000 && $id <= 9999999) ? $id : false;
}

function validate_ssid_suffix(string $raw): int|false
{
    $raw = trim($raw);
    if (!ctype_digit($raw)) return false;
    $suffix = (int) $raw;
    return ($suffix >= 1 && $suffix <= 99) ? $suffix : false;
}

function compute_peer_id(int $dmr_id, int $ssid_suffix): int
{
    return $dmr_id * 100 + $ssid_suffix;
}

function generate_device_passphrase(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $pass  = '';
    for ($i = 0; $i < 12; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

function validate_callsign(string $raw): string|false
{
    $raw = trim($raw);
    $len = strlen($raw);
    if ($len < 3 || $len > 16) return false;
    return preg_match('/^[A-Z0-9\/]+$/i', $raw) ? $raw : false;
}

function get_user_devices(int $user_id): array
{
    $stmt = get_db()->prepare(
        'SELECT id, callsign, dmr_id, ssid_suffix, peer_id, device_passphrase,
                tg_rewrite_enabled, tg_rewrite_prefix,
                device_type, hardware_desc, status, denied_reason,
                lat, lon, rx_freq, tx_freq, tx_power, height_m,
                location_desc, station_desc, station_url, rptc_updated_at,
                created_at, updated_at
         FROM devices WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_device(int $device_id, int $user_id): array|null
{
    $stmt = get_db()->prepare('SELECT * FROM devices WHERE id = ? AND user_id = ?');
    $stmt->execute([$device_id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_device_by_id(int $device_id): array|null
{
    $stmt = get_db()->prepare('SELECT * FROM devices WHERE id = ?');
    $stmt->execute([$device_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function register_device(int $user_id, array $data): array
{
    $db = get_db();

    $stmt = $db->prepare('SELECT moderation_state, dmr_id FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || in_array($user['moderation_state'], ['suspended', 'banned'], true)) {
        return ['ok' => false, 'error' => 'Your account is not eligible to register devices.', 'device_id' => null];
    }

    $callsign = validate_callsign($data['callsign'] ?? '');
    if ($callsign === false) {
        return ['ok' => false, 'error' => 'Invalid callsign. Must be 3–16 alphanumeric characters.', 'device_id' => null];
    }

    $device_type = $data['device_type'] ?? '';
    if (!in_array($device_type, ['hotspot', 'repeater'], true)) {
        return ['ok' => false, 'error' => 'Invalid device type.', 'device_id' => null];
    }

    $hardware_desc = substr(trim($data['hardware_desc'] ?? ''), 0, 255);

    // Hotspots: base DMR ID from user record + user-chosen suffix
    // Repeaters: direct DMR ID entry
    if ($device_type === 'hotspot') {
        $base_dmr_id = (int) ($user['dmr_id'] ?? 0);
        if ($base_dmr_id < 1000000) {
            return ['ok' => false, 'error' => 'Your account does not have a base DMR ID set. Contact an admin.', 'device_id' => null];
        }

        $ssid_suffix = validate_ssid_suffix($data['ssid_suffix'] ?? '');
        if ($ssid_suffix === false) {
            return ['ok' => false, 'error' => 'SSID suffix must be a number from 01 to 99.', 'device_id' => null];
        }

        $peer_id = compute_peer_id($base_dmr_id, $ssid_suffix);
        $dmr_id  = $base_dmr_id;

        // Check peer_id uniqueness
        $check = $db->prepare("SELECT id FROM devices WHERE peer_id = ? AND status IN ('pending','approved')");
        $check->execute([$peer_id]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => "Hotspot ID {$peer_id} (suffix {$ssid_suffix}) is already registered.", 'device_id' => null];
        }
    } else {
        // Repeater: accept direct 7-digit DMR ID
        $dmr_id = validate_dmr_id($data['dmr_id'] ?? '');
        if ($dmr_id === false) {
            return ['ok' => false, 'error' => 'Invalid DMR ID. Must be a 7-digit number (1000000–9999999).', 'device_id' => null];
        }
        $ssid_suffix = null;
        $peer_id     = $dmr_id;

        $check = $db->prepare("SELECT id FROM devices WHERE dmr_id = ? AND status IN ('pending','approved') AND device_type = 'repeater'");
        $check->execute([$dmr_id]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => "DMR ID {$dmr_id} is already registered.", 'device_id' => null];
        }
    }

    $passphrase = generate_device_passphrase();

    $insert = $db->prepare(
        "INSERT INTO devices (user_id, callsign, dmr_id, ssid_suffix, peer_id, device_passphrase,
                              device_type, hardware_desc, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $insert->execute([$user_id, $callsign, $dmr_id, $ssid_suffix, $peer_id, $passphrase, $device_type, $hardware_desc]);

    return ['ok' => true, 'error' => null, 'device_id' => (int) $db->lastInsertId()];
}

function get_pending_devices(): array
{
    $stmt = get_db()->prepare(
        'SELECT d.id, d.user_id, d.callsign, d.dmr_id, d.ssid_suffix, d.peer_id,
                d.device_type, d.hardware_desc, d.created_at,
                u.display_name, u.username, u.email
         FROM devices d
         JOIN users u ON u.id = d.user_id
         WHERE d.status = \'pending\'
         ORDER BY d.created_at ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function approve_device(int $device_id, int $admin_id): array
{
    $db = get_db();

    $device = $db->prepare('SELECT dmr_id, peer_id FROM devices WHERE id = ?');
    $device->execute([$device_id]);
    $row = $device->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Device not found.'];
    }

    $peer_id = (int) ($row['peer_id'] ?? $row['dmr_id']);

    $conflict = $db->prepare(
        "SELECT d.id FROM devices d
         JOIN users u ON u.id = d.user_id
         WHERE d.peer_id = ? AND d.status = 'approved' AND u.moderation_state = 'active'"
    );
    $conflict->execute([$peer_id]);
    if ($conflict->fetch()) {
        return ['ok' => false, 'error' => "Peer ID {$peer_id} is already approved for another active device."];
    }

    $db->prepare(
        "UPDATE devices SET status = 'approved', approved_by = ?, reviewed_at = NOW() WHERE id = ?"
    )->execute([$admin_id, $device_id]);

    // Bump routing version so patched bridge.py and peer_auth.json get refreshed
    $db->prepare('UPDATE routing_config_state SET version = version + 1 WHERE id = 1')->execute();

    return ['ok' => true, 'error' => null];
}

function deny_device(int $device_id, int $admin_id, string $reason): bool
{
    $db   = get_db();
    $stmt = $db->prepare(
        "UPDATE devices SET status = 'denied', approved_by = ?, denied_reason = ?, reviewed_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$admin_id, $reason, $device_id]);

    $db->prepare('UPDATE routing_config_state SET version = version + 1 WHERE id = 1')->execute();

    return $stmt->rowCount() > 0;
}

function regenerate_device_passphrase(int $device_id, int $admin_id): array
{
    $db   = get_db();
    $pass = generate_device_passphrase();
    $stmt = $db->prepare('UPDATE devices SET device_passphrase = ? WHERE id = ?');
    $stmt->execute([$pass, $device_id]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Device not found.', 'passphrase' => null];
    }
    $db->prepare('UPDATE routing_config_state SET version = version + 1 WHERE id = 1')->execute();
    log_audit_action($admin_id, 'device_passphrase_regenerated', 'device', $device_id, []);
    return ['ok' => true, 'error' => null, 'passphrase' => $pass];
}

function update_device_hardware(int $device_id, int $user_id, string $hardware_desc): bool
{
    $hardware_desc = substr(trim($hardware_desc), 0, 255);
    $stmt = get_db()->prepare('UPDATE devices SET hardware_desc = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$hardware_desc, $device_id, $user_id]);
    return $stmt->rowCount() > 0;
}

function update_device_details(int $device_id, int $user_id, string $callsign, string $hardware_desc): array
{
    $callsign = validate_callsign($callsign);
    if ($callsign === false) {
        return ['ok' => false, 'error' => 'Invalid callsign. Must be 3–16 alphanumeric characters.'];
    }
    $hardware_desc = substr(trim($hardware_desc), 0, 255);
    $stmt = get_db()->prepare('UPDATE devices SET callsign = ?, hardware_desc = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$callsign, $hardware_desc, $device_id, $user_id]);
    return ['ok' => true, 'error' => null];
}

function update_device_features(int $device_id, int $user_id, bool $tg_rewrite_enabled, string $tg_rewrite_prefix): bool
{
    $prefix = substr(preg_replace('/[^0-9]/', '', $tg_rewrite_prefix), 0, 10) ?: null;
    $stmt = get_db()->prepare('UPDATE devices SET tg_rewrite_enabled = ?, tg_rewrite_prefix = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $tg_rewrite_enabled, $prefix, $device_id, $user_id]);
    return true;
}

function update_device_location(int $device_id, int $user_id, array $data): array
{
    $lat         = $data['lat']          !== '' ? (float)  $data['lat']          : null;
    $lon         = $data['lon']          !== '' ? (float)  $data['lon']          : null;
    $rx_freq     = $data['rx_freq']      !== '' ? (int)    $data['rx_freq']      : null;
    $tx_freq     = $data['tx_freq']      !== '' ? (int)    $data['tx_freq']      : null;
    $tx_power    = $data['tx_power']     !== '' ? (int)    $data['tx_power']     : null;
    $height_m    = $data['height_m']     !== '' ? (int)    $data['height_m']     : null;
    $loc_desc    = substr(trim($data['location_desc'] ?? ''), 0, 255) ?: null;
    $sta_desc    = substr(trim($data['station_desc']  ?? ''), 0, 255) ?: null;
    $sta_url     = substr(trim($data['station_url']   ?? ''), 0, 512) ?: null;

    if ($lat !== null && ($lat < -90.0 || $lat > 90.0)) {
        return ['ok' => false, 'error' => 'Latitude must be between -90 and 90.'];
    }
    if ($lon !== null && ($lon < -180.0 || $lon > 180.0)) {
        return ['ok' => false, 'error' => 'Longitude must be between -180 and 180.'];
    }

    get_db()->prepare(
        'UPDATE devices SET lat=?, lon=?, rx_freq=?, tx_freq=?, tx_power=?,
                            height_m=?, location_desc=?, station_desc=?, station_url=?
         WHERE id=? AND user_id=?'
    )->execute([$lat, $lon, $rx_freq, $tx_freq, $tx_power,
                $height_m, $loc_desc, $sta_desc, $sta_url,
                $device_id, $user_id]);

    return ['ok' => true, 'error' => null];
}

function update_device_ssid(int $device_id, int $user_id, int $ssid_suffix): array
{
    $db = get_db();

    if ($ssid_suffix < 1 || $ssid_suffix > 99) {
        return ['ok' => false, 'error' => 'SSID suffix must be between 01 and 99.'];
    }

    $stmt = $db->prepare('SELECT dmr_id, device_type FROM devices WHERE id = ? AND user_id = ?');
    $stmt->execute([$device_id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Device not found.'];
    }
    if ($row['device_type'] !== 'hotspot') {
        return ['ok' => false, 'error' => 'SSID suffix only applies to hotspots.'];
    }

    $new_peer_id = (int) $row['dmr_id'] * 100 + $ssid_suffix;

    $check = $db->prepare("SELECT id FROM devices WHERE peer_id = ? AND status IN ('pending','approved') AND id != ?");
    $check->execute([$new_peer_id, $device_id]);
    if ($check->fetch()) {
        $pad = str_pad((string) $ssid_suffix, 2, '0', STR_PAD_LEFT);
        return ['ok' => false, 'error' => "Hotspot ID {$new_peer_id} (suffix {$pad}) is already in use."];
    }

    $db->prepare('UPDATE devices SET ssid_suffix = ?, peer_id = ? WHERE id = ?')
       ->execute([$ssid_suffix, $new_peer_id, $device_id]);
    $db->prepare('UPDATE routing_config_state SET version = version + 1 WHERE id = 1')->execute();

    return ['ok' => true, 'error' => null, 'peer_id' => $new_peer_id];
}

function delete_device(int $device_id, int $user_id): bool
{
    $db   = get_db();
    $stmt = $db->prepare('DELETE FROM devices WHERE id = ? AND user_id = ?');
    $stmt->execute([$device_id, $user_id]);
    if ($stmt->rowCount() > 0) {
        $db->prepare('UPDATE routing_config_state SET version = version + 1 WHERE id = 1')->execute();
        return true;
    }
    return false;
}

function get_whitelist_eligible_dmr_ids(): array
{
    // Returns peer_ids (full connection IDs) for all approved active devices
    $stmt = get_db()->prepare(
        "SELECT COALESCE(d.peer_id, d.dmr_id) AS peer_id
         FROM devices d
         JOIN users u ON u.id = d.user_id
         WHERE d.status = 'approved' AND u.moderation_state = 'active'"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_approved_devices_for_auth(): array
{
    // Returns full auth data for peer_auth.json generation
    $stmt = get_db()->prepare(
        "SELECT d.id, d.user_id, COALESCE(d.peer_id, d.dmr_id) AS peer_id,
                d.device_passphrase, d.device_type, d.callsign
         FROM devices d
         JOIN users u ON u.id = d.user_id
         WHERE d.status = 'approved'
           AND u.moderation_state = 'active'
           AND d.device_passphrase IS NOT NULL"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
