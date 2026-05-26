# Data Model: F9 — Last-Heard & Activity Log

**Date**: 2026-05-25
**Branch**: `006-lastheard-log`

---

## Overview

This feature introduces **no new database tables**. All transmission data is read directly from the HBMonv2 `lastheard.log` CSV file at request time. Two new rows are added to the existing `system_settings` table via a migration.

---

## Runtime Data Structure: `LhEntry` (in-memory only)

Represents one parsed transmission record from lastheard.log. No persistent storage for MVP.

```
LhEntry (PHP associative array)
  datetime    string    "2026-05-25 14:32:11"   Raw datetime string from column [0]
  duration    float     4.2                      Seconds, from column [1]
  call_type   string    "GROUP VOICE"            From column [2]; always "GROUP VOICE" for logged entries
  action      string    "END"                    From column [3]; always "END" for logged entries
  system_name string    "CFLAG-MASTER"           From column [4]
  src_id      string    "3171234"                Source peer/subscriber ID, from column [5]
  callsign    string    "W7ABC"                  Resolved callsign, from column [6]
  timeslot    string    "1"                      Slot number, "TS" prefix stripped from column [7]
  tgid        string    "91"                     Talkgroup ID, "TG" prefix stripped from column [8]
  tg_name     string    "Worldwide"              Talkgroup name, from column [9]; may be empty
  sub_id      string    "3171234"                Subscriber DMR ID, from column [10]
  short_name  string    "W7ABC"                  Short callsign/name, from column [11]
```

**Validation rules**:
- A line is silently skipped if it does not parse to exactly 12 fields via `str_getcsv()`
- A line is silently skipped if `$row[0]` does not parse to a valid timestamp via `strtotime()`
- Duration is cast with `(float)` — invalid values produce `0.0` (not an error)

---

## Runtime Data Structure: `LhResult` (return value of `load_lastheard()`)

```
LhResult (PHP associative array)
  rows         LhEntry[]   Filtered, ordered, paginated entries (newest first)
  total        int         Total matching rows before pagination (for page count)
  error        string|null Non-null only if file exists but is unreadable
```

**Empty file / file not found**: Returns `['rows' => [], 'total' => 0, 'error' => null]` — treated as "no activity yet", not as an error.

---

## Migration 007: New `system_settings` Rows

**File**: `migrations/007_seed_lastheard_settings.sql`

```sql
INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
  ('lastheard_log_path',
   '/opt/HBMonv2/log/lastheard.log',
   'Absolute path to HBMonv2 lastheard.log. Must be under an allowed base directory.'),

  ('public_lastheard_enabled',
   '1',
   'When 1, the last-heard page is accessible without login. When 0, login required.');
```

**No schema changes** — `system_settings` table already exists with `key`, `value`, `description` columns (seeded in prior migrations).

---

## Constant: `LASTHEARD_ALLOWED_DIRS`

Hardcoded in `app/lastheard/reader.php`. Never stored in the database.

```php
const LASTHEARD_ALLOWED_DIRS = ['/opt/HBMonv2/', '/var/log/hbmon/'];
```

Used by `validate_lastheard_path()` to verify the DB-configured path before any file read. Prevents path traversal if `system_settings` is tampered with.

---

## Source File Structure

```text
app/lastheard/
└── reader.php              # get_lastheard_setting(), validate_lastheard_path(),
                            # parse_lh_line(), load_lastheard()

migrations/
└── 007_seed_lastheard_settings.sql

public/
└── last-heard.php          # US1 (public) + US2 (authenticated) combined

public/admin/
└── index.php               # US3: add last-heard card (existing file, modified)
```

---

## Key Function Signatures

**`app/lastheard/reader.php`**:

```php
const LASTHEARD_ALLOWED_DIRS: array   // ['/opt/HBMonv2/', '/var/log/hbmon/']

get_lastheard_setting(string $key): string
// Reads a key from system_settings via PDO. Throws RuntimeException if key not found.

validate_lastheard_path(string $path): string|false
// realpath() then prefix-checks against LASTHEARD_ALLOWED_DIRS. Returns canonical path or false.

parse_lh_line(string $line): array|null
// str_getcsv() → 12-field validation → assoc array with stripped TS/TG prefixes. Returns null on bad line.

load_lastheard(int $limit = 0, int $offset = 0, array $filters = []): array
// Returns LhResult. $limit=0 means no limit (all rows). Filters: ['callsign','tgid','date_from','date_to'].
// Public view: load_lastheard(20)
// Authenticated view: load_lastheard(50, $offset, $filters)
// Dashboard card: load_lastheard(5)
```

---

## CSV Format Reference

Actual 12-column format written by `/opt/HBMonv2/monitor.py`:

```
datetime, duration, call_type, action, system_name, src_id, callsign, timeslot, tgid, tg_name, sub_id, short_name
```

| Col | Field | Example | Notes |
|-----|-------|---------|-------|
| [0] | datetime | `2026-05-25 14:32:11` | |
| [1] | duration | `4.2` | Float seconds; only entries >2s are logged |
| [2] | call_type | `GROUP VOICE` | Always this value for logged entries |
| [3] | action | `END` | Always this value for logged entries |
| [4] | system_name | `CFLAG-MASTER` | |
| [5] | src_id | `3171234` | Source peer/subscriber ID |
| [6] | callsign | `W7ABC` | Resolved by HBMonv2 alias lookup |
| [7] | timeslot | `TS1` | Strip "TS" prefix → `"1"` |
| [8] | tgid | `TG91` | Strip "TG" prefix → `"91"` |
| [9] | tg_name | `Worldwide` | May be empty if TGID unknown to HBMonv2 |
| [10] | sub_id | `3171234` | Subscriber DMR ID |
| [11] | short_name | `W7ABC` | Short name from alias lookup |
