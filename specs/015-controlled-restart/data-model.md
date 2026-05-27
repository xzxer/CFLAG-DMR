# Data Model: F13 — Controlled Restart / Reload

## Schema Changes

### Migration 020: Extend config_generation_history for apply tracking

```sql
ALTER TABLE config_generation_history
    ADD COLUMN applied          TINYINT(1)   NOT NULL DEFAULT 0  AFTER diff_text,
    ADD COLUMN applied_at       DATETIME     NULL                 AFTER applied,
    ADD COLUMN apply_success    TINYINT(1)   NULL                 AFTER applied_at,
    ADD COLUMN apply_error      TEXT         NULL                 AFTER apply_success,
    ADD COLUMN backup_path      VARCHAR(512) NULL                 AFTER apply_error;
```

**applied** — whether an apply was attempted for this generation  
**applied_at** — timestamp of the apply attempt  
**apply_success** — 1=success, 0=failure, NULL=not yet applied  
**apply_error** — error message if apply failed  
**backup_path** — filesystem path of the config backup made before this apply

No new tables required. `audit_log` (existing) captures the actor + action.

## Entities

### ConfigGeneration (extended)
Represents one generation of the HBLink config from the DB. Extended to also track apply status.

| Field | Type | Notes |
|-------|------|-------|
| id | INT UNSIGNED PK | |
| generated_at | DATETIME | When config was generated |
| generated_by_user_id | INT UNSIGNED FK→users | Actor |
| config_text | MEDIUMTEXT | Full generated config content |
| changed | TINYINT(1) | 1 if diff from previous generation |
| diff_text | MEDIUMTEXT NULL | Unified diff from previous |
| applied | TINYINT(1) | Whether apply was attempted |
| applied_at | DATETIME NULL | When apply was attempted |
| apply_success | TINYINT(1) NULL | 1=success, 0=failed, NULL=pending |
| apply_error | TEXT NULL | Error detail if failed |
| backup_path | VARCHAR(512) NULL | Path of backup file before apply |

## PHP Layer

### `app/config/generator.php` — additions
- `apply_hblink_config(int $actor_id): array` — orchestrates the full apply workflow:
  1. Call `generate_hblink_config($actor_id)` → get generation ID
  2. Validate the generated config text
  3. Backup the current live config file
  4. Write new config atomically
  5. Run docker restart command
  6. Poll container status for up to 10s
  7. Update `config_generation_history` with apply result
  8. Log to `audit_log`
  9. Return `['ok' => bool, 'error' => string|null, 'generation_id' => int]`

- `get_hblink_status(): string` — returns 'running', 'stopped', 'unknown' from docker inspect

- `get_config_generation_history(int $limit = 20): array` — returns recent generations with apply status

## File System Paths (hardcoded constants)

```php
const HBLINK_CONFIG_PATH   = '/etc/hblink3/hblink.cfg';
const HBLINK_COMPOSE_FILE  = '/etc/hblink3/docker-compose.yml';
const HBLINK_CONTAINER     = 'hblink';
const HBLINK_BACKUP_DIR    = '/etc/hblink3/backups/';
const HBLINK_BACKUP_KEEP   = 10;
```

These are deployment constants, not DB-configurable, per the constitution's engine-agnostic principle.
