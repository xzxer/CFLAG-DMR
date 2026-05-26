# Research: F6 — Talkgroup Management

**Branch**: `008-talkgroup-management` | **Date**: 2026-05-26

---

## Decision 1: Migration number

**Decision**: Migration `009_create_talkgroups.sql` — creates `talkgroups`, `talkgroup_requests`, `talkgroup_access_lists`, and `talkgroup_ownership_upgrade_requests` tables.

**Rationale**: Migration 008 is reserved for F5's `devices` table. F6 uses 009. The `device_talkgroup_subscriptions` table (FK to `talkgroups`) will be created as migration 010, which runs after both 008 (devices) and 009 (talkgroups) are applied.

---

## Decision 2: TGID valid range

**Decision**: TGIDs are unsigned 24-bit integers (1–16,777,215). Validated with `ctype_digit()` + range check at application layer. DB column: `INT UNSIGNED NOT NULL`.

**Rationale**: DMR uses 24-bit talkgroup IDs. The theoretical maximum is 16,777,215. IDs below 1 are reserved. The range is enforced at the PHP level; the DB column type (unsigned INT) provides a second layer. No external registry lookup needed for MVP.

---

## Decision 3: TGID uniqueness enforcement

**Decision**: UNIQUE constraint on `talkgroups.tgid` at DB level.

**Rationale**: Unlike device DMR IDs (where re-registration after denial is a valid flow), TGIDs are a globally-namespaced network resource. There is no legitimate case for two talkgroup rows with the same TGID regardless of status. The DB UNIQUE constraint is correct and appropriate here.

**Alternatives considered**: Application-level uniqueness check only — rejected; a DB constraint is the right mechanism for a globally unique network identifier.

---

## Decision 4: File structure

**Decision**:
- Admin talkgroup management: `public/admin/talkgroups/index.php`
- Public talkgroup browser (no login required): `public/talkgroups.php`
- User talkgroup request form: part of `public/user/talkgroups.php`

**Rationale**: The public talkgroup listing (FR-009) is unauthenticated, so it lives at the `public/` root level like `last-heard.php`. Admin CRUD lives under `public/admin/talkgroups/` following the admin sub-directory pattern. User-specific request and ownership management lives under `public/user/` (introduced by F5).

---

## Decision 5: Ownership tier storage

**Decision**: `ENUM('admin','user_partial','user_full')` stored in `talkgroups.ownership_tier`. Default: `admin`.

**Rationale**: Three well-defined tiers with no current need for extensibility. An ENUM provides DB-level constraint and clarity. No separate `ownership_tiers` table needed — premature abstraction.

---

## Decision 6: Talkgroup type storage

**Decision**: `ENUM('open','private','club')` stored in `talkgroups.tg_type`. Default: `open`.

**Rationale**: Three fixed types per spec. Club is stored but treated as private for MVP (F19 dependency). ENUM enforces valid values at DB level.

---

## Decision 7: Access list (allow/block) enforcement

**Decision**: `talkgroup_access_lists` table with `list_type ENUM('allow','block')`. Deny-wins at application layer: if a DMR ID appears on the block list, access is denied regardless of allow list state.

**Rationale**: Deny-wins is the standard security default and matches the F2 constitution principle applied to access control throughout the project. The implementation is: check block list first; if found, deny. Then check if there's an explicit allow list — if the list is non-empty and the DMR ID is not on it, deny. Otherwise allow.

---

## Decision 8: Service layer location

**Decision**: `app/talkgroups/manager.php` — single-purpose service file.

**Rationale**: Mirrors `app/devices/manager.php` (F5) and `app/lastheard/reader.php` (F9). Keeps DB queries and business logic out of page controllers.

---

## Decision 9: Public talkgroup listing

**Decision**: `public/talkgroups.php` shows active open talkgroups (name, TGID, type, description) without login. No auto-refresh needed (catalog changes infrequently).

**Rationale**: FR-009 specifies a public-facing listing. Following the pattern established by `public/last-heard.php`.

---

## Decision 10: Talkgroup request notifications

**Decision**: In-app status only for MVP. The user sees the updated status on their "My Talkgroup Requests" page. No email notification for request outcomes.

**Rationale**: Email is a future enhancement. The spec's assumption section explicitly states this. The notification channel can be added without changing the data model.

---

## Decision 11: Club talkgroup gating

**Decision**: `tg_type = 'club'` is stored and displayed with a "Club" badge, but access control behaves identically to `private` for MVP. Club membership gate (F19) is not implemented in this feature.

**Rationale**: The spec explicitly defers F19 as a dependency. Storing the type now avoids a migration and UI change later. The join request flow for private talkgroups handles club types correctly already.

---

## Decision 12: Talkgroup deletion rules

**Decision**: Hard delete allowed only when the talkgroup has no active subscriptions (`device_talkgroup_subscriptions` rows with that `talkgroup_id`). If subscriptions exist, admin must disable the talkgroup instead. The application enforces this; the DB FK with `ON DELETE RESTRICT` provides a safety net.

**Rationale**: Deleting a talkgroup with active subscriptions would leave orphaned subscription records and break the config generation data set. Disabling is the safe operation; deletion is a cleanup action for truly unused talkgroups.
