# Data Model: F8 — HBLink Config Visibility

**Date**: 2026-05-25 | **Branch**: `005-hblink-config-view`

F8 is read-only. It reads from `system_settings` (already created in migration 005) and from the Linux filesystem. **No new tables are created.** Migration 006 seeds four new keys into the existing `system_settings` table.

---

## Existing Table: system_settings

Already created by migration 005. Schema:

```sql
CREATE TABLE `system_settings` (
    `key`         VARCHAR(64)  NOT NULL,
    `value`       TEXT         NOT NULL,
    `description` TEXT             NULL,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## New Keys — Migration 006

```sql
-- Migration 006: seed HBLink path settings for F8

INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
    ('hblink_cfg_path',
     '/opt/hblink3/hblink.cfg',
     'Absolute path to hblink.cfg. Must be under an allowed base directory.'),

    ('hblink_rules_path',
     '/opt/hblink3/rules.py',
     'Absolute path to rules.py. Must be under an allowed base directory.'),

    ('hblink_pid_path',
     '/opt/hblink3/hblink.pid',
     'Absolute path to hblink PID file. If file does not exist, falls back to pgrep.'),

    ('hblink_process_name',
     'hblink.py',
     'Process name used with pgrep -f for fallback PID lookup.');
```

---

## Runtime Data (not persisted)

These are computed at request time and not stored in the database.

### HblinkFileResult (in-memory)

| Field | Type | Source |
|-------|------|--------|
| `content` | `string\|null` | `file_get_contents($real_path)` |
| `modified_at` | `int\|null` | `filemtime($real_path)` — Unix timestamp |
| `error` | `string\|null` | Set if file cannot be read |

### HblinkProcessStatus (in-memory)

| Field | Type | Source |
|-------|------|--------|
| `running` | `bool` | PID found and `/proc/{pid}` exists |
| `pid` | `int\|null` | From PID file or pgrep |
| `started_at` | `int\|null` | Computed from `/proc/{pid}/stat` + `/proc/stat btime` |
| `uptime_seconds` | `int\|null` | `time() - started_at` |
| `cfg_modified_at` | `int\|null` | `filemtime($cfg_path)` |
| `config_drifted` | `bool` | `cfg_modified_at > started_at` |

---

## Allowed Path Directories (hardcoded constant)

Not stored in the database — hardcoded in `app/hblink/reader.php` to prevent expansion via DB compromise.

```php
const HBLINK_ALLOWED_DIRS = [
    '/opt/hblink3/',
    '/etc/hblink/',
    '/opt/hblink/',
];
```
