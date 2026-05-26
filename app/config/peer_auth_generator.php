<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../devices/manager.php';

define('PEER_AUTH_JSON_PATH', '/etc/hblink3/json/peer_auth.json');

function generate_peer_auth_json(): array
{
    $devices = get_approved_devices_for_auth();

    $peers = [];
    foreach ($devices as $d) {
        $peers[(string) $d['peer_id']] = [
            'passphrase' => $d['device_passphrase'],
            'device_id'  => (int) $d['id'],
            'user_id'    => (int) $d['user_id'],
            'callsign'   => $d['callsign'],
            'allowed'    => true,
        ];
    }

    // Fetch current routing version for cache invalidation
    $stmt = get_db()->prepare('SELECT version FROM routing_config_state WHERE id = 1');
    $stmt->execute();
    $version = (int) ($stmt->fetchColumn() ?: 0);

    $payload = json_encode([
        'version'      => $version,
        'generated_at' => gmdate('c'),
        'peers'        => $peers,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $tmp = PEER_AUTH_JSON_PATH . '.tmp';
    if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Failed to write peer_auth.json.tmp — check permissions on ' . dirname(PEER_AUTH_JSON_PATH)];
    }

    if (!rename($tmp, PEER_AUTH_JSON_PATH)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Failed to install peer_auth.json (rename failed).'];
    }

    return ['ok' => true, 'peer_count' => count($peers)];
}
