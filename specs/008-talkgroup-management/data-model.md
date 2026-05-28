# Data Model: F6 — Talkgroup Management

**Branch**: `008-talkgroup-management` | **Date**: 2026-05-26

---

## New Tables

### `talkgroups`

The canonical talkgroup catalog. One row per network talkgroup.

```sql
-- Migration 009_create_talkgroups.sql
CREATE TABLE talkgroups (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tgid           INT UNSIGNED  NOT NULL,
    name           VARCHAR(128)  NOT NULL,
    description    TEXT          NULL,
    tg_type        ENUM('open','private','club') NOT NULL DEFAULT 'open',
    ownership_tier ENUM('admin','user_partial','user_full') NOT NULL DEFAULT 'admin',
    owner_user_id  INT UNSIGNED  NULL,
    active         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tgid        (tgid),
    KEY        idx_active     (active),
    KEY        idx_owner      (owner_user_id),
    CONSTRAINT fk_tg_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- `UNIQUE KEY uq_tgid` — TGIDs are globally unique network identifiers; duplicate TGIDs are never permitted.
- `ON DELETE SET NULL` for `owner_user_id` — if the owner's account is deleted, the talkgroup reverts to admin-owned.
- `active = 0` disables the talkgroup (hidden from subscription lists, excluded from config generation). Hard delete is only permitted when no active subscriptions exist.

---

### `talkgroup_requests`

User-submitted proposals for new talkgroups, pending admin review.

```sql
CREATE TABLE talkgroup_requests (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    requester_id      INT UNSIGNED  NOT NULL,
    proposed_tgid     INT UNSIGNED  NOT NULL,
    proposed_name     VARCHAR(128)  NOT NULL,
    proposed_type     ENUM('open','private','club') NOT NULL DEFAULT 'open',
    description       TEXT          NULL,
    status            ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by       INT UNSIGNED  NULL,
    denial_reason     TEXT          NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at       DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_status        (status),
    KEY idx_requester     (requester_id),
    CONSTRAINT fk_tgr_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tgr_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `talkgroup_access_lists`

Explicit allow/block entries for user_full-owned talkgroups.

```sql
CREATE TABLE talkgroup_access_lists (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    talkgroup_id INT UNSIGNED NOT NULL,
    dmr_id       INT UNSIGNED NOT NULL,
    list_type    ENUM('allow','block') NOT NULL,
    added_by     INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tg_dmr_type (talkgroup_id, dmr_id, list_type),
    KEY idx_talkgroup (talkgroup_id),
    CONSTRAINT fk_tal_talkgroup FOREIGN KEY (talkgroup_id) REFERENCES talkgroups (id) ON DELETE CASCADE,
    CONSTRAINT fk_tal_adder    FOREIGN KEY (added_by)     REFERENCES users      (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- A DMR ID can appear once per talkgroup per list_type (can be on both allow AND block — deny-wins at application layer).
- `ON DELETE CASCADE` on talkgroup — access list entries are meaningless without the talkgroup.

---

### `talkgroup_ownership_upgrade_requests`

Requests from user_partial owners to escalate to user_full ownership.

```sql
CREATE TABLE talkgroup_ownership_upgrade_requests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    talkgroup_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    reason       TEXT         NOT NULL,
    status       ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by  INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at  DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_status      (status),
    KEY idx_talkgroup   (talkgroup_id),
    CONSTRAINT fk_tour_tg        FOREIGN KEY (talkgroup_id) REFERENCES talkgroups (id) ON DELETE CASCADE,
    CONSTRAINT fk_tour_requester FOREIGN KEY (requester_id) REFERENCES users      (id) ON DELETE CASCADE,
    CONSTRAINT fk_tour_reviewer  FOREIGN KEY (reviewed_by)  REFERENCES users      (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `device_talkgroup_subscriptions` (Migration 010)

This table references both `devices` (F5, migration 008) and `talkgroups` (F6, migration 009). It ships as **migration 010**, which must be applied after both 008 and 009.

```sql
-- Migration 010_create_device_talkgroup_subscriptions.sql
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

---

## Migration Sequence

| Migration | Created by | Depends on | Content |
|-----------|-----------|-----------|---------|
| 008 | F5 | — | `devices` |
| 009 | F6 | — | `talkgroups`, `talkgroup_requests`, `talkgroup_access_lists`, `talkgroup_ownership_upgrade_requests` |
| 010 | F6 (or F5/F6 joint) | 008 + 009 | `device_talkgroup_subscriptions` |

---

## Entity Relationships

```
users (F3)
  ├── talkgroups (owner_user_id FK)     1 user → many owned talkgroups
  ├── talkgroup_requests                1 user → many requests
  ├── talkgroup_access_lists (added_by) admin adding entries
  └── devices (F5)
        └── device_talkgroup_subscriptions (migration 010)
                                           1 device → many talkgroup+slot rows
                                           1 talkgroup → many device subscriptions
```

---

## State Transitions

### Talkgroup

```
[Admin creates] → active=1
[Admin disables] → active=0   (subscriptions retained, excluded from config)
[Admin enables] → active=1
[Admin deletes] → hard delete (blocked if active subscriptions exist)
```

### Talkgroup Request

```
[User submits] → pending
[Admin approves + assigns TGID + tier] → approved (talkgroup row created)
[Admin denies + reason] → denied
```

### Ownership Upgrade Request

```
[user_partial owner submits] → pending
[Admin approves] → approved (talkgroup.ownership_tier updated to user_full)
[Admin denies] → denied
```

---

## Validation Rules

| Field | Rule |
|-------|------|
| `tgid` | 1–16,777,215; `ctype_digit()` + range check |
| `name` | 1–128 chars, trimmed, non-empty |
| `description` | 0–2000 chars, trimmed, optional |
| `tg_type` | Must be `open`, `private`, or `club` |
| `ownership_tier` | Must be `admin`, `user_partial`, or `user_full` |
| `dmr_id` (access list) | 1000000–9999999; same as device DMR ID validation |
| `timeslot` | Must be `1` or `2` |

---

## Access Control Summary

| Action | Who |
|--------|-----|
| Create/edit/disable/delete talkgroup | system_admin |
| View active open talkgroups | Anyone (public) |
| Request new talkgroup | Logged-in user |
| Approve/deny talkgroup request | system_admin |
| Edit talkgroup name/description | Owner (any tier) or system_admin |
| Manage access lists | Owner (user_full tier) or system_admin |
| Request ownership upgrade | Owner (user_partial tier) |
| Approve ownership upgrade | system_admin |
| Subscribe device to open talkgroup | Device owner (approved device) |
| Request join to private/club talkgroup | Device owner (approved device) |
| Approve join request | Talkgroup owner (any ownership tier) or system_admin |
