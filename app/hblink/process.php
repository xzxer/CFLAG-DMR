<?php
declare(strict_types=1);

require_once __DIR__ . '/reader.php';

function get_btime(): ?int
{
    $stat = @file_get_contents('/proc/stat');
    if ($stat === false) return null;
    if (preg_match('/^btime\s+(\d+)/m', $stat, $m)) {
        return (int) $m[1];
    }
    return null;
}

function get_process_start_time(int $pid): ?int
{
    $stat = @file_get_contents("/proc/{$pid}/stat");
    if ($stat === false) return null;
    // comm field is wrapped in parens and may contain spaces — skip past last ')'
    $pos = strrpos($stat, ')');
    if ($pos === false) return null;
    $fields = explode(' ', trim(substr($stat, $pos + 2)));
    // starttime is field 21 overall (0-based) = index 19 after comm removal
    $starttime_ticks = isset($fields[19]) ? (int) $fields[19] : 0;
    if ($starttime_ticks === 0) return null;
    $btime = get_btime();
    if ($btime === null) return null;
    return $btime + (int) ($starttime_ticks / 100);
}

function resolve_pid(string $pid_path, string $process_name): ?int
{
    if ($pid_path !== '' && file_exists($pid_path)) {
        $content = @file_get_contents($pid_path);
        if ($content !== false) {
            $pid = (int) trim($content);
            if ($pid > 0 && file_exists("/proc/{$pid}")) {
                return $pid;
            }
        }
    }

    if ($process_name !== '') {
        $output = @shell_exec('pgrep -f ' . escapeshellarg($process_name));
        if ($output !== null && $output !== '') {
            foreach (array_filter(array_map('trim', explode("\n", $output))) as $line) {
                $pid = (int) $line;
                if ($pid > 0) return $pid;
            }
        }
    }

    return null;
}

function format_uptime(int $seconds): string
{
    $days    = intdiv($seconds, 86400);
    $hours   = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts   = [];
    if ($days)    $parts[] = "{$days} "    . ($days    === 1 ? 'day'    : 'days');
    if ($hours)   $parts[] = "{$hours} "   . ($hours   === 1 ? 'hour'   : 'hours');
    if ($minutes) $parts[] = "{$minutes} " . ($minutes === 1 ? 'minute' : 'minutes');
    return $parts ? implode(', ', $parts) : 'less than a minute';
}

function get_hblink_status(): array
{
    $pid_path     = get_hblink_setting('hblink_pid_path');
    $process_name = get_hblink_setting('hblink_process_name');
    $cfg_path     = get_hblink_setting('hblink_cfg_path');

    $pid        = resolve_pid($pid_path, $process_name);
    $running    = $pid !== null;
    $started_at = null;
    $uptime     = null;

    if ($running) {
        $started_at = get_process_start_time($pid);
        if ($started_at !== null) {
            $uptime = max(0, time() - $started_at);
        }
    }

    $cfg_real  = ($cfg_path !== '') ? validate_hblink_path($cfg_path) : false;
    $cfg_mtime = ($cfg_real !== false) ? (filemtime($cfg_real) ?: null) : null;
    $drifted   = ($started_at !== null && $cfg_mtime !== null && $cfg_mtime > $started_at);

    return [
        'running'        => $running,
        'pid'            => $pid,
        'started_at'     => $started_at,
        'uptime_seconds' => $uptime,
        'cfg_modified_at'=> $cfg_mtime,
        'config_drifted' => $drifted,
    ];
}
