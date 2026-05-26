# Data Model: Network Status Dashboard (F10)

## No New Tables

F10 is read-only and introduces no schema changes.

## Data Sources (Existing)

### Server State
- Source: `get_hblink_status()` in `app/hblink/process.php`
- Returns: `running`, `pid`, `uptime_seconds`, `config_drifted`

### Recently Active Devices
- Source: lastheard log file (via `load_lastheard()` in `app/lastheard/reader.php`) + `devices` table JOIN
- New helper function: `get_recently_active_dmr_ids(int $minutes = 30): array` added to `app/lastheard/reader.php`
- Returns unique DMR IDs seen in the log within the last N minutes, with callsign from `devices` table

### Last Heard Activity Feed
- Source: `load_lastheard(int $limit)` in `app/lastheard/reader.php`
- 10 most recent entries shown on status page

### Config Drift
- Source: `get_hblink_status()['config_drifted']` (compares cfg file mtime vs HBLink start time)

## New Helper Function (app/lastheard/reader.php)

```
get_recently_active_dmr_ids(int $minutes = 30): array
```

Parses the last N lines of the lastheard log, filters entries with a datetime within the last `$minutes` minutes, extracts unique `src_id` values, and joins against the local `devices` table (status='approved') to resolve callsign. Returns array of `['dmr_id' => string, 'callsign' => string, 'last_seen' => string]`.

Falls back to empty array if lastheard log is unavailable.
