# Research: F5 — Hotspot & Repeater Registration

**Branch**: `007-hotspot-registration` | **Date**: 2026-05-26

---

## Decision 1: User-facing page directory

**Decision**: Create `public/user/` for authenticated user pages (devices, profile, etc.).

**Rationale**: `public/admin/` already establishes the pattern of role-scoped subdirectories. `public/user/` is the natural parallel for registered-user pages. Pages at `public/` root are for unauthenticated access (login, register, last-heard). Keeping user-scoped pages in a subdirectory makes access control obvious and auditable.

**Alternatives considered**: Flat `public/devices.php` — rejected because it pollutes the public root with authenticated pages and has no parallel in existing structure.

---

## Decision 2: Service layer location

**Decision**: `app/devices/manager.php` — dedicated file following the `app/lastheard/reader.php` pattern.

**Rationale**: Database queries and business logic (DMR ID uniqueness check at approval time, moderation state check, whitelist eligibility query) belong outside the page controller. A single-purpose file in `app/devices/` keeps the pattern consistent with `app/lastheard/` and `app/hblink/`.

**Alternatives considered**: Inline in page controller — rejected; would mix presentation and business logic, making the uniqueness enforcement invisible and untestable in isolation.

---

## Decision 3: DMR ID validation range

**Decision**: Valid DMR IDs are 7-digit integers in range 1000000–9999999. Validated with `ctype_digit()` + range check.

**Rationale**: DMR IDs follow the ITU-T E.164 subscriber number scheme. Valid individual radio IDs are 7 digits (country code 1 digit + 6-digit subscriber). IDs outside this range (e.g., 6-digit talkgroup IDs, 8-digit+ special IDs) are not valid individual subscriber IDs. The `ctype_digit()` check ensures pure numeric input; the range check enforces the 7-digit constraint.

**Alternatives considered**: Querying the RadioID.net API for real-time validation — rejected for MVP; adds external HTTP dependency, rate limit risk, and latency. The API can be added in a future enhancement. Local range validation is sufficient to reject obviously invalid submissions.

---

## Decision 4: DMR ID uniqueness enforcement strategy

**Decision**: No DB-level UNIQUE constraint on `devices.dmr_id`. Application-level check at approval time: reject approval if another approved device with the same DMR ID exists for an active (non-suspended, non-banned) user.

**Rationale**: A DB UNIQUE constraint would prevent a user from re-registering a DMR ID after a previous registration was denied, or from submitting a second attempt if the first was rejected. The correct uniqueness invariant is: at most one *approved* device per DMR ID for an *active* user. This is a business rule, not a simple row uniqueness constraint, so it belongs at the application layer.

**Alternatives considered**: Unique index on `(dmr_id, status)` — doesn't enforce the invariant either (could have two 'pending' for same DMR ID). Partial unique index (WHERE status='approved') — MariaDB supports filtered indexes only as of 10.5; not reliable across all target environments. Application-level check is portable and expressive.

---

## Decision 5: Device status state machine

**Decision**: `ENUM('pending', 'approved', 'denied')` stored in `devices.status`. State transitions:
- On creation: `pending`
- Admin approves: `pending` → `approved`
- Admin denies: `pending` → `denied`
- User deletes: hard delete (any status)
- Re-registration after denial: new row (old denied row remains for audit; or user can delete it first)

**Rationale**: Three states cover all business scenarios without over-engineering. Keeping denied rows provides an admin audit trail. A user wishing to re-register simply submits again, creating a new pending entry.

**Alternatives considered**: Soft delete with `deleted_at` — adds complexity without benefit for this feature. A separate `device_approvals` table to track history — out of scope for MVP; the `approved_by` and `denied_reason` columns on `devices` are sufficient.

---

## Decision 6: Migration number

**Decision**: Migration `008_create_devices.sql` — creates `devices` and `device_talkgroup_subscriptions` tables.

**Rationale**: Current highest is `007_seed_lastheard_settings.sql`. Both tables are needed now (the subscription table will be referenced by F6), so they ship in a single migration file.

---

## Decision 7: Talkgroup subscription scope

**Decision**: `device_talkgroup_subscriptions` table is defined in this migration but the subscription management UI (US4) is deferred until F6 delivers a queryable talkgroup catalog. The DB schema is stable now; the page ships as part of F6's cross-feature work.

**Rationale**: Defining the table now avoids a second migration when F6 arrives. The table is a pure join table with no logic to defer; only the UI depends on the talkgroup list existing.

---

## Decision 8: CSRF protection

**Decision**: All POST forms use the existing `csrf_token()` / `verify_csrf()` pattern from `app/auth/session.php`. No new CSRF infrastructure needed.

**Rationale**: The pattern is already established for admin and registration forms. Reuse is consistent with Principle I (simplicity).

---

## Decision 9: Suspended/banned user access

**Decision**: At the top of user-facing device pages, check `current_user()['moderation_state']` — if `suspended` or `banned`, redirect to login with an error flash. Suspended users must not be able to register or manage devices.

**Rationale**: FR-011 requires blocking these users. `current_user()` returns the live moderation_state from the DB on each request. No session-cached state can be stale.

---

## Decision 10: Whitelist eligibility query for F7

**Decision**: F7 config generation can call a `get_whitelist_eligible_dmr_ids(): array` function in `app/devices/manager.php`. Query: `SELECT d.dmr_id FROM devices d JOIN users u ON u.id = d.user_id WHERE d.status = 'approved' AND u.moderation_state = 'active'`.

**Rationale**: Centralising this query in the service layer means F7 has a single, well-defined entry point. The query is simple and doesn't require complex joins.
