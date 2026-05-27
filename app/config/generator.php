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

// --- F13: Controlled Restart constants (hardcoded, not DB-configurable) ---
const HBLINK_COMPOSE_FILE = '/etc/hblink3/docker-compose.yml';
const HBLINK_CONTAINER    = 'hblink';
const HBLINK_BACKUP_DIR   = '/etc/hblink3/backups/';
const HBLINK_BACKUP_KEEP  = 10;

function get_hblink_status(): string
{
    $output = shell_exec('sudo /usr/bin/docker inspect --format=\'{{.State.Status}}\' ' . HBLINK_CONTAINER . ' 2>/dev/null');
    if ($output === null) return 'unknown';
    $status = trim($output);
    return in_array($status, ['running', 'stopped', 'exited', 'restarting', 'paused', 'dead'], true)
        ? $status
        : 'unknown';
}

function get_generation_history(int $limit = 50, int $offset = 0): array
{
    $stmt = get_db()->prepare(
        'SELECT id, generated_at, generated_by_user_id, changed,
                applied, applied_at, apply_success, apply_error,
                (SELECT username FROM users WHERE id = h.generated_by_user_id) AS actor_username
         FROM config_generation_history h
         ORDER BY generated_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

function get_generation_detail(int $history_id): array|null
{
    $stmt = get_db()->prepare(
        'SELECT id, generated_at, generated_by_user_id, config_text, changed, diff_text,
                applied, applied_at, apply_success, apply_error, backup_path,
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

function _rotate_backups(): void
{
    $files = glob(HBLINK_BACKUP_DIR . 'hblink-*.cfg');
    if ($files === false || count($files) < HBLINK_BACKUP_KEEP) return;
    sort($files);
    $excess = count($files) - (HBLINK_BACKUP_KEEP - 1);
    foreach (array_slice($files, 0, $excess) as $old) {
        @unlink($old);
    }
}

function apply_hblink_config(int $actor_id): array
{
    $config_path = _get_config_output_path();
    if ($config_path === false) {
        return ['ok' => false, 'error' => 'HBLINK_CONFIG_PATH is not configured or not in an allowed directory.', 'generation_id' => null];
    }

    // Step 1: back up current live config before overwriting
    $backup_path = null;
    if (is_readable($config_path)) {
        $backup_path = HBLINK_BACKUP_DIR . 'hblink-' . date('Ymd-His') . '.cfg';
        if (!is_dir(HBLINK_BACKUP_DIR)) {
            @mkdir(HBLINK_BACKUP_DIR, 0750, true);
        }
        if (@copy($config_path, $backup_path) === false) {
            return ['ok' => false, 'error' => 'Failed to create config backup at ' . $backup_path . '. Check permissions.', 'generation_id' => null];
        }
        _rotate_backups();
    }

    // Step 2: generate config (writes file, creates history record)
    $gen = generate_hblink_config($actor_id);
    if (!$gen['ok']) {
        return ['ok' => false, 'error' => $gen['error'] ?? 'Config generation failed.', 'generation_id' => null];
    }

    // Step 3: get the just-created generation record
    $stmt = get_db()->prepare(
        'SELECT id FROM config_generation_history ORDER BY generated_at DESC LIMIT 1'
    );
    $stmt->execute();
    $gen_id = (int) ($stmt->fetchColumn() ?: 0);

    if ($gen_id === 0) {
        return ['ok' => false, 'error' => 'Could not locate generation record after write.', 'generation_id' => null];
    }

    // Mark as applied and record backup path
    get_db()->prepare(
        'UPDATE config_generation_history SET applied=1, applied_at=NOW(), backup_path=? WHERE id=?'
    )->execute([$backup_path, $gen_id]);

    // Step 4: restart HBLink container
    $restart_output = shell_exec('sudo /usr/bin/docker restart ' . HBLINK_CONTAINER . ' 2>&1');

    // Step 5: poll for up to 10s for container to return to running
    $running = false;
    $deadline = time() + 10;
    while (time() < $deadline) {
        $status = get_hblink_status();
        if ($status === 'running') {
            $running = true;
            break;
        }
        sleep(1);
    }

    $apply_success = $running ? 1 : 0;
    $apply_error   = $running ? null : 'HBLink did not return to running state within 10 seconds. Last restart output: ' . trim((string) $restart_output);

    get_db()->prepare(
        'UPDATE config_generation_history SET apply_success=?, apply_error=? WHERE id=?'
    )->execute([$apply_success, $apply_error, $gen_id]);

    log_audit_action($actor_id, 'config_applied', 'config', $gen_id, [
        'success'    => $running,
        'backup'     => $backup_path,
    ]);

    if (!$running) {
        return ['ok' => false, 'error' => $apply_error, 'generation_id' => $gen_id];
    }

    return ['ok' => true, 'error' => null, 'generation_id' => $gen_id];
}
