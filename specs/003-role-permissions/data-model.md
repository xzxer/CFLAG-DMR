# Data Model: F2 — Role & Permission System

---

## New Tables

### `users`

Replaces `admin_users` as the canonical identity store. All existing `admin_users` records are migrated here.

```sql
CREATE TABLE users (
    id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username          VARCHAR(64)     NOT NULL,
    email             VARCHAR(255)    NOT NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    display_name      VARCHAR(128)    NOT NULL,
    dmr_id            INT UNSIGNED    NULL,
    moderation_state  ENUM('active','suspended','banned','muted_on_network')
                                      NOT NULL DEFAULT 'active',
    mute_expires_at   DATETIME        NULL,
    tier              ENUM('free','gold','premium')
                                      NOT NULL DEFAULT 'free',
    invite_count      INT UNSIGNED    NOT NULL DEFAULT 0,
    last_login_at     DATETIME        NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email    (email),
    UNIQUE KEY uq_dmr_id   (dmr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Column notes**:
- `dmr_id`: NULL for migrated admin accounts that have no DMR ID; required for radio-capable users (enforced at F3).
- `mute_expires_at`: only populated when `moderation_state = 'muted_on_network'` with a timed duration. NULL means indefinite mute (or no mute).
- `tier`: foundation for future paid tiers (F3+). No tier-based access control is enforced in F2.
- `invite_count`: incremented by F20 when an invite code issued by this user is consumed.
- Migrated admin records: `email` set to `{username}@migrated.local` as placeholder (no email in `admin_users`).

---

### `roles`

Stores role definitions. The four system roles are seeded on migration and cannot be deleted.

```sql
CREATE TABLE roles (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    name           VARCHAR(64)    NOT NULL,
    display_name   VARCHAR(128)   NOT NULL,
    description    TEXT           NULL,
    is_system_role TINYINT(1)     NOT NULL DEFAULT 0,
    sort_order     INT UNSIGNED   NOT NULL DEFAULT 0,
    created_at     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Seed data** (applied in migration 003):

| name         | display_name     | sort_order | is_system_role |
|--------------|------------------|-----------|----------------|
| user         | User             | 1         | 1              |
| moderator    | Moderator        | 2         | 1              |
| admin        | Administrator    | 3         | 1              |
| system_admin | System Admin     | 4         | 1              |

**Role hierarchy** (enforced in PHP, not DB):
```
user(1) < moderator(2) < admin(3) < system_admin(4)
```

---

### `user_roles`

Join table assigning roles to users. A user may have multiple rows (multiple roles).

```sql
CREATE TABLE user_roles (
    user_id              INT UNSIGNED NOT NULL,
    role_id              INT UNSIGNED NOT NULL,
    assigned_by_user_id  INT UNSIGNED NOT NULL,
    assigned_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user     FOREIGN KEY (user_id)             REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role     FOREIGN KEY (role_id)             REFERENCES roles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ur_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes**:
- `ON DELETE CASCADE` on `user_id`: if a user is deleted, their role assignments are removed automatically.
- `ON DELETE RESTRICT` on `role_id`: a role cannot be deleted while any user holds it (enforces system role immutability in concert with the `is_system_role` flag).
- Migrated admin users are assigned `system_admin` role with `assigned_by_user_id = user_id` (self-assignment during migration).

---

### `mod_log`

Records every moderation state change — mutes, suspensions, bans, lifts, and automatic expiries.

```sql
CREATE TABLE mod_log (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id    INT UNSIGNED NOT NULL,
    target_user_id   INT UNSIGNED NOT NULL,
    action           VARCHAR(64)  NOT NULL,
    reason           TEXT         NOT NULL,
    duration_hours   SMALLINT UNSIGNED NULL,
    expires_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ml_actor  FOREIGN KEY (actor_user_id)  REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ml_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`action` values**:
- `muted_on_network` — timed or indefinite network mute applied
- `suspended` — account suspended
- `banned` — account permanently banned
- `activated` — suspension or indefinite mute lifted by admin
- `auto_expired` — timed mute expired automatically by cron script

**Notes**:
- `reason` is NOT NULL — all moderation actions require a written reason.
- `duration_hours` and `expires_at` are NULL for indefinite actions and for `activated`/`auto_expired` entries.
- For `auto_expired` entries, `actor_user_id` references the system admin account (id=1) by convention.

---

### `audit_log`

Append-only log of all administrative actions across the platform. Role changes go here.

```sql
CREATE TABLE audit_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id   INT UNSIGNED NOT NULL,
    action_type     VARCHAR(64)  NOT NULL,
    target_type     VARCHAR(64)  NOT NULL,
    target_id       INT UNSIGNED NULL,
    detail_json     JSON         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_al_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`action_type` values (F2 scope)**:
- `role_assigned` — a role was added to a user; `detail_json` includes `{role_name, target_username}`
- `role_revoked` — a role was removed; `detail_json` includes `{role_name, target_username}`

**Notes**:
- `target_type` is a string like `'user'`, `'role'`, `'talkgroup'` — future features will add their own action types.
- `target_id` may be NULL for actions with no single target record.

---

### `config_change_queue`

Records pending network configuration regeneration requests. A NULL `applied_at` means the change has not yet been applied. F13 reads and processes this table.

```sql
CREATE TABLE config_change_queue (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    triggered_by_user_id  INT UNSIGNED NOT NULL,
    change_type           VARCHAR(64)  NOT NULL,
    details_json          JSON         NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at            DATETIME     NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_ccq_user FOREIGN KEY (triggered_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**`change_type` values (F2 scope)**:
- `moderation_state_change` — a user's `moderation_state` changed in a way that affects the DMR ID whitelist; `details_json` includes `{target_user_id, new_state, dmr_id}`

**Notes**:
- F13 will add additional `change_type` values (e.g., `peer_config_change`, `talkgroup_config_change`).
- Multiple pending rows may exist simultaneously. F13 processes all NULL `applied_at` rows in one apply cycle and sets them all in a single transaction.

---

## Modified Tables

### `admin_users` (no schema change)

Retained as-is for historical reference. No new writes after migration. The `app/auth/login.php` update will stop querying this table; all auth checks use `users` going forward.

---

## Entity Relationships

```
users ──< user_roles >── roles
users ──< mod_log (as actor)
users ──< mod_log (as target)
users ──< audit_log (as actor)
users ──< config_change_queue (as triggered_by)
```

---

## Migration Files

| File | Contents |
|------|----------|
| `migrations/002_create_users_roles_tables.sql` | CREATE: users, roles, user_roles, mod_log, audit_log, config_change_queue |
| `migrations/003_seed_system_roles.sql` | INSERT four system roles into roles table |
| `migrations/004_migrate_admin_users.sql` | INSERT admin_users → users; INSERT user_roles with system_admin |
