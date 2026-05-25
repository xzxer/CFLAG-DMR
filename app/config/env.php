<?php
declare(strict_types=1);

(static function (): void {
    $path = dirname(__DIR__, 2) . '/.env';

    if (!is_file($path)) {
        throw new RuntimeException('.env file not found at ' . $path);
    }

    $parsed = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if (
            strlen($value) >= 2
            && (
                ($value[0] === '"' && $value[-1] === '"')
                || ($value[0] === "'" && $value[-1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }
        $parsed[$key] = $value;
    }

    $GLOBALS['_ENV_PARSED'] = $parsed;
})();

function env(string $key, mixed $default = null): mixed
{
    return $GLOBALS['_ENV_PARSED'][$key] ?? $default;
}
