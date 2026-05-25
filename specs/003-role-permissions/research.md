# Research: F2 — Role & Permission System

All technical unknowns resolved from existing project context. No external research required.

---

## Decision 1: Role check on each request — DB query vs session cache

**Decision**: Re-fetch the user's roles from the database on every authenticated page load via a single JOIN query. Do not cache roles in the session.

**Rationale**: Session-cached roles create a window where a demoted user retains access until their session expires. At this platform's scale (tens of admins/moderators, not thousands), one JOIN query per request has no meaningful performance impact. Correctness outweighs the micro-optimisation.

**Alternatives considered**:
- Session cache with a `roles_version` counter: add a counter to the users table, store it in session, re-fetch when they differ. More correct than plain caching, but adds complexity and a write on every role change. Rejected — premature optimisation.
- Hard-code roles in session on login: simplest, but stale until logout. Rejected — breaks FR-004.

---

## Decision 2: Network regen flag structure

**Decision**: A `config_change_queue` table with columns `id`, `triggered_by_user_id`, `change_type`, `details_json`, `created_at`, `applied_at`. A NULL `applied_at` means the change is pending. F13 will SELECT WHERE `applied_at IS NULL` and set `applied_at` after applying.

**Rationale**: A queue table is more useful than a simple boolean flag: it records who triggered what and when, supports multiple pending changes, and gives F13 a complete audit trail of what was applied and when. Fits naturally with the `audit_log` pattern already planned for this feature.

**Alternatives considered**:
- Boolean column on a `network_state` singleton table: simple, but loses history. Rejected — audit trail matters for a live radio network.
- File-based flag: avoids a DB query, but couples the control plane to the filesystem layout. Rejected — Principle VIII requires DB as source of truth.

---

## Decision 3: Timed mute expiry mechanism

**Decision**: A PHP CLI script at `scripts/expire-mutes.php`, scheduled via Linux cron to run every minute. The script: (1) finds all users with `moderation_state = 'muted_on_network'` AND `mute_expires_at <= NOW()`, (2) sets each to `active` and clears `mute_expires_at`, (3) inserts a `config_change_queue` row, (4) inserts a `mod_log` row (actor = system, action = `auto_expired`).

**Rationale**: Linux cron + PHP CLI script is the simplest possible scheduled task mechanism on this Apache/PHP stack — no daemon, no framework, no new dependencies. Runs every minute, satisfying SC-004 (expiry within 5 minutes of scheduled time).

**Alternatives considered**:
- Check on every web request: no background process needed, but expiry only happens when a user visits a page. Non-deterministic timing. Rejected.
- Separate daemon process: more reliable but requires process management. Overkill for this scale. Rejected.

---

## Decision 4: Migration of admin_users → users (email placeholder)

**Decision**: The `admin_users` table has no `email` column. During migration, migrated user records receive a placeholder email of `{username}@migrated.local`. After migration, the system admin should update their email via the profile settings page (F4).

**Rationale**: The `users` table requires a unique email. Using a placeholder keeps the migration fully automated and does not require manual admin input at deploy time. The placeholder domain `migrated.local` is clearly synthetic and will not collide with real email addresses.

**Alternatives considered**:
- Leave email NULL during migration and make email nullable: changes the schema constraint for all future users. Rejected — email is required for the registration flow (F3).
- Prompt for email interactively during migration: not suitable for a SQL migration file. Rejected.

---

## Decision 5: Role hierarchy representation

**Decision**: A PHP constant array maps role names to integer levels:
```
user => 1, moderator => 2, admin => 3, system_admin => 4
```
`user_has_role(user_id, min_role)` returns true if the user holds any role with a level >= the minimum. This supports "at least moderator" semantics cleanly and is readable without a database lookup.

**Rationale**: The hierarchy is fixed and small (4 levels). A constant array in PHP is instantaneous to evaluate. Storing hierarchy in the DB would require an additional query or a self-join.

**Alternatives considered**:
- Bitfield permissions: fine-grained but complex and harder to reason about for this use case. Rejected.
- Database-stored hierarchy order: flexible but adds a query to every permission check. Rejected.

---

## Decision 6: `mod_log` vs `audit_log` — keep separate

**Decision**: Two separate tables:
- `audit_log`: all administrative actions (role changes, settings changes, any admin action). Append-only.
- `mod_log`: moderation state changes specifically (mutes, suspensions, bans). Includes required `reason` field, `duration_hours`, and `expires_at`.

**Rationale**: Moderation log entries have fields (reason, duration, expiry) that are meaningless for general admin actions like role assignments. Keeping them separate avoids a sparse table with many NULLs and makes it easy to display "moderation history" separately from "admin audit trail" in future UI work.

**Alternatives considered**:
- Single `audit_log` with nullable reason/duration: works, but mixes concerns and makes moderation-specific queries noisier. Rejected.
