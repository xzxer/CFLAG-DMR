# Research: F8 — HBLink Config Visibility

**Date**: 2026-05-25 | **Branch**: `005-hblink-config-view`

All decisions below are resolved. No NEEDS CLARIFICATION items remain.

---

## Decision 1: Process Start Time on Linux

**Decision**: Read `/proc/{pid}/stat`, take field index 21 (0-based, the `starttime` field in clock ticks since boot). Get boot epoch from `/proc/stat` line `btime`. Compute: `$start_epoch = $btime + (int)($starttime / 100)`.

**Rationale**: This is the authoritative, low-overhead method on Linux. No shell exec needed. The `100` divisor is the standard CLK_TCK value on x86-64 Linux — confirmed by the spec's own assumption ("process start time is read from `/proc/[pid]/stat`"). Using `posix_sysconf(_SC_CLK_TCK)` is more correct but requires the `posix` PHP extension, which may not be installed. Hardcoding 100 is safe for standard Ubuntu 24.04 x86-64 deployments and is explicitly noted as a simplification for this feature.

**Alternatives considered**:
- `shell_exec('ps -o lstart= -p ' . $pid)` — works but requires shell exec just for a timestamp; adds attack surface unnecessarily.
- `posix_sysconf(2)` — correct but requires `php8.3-posix` extension; not in the installed dependency list.

**Implementation**:
```php
function get_process_start_time(int $pid): ?int
{
    $stat = @file_get_contents("/proc/{$pid}/stat");
    if ($stat === false) return null;
    // stat format: pid (comm) state ppid ... starttime(field 21, 0-based)
    // comm can contain spaces and parens — find last ')' to skip it safely
    $after_comm = substr($stat, strrpos($stat, ')') + 2);
    $fields = explode(' ', $after_comm);
    $starttime_ticks = (int)($fields[19] ?? 0); // field 21 overall = field 19 after comm
    $btime = get_btime();
    if ($btime === null) return null;
    return $btime + (int)($starttime_ticks / 100);
}

function get_btime(): ?int
{
    $stat = @file_get_contents('/proc/stat');
    if ($stat === false) return null;
    if (preg_match('/^btime\s+(\d+)/m', $stat, $m)) {
        return (int)$m[1];
    }
    return null;
}
```

**Field indexing note**: `/proc/[pid]/stat` field layout after the `comm` field (which is enclosed in parens): state=0, ppid=1, pgrp=2, session=3, tty_nr=4, tpgid=5, flags=6, minflt=7, cminflt=8, majflt=9, cmajflt=10, utime=11, stime=12, cutime=13, cstime=14, priority=15, nice=16, num_threads=17, itrealvalue=18, **starttime=19** (relative to fields after comm removal).

---

## Decision 2: INI File Masking — Regex on Raw Content vs parse_ini_file()

**Decision**: Regex on the raw file string. Do not use `parse_ini_file()`.

**Rationale**: `parse_ini_file()` discards comments, collapses whitespace, and does not preserve the original formatting. The feature requirement is to display the *actual* file contents with line numbers — the original text must be preserved. Masking is applied in the rendering layer by line-scanning with a regex, not by round-tripping through an INI parser.

**Masking regex pattern** (applied per-line):
```php
// Matches: optional whitespace, key (no ; or # prefix), =, value
// Key must match PASSPHRASE|PASSWORD|SECRET (case-insensitive)
if (preg_match('/^\s*([^;#\s][^=]*)\s*=\s*(.*)/i', $line, $m)
    && preg_match('/PASSPHRASE|PASSWORD|SECRET/i', trim($m[1]))) {
    // mask the value
}
```

**Comment handling**: Lines where the key is part of a comment (starts with `;` or `#`) are rendered as-is — the spec explicitly requires this (Edge Cases section).

**Alternatives considered**:
- `parse_ini_file()` — loses formatting; unsuitable for display
- Separate parse pass just for masking then display raw — complex and fragile if parsing diverges from display

---

## Decision 3: Line-Numbered Code Display

**Decision**: Render file content as an `<ol>` with one `<li>` per line. CSS removes list markers and adds monospace styling. A wrapping `<div class="code-viewer">` provides `overflow-x: auto` for horizontal scrolling on narrow viewports.

**Rationale**: Pure CSS/HTML solution — no JS needed for line numbers. `<ol>` provides semantically correct numbered list, and CSS `counter` is not needed since `<ol>` handles numbering natively. Line numbers are styled with a muted color via `::marker` or a `<span class="ln">` beside each line.

**Implementation approach**:
```html
<div class="code-viewer">
  <ol>
    <li><span class="line-content">...</span></li>
    ...
  </ol>
</div>
```

CSS:
```css
.code-viewer { overflow-x: auto; background: #020617; border: 1px solid #334155; border-radius: 8px; }
.code-viewer ol { margin: 0; padding: 0.75rem 0; font-family: monospace; font-size: 0.85rem; line-height: 1.6; }
.code-viewer li { padding: 0 1rem 0 0.5rem; color: #f8fafc; white-space: pre; list-style-position: inside; }
.code-viewer li::marker { color: #475569; min-width: 2.5rem; display: inline-block; }
```

**Alternatives considered**:
- `<pre>` with CSS `counter-reset`/`counter-increment` per line — works but requires wrapping each line in a `<span>`, which is essentially the same amount of HTML as `<li>` elements
- JS-based line numbering — unnecessary complexity for a server-rendered feature

---

## Decision 4: Path Allowlist Validation

**Decision**: Use `realpath()` to resolve the path (following symlinks), then check that the resolved absolute path starts with one of the entries in `HBLINK_ALLOWED_DIRS`. Both the resolved path and the allowed-dir prefix are compared after normalization (trailing slash on allowed dirs).

**Rationale**: `realpath()` resolves symlinks, preventing traversal via symlinks. Checking a prefix after resolution prevents `../` traversal. The allowed dirs are hardcoded constants — not configurable — so they cannot be expanded by database tampering.

**Implementation**:
```php
const HBLINK_ALLOWED_DIRS = ['/opt/hblink3/', '/etc/hblink/', '/opt/hblink/'];

function validate_hblink_path(string $path): string|false
{
    $real = realpath($path);
    if ($real === false) return false; // file does not exist
    foreach (HBLINK_ALLOWED_DIRS as $dir) {
        if (str_starts_with($real, $dir)) return $real;
    }
    return false; // outside allowlist
}
```

**Alternatives considered**:
- Configurable allowlist in system_settings — rejected because it would allow the allowlist itself to be expanded via DB compromise, defeating the purpose
- Basename-only check — insufficient; doesn't prevent symlinks pointing outside the allowed tree

---

## Decision 5: PID Resolution

**Decision**: Attempt to read the PID file path from system_settings (`hblink_pid_path`). If the file exists and contains a valid integer, use that PID. Otherwise, fall back to `shell_exec('pgrep -f ' . escapeshellarg($process_name))` and take the first returned PID. If neither returns a valid PID, HBLink is considered stopped.

**Rationale**: PID file is the preferred source (no shell exec needed). `pgrep` fallback handles cases where HBLink was started without writing a PID file. `escapeshellarg()` ensures the process name cannot inject shell metacharacters even if the system_settings value is tampered with.

**Alternatives considered**:
- Always use pgrep — simpler but requires shell exec on every status page load; less efficient
- Only use PID file — breaks if HBLink doesn't write one (common in some Docker configurations)

---

## Decision 6: Uptime Display Format

**Decision**: Display uptime as a human-readable string: days/hours/minutes (e.g., "2 days, 4 hours, 17 minutes"). Format: largest non-zero unit first, down to minutes; seconds omitted for display clarity.

**Implementation**:
```php
function format_uptime(int $seconds): string
{
    $days    = intdiv($seconds, 86400);
    $hours   = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $parts = [];
    if ($days)    $parts[] = "{$days} " . ($days === 1 ? 'day' : 'days');
    if ($hours)   $parts[] = "{$hours} " . ($hours === 1 ? 'hour' : 'hours');
    if ($minutes) $parts[] = "{$minutes} " . ($minutes === 1 ? 'minute' : 'minutes');
    return $parts ? implode(', ', $parts) : 'less than a minute';
}
```

---

## Decision 7: Error Display (FR-013)

**Decision**: Wrap all file reads and process checks in try/catch or check return values. On failure, set an `$error` variable and render a styled error message in the page. PHP's `display_errors` is Off in production (documented in install-dependencies.md) so raw errors won't leak regardless, but the page must show a meaningful message.

**Pattern** (used in all three viewer pages):
```php
$error = null;
$content = null;
try {
    $real = validate_hblink_path($path);
    if ($real === false) {
        $error = 'File not found or path is outside the permitted directory.';
    } elseif (!is_readable($real)) {
        $error = 'File exists but cannot be read. Check web server file permissions.';
    } else {
        $content = file_get_contents($real);
    }
} catch (\Throwable $e) {
    $error = 'An unexpected error occurred reading the file.';
    error_log('HBLink config reader: ' . $e->getMessage());
}
```
