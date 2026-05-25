# CFLAG DMR — Database Schema Reference

This document captures the full schema designs for CFLAG DMR's database, drawn from the V2 talkgroup management specification. It is the reference for feature planning in F5–F13.

The database is **MariaDB** (utf8mb4, InnoDB). All schemas here are design references — the authoritative source for what is currently applied is the ordered migrations in `migrations/`.

---

## Architecture Principle

**The database is the source of truth.** HBLink config files (`hblink.cfg`, `rules.py`) are generated artifacts written from database state at apply time. The engine (HBLink) reads the generated files; it does not read the database directly in the current CFLAG setup. This separation means the engine can be swapped without a schema rewrite.

---

## Schema Areas

### Users & Authentication

```sql
-- Applied in migration 001 (admin_users) and migration 003 (users, F2/F3)
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    callsign        VARCHAR(12)  NOT NULL UNIQUE,
    dmr_id          INT UNSIGNED NOT NULL UNIQUE,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    display_name    VARCHAR(100) NOT NULL,
    tier            ENUM('free','gold','premium') NOT NULL DEFAULT 'free',
    moderation_state ENUM('active','suspended','banned','muted_on_network') NOT NULL DEFAULT 'active',
    email_verified  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Roles & Permissions (F2)

```sql
CREATE TABLE roles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)  NOT NULL UNIQUE,
    display_name    VARCHAR(100) NOT NULL,
    description     TEXT,
    is_system_role  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded system roles: user, moderator, admin, system_admin

CREATE TABLE user_roles (
    user_id         INT UNSIGNED NOT NULL,
    role_id         INT UNSIGNED NOT NULL,
    assigned_by     INT UNSIGNED NULL,
    assigned_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id)     REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Subscriber / RadioID Lookup

```sql
CREATE TABLE subscriber_ids (
    radio_id        INT UNSIGNED NOT NULL PRIMARY KEY,
    callsign        VARCHAR(20)  NOT NULL,
    name            VARCHAR(100),
    city            VARCHAR(100),
    state           VARCHAR(100),
    country         VARCHAR(100),
    source          ENUM('radioid_net','local') NOT NULL DEFAULT 'radioid_net',
    local_override  TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Devices (F5)

```sql
CREATE TABLE devices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    callsign        VARCHAR(20)  NOT NULL,
    device_type     ENUM('hotspot','repeater') NOT NULL,
    hardware_desc   VARCHAR(255),
    approved        TINYINT(1)   NOT NULL DEFAULT 0,
    approved_by     INT UNSIGNED NULL,
    approved_at     DATETIME     NULL,
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Talkgroups (F6)

```sql
CREATE TABLE talkgroups (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tgid            INT UNSIGNED NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    tg_type         ENUM('open','private','club') NOT NULL DEFAULT 'open',
    owner_user_id   INT UNSIGNED NULL,
    ownership_tier  ENUM('admin','user_partial','user_full') NOT NULL DEFAULT 'admin',
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Polymorphic permission model (V2): deny wins over allow at same scope level
CREATE TABLE talkgroup_permissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    talkgroup_id    INT UNSIGNED NOT NULL,
    subject_type    ENUM('user','device','role') NOT NULL,
    subject_id      INT UNSIGNED NOT NULL,
    permission_type ENUM('allow','deny') NOT NULL,
    granted_by      INT UNSIGNED NULL,
    granted_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (talkgroup_id) REFERENCES talkgroups(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by)   REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tg_subject (talkgroup_id, subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE talkgroup_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id    INT UNSIGNED NOT NULL,
    requested_name  VARCHAR(100) NOT NULL,
    requested_tgid  INT UNSIGNED NULL,
    description     TEXT,
    status          ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME     NULL,
    assigned_tgid   INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Network Configuration (F7)

```sql
-- HBLink systems (masters, peers, OBP connections)
CREATE TABLE systems (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)  NOT NULL UNIQUE,
    system_type     ENUM('master','peer','obp') NOT NULL,
    address         VARCHAR(255),
    port            SMALLINT UNSIGNED,
    passphrase      VARCHAR(64),
    enabled         TINYINT(1)   NOT NULL DEFAULT 1,
    config_json     JSON         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Named bridge groups linking systems
CREATE TABLE bridges (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)  NOT NULL UNIQUE,
    description     VARCHAR(255),
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Routing rules per bridge — generates rules.py entries
CREATE TABLE bridge_rules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bridge_id       INT UNSIGNED NOT NULL,
    system_name     VARCHAR(50)  NOT NULL,
    timeslot        TINYINT UNSIGNED NOT NULL,
    talkgroup_id    INT UNSIGNED NOT NULL,
    active          TINYINT(1)   NOT NULL DEFAULT 1,
    timeout         SMALLINT UNSIGNED NULL,
    to_type         ENUM('GROUP','UNIT') NOT NULL DEFAULT 'GROUP',
    on_triggers     JSON         NULL,
    off_triggers    JSON         NULL,
    reset_triggers  JSON         NULL,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (bridge_id)    REFERENCES bridges(id)    ON DELETE CASCADE,
    FOREIGN KEY (talkgroup_id) REFERENCES talkgroups(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Config Generation & Audit (F7/F13)

Config generation follows a 7-step workflow: validate → write temp file → syntax check → backup current → atomic swap → restart HBLink → record audit entry.

```sql
CREATE TABLE config_generations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id        INT UNSIGNED NULL,
    generated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hblink_cfg_hash VARCHAR(64),
    rules_py_hash   VARCHAR(64),
    diff_summary    TEXT,
    backup_path     VARCHAR(500),
    outcome         ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
    notes           TEXT,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### DMR Activity (F9)

```sql
-- Session-level records (preferred over simple last_heard)
CREATE TABLE dmr_call_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_key     VARCHAR(64)  NOT NULL UNIQUE,
    started_at      DATETIME(3)  NOT NULL,
    ended_at        DATETIME(3)  NULL,
    duration_sec    DECIMAL(8,2) NULL,
    system_name     VARCHAR(50)  NOT NULL,
    peer_id         INT UNSIGNED,
    radio_id        INT UNSIGNED NOT NULL,
    callsign        VARCHAR(20),
    tg_number       INT UNSIGNED NOT NULL,
    tg_name         VARCHAR(100),
    slot            TINYINT UNSIGNED NOT NULL,
    call_type       ENUM('GROUP','UNIT') NOT NULL DEFAULT 'GROUP',
    INDEX idx_started  (started_at),
    INDEX idx_radio    (radio_id),
    INDEX idx_tg       (tg_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw event capture for debugging (TGRewrite issues, etc.)
CREATE TABLE dmr_events (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_time      DATETIME(3)  NOT NULL,
    event_type      VARCHAR(50)  NOT NULL,
    peer_id         INT UNSIGNED,
    radio_id        INT UNSIGNED,
    tg_number       INT UNSIGNED,
    slot            TINYINT UNSIGNED,
    source_ip       VARCHAR(45),
    raw_payload     TEXT,
    INDEX idx_event_time (event_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Moderation (F11)

```sql
CREATE TABLE mod_actions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id        INT UNSIGNED NOT NULL,
    target_user_id  INT UNSIGNED NOT NULL,
    state_applied   ENUM('muted_on_network','suspended','banned','restored') NOT NULL,
    reason          TEXT,
    evidence_notes  TEXT,
    duration_hours  SMALLINT UNSIGNED NULL,
    auto_reverse_at DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id)       REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ban_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submitter_id    INT UNSIGNED NOT NULL,
    target_user_id  INT UNSIGNED NOT NULL,
    requested_state ENUM('suspended','banned') NOT NULL,
    reason          TEXT         NOT NULL,
    evidence_notes  TEXT,
    status          ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submitter_id)   REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by)    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Audit Log

```sql
CREATE TABLE audit_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id   INT UNSIGNED NULL,
    action          VARCHAR(100) NOT NULL,
    target_type     VARCHAR(50)  NULL,
    target_id       INT UNSIGNED NULL,
    before_json     JSON         NULL,
    after_json      JSON         NULL,
    ip_address      VARCHAR(45),
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor  (actor_user_id),
    INDEX idx_action (action),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### System Settings

```sql
CREATE TABLE system_settings (
    setting_key     VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value   TEXT         NOT NULL,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Known keys** (seeded by migrations):

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `public_lastheard_enabled` | bool | `0` | Show last-heard without login |
| `registration_open` | bool | `1` | Allow new registrations |
| `require_device_approval` | bool | `1` | Admin approves device requests |
| `network_passphrase` | string | — | Short MMDVM passphrase (≤16 chars) |
| `callsign_verification_mode` | enum | `amateur` | `amateur` / `commercial` / `disabled` |
| `require_invite_for_registration` | bool | `0` | Invite required to register |
| `feature_tier_enabled` | bool | `0` | Gates tier-specific features |
| `hblink_cfg_path` | path | `/opt/hblink3/hblink.cfg` | Path to config file (F8) |
| `hblink_rules_path` | path | `/opt/hblink3/rules.py` | Path to rules file (F8) |
| `hblink_pid_path` | path | `/opt/hblink3/hblink.pid` | Path to PID file (F8) |
| `hblink_process_name` | string | `hblink.py` | Process name for pgrep (F8) |

### Clubs & Invites (F19/F20)

```sql
CREATE TABLE clubs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(100) NOT NULL UNIQUE,
    description     TEXT,
    owner_user_id   INT UNSIGNED NOT NULL,
    join_mode       ENUM('open','invite') NOT NULL DEFAULT 'open',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE club_members (
    club_id         INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    club_role       ENUM('owner','officer','member') NOT NULL DEFAULT 'member',
    joined_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (club_id, user_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invites (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(32)  NOT NULL UNIQUE,
    inviter_user_id INT UNSIGNED NOT NULL,
    used_by_user_id INT UNSIGNED NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME     NULL,
    used_at         DATETIME     NULL,
    FOREIGN KEY (inviter_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Migration Numbering Convention

```
migrations/
  001_create_admin_users_table.sql    ← F1
  002_create_users_roles_table.sql    ← F2
  003_create_email_verifications.sql  ← F3
  004_seed_system_settings.sql        ← F3
  005_hblink_settings.sql             ← F8 (hblink path settings)
  ...
```

Each migration is a standalone, ordered SQL file applied via:

```bash
for f in migrations/*.sql; do
    mysql -u cflag_dmr_user -p'<password>' cflag_dmr < "$f"
done
```

---

## Permission Evaluation Logic (V2)

When checking whether a subject (user, device, or role) can access a talkgroup:

1. Collect all matching rows from `talkgroup_permissions` for the subject
2. If any row has `permission_type = 'deny'` → **access denied** (deny wins)
3. If any row has `permission_type = 'allow'` → **access granted**
4. If no rows match → apply the talkgroup's default (open: allow; private/club: deny)

This model allows fine-grained exceptions: a role might be allowed, but a specific user in that role can be individually denied.
