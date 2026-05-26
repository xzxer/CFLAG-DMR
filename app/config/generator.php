<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/openbridge.php';
require_once __DIR__ . '/../config/peer_auth_generator.php';
require_once __DIR__ . '/../config/routing_generator.php';
require_once __DIR__ . '/../devices/manager.php';
require_once __DIR__ . '/../talkgroups/manager.php';
require_once __DIR__ . '/../hblink/reader.php';

function _get_config_output_path(): string|false
{
    require_once __DIR__ . '/../config/env.php';
    $path = (string) env('HBLINK_CONFIG_PATH', '');
    if ($path === '') return false;
    $real = realpath($path);
    if ($real === false) {
        $dir  = realpath(dirname($path));
        if ($dir === false) return false;
        $real = $dir . '/' . basename($path);
    }
    foreach (HBLINK_ALLOWED_DIRS as $allowed) {
        if (str_starts_with($real, $allowed)) return $real;
    }
    return false;
}

function _render_config_text(array $settings, array $peers, array $openbridge_connections): string
{
    $lines = [];

    $lines[] = '[GLOBAL]';
    $lines[] = 'PATH: ./';
    $lines[] = 'PING_TIME: ' . (int) $settings['ping_time'];
    $lines[] = 'MAX_MISSED: ' . (int) $settings['max_missed'];
    $lines[] = '';

    $lines[] = '[LOGGER]';
    $lines[] = 'LOG_FILE: ./log/hblink.log';
    $lines[] = 'LOG_HANDLERS: file';
    $lines[] = '';

    $lines[] = '[REPORTS]';
    $lines[] = 'REPORT: True';
    $lines[] = 'REPORT_INTERVAL: 60';
    $lines[] = 'REPORT_PORT: ' . (int) $settings['report_port'];
    $lines[] = 'REPORT_CLIENTS: ' . $settings['report_address'];
    $lines[] = '';

    $lines[] = '[MASTER]';
    $lines[] = 'MODE: MASTER';
    $lines[] = 'ENABLED: True';
    $lines[] = 'REPEAT: True';
    $lines[] = 'MAX_PEERS: 10';
    $lines[] = 'EXPORT_AMBE: False';
    $lines[] = 'IP: ' . $settings['bind_address'];
    $lines[] = 'PORT: ' . (int) $settings['port'];
    $lines[] = 'PASSPHRASE: ' . $settings['passphrase'];
    $lines[] = 'GROUP_HANGTIME: 5';

    if (!empty($peers)) {
        $reg_acl_parts = array_map(fn($p) => 'PERMIT:' . $p, $peers);
        $lines[] = 'REG_ACL: ' . implode(',', $reg_acl_parts);
    } else {
        $lines[] = 'REG_ACL: DENY:ALL';
    }
    $lines[] = 'SUB_ACL: DENY:0';
    $lines[] = 'TGID_TS1_ACL: PERMIT:ALL';
    $lines[] = 'TGID_TS2_ACL: PERMIT:ALL';
    $lines[] = '';

    foreach ($openbridge_connections as $ob) {
        $section_name = 'OPENBRIDGE-' . strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '_', $ob['name']));
        $lines[] = "[{$section_name}]";
        $lines[] = 'MODE: OPENBRIDGE';
        $lines[] = 'ENABLED: True';
        $lines[] = 'IP: ' . $ob['remote_address'];
        $lines[] = 'PORT: ' . (int) $ob['port'];
        $lines[] = 'PASSPHRASE: ' . $ob['passphrase'];
        $lines[] = 'NETWORK_ID: ' . (int) $ob['network_id'];
        $lines[] = 'TARGET_SOCK: ' . $ob['remote_address'] . ':' . (int) $ob['port'];
        $lines[] = 'USE_ACL: True';
        $lines[] = 'SUB_ACL: DENY:0';
        $lines[] = 'TGID_ACL: PERMIT:ALL';
        $lines[] = '';
    }

    return implode("\n", $lines);
}

function _compute_diff(string $old_text, string $new_text): string
{
    $old_lines = explode("\n", $old_text);
    $new_lines = explode("\n", $new_text);

    $removed = array_diff($old_lines, $new_lines);
    $added   = array_diff($new_lines, $old_lines);

    $diff_parts = [];
    foreach ($removed as $line) {
        $diff_parts[] = '- ' . $line;
    }
    foreach ($added as $line) {
        $diff_parts[] = '+ ' . $line;
    }

    return implode("\n", $diff_parts);
}

function get_generation_history(int $limit = 50): array
{
    $stmt = get_db()->prepare(
        'SELECT id, generated_at, generated_by_user_id, changed,
                (SELECT username FROM users WHERE id = h.generated_by_user_id) AS actor_username
         FROM config_generation_history h
         ORDER BY generated_at DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function get_generation_detail(int $history_id): array|null
{
    $stmt = get_db()->prepare(
        'SELECT id, generated_at, generated_by_user_id, config_text, changed, diff_text,
                (SELECT username FROM users WHERE id = h.generated_by_user_id) AS actor_username
         FROM config_generation_history h
         WHERE id = ?'
    );
    $stmt->execute([$history_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function generate_hblink_config(int $actor_id): array
{
    $config_path = _get_config_output_path();
    if ($config_path === false) {
        return ['ok' => false, 'changed' => false, 'error' => 'HBLINK_CONFIG_PATH is not configured or not in allowed directories.'];
    }

    $settings = get_master_settings();
    if ($settings === null) {
        return ['ok' => false, 'changed' => false, 'error' => 'Master server settings have not been configured yet.'];
    }

    $openbridge = get_openbridge_connections(true);
    $peer_ids   = get_whitelist_eligible_dmr_ids();

    $new_config_text = _render_config_text($settings, $peer_ids, $openbridge);

    $stmt = get_db()->prepare(
        'SELECT config_text FROM config_generation_history ORDER BY generated_at DESC LIMIT 1'
    );
    $stmt->execute();
    $last = $stmt->fetch();
    $old_config_text = $last ? $last['config_text'] : null;

    $changed = ($old_config_text === null || trim($old_config_text) !== trim($new_config_text));

    $tmp_path = $config_path . '.tmp';
    if (file_put_contents($tmp_path, $new_config_text, LOCK_EX) === false) {
        return ['ok' => false, 'changed' => false, 'error' => 'Failed to write temporary config file. Check directory permissions.'];
    }

    if (!rename($tmp_path, $config_path)) {
        @unlink($tmp_path);
        return ['ok' => false, 'changed' => false, 'error' => 'Failed to install config file (rename failed).'];
    }

    $diff_text = $changed && $old_config_text !== null
        ? _compute_diff($old_config_text, $new_config_text)
        : null;

    get_db()->prepare(
        'INSERT INTO config_generation_history (generated_by_user_id, config_text, changed, diff_text)
         VALUES (?, ?, ?, ?)'
    )->execute([
        $actor_id,
        $new_config_text,
        $changed ? 1 : 0,
        $diff_text,
    ]);

    get_db()->prepare(
        'DELETE FROM config_change_queue WHERE created_at <= NOW()'
    )->execute();

    // Regenerate peer auth and routing JSON files for patched HBLink
    generate_peer_auth_json();
    generate_bridge_routes_json();

    log_audit_action($actor_id, 'config_generated', 'config', null, [
        'changed' => $changed,
        'peers'   => count($peer_ids),
    ]);

    return ['ok' => true, 'changed' => $changed, 'error' => null];
}
