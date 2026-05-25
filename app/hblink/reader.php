<?php
declare(strict_types=1);

// Hardcoded — not configurable via DB to prevent allowlist expansion through DB compromise.
const HBLINK_ALLOWED_DIRS = [
    '/etc/hblink3/',
    '/opt/hblink3/',
    '/etc/hblink/',
    '/opt/hblink/',
];

function get_hblink_setting(string $key): string
{
    $stmt = get_db()->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string) $row['value'] : '';
}

function validate_hblink_path(string $path): string|false
{
    if ($path === '') return false;
    $real = realpath($path);
    if ($real === false) return false;
    foreach (HBLINK_ALLOWED_DIRS as $dir) {
        if (str_starts_with($real, $dir)) return $real;
    }
    return false;
}

function load_hblink_file(string $setting_key): array
{
    $path    = get_hblink_setting($setting_key);
    $content = null;
    $modified_at = null;
    $error   = null;

    if ($path === '') {
        $error = 'File path is not configured in system settings.';
    } else {
        $real = validate_hblink_path($path);
        if ($real === false) {
            $error = 'File not found or path is outside the permitted directory.';
        } elseif (!is_readable($real)) {
            $error = 'File exists but cannot be read. Check web server file permissions.';
        } else {
            $content     = file_get_contents($real);
            $modified_at = filemtime($real) ?: null;
            if ($content === false) {
                $content = null;
                $error   = 'Failed to read file.';
            }
        }
    }

    return ['content' => $content, 'modified_at' => $modified_at, 'error' => $error];
}
