<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';

function validate_tgid(string $raw): int|false
{
    $raw = trim($raw);
    if (!ctype_digit($raw)) return false;
    $id = (int) $raw;
    return ($id >= 1 && $id <= 16777215) ? $id : false;
}

function validate_tg_name(string $raw): string|false
{
    $raw = trim($raw);
    $len = strlen($raw);
    return ($len >= 1 && $len <= 128) ? $raw : false;
}

// ── Read functions ────────────────────────────────────────────────────────────

function get_all_talkgroups(bool $include_inactive = false): array
{
    $sql = 'SELECT tg.*, u.display_name AS owner_display_name, u.username AS owner_username
            FROM talkgroups tg
            LEFT JOIN users u ON u.id = tg.owner_user_id';
    if (!$include_inactive) {
        $sql .= ' WHERE tg.active = 1';
    }
    $sql .= ' ORDER BY tg.tgid ASC';
    $stmt = get_db()->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_talkgroup(int $id): array|null
{
    $stmt = get_db()->prepare(
        'SELECT tg.*, u.display_name AS owner_display_name, u.username AS owner_username
         FROM talkgroups tg
         LEFT JOIN users u ON u.id = tg.owner_user_id
         WHERE tg.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_talkgroup_by_tgid(int $tgid): array|null
{
    $stmt = get_db()->prepare('SELECT * FROM talkgroups WHERE tgid = ?');
    $stmt->execute([$tgid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_public_talkgroups(): array
{
    $stmt = get_db()->prepare(
        "SELECT id, tgid, name, description FROM talkgroups
         WHERE active = 1 AND tg_type = 'open'
         ORDER BY tgid ASC"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_owned_talkgroups(int $user_id): array
{
    $stmt = get_db()->prepare(
        "SELECT * FROM talkgroups WHERE owner_user_id = ? ORDER BY tgid ASC"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function search_talkgroups(string $q): array
{
    $q = trim($q);
    $like = '%' . $q . '%';
    $stmt = get_db()->prepare(
        "SELECT tg.*, u.display_name AS owner_display_name, u.username AS owner_username
         FROM talkgroups tg
         LEFT JOIN users u ON u.id = tg.owner_user_id
         WHERE tg.active = 1 AND (tg.name LIKE ? OR CAST(tg.tgid AS CHAR) = ?)
         ORDER BY tg.tgid ASC"
    );
    $stmt->execute([$like, $q]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Admin write functions (US1) ───────────────────────────────────────────────

function create_talkgroup(array $data, int $admin_id): array
{
    $tgid = validate_tgid($data['tgid'] ?? '');
    if ($tgid === false) {
        return ['ok' => false, 'error' => 'Invalid TGID. Must be 1–16,777,215.'];
    }

    $name = validate_tg_name($data['name'] ?? '');
    if ($name === false) {
        return ['ok' => false, 'error' => 'Name is required (1–128 characters).'];
    }

    $tg_type = $data['tg_type'] ?? 'open';
    if (!in_array($tg_type, ['open', 'private', 'club'], true)) {
        return ['ok' => false, 'error' => 'Invalid talkgroup type.'];
    }

    $description = substr(trim($data['description'] ?? ''), 0, 2000) ?: null;

    if (get_talkgroup_by_tgid($tgid) !== null) {
        return ['ok' => false, 'error' => 'TGID ' . $tgid . ' is already in use.'];
    }

    get_db()->prepare(
        "INSERT INTO talkgroups (tgid, name, description, tg_type, ownership_tier, owner_user_id)
         VALUES (?, ?, ?, ?, 'admin', NULL)"
    )->execute([$tgid, $name, $description, $tg_type]);

    return ['ok' => true, 'error' => null, 'id' => (int) get_db()->lastInsertId()];
}

function update_talkgroup(int $id, array $data, int $actor_id): array
{
    $db  = get_db();
    $tg  = get_talkgroup($id);
    if (!$tg) {
        return ['ok' => false, 'error' => 'Talkgroup not found.'];
    }

    $is_sysadmin = user_has_role($actor_id, 'system_admin');
    $is_owner    = ((int) $tg['owner_user_id'] === $actor_id);

    if (!$is_sysadmin && !$is_owner) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }

    $name = validate_tg_name($data['name'] ?? $tg['name']);
    if ($name === false) {
        return ['ok' => false, 'error' => 'Name is required (1–128 characters).'];
    }

    $description = array_key_exists('description', $data)
        ? (substr(trim($data['description']), 0, 2000) ?: null)
        : $tg['description'];

    if ($is_sysadmin) {
        $tg_type = $data['tg_type'] ?? $tg['tg_type'];
        if (!in_array($tg_type, ['open', 'private', 'club'], true)) {
            return ['ok' => false, 'error' => 'Invalid talkgroup type.'];
        }
        $db->prepare(
            'UPDATE talkgroups SET name=?, description=?, tg_type=? WHERE id=?'
        )->execute([$name, $description, $tg_type, $id]);
    } else {
        $db->prepare(
            'UPDATE talkgroups SET name=?, description=? WHERE id=?'
        )->execute([$name, $description, $id]);
    }

    return ['ok' => true, 'error' => null];
}

function set_talkgroup_active(int $id, bool $active, int $admin_id): bool
{
    $stmt = get_db()->prepare('UPDATE talkgroups SET active=? WHERE id=?');
    $stmt->execute([$active ? 1 : 0, $id]);
    return $stmt->rowCount() > 0;
}

function delete_talkgroup(int $id, int $admin_id): array
{
    $db = get_db();
    $check = $db->prepare(
        'SELECT COUNT(*) FROM device_talkgroup_subscriptions WHERE talkgroup_id = ?'
    );
    $check->execute([$id]);
    if ((int) $check->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'Cannot delete: talkgroup has active device subscriptions.'];
    }
    $db->prepare('DELETE FROM talkgroups WHERE id=?')->execute([$id]);
    return ['ok' => true, 'error' => null];
}

// ── Talkgroup requests (US2) ──────────────────────────────────────────────────

function submit_talkgroup_request(int $user_id, array $data): array
{
    $tgid = validate_tgid($data['proposed_tgid'] ?? '');
    if ($tgid === false) {
        return ['ok' => false, 'error' => 'Invalid proposed TGID. Must be 1–16,777,215.'];
    }

    $name = validate_tg_name($data['proposed_name'] ?? '');
    if ($name === false) {
        return ['ok' => false, 'error' => 'Talkgroup name is required (1–128 characters).'];
    }

    $proposed_type = $data['proposed_type'] ?? 'open';
    if (!in_array($proposed_type, ['open', 'private', 'club'], true)) {
        return ['ok' => false, 'error' => 'Invalid talkgroup type.'];
    }

    $description = substr(trim($data['description'] ?? ''), 0, 2000) ?: null;

    $db = get_db();
    $dup = $db->prepare(
        "SELECT id FROM talkgroup_requests WHERE requester_id=? AND proposed_tgid=? AND status='pending'"
    );
    $dup->execute([$user_id, $tgid]);
    if ($dup->fetch()) {
        return ['ok' => false, 'error' => 'You already have a pending request for TGID ' . $tgid . '.'];
    }

    $db->prepare(
        'INSERT INTO talkgroup_requests (requester_id, proposed_tgid, proposed_name, proposed_type, description)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$user_id, $tgid, $name, $proposed_type, $description]);

    return ['ok' => true, 'error' => null];
}

function get_user_talkgroup_requests(int $user_id): array
{
    $stmt = get_db()->prepare(
        'SELECT * FROM talkgroup_requests WHERE requester_id=? ORDER BY created_at DESC'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_pending_talkgroup_requests(): array
{
    $stmt = get_db()->prepare(
        'SELECT r.*, u.display_name, u.username, u.email
         FROM talkgroup_requests r
         JOIN users u ON u.id = r.requester_id
         WHERE r.status = \'pending\'
         ORDER BY r.created_at ASC'
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function approve_talkgroup_request(int $request_id, int $admin_id, int $final_tgid, string $tier): array
{
    if (!in_array($tier, ['admin', 'user_partial', 'user_full'], true)) {
        return ['ok' => false, 'error' => 'Invalid ownership tier.'];
    }

    $db = get_db();
    $req = $db->prepare('SELECT * FROM talkgroup_requests WHERE id=?');
    $req->execute([$request_id]);
    $row = $req->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Request not found.'];
    }

    if (get_talkgroup_by_tgid($final_tgid) !== null) {
        return ['ok' => false, 'error' => 'TGID ' . $final_tgid . ' is already in use.'];
    }

    $owner_id = in_array($tier, ['user_partial', 'user_full'], true)
        ? (int) $row['requester_id']
        : null;

    $db->prepare(
        "INSERT INTO talkgroups (tgid, name, description, tg_type, ownership_tier, owner_user_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$final_tgid, $row['proposed_name'], $row['description'], $row['proposed_type'], $tier, $owner_id]);

    $db->prepare(
        "UPDATE talkgroup_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?"
    )->execute([$admin_id, $request_id]);

    return ['ok' => true, 'error' => null];
}

function deny_talkgroup_request(int $request_id, int $admin_id, string $reason): bool
{
    $stmt = get_db()->prepare(
        "UPDATE talkgroup_requests SET status='denied', reviewed_by=?, denial_reason=?, reviewed_at=NOW() WHERE id=?"
    );
    $stmt->execute([$admin_id, $reason, $request_id]);
    return $stmt->rowCount() > 0;
}

// ── Access lists (US3) ────────────────────────────────────────────────────────

function get_access_list(int $talkgroup_id): array
{
    $stmt = get_db()->prepare(
        'SELECT * FROM talkgroup_access_lists WHERE talkgroup_id=? ORDER BY list_type, dmr_id'
    );
    $stmt->execute([$talkgroup_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_access_entry(int $talkgroup_id, int $dmr_id, string $list_type, int $actor_id): bool
{
    if (!in_array($list_type, ['allow', 'block'], true)) return false;
    $stmt = get_db()->prepare(
        'INSERT IGNORE INTO talkgroup_access_lists (talkgroup_id, dmr_id, list_type, added_by)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$talkgroup_id, $dmr_id, $list_type, $actor_id]);
    return true;
}

function remove_access_entry(int $talkgroup_id, int $dmr_id, string $list_type, int $actor_id): bool
{
    $stmt = get_db()->prepare(
        'DELETE FROM talkgroup_access_lists WHERE talkgroup_id=? AND dmr_id=? AND list_type=?'
    );
    $stmt->execute([$talkgroup_id, $dmr_id, $list_type]);
    return $stmt->rowCount() > 0;
}

function check_talkgroup_access(int $talkgroup_id, int $dmr_id): bool
{
    $db = get_db();
    $block = $db->prepare(
        "SELECT id FROM talkgroup_access_lists WHERE talkgroup_id=? AND dmr_id=? AND list_type='block'"
    );
    $block->execute([$talkgroup_id, $dmr_id]);
    if ($block->fetch()) return false;

    $allow_count = $db->prepare(
        "SELECT COUNT(*) FROM talkgroup_access_lists WHERE talkgroup_id=? AND list_type='allow'"
    );
    $allow_count->execute([$talkgroup_id]);
    if ((int) $allow_count->fetchColumn() === 0) return true;

    $allow = $db->prepare(
        "SELECT id FROM talkgroup_access_lists WHERE talkgroup_id=? AND dmr_id=? AND list_type='allow'"
    );
    $allow->execute([$talkgroup_id, $dmr_id]);
    return (bool) $allow->fetch();
}

function submit_ownership_upgrade(int $talkgroup_id, int $requester_id, string $reason): array
{
    $tg = get_talkgroup($talkgroup_id);
    if (!$tg) {
        return ['ok' => false, 'error' => 'Talkgroup not found.'];
    }
    if ((int) $tg['owner_user_id'] !== $requester_id || $tg['ownership_tier'] !== 'user_partial') {
        return ['ok' => false, 'error' => 'Only the user_partial owner may request a tier upgrade.'];
    }
    $reason = trim($reason);
    if ($reason === '') {
        return ['ok' => false, 'error' => 'A reason is required.'];
    }
    get_db()->prepare(
        'INSERT INTO talkgroup_ownership_upgrade_requests (talkgroup_id, requester_id, reason)
         VALUES (?, ?, ?)'
    )->execute([$talkgroup_id, $requester_id, $reason]);
    return ['ok' => true, 'error' => null];
}

function approve_ownership_upgrade(int $request_id, int $admin_id): bool
{
    $db = get_db();
    $req = $db->prepare('SELECT * FROM talkgroup_ownership_upgrade_requests WHERE id=?');
    $req->execute([$request_id]);
    $row = $req->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;

    $db->prepare(
        "UPDATE talkgroups SET ownership_tier='user_full' WHERE id=?"
    )->execute([(int) $row['talkgroup_id']]);

    $db->prepare(
        "UPDATE talkgroup_ownership_upgrade_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?"
    )->execute([$admin_id, $request_id]);

    return true;
}

function deny_ownership_upgrade(int $request_id, int $admin_id): bool
{
    $stmt = get_db()->prepare(
        "UPDATE talkgroup_ownership_upgrade_requests SET status='denied', reviewed_by=?, reviewed_at=NOW() WHERE id=?"
    );
    $stmt->execute([$admin_id, $request_id]);
    return $stmt->rowCount() > 0;
}

function get_pending_ownership_upgrades(): array
{
    $stmt = get_db()->prepare(
        "SELECT r.*, tg.name AS talkgroup_name, tg.tgid,
                u.display_name, u.username, u.email
         FROM talkgroup_ownership_upgrade_requests r
         JOIN talkgroups tg ON tg.id = r.talkgroup_id
         JOIN users u ON u.id = r.requester_id
         WHERE r.status = 'pending'
         ORDER BY r.created_at ASC"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Device subscriptions (US4) ────────────────────────────────────────────────

function get_subscriptions_for_device(int $device_id): array
{
    $stmt = get_db()->prepare(
        'SELECT dts.id, dts.talkgroup_id, dts.timeslot,
                tg.tgid, tg.name AS tg_name, tg.tg_type
         FROM device_talkgroup_subscriptions dts
         JOIN talkgroups tg ON tg.id = dts.talkgroup_id
         WHERE dts.device_id = ?
         ORDER BY tg.tgid ASC'
    );
    $stmt->execute([$device_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_subscription(int $device_id, int $talkgroup_id, int $timeslot, int $actor_user_id): array
{
    if (!in_array($timeslot, [1, 2], true)) {
        return ['ok' => false, 'error' => 'Timeslot must be 1 or 2.'];
    }

    $db = get_db();

    $device = $db->prepare("SELECT status, user_id FROM devices WHERE id=?");
    $device->execute([$device_id]);
    $dev = $device->fetch(PDO::FETCH_ASSOC);
    if (!$dev) {
        return ['ok' => false, 'error' => 'Device not found.'];
    }
    if ((int) $dev['user_id'] !== $actor_user_id) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }
    if ($dev['status'] !== 'approved') {
        return ['ok' => false, 'error' => 'Device must be approved before subscribing.'];
    }

    $tg = get_talkgroup($talkgroup_id);
    if (!$tg || !$tg['active']) {
        return ['ok' => false, 'error' => 'Talkgroup not found or inactive.'];
    }
    if ($tg['tg_type'] !== 'open') {
        return ['ok' => false, 'error' => 'Use "Request Access" for private or club talkgroups.'];
    }

    $db->prepare(
        'INSERT IGNORE INTO device_talkgroup_subscriptions (device_id, talkgroup_id, timeslot)
         VALUES (?, ?, ?)'
    )->execute([$device_id, $talkgroup_id, $timeslot]);

    return ['ok' => true, 'error' => null];
}

function remove_subscription(int $device_id, int $talkgroup_id, int $timeslot, int $actor_user_id): bool
{
    $db = get_db();
    $check = $db->prepare('SELECT user_id FROM devices WHERE id=?');
    $check->execute([$device_id]);
    $dev = $check->fetch(PDO::FETCH_ASSOC);
    if (!$dev || (int) $dev['user_id'] !== $actor_user_id) return false;

    $stmt = $db->prepare(
        'DELETE FROM device_talkgroup_subscriptions
         WHERE device_id=? AND talkgroup_id=? AND timeslot=?'
    );
    $stmt->execute([$device_id, $talkgroup_id, $timeslot]);
    return $stmt->rowCount() > 0;
}

function submit_join_request(int $device_id, int $talkgroup_id, int $actor_user_id): array
{
    $db = get_db();
    $device = $db->prepare("SELECT status, user_id FROM devices WHERE id=?");
    $device->execute([$device_id]);
    $dev = $device->fetch(PDO::FETCH_ASSOC);
    if (!$dev) {
        return ['ok' => false, 'error' => 'Device not found.'];
    }
    if ((int) $dev['user_id'] !== $actor_user_id) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }
    if ($dev['status'] !== 'approved') {
        return ['ok' => false, 'error' => 'Device must be approved before requesting access.'];
    }

    $tg = get_talkgroup($talkgroup_id);
    if (!$tg || !$tg['active']) {
        return ['ok' => false, 'error' => 'Talkgroup not found or inactive.'];
    }
    if (!in_array($tg['tg_type'], ['private', 'club'], true)) {
        return ['ok' => false, 'error' => 'Use the subscribe form for open talkgroups.'];
    }

    $dup = $db->prepare(
        "SELECT id FROM talkgroup_requests WHERE requester_id=? AND proposed_tgid=? AND status='pending'"
    );
    $dup->execute([$actor_user_id, (int) $tg['tgid']]);
    if ($dup->fetch()) {
        return ['ok' => false, 'error' => 'You already have a pending access request for this talkgroup.'];
    }

    $db->prepare(
        "INSERT INTO talkgroup_requests (requester_id, proposed_tgid, proposed_name, proposed_type, description)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([
        $actor_user_id,
        (int) $tg['tgid'],
        $tg['name'],
        $tg['tg_type'],
        'Device access request (device_id=' . $device_id . ')',
    ]);

    return ['ok' => true, 'error' => null];
}

// ── F7 data contract ──────────────────────────────────────────────────────────

function get_device_subscriptions_for_config(): array
{
    $stmt = get_db()->prepare(
        "SELECT dts.device_id, d.dmr_id, dts.talkgroup_id,
                tg.tgid, tg.name AS tg_name, dts.timeslot
         FROM device_talkgroup_subscriptions dts
         JOIN talkgroups tg ON tg.id = dts.talkgroup_id
         JOIN devices d ON d.id = dts.device_id
         JOIN users u ON u.id = d.user_id
         WHERE tg.active = 1
           AND d.status = 'approved'
           AND u.moderation_state = 'active'"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
