# Data Model: F16 — Backup & Rollback

## Schema Changes

### Migration 023: Add rolled_back_from_id to config_generation_history

```sql
ALTER TABLE config_generation_history
    ADD COLUMN rolled_back_from_id INT UNSIGNED NULL DEFAULT NULL AFTER backup_path,
    ADD CONSTRAINT fk_cgh_rollback_from
        FOREIGN KEY (rolled_back_from_id) REFERENCES config_generation_history (id)
        ON DELETE SET NULL;
```

**rolled_back_from_id** — when a rollback creates a new history record, this references the original generation whose config_text was restored. NULL for normal (non-rollback) applies.

**Prerequisite**: Migration 020 (F13) must have run first — this migration depends on the `backup_path` column added in 020.

## Entities

### ConfigGeneration (further extended, on top of F13/migration 020)

| Field | Type | Notes |
|-------|------|-------|
| id | INT UNSIGNED PK | |
| generated_at | DATETIME | When config was generated |
| generated_by_user_id | INT UNSIGNED FK→users | Actor |
| config_text | MEDIUMTEXT | Full config content |
| changed | TINYINT(1) | 1 if diff from previous |
| diff_text | MEDIUMTEXT NULL | Unified diff from previous |
| applied | TINYINT(1) | Whether apply was attempted |
| applied_at | DATETIME NULL | When apply was attempted |
| apply_success | TINYINT(1) NULL | 1=success, 0=failed, NULL=pending |
| apply_error | TEXT NULL | Error detail if failed |
| backup_path | VARCHAR(512) NULL | Backup path before this apply |
| **rolled_back_from_id** | **INT UNSIGNED NULL FK→self** | **Source generation ID if rollback** |

## PHP Layer

### `app/config/generator.php` — additions

```php
function rollback_to_generation(int $generation_id, int $actor_id): array
```
- Fetch `config_text` from `config_generation_history` WHERE id = $generation_id — ERROR if not found
- Call `apply_hblink_config_text(string $config_text, int $actor_id, int $rolled_back_from_id): array` (new internal function)
- Returns `['ok' => bool, 'error' => string|null, 'new_generation_id' => int]`

```php
function apply_hblink_config_text(string $config_text, int $actor_id, ?int $rolled_back_from_id = null): array
```
- Refactored from existing `apply_hblink_config()`: the existing function generates config then calls this
- Steps: validate → backup current file → atomic write → docker restart → poll → write history record with `rolled_back_from_id` → audit_log
- Returns `['ok' => bool, 'error' => string|null, 'generation_id' => int]`

```php
function get_config_generation_history(int $limit = 20, int $offset = 0): array
```
- Updated signature adds `$offset` for pagination
- SELECT metadata columns only — does NOT select `config_text` (performance)
- Includes `rolled_back_from_id` and joined display of rollback source `generated_at`

```php
function get_config_generation_by_id(int $id): array|null
```
- SELECT all columns including `config_text` for a single record
- Used by download and diff-expand actions

```php
function get_running_hblink_config(): string|null
```
- Reads `HBLINK_CONFIG_PATH` with `file_get_contents()`
- Returns file content string, or null if unreadable

## Admin UI Pages

### `public/admin/config/history.php` (new — separate from index.php)
- Paginated table of all `config_generation_history` records, newest-first (20/page)
- Columns: date/time, applied-by user, applied (yes/no badge), apply success/failure badge, changed (yes/no), rollback indicator (↩ from #N if rolled_back_from_id set)
- Per-row actions: Download (.cfg), Roll Back to This (POST form with CSRF), Expand Diff (if changed=1)
- Diff expand: inline `<pre>` block with CSS coloring of + / - lines
- "Show Running Config" button at top → GET `?action=running` → modal or inline `<pre>` display
- Rollback POST → `?action=rollback&id=NNN` → PRG pattern with flash message
- Download GET → `?action=download&id=NNN` → streams config_text as attachment

### `public/admin/config/index.php` (update — add link)
- Add "View History" link pointing to `history.php`

### Access control
- Both download and rollback require `system_admin` role check
- History view (read-only) accessible to `admin` and `system_admin`
- Running config view requires `system_admin`
