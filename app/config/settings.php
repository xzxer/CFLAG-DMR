<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../auth/roles.php';

function validate_passphrase(string $passphrase): bool
{
    $len = strlen($passphrase);
    return $len >= 1 && $len <= 15;
}

function get_master_settings(): array|null
{
    $stmt = get_db()->prepare('SELECT * FROM master_server_settings WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_master_settings(int $actor_id, array $data): array
{
    $bind_address   = trim($data['bind_address'] ?? '');
    $public_address = trim($data['public_address'] ?? '');
    $port           = (int) ($data['port'] ?? 0);
    $passphrase     = $data['passphrase'] ?? '';
    $report_address = trim($data['report_address'] ?? '');
    $report_port    = (int) ($data['report_port'] ?? 0);
    $ping_time      = (int) ($data['ping_time'] ?? 5);
    $max_missed     = (int) ($data['max_missed'] ?? 3);

    if ($bind_address === '') {
        return ['ok' => false, 'error' => 'Bind address is required.'];
    }
    if ($public_address === '') {
        return ['ok' => false, 'error' => 'Public address is required.'];
    }
    if ($port < 1 || $port > 65535) {
        return ['ok' => false, 'error' => 'Port must be between 1 and 65535.'];
    }
    if (!validate_passphrase($passphrase)) {
        return ['ok' => false, 'error' => 'Passphrase must be 1–15 characters.'];
    }
    if ($report_address === '') {
        return ['ok' => false, 'error' => 'Report address is required.'];
    }
    if ($report_port < 1 || $report_port > 65535) {
        return ['ok' => false, 'error' => 'Report port must be between 1 and 65535.'];
    }
    if ($ping_time < 1) {
        return ['ok' => false, 'error' => 'Ping time must be at least 1 second.'];
    }
    if ($max_missed < 1) {
        return ['ok' => false, 'error' => 'Max missed must be at least 1.'];
    }

    get_db()->prepare(
        'INSERT INTO master_server_settings
             (id, bind_address, public_address, port, passphrase, report_address, report_port, ping_time, max_missed, updated_by_user_id)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             bind_address       = VALUES(bind_address),
             public_address     = VALUES(public_address),
             port               = VALUES(port),
             passphrase         = VALUES(passphrase),
             report_address     = VALUES(report_address),
             report_port        = VALUES(report_port),
             ping_time          = VALUES(ping_time),
             max_missed         = VALUES(max_missed),
             updated_by_user_id = VALUES(updated_by_user_id)'
    )->execute([
        $bind_address, $public_address, $port, $passphrase,
        $report_address, $report_port,
        $ping_time, $max_missed, $actor_id,
    ]);

    log_audit_action($actor_id, 'master_settings_updated', 'config', null, []);
    return ['ok' => true, 'error' => null];
}
