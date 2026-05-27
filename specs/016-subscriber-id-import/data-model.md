# Data Model: F14 — Subscriber ID Import

## Schema Changes

### Migration 021: subscriber_ids table

```sql
CREATE TABLE IF NOT EXISTS subscriber_ids (
    radio_id        INT UNSIGNED    NOT NULL,
    callsign        VARCHAR(16)     NOT NULL,
    name            VARCHAR(128)    NOT NULL DEFAULT '',
    city            VARCHAR(128)    NOT NULL DEFAULT '',
    state           VARCHAR(64)     NOT NULL DEFAULT '',
    country         VARCHAR(64)     NOT NULL DEFAULT '',
    source          ENUM('radioid','local') NOT NULL DEFAULT 'radioid',
    last_updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (radio_id),
    INDEX idx_callsign (callsign),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Migration 022: system_settings keys for import metadata

```sql
INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('subscriber_last_import_at',           ''),
    ('subscriber_last_modified',            ''),
    ('subscriber_import_count',             '0'),
    ('subscriber_min_import_interval_hours','23')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
```

**subscriber_last_import_at** — ISO datetime of last successful import (used to enforce minimum interval)  
**subscriber_last_modified** — `Last-Modified` header value from the last successful RadioID.net download (e.g., `Wed, 27 May 2026 05:01:00 GMT`) — sent as `If-Modified-Since` on subsequent requests to avoid unnecessary downloads  
**subscriber_import_count** — record count displayed on the admin page  
**subscriber_min_import_interval_hours** — configurable minimum interval; import attempts within this window return early without downloading

## Entities

### SubscriberId
| Field | Type | Notes |
|-------|------|-------|
| radio_id | INT UNSIGNED PK | DMR ID — integer, not string |
| callsign | VARCHAR(16) | Uppercase callsign from RadioID.net |
| name | VARCHAR(128) | `TRIM(CONCAT(first_name, ' ', last_name))` from CSV |
| city | VARCHAR(128) | City from CSV |
| state | VARCHAR(64) | State/province from CSV |
| country | VARCHAR(64) | Country from CSV |
| source | ENUM('radioid','local') | 'local' records never overwritten by import |
| last_updated_at | DATETIME | Auto-updated on each row change |

## CSV Source Format (confirmed)

URL: `https://radioid.net/static/user.csv` (singular "user")  
Columns: `RADIO_ID, CALLSIGN, FIRST_NAME, LAST_NAME, CITY, STATE, COUNTRY`  
Size: ~15.9 MB, ~306,000 records  
Update schedule: daily at ~05:00 UTC  
HTTP headers: `Last-Modified` present (enables conditional GET); no ETag

## PHP Layer

### `app/subscribers/manager.php` (new file)

```php
function import_subscribers_from_radioid(bool $force = false): array
```
- Check minimum interval: if `subscriber_last_import_at` + `subscriber_min_import_interval_hours` > now AND !$force → return ['status' => 'too_soon', 'next_at' => ...]
- Build HTTP context with `If-Modified-Since: [subscriber_last_modified]` and `User-Agent: CFLAG-DMR/1.0 (contact: admin@cflag.net)`
- Open stream with fopen(); if response is 304 → return ['status' => 'not_modified']
- Capture response headers from `$http_response_header` to extract new `Last-Modified` value
- fgetcsv() row by row, skip header row, batch INSERT … ON DUPLICATE KEY UPDATE every 500 rows (skip rows where source='local' via: INSERT IGNORE + UPDATE ... WHERE source != 'local')
- On completion: update system_settings (last_import_at, last_modified, import_count)
- Return `['status' => 'ok', 'imported' => int, 'batches_failed' => int, 'error' => string|null]`

```php
function get_subscriber_import_stats(): array
```
- Returns: `['count' => int, 'last_import_at' => string|null, 'last_modified' => string|null, 'min_interval_hours' => int, 'next_import_at' => string|null]`

```php
function get_subscriber(int $dmr_id): array|null
```
- SELECT * FROM subscriber_ids WHERE radio_id = ?; return array or null

```php
function get_subscribers_for_ids(array $dmr_ids): array
```
- SELECT * FROM subscriber_ids WHERE radio_id IN (?) for batch lookup; returns [radio_id => record] keyed array

```php
function upsert_subscriber_override(int $radio_id, string $callsign, string $name): void
```
- INSERT INTO subscriber_ids ... ON DUPLICATE KEY UPDATE callsign=?, name=?, source='local'

```php
function delete_subscriber_override(int $radio_id): bool
```
- DELETE WHERE radio_id = ? AND source = 'local'; returns true if deleted

```php
function get_subscriber_overrides(int $limit = 50, int $offset = 0): array
```
- SELECT WHERE source = 'local' ORDER BY radio_id LIMIT ? OFFSET ?

## Standalone CLI Script

### `scripts/import_subscribers.php` (new file)
- Bootstrap: require app/config/env.php, app/database/connection.php, app/subscribers/manager.php
- Call `import_subscribers_from_radioid()`; log result to stdout
- Exit code 0 for success/not_modified/too_soon; exit code 1 for error
- Suitable for: `0 6 * * * www-data php /opt/cflag-dmr/scripts/import_subscribers.php >> /var/log/cflag-subscriber-import.log 2>&1`

## Admin UI Pages

### `public/admin/subscribers/index.php` (new)
- system_admin role check
- Displays: record count, last import time, last RadioID.net Last-Modified value, next allowed import time
- "Import Now" button: POST with CSRF → calls import_subscribers_from_radioid(true) (force=true for manual)
- "Check for Updates" button: POST with CSRF → calls import_subscribers_from_radioid(false) (respects interval)
- Shows cron setup instructions: sample crontab line in a code block
- Override management table: paginated, with add form and delete buttons
