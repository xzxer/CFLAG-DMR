# Tasks: F2 — Role & Permission System

**Branch**: `003-role-permissions`
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Data model**: [data-model.md](data-model.md)

**Format**: `- [ ] [TaskID] [P?] [Story?] Description with file path`
- **[P]**: Parallelizable — different files, no dependency on an incomplete sibling task
- **[USn]**: User story this task belongs to

---

## Phase 1: Setup

**Purpose**: Create directory structure needed by new files.

- [x] T001 Create `public/admin/users/` directory (will hold index.php and view.php)

**Checkpoint**: Directory exists. Proceed to foundational work.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migrations, permission layer, and auth layer updates. ALL must complete before any user story work begins.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T002 Write `migrations/002_create_users_roles_tables.sql` — CREATE TABLE for: `users` (id, username, email, password_hash, display_name, dmr_id, moderation_state ENUM, mute_expires_at, tier, invite_count, last_login_at, created_at, updated_at; UNIQUE on username, email, dmr_id), `roles` (id, name, display_name, description, is_system_role, sort_order, created_at; UNIQUE on name), `user_roles` (user_id, role_id, assigned_by_user_id, assigned_at; PK composite; FK cascade on user delete, restrict on role delete), `mod_log` (id, actor_user_id, target_user_id, action, reason NOT NULL, duration_hours, expires_at, created_at), `audit_log` (id, actor_user_id, action_type, target_type, target_id, detail_json JSON, created_at), `config_change_queue` (id, triggered_by_user_id, change_type, details_json JSON, created_at, applied_at); all ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci — see data-model.md for full DDL

- [x] T003 [P] Write `migrations/003_seed_system_roles.sql` — INSERT four system roles into `roles`: (name=user, display_name='User', sort_order=1, is_system_role=1), (name=moderator, display_name='Moderator', sort_order=2, is_system_role=1), (name=admin, display_name='Administrator', sort_order=3, is_system_role=1), (name=system_admin, display_name='System Admin', sort_order=4, is_system_role=1)

- [x] T004 [P] Write `migrations/004_migrate_admin_users.sql` — INSERT each row from `admin_users` into `users` (username, email as `CONCAT(username, '@migrated.local')`, password_hash, display_name, moderation_state='active', created_at, updated_at); then INSERT into `user_roles` (user_id=newly inserted id, role_id=id of system_admin role, assigned_by_user_id=user_id) using LAST_INSERT_ID() or a subquery per migrated user; include comment noting placeholder email must be updated after migration

- [x] T005 Apply migrations in order to `cflag_dmr_dev` database: `002_create_users_roles_tables.sql`, then `003_seed_system_roles.sql`, then `004_migrate_admin_users.sql` — run via `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/00N_*.sql` for each; verify with `SHOW TABLES` and `SELECT * FROM users; SELECT * FROM roles; SELECT * FROM user_roles;`

- [x] T006 Create `app/auth/roles.php` — `declare(strict_types=1)`; define `ROLE_HIERARCHY` constant array `['user'=>1,'moderator'=>2,'admin'=>3,'system_admin'=>4]`; implement `current_user(): ?array` (reads `$_SESSION['user_id']`, queries `users` table for id/username/display_name/moderation_state, returns null if not found); implement `get_user_roles(int $user_id): array` (SELECT role names via JOIN on `user_roles`+`roles`, return array of name strings); implement `user_has_role(int $user_id, string $min_role): bool` (calls `get_user_roles`, checks if any returned role has ROLE_HIERARCHY level >= ROLE_HIERARCHY[$min_role]); implement `require_role(string $min_role): void` (calls `require_login()` from session.php, then `user_has_role`; on fail: `header('HTTP/1.1 403 Forbidden')` + `redirect('/admin/')`)

- [x] T007 Update `app/auth/session.php` — add `is_logged_in(): bool` checking `isset($_SESSION['user_id']) && is_int($_SESSION['user_id'])`; add `require_login(): void` that redirects to `/login.php` if not logged in; remove `is_admin()` and `require_admin()` (replaced by `require_role()` in roles.php); all other functions (`start_session`, `csrf_token`, `verify_csrf`, `redirect`) unchanged

- [x] T008 Update `app/auth/login.php` — change `attempt_login()` to query `users` table (not `admin_users`) with `SELECT id, username, display_name, password_hash, moderation_state FROM users WHERE username = ?`; after `password_verify` passes, check moderation_state: if `suspended` or `banned` return false (same generic failure path, no state revealed); on success: call `session_regenerate_id(true)`, set `$_SESSION['user_id']` (int), `$_SESSION['username']`, `$_SESSION['display_name']`; UPDATE `users SET last_login_at = NOW() WHERE id = ?`; remove all `admin_id` session references

- [x] T009 Update `public/logout.php` — in the session clear block, reference `user_id` instead of `admin_id` (or clear `$_SESSION = []` which already handles it); update the session cookie expiry `setcookie` call if it references the old key; verify no other `admin_id` references remain in this file

**Checkpoint**: Migrations applied, roles.php created, session.php + login.php + logout.php updated. All existing admin accounts can log in using the new `users` table. Run Quickstart Scenarios 1–2 before proceeding.

---

## Phase 3: User Story 1 — Protected Pages Enforce Role Requirements (Priority: P1) 🎯 MVP

**Goal**: All protected pages use `require_role()`. Login checks the new `users` table with moderation state enforcement. Access control is fully operational.

**Independent Test**: Log in as the migrated admin. Log in as a `user`-role test account and confirm they cannot reach `/admin/`. Confirm a `suspended` test account cannot log in. See Quickstart Scenarios 1–3.

- [x] T010 [US1] Update `public/admin/index.php` — replace `require_admin()` call with `require_role('admin')`; ensure `app/auth/roles.php` is included (add `require_once` at top alongside existing includes); verify page still renders correctly for the migrated system_admin account

**Checkpoint**: US1 complete. The full auth + role enforcement layer is live. Verify with Quickstart Scenarios 1, 2, and 3 before proceeding.

---

## Phase 4: User Story 2 — System Admin Assigns and Revokes Roles (Priority: P2)

**Goal**: System admins can view user accounts, add roles, and remove roles. Changes are recorded in the audit log and take effect on the target user's next page load.

**Independent Test**: System admin promotes a test `user` to `moderator`. Verify the test user can then access moderator-only pages. Verify the audit log has the entry. See Quickstart Scenario 4.

- [x] T011 [P] [US2] Add to `app/auth/roles.php`: implement `log_audit_action(int $actor_id, string $action_type, string $target_type, ?int $target_id, array $detail = []): void` (INSERT into `audit_log`); implement `assign_role(int $actor_id, int $target_id, string $role_name): void` — validate role exists, enforce actor-level ceiling (actor's max role level must be >= target role level), INSERT into `user_roles`, call `log_audit_action` with action_type='role_assigned'; implement `revoke_role(int $actor_id, int $target_id, string $role_name): void` — enforce last-system-admin guard (if revoking system_admin: count remaining system_admins, throw if would reach 0), DELETE from `user_roles`, call `log_audit_action` with action_type='role_revoked'; both functions use prepared statements

- [x] T012 [P] [US2] Create `public/admin/users/index.php` — `require_once` env, db, session, roles; call `require_role('system_admin')`; SELECT users with their comma-joined roles via GROUP_CONCAT JOIN; paginate at 25 per page; display table: username, display_name, roles, moderation_state (colour-coded badge), last_login_at; each username links to `view.php?id={id}`; mobile-first layout (table wraps with `overflow-x: auto` on small viewports); all output through `htmlspecialchars`

- [x] T013 [US2] Create `public/admin/users/view.php` — `require_role('admin')`; load user by GET `id` param (prepared statement; 404 if not found); display: username, email, DMR ID, moderation_state badge, tier, created_at, last_login_at; **Role Management section** (shown only if current user has `system_admin` role): list current roles each with a CSRF-protected POST form for Revoke; dropdown of assignable roles (filtered to roles ≤ actor's level) with Assign button and CSRF token; on POST: call `assign_role()` or `revoke_role()`, catch exceptions and show inline error, redirect back to same page; mobile-first layout

**Checkpoint**: US2 complete. Verify Quickstart Scenarios 4, 7, and 8 before proceeding.

---

## Phase 5: User Story 3 — Moderator Applies Timed Network Mute (Priority: P3)

**Goal**: Moderators can mute a user on the radio network for 1–24 hours. The mute is recorded in `mod_log`, a regen is queued in `config_change_queue`, and it expires automatically via cron.

**Independent Test**: Apply a 1-hour mute to a test user. Verify `moderation_state = 'muted_on_network'`, `mute_expires_at` is set, `mod_log` has the entry, and `config_change_queue` has a pending row. See Quickstart Scenarios 5 and 6.

- [x] T014 [P] [US3] Add to `app/auth/roles.php`: implement `log_mod_action(int $actor_id, int $target_id, string $action, string $reason, ?int $duration_hours = null, ?string $expires_at = null): void` (INSERT into `mod_log`); implement `queue_network_regen(int $actor_id, string $change_type, array $details = []): void` (INSERT into `config_change_queue` with `applied_at = NULL`, details as JSON); both use prepared statements

- [x] T015 [US3] Add muted_on_network moderation controls to `public/admin/users/view.php` — **Moderation section** (shown only if viewer has `moderator` role or higher): when user is `active`: show "Mute on network" form with duration select (1hr/3hr/6hr/24hr), reason textarea (required), CSRF token, submit button; on POST handler: validate CSRF + reason; UPDATE users SET moderation_state='muted_on_network', mute_expires_at=DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id=?; call `log_mod_action()`; call `queue_network_regen()` only if user has a non-null `dmr_id`; redirect back; when user is `muted_on_network`: show "Lift mute" button with reason (POST handler sets state back to 'active', clears mute_expires_at, calls log_mod_action+'active', queues regen); all output escaped

- [x] T016 [P] [US3] Create `scripts/expire-mutes.php` — `declare(strict_types=1)`; `require_once` env.php, connection.php, roles.php using absolute paths (`__DIR__ . '/../app/...'`); SELECT all users WHERE `moderation_state = 'muted_on_network' AND mute_expires_at IS NOT NULL AND mute_expires_at <= NOW()`; for each: UPDATE users SET moderation_state='active', mute_expires_at=NULL WHERE id=?; `log_mod_action(1, $user_id, 'auto_expired', 'Timed mute expired automatically')`; `queue_network_regen(1, 'moderation_state_change', ['target_user_id'=>$id, 'new_state'=>'active'])` only if user has non-null dmr_id; echo one line per expired mute (for cron log); exit 0

- [x] T017 [US3] Register cron job for expire-mutes script — add to root crontab via `crontab -e`: `* * * * * php /opt/cflag-dmr/scripts/expire-mutes.php >> /var/log/cflag-dmr-cron.log 2>&1`; create `/var/log/cflag-dmr-cron.log` with `touch` if it doesn't exist; verify cron is registered with `crontab -l`

**Checkpoint**: US3 complete. Verify Quickstart Scenarios 5 and 6 before proceeding.

---

## Phase 6: User Story 4 — Admin Suspends or Bans a User (Priority: P4)

**Goal**: Admins can suspend (reversible) or permanently ban (system_admin-only reversal) any user. Both actions block login and queue a network regen. Moderation log records all actions.

**Independent Test**: Admin suspends a test user; confirm they cannot log in. Admin lifts suspension; confirm login works again. Confirm a non-system-admin cannot reverse a ban. See Quickstart Scenarios 2 (suspended), 7 (last admin guard), and 8 (role ceiling).

- [x] T018 [US4] Add suspension and ban controls to `public/admin/users/view.php` moderation section — when user is `active`: add "Suspend" form (reason required; admin+ only) and "Ban" form (reason required; admin+ only) below the mute form; when user is `suspended`: show "Lift suspension" button (admin+; POST sets state to 'active', calls log_mod_action+'activated', queues regen); when user is `banned`: show "Reverse ban" button ONLY if current user has `system_admin` role (POST sets state to 'active', calls log_mod_action+'activated', queues regen); POST handlers: validate CSRF + required reason; UPDATE users SET moderation_state=?, mute_expires_at=NULL WHERE id=?; call `log_mod_action()`; call `queue_network_regen()` if user has non-null dmr_id; all outputs escaped; access denied response (403 redirect) if actor lacks required role

**Checkpoint**: US4 complete. Full moderation lifecycle is operational. Verify all 8 Quickstart scenarios before proceeding to polish.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Navigation, security audit, mobile verification, and final sign-off.

- [x] T019 Add "User Management" navigation link to `public/admin/index.php` pointing to `/admin/users/` — shown only to users with `system_admin` role (use `user_has_role()` to gate visibility); mobile-friendly nav link styling per existing `.nav-link` class

- [x] T020 [P] Audit all new and modified `public/` files for output escaping — verify every variable rendered to HTML passes through `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`; check: `public/admin/users/index.php`, `public/admin/users/view.php`, `public/admin/index.php`; fix any unescaped output found

- [x] T021 [P] Verify mobile-first CSS on `public/admin/users/index.php` and `public/admin/users/view.php` — test in browser at 375px viewport width; confirm table uses `overflow-x: auto` wrapper; confirm all buttons meet 44px minimum touch target (Principle VII); add any missing CSS to `public/assets/css/app.css`

- [x] T022 Run all 8 manual test scenarios from `specs/003-role-permissions/quickstart.md` — confirm each passes; document any failures and fix before marking feature complete

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
    └── Phase 2 (Foundational)  ← BLOCKS all user stories
            ├── Phase 3 (US1 P1)  ← proves auth layer works
            │       └── Phase 4 (US2 P2)
            │               └── Phase 5 (US3 P3)
            │                       └── Phase 6 (US4 P4)
            │                               └── Phase 7 (Polish)
            └── (cannot start US2+ until US1 checkpoint passes)
```

### Within Phase 2

T002, T003, T004 can run in parallel (different files).
T005 depends on T002 + T003 + T004.
T006, T007, T008, T009 can run in parallel after T005 (different files).

### Within Phase 4

T011 and T012 can run in parallel (different files).
T013 depends on T011 (uses `assign_role()` and `revoke_role()`).

### Within Phase 5

T014 and T016 can run in parallel (different files).
T015 depends on T014 (uses `log_mod_action()` and `queue_network_regen()`).
T017 depends on T016 (registers the script).

---

## Parallel Opportunities

```
# Phase 2 — migrations can be drafted in parallel:
T002  Write 002_create_users_roles_tables.sql
T003  Write 003_seed_system_roles.sql
T004  Write 004_migrate_admin_users.sql

# Phase 2 — app layer updates can run in parallel after T005:
T006  Create app/auth/roles.php
T007  Update app/auth/session.php
T008  Update app/auth/login.php
T009  Update public/logout.php

# Phase 4 — parallel within US2:
T011  Add assign/revoke/audit functions to roles.php
T012  Create public/admin/users/index.php

# Phase 5 — parallel within US3:
T014  Add log_mod_action + queue_network_regen to roles.php
T016  Create scripts/expire-mutes.php

# Phase 7 — parallel polish:
T020  Output escaping audit
T021  Mobile CSS verification
```

---

## Implementation Strategy

### MVP: US1 Only (Phases 1–3)

1. Phase 1: Create directory
2. Phase 2: Apply migrations, create roles.php, update session/login/logout
3. Phase 3: Update admin/index.php to use require_role()
4. **STOP AND VALIDATE**: Run Quickstart Scenarios 1, 2, 3
5. Result: Auth layer fully migrated. Login uses `users` table. Role enforcement live.

### Incremental Delivery

1. Phases 1–3 → US1 live: auth layer migrated, role enforcement working ✓
2. Phase 4 → US2 live: admins can manage roles via UI ✓
3. Phase 5 → US3 live: moderators can apply timed network mutes ✓
4. Phase 6 → US4 live: admins can suspend and ban ✓
5. Phase 7 → Feature complete: polished, audited, all quickstart tests passing ✓

---

## Summary

| Phase | Tasks | Parallelizable |
|-------|-------|----------------|
| 1 — Setup | T001 | — |
| 2 — Foundational | T002–T009 (8 tasks) | T002/T003/T004; T006/T007/T008/T009 |
| 3 — US1 P1 | T010 (1 task) | — |
| 4 — US2 P2 | T011–T013 (3 tasks) | T011/T012 |
| 5 — US3 P3 | T014–T017 (4 tasks) | T014/T016 |
| 6 — US4 P4 | T018 (1 task) | — |
| 7 — Polish | T019–T022 (4 tasks) | T020/T021 |
| **Total** | **22 tasks** | |
