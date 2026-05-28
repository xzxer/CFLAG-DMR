# Data Model: F5 — Hotspot & Repeater Registration

**Branch**: `007-hotspot-registration` | **Date**: 2026-05-26

---

## New Tables

### `devices`

Represents a hotspot or repeater registered by a user and submitted for admin approval.

```sql
-- Migration 008_create_devices.sql
CREATE TABLE devices (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED  NOT NULL,
    callsign           VARCHAR(16)   NOT NULL,
    dmr_id             INT UNSIGNED  NOT NULL,
    device_type        ENUM('hotspot','repeater') NOT NULL,
    hardware_desc      VARCHAR(255)  NOT NULL DEFAULT '',
    status             ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    approved_by        INT UNSIGNED  NULL,
    denied_reason      TEXT          NULL,
    reviewed_at        DATETIME      NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id   (user_id),
    KEY idx_status    (status),
    KEY idx_dmr_id    (dmr_id),
    CONSTRAINT fk_devices_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_devices_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- No UNIQUE constraint on `dmr_id` — allows re-registration after denial. Uniqueness enforced at application level during approval: at most one approved device per `dmr_id` for an active user.
- `approved_by` is set for both approvals and denials (records who acted). `denied_reason` is set on denial.
- `reviewed_at` is set when status changes from `pending`.
- `ON DELETE CASCADE` on `user_id` — if a user account is deleted, their devices are deleted too.

---

### `device_talkgroup_subscriptions`

Static talkgroup assignments for an approved device. Each row maps a device to a talkgroup on a specific timeslot.

```sql
CREATE TABLE device_talkgroup_subscriptions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id    INT UNSIGNED NOT NULL,
    talkgroup_id INT UNSIGNED NOT NULL,
    timeslot     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_tg_slot (device_id, talkgroup_id, timeslot),
    CONSTRAINT fk_dts_device    FOREIGN KEY (device_id)    REFERENCES devices    (id) ON DELETE CASCADE,
    CONSTRAINT fk_dts_talkgroup FOREIGN KEY (talkgroup_id) REFERENCES talkgroups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- The FK to `talkgroups` references the table F6 will create. **This table must be created after `talkgroups` exists.** Migration 008 must run after F6's talkgroup migration, or `device_talkgroup_subscriptions` is split into a separate migration (009) that runs after F6.
- `UNIQUE KEY` prevents duplicate device+talkgroup+slot combinations.
- UI for managing subscriptions is deferred until F6 delivers the talkgroup catalog.

---

## Migration Strategy

Because `device_talkgroup_subscriptions` references `talkgroups` (F6), two options:

**Option A (recommended)**: Split into two migrations:
- `008_create_devices.sql` — creates `devices` only (no FK dependency)
- `009_create_device_talkgroup_subscriptions.sql` — creates `device_talkgroup_subscriptions` after `talkgroups` exists (runs when F6's migration has been applied)

**Option B**: Single `008_create_devices.sql` creates both tables but includes a note that it must run after F6's talkgroup migration. This creates an ordering dependency risk.

**Chosen**: Option A. F5 ships migration 008 for `devices`. The subscription join table ships as migration 009 alongside or after F6's talkgroup migration.

---

## Entity Relationships

```
users (F3)
  └── devices (F5)                  1 user → many devices
        └── device_talkgroup_subscriptions (F5/F6)
                                     1 device → many talkgroup+slot assignments
                                     1 talkgroup (F6) → many device subscriptions
```

---

## State Transitions: Device Status

```
                  [User submits]
                       │
                   (pending)
                  /         \
         [Admin approves]  [Admin denies]
               │                 │
           (approved)         (denied)
                                 │
                     [User may delete + resubmit]
```

Whitelist eligibility: `status = 'approved'` AND `users.moderation_state = 'active'`

---

## Validation Rules

| Field | Rule |
|-------|------|
| `callsign` | 3–16 chars, alphanumeric + `/` only, trimmed |
| `dmr_id` | 7-digit integer, range 1000000–9999999, `ctype_digit()` |
| `device_type` | Must be `hotspot` or `repeater` |
| `hardware_desc` | 0–255 chars, trimmed, optional |
| `denied_reason` | Required when status changes to `denied` |
| `timeslot` | Must be `1` or `2` |

---

## Existing Tables Used (read-only)

| Table | Usage |
|-------|-------|
| `users` | FK for `user_id`; `moderation_state` checked before registration and at approval |
| `user_roles` + `roles` | `user_has_role()` for admin-only pages |
| `talkgroups` (F6) | FK for subscription join table (deferred) |
