# Research: F9 — Last-Heard & Activity Log

**Date**: 2026-05-25
**Branch**: `006-lastheard-log`

---

## Decision 1: Actual lastheard.log CSV Format

**Decision**: The log has 12 fields, not 8 as the spec assumed. Parse by column index, not by name.

**Rationale**: Confirmed by reading `/opt/HBMonv2/monitor.py` line 787. The write format string is:

```python
'{},{},{},{},{},{},{},TS{},TG{},{},{},{}'.format(
    _now,                                   # [0] datetime        "2026-05-25 14:32:11"
    p[9],                                   # [1] duration        "4.2"  (float seconds)
    p[0],                                   # [2] call_type       "GROUP VOICE"
    p[1],                                   # [3] action          "END"
    p[3],                                   # [4] system_name     "CFLAG-MASTER"
    p[5],                                   # [5] src_id          "3171234"
    alias_call(int(p[5]), subscriber_ids),  # [6] callsign        "W7ABC"
    p[7],                                   # [7] timeslot        "1" → stored as "TS1"
    p[8],                                   # [8] tgid            "91"  → stored as "TG91"
    alias_tgid(int(p[8]), talkgroup_ids),   # [9] tg_name         "Worldwide"
    p[6],                                   # [10] sub_id         "3171234"
    alias_short(int(p[6]), subscriber_ids)  # [11] short_name     "W7ABC"
)
```

**Key parsing notes**:
- Column [7] has a "TS" prefix — strip it to get the numeric slot: `ltrim($row[7], 'TS')`
- Column [8] has a "TG" prefix — strip it to get the numeric TGID: `ltrim($row[8], 'TG')`
- Column [1] is a float string like `"4.2"` — cast with `(float)` and display as `round()` seconds
- Only lines with `action == "END"` are logged (transmissions > 2 seconds only)
- The file only exists once the first valid transmission occurs. PHP must handle `file_not_found` gracefully.

**Columns for display**:

| Display Name | CSV Column | Notes |
|---|---|---|
| Date/Time | [0] | Format: `Y-m-d H:i:s` |
| Callsign | [6] | Primary identifier |
| DMR ID | [5] (src_id) | Numeric |
| TG Name | [9] | May be blank if unknown |
| TG ID | [8] | Strip "TG" prefix |
| Slot | [7] | Strip "TS" prefix → "1" or "2" |
| System | [4] | Network system name |
| Duration | [1] | Float seconds → display as `Xs` |

**Alternatives considered**: Using HBMonv2's generated `lastheard.html` template — rejected because we need a styled, responsive PHP page integrated into CFLAG DMR's UI, not HBMonv2's standalone HTML.

---

## Decision 2: File Reading Strategy for Large Logs

**Decision**: Use PHP `file()` to read all lines, then `array_reverse()` and `array_slice()` for ordering and limiting. For the public view, read only the tail of the file using `SplFileObject::seek()` to avoid loading unbounded data.

**Rationale**: At typical DMR network activity (< 200 transmissions/day), the log will be ~1–10 MB after a year of operation. `file()` is adequate for authenticated filtered views where all rows may be needed. For the public view (last 20), reading the whole file is wasteful if it grows large; `SplFileObject` with reverse seek is more efficient.

**Implementation for public view** (read last N lines efficiently):
```php
// Read last $limit lines without loading whole file
function tail_file(string $path, int $lines): array {
    $file = new SplFileObject($path, 'r');
    $file->seek(PHP_INT_MAX);
    $total = $file->key();
    $start = max(0, $total - $lines);
    $result = [];
    $file->seek($start);
    while (!$file->eof()) {
        $line = $file->current();
        if (trim($line) !== '') $result[] = $line;
        $file->next();
    }
    return array_reverse($result); // newest first
}
```

**For authenticated view**: `file($path)` + `array_reverse()` + filter + `array_slice()` for pagination. This loads all lines but allows full-text filtering across the entire history.

**Alternatives considered**:
- `shell_exec("tail -n 100 $path")` — fast, but introduces shell dependency and requires `escapeshellarg()` on a path already validated. Acceptable but less portable.
- Database ingestion (future F9 enhancement) — correct long-term solution but out of scope for MVP.

---

## Decision 3: Auto-Refresh Approach

**Decision**: 
- Public view: `<meta http-equiv="refresh" content="30">` — zero JS, reliable, universally supported.
- Authenticated view: `setTimeout(() => location.reload(), 30000)` — reloads the same URL, preserving all GET filter/pagination parameters automatically.

**Rationale**: Full page reload is sufficient for a log-viewer page. No partial DOM updates needed. `meta refresh` in the public view avoids any JS dependency for the simplest case.

**Alternatives considered**: `fetch()` polling to update only the table — more complex, not justified for MVP. SSE/WebSocket — out of scope per spec.

---

## Decision 4: Filter Implementation

**Decision**: GET-based form submission (`<form method="get">`). PHP reads `$_GET['callsign']`, `$_GET['tgid']`, `$_GET['date_from']`, `$_GET['date_to']`. All values sanitized (`htmlspecialchars` for display, no SQL involved). Filtering is done in PHP by iterating parsed CSV rows.

**Rationale**: GET params preserve filter state across page loads and auto-refreshes. No AJAX required.

**Filter logic**:
- Callsign: `stripos($row[6], $filter_callsign) !== false` (substring match, case-insensitive)
- TG: if numeric, compare `ltrim($row[8], 'TG') === $filter_tgid`; if text, `stripos($row[9], $filter_tg)`
- Date from/to: `strtotime($row[0]) >= $date_from && strtotime($row[0]) <= $date_to`

**Alternatives considered**: Client-side JS filtering — rejected because it requires sending all log entries to the browser before filtering, which is impractical for large logs.

---

## Decision 5: Path Allowlist Constant

**Decision**: `LASTHEARD_ALLOWED_DIRS = ['/opt/HBMonv2/', '/var/log/hbmon/']` hardcoded in `app/lastheard/reader.php`. Same pattern as `HBLINK_ALLOWED_DIRS` in F8.

**Rationale**: System admins update the path via `system_settings` table directly for now. The allowlist prevents path traversal even if the DB value is tampered with.

**File path**: Default `lastheard_log_path = /opt/HBMonv2/log/lastheard.log`. The directory `0755` and file `0644` — `www-data` can read without any `chown` (confirmed by `ls -la /opt/HBMonv2/log/`).

---

## Decision 6: Dashboard Card Data

**Decision**: System_admin dashboard shows last 5 entries in a compact table (callsign, tg_name, time). Use the same `load_lastheard()` function with `$limit = 5`. Guard with `user_has_role()` check so regular admins don't trigger a file read on every dashboard load.

**Rationale**: Follows the same pattern as the HBLink status card in F8. Low-cost addition with high visibility.

---

## Decision 7: Migration Strategy

**Decision**: `migrations/007_seed_lastheard_settings.sql` inserts 2 new rows into `system_settings`:
- `lastheard_log_path` → `/opt/HBMonv2/log/lastheard.log`
- `public_lastheard_enabled` → `1`

**Rationale**: Consistent with how F8 seeded HBLink path settings. Configurable without code changes.

---

## Decision 8: Handling Missing Log File

**Decision**: When the log file does not exist (no transmissions have occurred yet), `load_lastheard()` returns `['rows' => [], 'error' => null]` — treat as empty, not as an error. Only return an error string if the file exists but cannot be read (permission denied or I/O error).

**Rationale**: On a freshly started server, the lastheard.log does not exist until the first transmission > 2 seconds occurs. This is normal. The page should show "No activity recorded yet" — not an error.

---

## Constitution Check

All 8 principles pass:

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Simplicity | ✅ | Plain PHP, no libraries, no new abstractions beyond a single reader.php |
| II. Security First | ✅ | Path allowlist via `validate_lastheard_path()`, `htmlspecialchars` on all rendered content, no SQL (file read only), `session_start()` auth check |
| III. Public Dir Isolation | ✅ | `app/lastheard/reader.php` outside `public/`; only `public/last-heard.php` is web-accessible |
| IV. DB Migrations | ✅ | Migration 007 seeds 2 system_settings keys |
| V. Feature Quality | ✅ | 3 user stories with Given/When/Then scenarios |
| VI. Branch Strategy | ✅ | Working on `006-lastheard-log`, branched from `dev` |
| VII. Mobile-First | ✅ | Table gets `overflow-x: auto` wrapper; responsive CSS |
| VIII. Observability | ✅ | Feeds from live DMR activity; lays groundwork for dmr_call_sessions DB ingestion |
