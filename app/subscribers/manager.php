<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';

const RADIOID_CSV_URL = 'https://radioid.net/static/user.csv';

function get_subscriber_import_stats(): array
{
    $stmt = get_db()->prepare(
        "SELECT `key`, `value` FROM system_settings
         WHERE `key` IN (
             'subscriber_last_import_at',
             'subscriber_last_modified',
             'subscriber_import_count',
             'subscriber_min_import_interval_hours'
         )"
    );
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }

    $last_import_at = $settings['subscriber_last_import_at']            ?? '';
    $last_modified  = $settings['subscriber_last_modified']             ?? '';
    $count          = (int)($settings['subscriber_import_count']        ?? 0);
    $min_hours      = (int)($settings['subscriber_min_import_interval_hours'] ?? 23);

    $next_import_at = null;
    if ($last_import_at !== '') {
        $ts = strtotime($last_import_at);
        if ($ts !== false) {
            $next_import_at = date('Y-m-d H:i:s', $ts + $min_hours * 3600);
        }
    }

    return [
        'count'              => $count,
        'last_import_at'     => $last_import_at !== '' ? $last_import_at : null,
        'last_modified'      => $last_modified  !== '' ? $last_modified  : null,
        'min_interval_hours' => $min_hours,
        'next_import_at'     => $next_import_at,
    ];
}

function _flush_subscriber_batch(array $batch): int|false
{
    if (empty($batch)) return 0;

    $placeholders = implode(',', array_fill(0, count($batch), '(?,?,?,?,?,?,\'radioid\')'));
    $params = [];
    foreach ($batch as $row) {
        foreach ($row as $val) {
            $params[] = $val;
        }
    }

    $sql = 'INSERT INTO subscriber_ids (radio_id, callsign, name, city, state, country, source) VALUES '
         . $placeholders
         . ' ON DUPLICATE KEY UPDATE'
         . ' callsign = IF(source = \'local\', callsign, VALUES(callsign)),'
         . ' name     = IF(source = \'local\', name,     VALUES(name)),'
         . ' city     = IF(source = \'local\', city,     VALUES(city)),'
         . ' state    = IF(source = \'local\', state,    VALUES(state)),'
         . ' country  = IF(source = \'local\', country,  VALUES(country))';

    try {
        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return count($batch);
    } catch (\Throwable $e) {
        error_log('[subscriber import] batch failed: ' . $e->getMessage());
        return false;
    }
}

function import_subscribers_from_radioid(bool $force = false): array
{
    if (!ini_get('allow_url_fopen')) {
        return ['status' => 'error', 'imported' => 0, 'batches_failed' => 0, 'error' => 'allow_url_fopen is disabled in PHP configuration.'];
    }

    $stats = get_subscriber_import_stats();

    if (!$force && $stats['next_import_at'] !== null && strtotime($stats['next_import_at']) > time()) {
        return [
            'status'         => 'too_soon',
            'imported'       => 0,
            'batches_failed' => 0,
            'error'          => null,
            'next_at'        => $stats['next_import_at'],
        ];
    }

    $last_modified = $stats['last_modified'] ?? '';
    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "If-Modified-Since: {$last_modified}\r\nUser-Agent: CFLAG-DMR/1.0 (contact: admin@cflag.net)\r\n",
            'ignore_errors' => true,
            'timeout'       => 60,
        ],
    ]);

    $stream = @fopen(RADIOID_CSV_URL, 'r', false, $context);
    if ($stream === false) {
        return ['status' => 'error', 'imported' => 0, 'batches_failed' => 0, 'error' => 'Failed to connect to RadioID.net. Check network connectivity.'];
    }

    $status_line = $http_response_header[0] ?? '';

    if (strpos($status_line, '304') !== false) {
        fclose($stream);
        return ['status' => 'not_modified', 'imported' => 0, 'batches_failed' => 0, 'error' => null];
    }

    if (strpos($status_line, '200') === false) {
        fclose($stream);
        return ['status' => 'error', 'imported' => 0, 'batches_failed' => 0, 'error' => 'Unexpected HTTP response: ' . $status_line];
    }

    $new_last_modified = '';
    foreach ($http_response_header as $h) {
        if (stripos($h, 'Last-Modified:') === 0) {
            $new_last_modified = trim(substr($h, 14));
            break;
        }
    }

    set_time_limit(300);

    $batch          = [];
    $imported       = 0;
    $batches_failed = 0;
    $header_skipped = false;

    while (($row = fgetcsv($stream)) !== false) {
        if (!$header_skipped) {
            $header_skipped = true;
            continue;
        }

        if (count($row) < 7) continue;

        $radio_id = (int) trim($row[0]);
        if ($radio_id <= 0) continue;

        $callsign = substr(trim($row[1]), 0, 16);
        $name     = substr(trim(trim($row[2]) . ' ' . trim($row[3])), 0, 128);
        $city     = substr(trim($row[4]), 0, 128);
        $state    = substr(trim($row[5]), 0, 64);
        $country  = substr(trim($row[6]), 0, 64);

        $batch[] = [$radio_id, $callsign, $name, $city, $state, $country];

        if (count($batch) >= 500) {
            $result = _flush_subscriber_batch($batch);
            if ($result === false) {
                $batches_failed++;
            } else {
                $imported += $result;
            }
            $batch = [];
        }
    }
    fclose($stream);

    if (!empty($batch)) {
        $result = _flush_subscriber_batch($batch);
        if ($result === false) {
            $batches_failed++;
        } else {
            $imported += $result;
        }
    }

    $db = get_db();
    $db->prepare("UPDATE system_settings SET `value`=? WHERE `key`='subscriber_import_count'")->execute([$imported]);

    if ($batches_failed === 0) {
        $db->prepare("UPDATE system_settings SET `value`=? WHERE `key`='subscriber_last_import_at'")->execute([date('Y-m-d H:i:s')]);
        if ($new_last_modified !== '') {
            $db->prepare("UPDATE system_settings SET `value`=? WHERE `key`='subscriber_last_modified'")->execute([$new_last_modified]);
        }
    }

    return [
        'status'         => 'ok',
        'imported'       => $imported,
        'batches_failed' => $batches_failed,
        'error'          => $batches_failed > 0 ? "{$batches_failed} batch(es) failed; partial import. Check error log." : null,
    ];
}

function get_subscriber(int $dmr_id): array|null
{
    $stmt = get_db()->prepare('SELECT * FROM subscriber_ids WHERE radio_id = ?');
    $stmt->execute([$dmr_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_subscribers_for_ids(array $dmr_ids): array
{
    if (empty($dmr_ids)) return [];

    $dmr_ids = array_values(array_unique(array_map('intval', $dmr_ids)));
    $placeholders = implode(',', array_fill(0, count($dmr_ids), '?'));

    $stmt = get_db()->prepare(
        "SELECT * FROM subscriber_ids WHERE radio_id IN ({$placeholders})"
    );
    $stmt->execute($dmr_ids);

    $map = [];
    while ($row = $stmt->fetch()) {
        $map[(int) $row['radio_id']] = $row;
    }
    return $map;
}

function upsert_subscriber_override(int $radio_id, string $callsign, string $name): void
{
    get_db()->prepare(
        'INSERT INTO subscriber_ids (radio_id, callsign, name, source)
         VALUES (?, ?, ?, \'local\')
         ON DUPLICATE KEY UPDATE callsign = VALUES(callsign), name = VALUES(name), source = \'local\''
    )->execute([$radio_id, substr($callsign, 0, 16), substr($name, 0, 128)]);
}

function delete_subscriber_override(int $radio_id): bool
{
    $stmt = get_db()->prepare(
        "DELETE FROM subscriber_ids WHERE radio_id = ? AND source = 'local'"
    );
    $stmt->execute([$radio_id]);
    return $stmt->rowCount() > 0;
}

function get_subscriber_overrides(int $limit = 50, int $offset = 0): array
{
    $stmt = get_db()->prepare(
        "SELECT * FROM subscriber_ids WHERE source = 'local' ORDER BY radio_id LIMIT ? OFFSET ?"
    );
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}
