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
        'SELECT id, callsign, dmr_id, device_type, hardware_desc, status, denied_reason, created_at, updated_at
         FROM devices WHERE user_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_device(int $device_id, int $user_id): array|null
{
    $stmt = get_db()->prepare(
        'SELECT * FROM devices WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$device_id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function register_device(int $user_id, array $data): array
{
    $db = get_db();

    $stmt = $db->prepare('SELECT moderation_state FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || in_array($user['moderation_state'], ['suspended', 'banned'], true)) {
        return ['ok' => false, 'error' => 'Your account is not eligible to register devices.', 'device_id' => null];
    }

    $callsign = validate_callsign($data['callsign'] ?? '');
    if ($callsign === false) {
        return ['ok' => false, 'error' => 'Invalid callsign. Must be 3–16 alphanumeric characters.', 'device_id' => null];
    }

    $dmr_id = validate_dmr_id($data['dmr_id'] ?? '');
    if ($dmr_id === false) {
        return ['ok' => false, 'error' => 'Invalid DMR ID. Must be a 7-digit number (1000000–9999999).', 'device_id' => null];
    }

    $device_type = $data['device_type'] ?? '';
    if (!in_array($device_type, ['hotspot', 'repeater'], true)) {
        return ['ok' => false, 'error' => 'Invalid device type.', 'device_id' => null];
    }

    $hardware_desc = substr(trim($data['hardware_desc'] ?? ''), 0, 255);

    $check = $db->prepare(
        "SELECT id FROM devices WHERE dmr_id = ? AND status IN ('pending','approved')"
    );
    $check->execute([$dmr_id]);
    if ($check->fetch()) {
        return ['ok' => false, 'error' => 'DMR ID ' . $dmr_id . ' is already registered by another user.', 'device_id' => null];
    }

    $insert = $db->prepare(
        'INSERT INTO devices (user_id, callsign, dmr_id, device_type, hardware_desc, status)
         VALUES (?, ?, ?, ?, ?, \'pending\')'
    );
    $insert->execute([$user_id, $callsign, $dmr_id, $device_type, $hardware_desc]);

    return ['ok' => true, 'error' => null, 'device_id' => (int) $db->lastInsertId()];
}

function get_pending_devices(): array
{
    $stmt = get_db()->prepare(
        'SELECT d.id, d.callsign, d.dmr_id, d.device_type, d.hardware_desc, d.created_at,
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

    $device = $db->prepare('SELECT dmr_id FROM devices WHERE id = ?');
    $device->execute([$device_id]);
    $row = $device->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Device not found.'];
    }

    $conflict = $db->prepare(
        "SELECT d.id FROM devices d
         JOIN users u ON u.id = d.user_id
         WHERE d.dmr_id = ? AND d.status = 'approved' AND u.moderation_state = 'active'"
    );
    $conflict->execute([$row['dmr_id']]);
    if ($conflict->fetch()) {
        return ['ok' => false, 'error' => 'DMR ID ' . $row['dmr_id'] . ' is already approved for another active device.'];
    }

    $db->prepare(
        "UPDATE devices SET status = 'approved', approved_by = ?, reviewed_at = NOW() WHERE id = ?"
    )->execute([$admin_id, $device_id]);

    return ['ok' => true, 'error' => null];
}

function deny_device(int $device_id, int $admin_id, string $reason): bool
{
    $stmt = get_db()->prepare(
        "UPDATE devices SET status = 'denied', approved_by = ?, denied_reason = ?, reviewed_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$admin_id, $reason, $device_id]);
    return $stmt->rowCount() > 0;
}

function update_device_hardware(int $device_id, int $user_id, string $hardware_desc): bool
{
    $hardware_desc = substr(trim($hardware_desc), 0, 255);
    $stmt = get_db()->prepare(
        'UPDATE devices SET hardware_desc = ? WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$hardware_desc, $device_id, $user_id]);
    return $stmt->rowCount() > 0;
}

function delete_device(int $device_id, int $user_id): bool
{
    $stmt = get_db()->prepare(
        'DELETE FROM devices WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$device_id, $user_id]);
    return $stmt->rowCount() > 0;
}

function get_whitelist_eligible_dmr_ids(): array
{
    $stmt = get_db()->prepare(
        "SELECT d.dmr_id
         FROM devices d
         JOIN users u ON u.id = d.user_id
         WHERE d.status = 'approved' AND u.moderation_state = 'active'"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
