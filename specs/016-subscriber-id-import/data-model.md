# Data Model: F14 — Subscriber ID Import

## Schema Changes

### Migration 021: subscriber_ids table

```sql
CREATE TABLE subscriber_ids (
    radio_id        INT UNSIGNED    NOT NULL,
    callsign        VARCHAR(16)     NOT NULL DEFAULT '',
    name            VARCHAR(128)    NOT NULL DEFAULT '',
    city            VARCHAR(64)     NOT NULL DEFAULT '',
    state           VARCHAR(64)     NOT NULL DEFAULT '',
    country         VARCHAR(64)     NOT NULL DEFAULT '',
    source          ENUM('radioid','local') NOT NULL DEFAULT 'radioid',
    last_updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (radio_id),
    KEY idx_callsign (callsign),
    KEY idx_source   (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Migration 022: Import metadata in system_settings

Add two keys to the existing `system_settings` table:

```sql
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('subscriber_last_import_at', NULL),
    ('subscriber_last_import_count', NULL);
```

## Entities

### SubscriberId
Represents one DMR subscriber record.

| Field | Type | Notes |
|-------|------|-------|
| radio_id | INT UNSIGNED PK | The DMR ID |
| callsign | VARCHAR(16) | Amateur callsign |
| name | VARCHAR(128) | Full name (first + last concatenated) |
| city | VARCHAR(64) | City |
| state | VARCHAR(64) | State/province |
| country | VARCHAR(64) | Country |
| source | ENUM('radioid','local') | 'local' = admin override, never overwritten by import |
| last_updated_at | DATETIME | Auto-updated |

## PHP Layer

### `app/subscribers/manager.php` (new file)

```php
function get_subscriber(int $dmr_id): array|null
function get_subscribers_for_ids(array $dmr_ids): array  // keyed by radio_id
function import_subscribers_from_radioid(): array         // returns ['ok', 'count', 'error']
function upsert_subscriber_override(int $radio_id, string $callsign, string $name): void
function delete_subscriber_override(int $radio_id): bool
function get_subscriber_overrides(): array
function get_subscriber_import_stats(): array             // last import time + count
```

## Integration Points

### Last-Heard page (`public/user/lastheard.php` or equivalent)
- Replace current display of raw `radio_id` with: callsign (bold) + name, falling back to radio_id if no match
- Use `get_subscribers_for_ids()` with the full set of radio_ids on the page — one query for all rows

### Admin Subscribers page (new: `public/admin/subscribers/index.php`)
- Show: last import time, record count, import button, local overrides table with add/delete
