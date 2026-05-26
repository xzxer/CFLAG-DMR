<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/settings.php';

function get_openbridge_connections(bool $enabled_only = false): array
{
    $sql  = 'SELECT * FROM openbridge_connections';
    $sql .= $enabled_only ? ' WHERE enabled = 1' : '';
    $sql .= ' ORDER BY name';
    return get_db()->query($sql)->fetchAll();
}

function get_openbridge_connection(int $id): array|null
{
    $stmt = get_db()->prepare('SELECT * FROM openbridge_connections WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_openbridge_connection(int $actor_id, array $data): array
{
    $id             = (int) ($data['id'] ?? 0);
    $name           = trim($data['name'] ?? '');
    $remote_address = trim($data['remote_address'] ?? '');
    $port           = (int) ($data['port'] ?? 0);
    $passphrase     = $data['passphrase'] ?? '';
    $network_id     = (int) ($data['network_id'] ?? 0);
    $enabled        = isset($data['enabled']) ? 1 : 0;

    if ($name === '' || strlen($name) > 64) {
        return ['ok' => false, 'id' => null, 'error' => 'Name must be 1–64 characters.'];
    }
    if ($remote_address === '') {
        return ['ok' => false, 'id' => null, 'error' => 'Remote address is required.'];
    }
    if ($port < 1 || $port > 65535) {
        return ['ok' => false, 'id' => null, 'error' => 'Port must be between 1 and 65535.'];
    }
    if (!validate_passphrase($passphrase)) {
        return ['ok' => false, 'id' => null, 'error' => 'Passphrase must be 1–15 characters.'];
    }
    if ($network_id < 1) {
        return ['ok' => false, 'id' => null, 'error' => 'Network ID must be a positive integer.'];
    }

    $db = get_db();

    $stmt = $db->prepare('SELECT id FROM openbridge_connections WHERE name = ? AND id != ?');
    $stmt->execute([$name, $id]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'id' => null, 'error' => 'An OpenBridge connection with that name already exists.'];
    }

    if ($id > 0) {
        $db->prepare(
            'UPDATE openbridge_connections SET name=?, remote_address=?, port=?, passphrase=?, network_id=?, enabled=?
             WHERE id=?'
        )->execute([$name, $remote_address, $port, $passphrase, $network_id, $enabled, $id]);
        log_audit_action($actor_id, 'openbridge_updated', 'config', $id, ['name' => $name]);
        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    $stmt = $db->prepare(
        'INSERT INTO openbridge_connections (name, remote_address, port, passphrase, network_id, enabled)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $remote_address, $port, $passphrase, $network_id, $enabled]);
    $new_id = (int) $db->lastInsertId();
    log_audit_action($actor_id, 'openbridge_created', 'config', $new_id, ['name' => $name]);
    return ['ok' => true, 'id' => $new_id, 'error' => null];
}

function toggle_openbridge_connection(int $id, bool $enabled): bool
{
    $stmt = get_db()->prepare('UPDATE openbridge_connections SET enabled = ? WHERE id = ?');
    $stmt->execute([$enabled ? 1 : 0, $id]);
    return $stmt->rowCount() > 0;
}

function delete_openbridge_connection(int $id): bool
{
    $stmt = get_db()->prepare('DELETE FROM openbridge_connections WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}
