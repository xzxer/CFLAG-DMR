<?php
declare(strict_types=1);

define('CFLAG_ROOT', dirname(__DIR__));

require_once CFLAG_ROOT . '/app/config/env.php';
require_once CFLAG_ROOT . '/app/database/connection.php';
require_once CFLAG_ROOT . '/app/subscribers/manager.php';

if (!ini_get('allow_url_fopen')) {
    echo '[' . date('Y-m-d H:i:s') . '] status=error error=allow_url_fopen_disabled' . PHP_EOL;
    exit(1);
}

$result = import_subscribers_from_radioid(false);

$imported = $result['imported'] ?? 0;
$failed   = $result['batches_failed'] ?? 0;
$status   = $result['status'] ?? 'error';
$next_at  = $result['next_at'] ?? null;

$log = '[' . date('Y-m-d H:i:s') . ']'
     . ' status=' . $status
     . ' imported=' . $imported
     . ' batches_failed=' . $failed;

if ($status === 'too_soon' && $next_at !== null) {
    $log .= ' next_allowed=' . $next_at;
}
if (!empty($result['error'])) {
    $log .= ' error=' . str_replace(["\r", "\n"], ' ', $result['error']);
}

echo $log . PHP_EOL;

exit(in_array($status, ['ok', 'not_modified', 'too_soon'], true) ? 0 : 1);
