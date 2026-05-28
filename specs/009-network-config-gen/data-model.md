# Data Model: Network Config & Peer Management (F7)

## New Tables

### master_server_settings

Singleton record (always id=1). Stores HBLink `[MASTER]` section parameters.

```sql
CREATE TABLE master_server_settings (
    id                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    bind_address        VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
    port                SMALLINT UNSIGNED NOT NULL DEFAULT 62031,
    passphrase          VARCHAR(15) NOT NULL DEFAULT 'passphrase',
    report_address      VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
    report_port         SMALLINT UNSIGNED NOT NULL DEFAULT 4321,
    ping_time           SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    max_missed          SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    hblink_instance_id  INT UNSIGNED NULL DEFAULT NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by_user_id  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_mss_user FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO master_server_settings (id) VALUES (1);
```

### openbridge_connections

Named OpenBridge links to external DMR networks.

```sql
CREATE TABLE openbridge_connections (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(64) NOT NULL,
    remote_address      VARCHAR(45) NOT NULL,
    port                SMALLINT UNSIGNED NOT NULL DEFAULT 62035,
    passphrase          VARCHAR(15) NOT NULL,
    network_id          INT UNSIGNED NOT NULL,
    enabled             TINYINT(1) NOT NULL DEFAULT 1,
    hblink_instance_id  INT UNSIGNED NULL DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ob_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### config_generation_history

Immutable record of every config generation event.

```sql
CREATE TABLE config_generation_history (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    generated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    generated_by_user_id    INT UNSIGNED NULL,
    config_text             MEDIUMTEXT NOT NULL,
    changed                 TINYINT(1) NOT NULL DEFAULT 1,
    diff_text               MEDIUMTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_generated_at (generated_at),
    CONSTRAINT fk_cgh_user FOREIGN KEY (generated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Existing Tables Used (read-only by F7)

- `devices` — approved devices, DMR IDs (via `get_whitelist_eligible_dmr_ids()`)
- `device_talkgroup_subscriptions` — subscription records (via `get_device_subscriptions_for_config()`)
- `talkgroups` — talkgroup TGID and name
- `config_change_queue` — pending changes signal (written by F5/F6, read/cleared by F7 post-generation)

## Entity Relationships

```
master_server_settings (1) ─────── standalone singleton
openbridge_connections (N) ─────── standalone list
config_generation_history (N) ──── references users (generated_by_user_id)

devices ─────────────────────────── source of REG_ACL entries (read via contract)
device_talkgroup_subscriptions ─── source of PEER stanzas (read via contract)
```

## Notes

- `passphrase` columns are `VARCHAR(15)` — the DB-level constraint enforces the maximum in addition to application validation
- `hblink_instance_id` is NULL in all rows for the current single-node deployment; reserved for multi-node (Principle VIII)
- `config_generation_history.config_text` stores the complete rendered config text, not a hash — enables full diff computation without re-generation
