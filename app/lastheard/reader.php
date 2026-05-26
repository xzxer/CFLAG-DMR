<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';

// Hardcoded — not configurable via DB to prevent allowlist expansion through DB compromise.
const LASTHEARD_ALLOWED_DIRS = ['/opt/HBMonv2/', '/var/log/hbmon/'];

function get_lastheard_setting(string $key): string
{
    $stmt = get_db()->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string) $row['value'] : '';
}

function validate_lastheard_path(string $path): string|false
{
    if ($path === '') return false;
    // Resolve the directory (must exist); append basename to get canonical path even if file doesn't exist yet.
    $dir  = realpath(dirname($path));
    if ($dir === false) return false;
    $real = $dir . '/' . basename($path);
    foreach (LASTHEARD_ALLOWED_DIRS as $allowed) {
        if (str_starts_with($real, $allowed)) return $real;
    }
    return false;
}

function parse_lh_line(string $line): array|null
{
    $line = trim($line);
    if ($line === '') return null;
    $row = str_getcsv($line);
    if (count($row) !== 12) return null;
    if (strtotime($row[0]) === false) return null;
    return [
        'datetime'    => $row[0],
        'duration'    => $row[1],
        'call_type'   => $row[2],
        'action'      => $row[3],
        'system_name' => $row[4],
        'src_id'      => $row[5],
        'callsign'    => $row[6],
        'timeslot'    => ltrim($row[7], 'TS'),
        'tgid'        => ltrim($row[8], 'TG'),
        'tg_name'     => $row[9],
        'sub_id'      => $row[10],
        'short_name'  => $row[11],
    ];
}

function load_lastheard(int $limit = 0, int $offset = 0, array $filters = []): array
{
    $path_raw = get_lastheard_setting('lastheard_log_path');
    $path     = validate_lastheard_path($path_raw);

    if ($path === false) {
        return ['rows' => [], 'total' => 0, 'error' => 'Activity log is currently unavailable.'];
    }
    if (!file_exists($path)) {
        return ['rows' => [], 'total' => 0, 'error' => null];
    }
    if (!is_readable($path)) {
        return ['rows' => [], 'total' => 0, 'error' => 'Activity log is currently unavailable.'];
    }

    $has_filters = !empty($filters);
    $small_limit = ($limit > 0 && $limit <= 20 && !$has_filters);

    if ($small_limit) {
        $lines = _tail_lines($path, max(50, $limit * 3));
    } else {
        $lines = array_reverse(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
    }

    $parsed  = [];
    $matched = [];

    foreach ($lines as $line) {
        $entry = parse_lh_line($line);
        if ($entry === null) continue;

        if ($has_filters && !_matches_filters($entry, $filters)) continue;

        $matched[] = $entry;
    }

    $total = count($matched);
    $rows  = ($limit > 0)
        ? array_slice($matched, $offset, $limit)
        : $matched;

    return ['rows' => $rows, 'total' => $total, 'error' => null];
}

function _tail_lines(string $path, int $lines): array
{
    $file  = new SplFileObject($path, 'r');
    $file->seek(PHP_INT_MAX);
    $total = $file->key();
    $start = max(0, $total - $lines);
    $result = [];
    $file->seek($start);
    while (!$file->eof()) {
        $line = trim((string) $file->current());
        if ($line !== '') $result[] = $line;
        $file->next();
    }
    return array_reverse($result);
}

function get_recently_active_dmr_ids(int $minutes = 30): array
{
    $result = load_lastheard(200);
    if (!empty($result['error']) || empty($result['rows'])) {
        return [];
    }

    $cutoff = time() - ($minutes * 60);
    $seen   = [];

    foreach ($result['rows'] as $row) {
        $src_id = trim($row['src_id'] ?? '');
        if ($src_id === '' || !ctype_digit($src_id)) continue;
        if (strtotime($row['datetime']) < $cutoff) continue;
        if (isset($seen[$src_id])) continue;
        $seen[$src_id] = $row['datetime'];
    }

    if (empty($seen)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($seen), '?'));
    $stmt = get_db()->prepare(
        "SELECT dmr_id, callsign FROM devices
         WHERE dmr_id IN ({$placeholders}) AND status = 'approved'"
    );
    $stmt->execute(array_map('intval', array_keys($seen)));
    $device_map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $device_map[(string) $row['dmr_id']] = $row['callsign'];
    }

    $active = [];
    foreach ($seen as $dmr_id => $last_seen) {
        $active[] = [
            'dmr_id'    => $dmr_id,
            'callsign'  => $device_map[$dmr_id] ?? '',
            'last_seen' => $last_seen,
        ];
    }

    return $active;
}

function _matches_filters(array $entry, array $filters): bool
{
    if (!empty($filters['callsign'])) {
        if (stripos($entry['callsign'], $filters['callsign']) === false) return false;
    }
    if (!empty($filters['tg'])) {
        $tg = $filters['tg'];
        if (ctype_digit($tg)) {
            if ($entry['tgid'] !== $tg) return false;
        } else {
            if (stripos($entry['tg_name'], $tg) === false) return false;
        }
    }
    if (!empty($filters['date_from'])) {
        $from = strtotime($filters['date_from']);
        if ($from !== false && strtotime($entry['datetime']) < $from) return false;
    }
    if (!empty($filters['date_to'])) {
        // Include the full end day
        $to = strtotime($filters['date_to'] . ' 23:59:59');
        if ($to !== false && strtotime($entry['datetime']) > $to) return false;
    }
    return true;
}
