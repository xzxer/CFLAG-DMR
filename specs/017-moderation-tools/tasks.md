# Tasks: F15 — Moderation Tools

**Input**: Design documents from `specs/017-moderation-tools/`

**Organization**: No migration needed (schema exists). US1-US4 are all P1 — the manager.php (foundational) blocks everything else. US1 (suspend), US2 (ban), US3 (reinstate) all share the same action forms on view.php so they are grouped together. US4 (mod log page) and US5 (per-user history) are independent of each other.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

**Purpose**: Verify schema is complete before writing PHP.

- [ ] T001 Confirm moderation_state ENUM values on users table match the required set: `SHOW COLUMNS FROM users LIKE 'moderation_state'` — expected: enum('active','suspended','banned','muted_on_network')
- [ ] T002 Confirm mod_log table columns exist: `DESCRIBE mod_log` — expected: actor_user_id, target_user_id, action, reason, duration_hours, expires_at, created_at

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The manager.php provides all shared functions used by every user story.

**⚠️ CRITICAL**: All user story pages depend on app/moderation/manager.php existing.

- [ ] T003 Create app/moderation/manager.php — declare(strict_types=1), require get_db(), stub all function signatures: suspend_user, ban_user, reinstate_user, mute_user, unmute_user, get_mod_log, get_mod_log_for_user, check_and_auto_reinstate
- [ ] T004 Implement `check_and_auto_reinstate(int $user_id): void` in app/moderation/manager.php — SELECT moderation_state, expires_at FROM users WHERE id=?; if state='suspended' AND expires_at IS NOT NULL AND expires_at < NOW(), UPDATE users SET moderation_state='active' WHERE id=? and INSERT mod_log record with action='reinstated', reason='Suspension expired (auto)'
- [ ] T005 Implement `suspend_user(int $target_id, int $actor_id, string $reason, ?int $duration_hours): array` in app/moderation/manager.php — UPDATE users SET moderation_state='suspended' WHERE id=?; INSERT mod_log; INSERT audit_log; if duration_hours set, compute expires_at; return ['ok'=>bool, 'error'=>string|null]
- [ ] T006 Implement `ban_user(int $target_id, int $actor_id, string $reason): array` in app/moderation/manager.php — UPDATE users SET moderation_state='banned' WHERE id=?; INSERT mod_log; INSERT audit_log; return ['ok', 'error']
- [ ] T007 Implement `reinstate_user(int $target_id, int $actor_id, string $reason): array` in app/moderation/manager.php — UPDATE users SET moderation_state='active', expires_at=NULL WHERE id=?; INSERT mod_log with action='reinstated'; INSERT audit_log; return ['ok', 'error']
- [ ] T008 Implement `mute_user(int $target_id, int $actor_id, string $reason): array` in app/moderation/manager.php — UPDATE users SET moderation_state='muted_on_network' WHERE id=?; INSERT mod_log; INSERT audit_log
- [ ] T009 Implement `unmute_user(int $target_id, int $actor_id, string $reason): array` in app/moderation/manager.php — UPDATE users SET moderation_state='active' WHERE id=?; INSERT mod_log; INSERT audit_log
- [ ] T010 Implement `get_mod_log(int $limit = 50, int $offset = 0): array` in app/moderation/manager.php — SELECT mod_log JOIN users actor ON actor_user_id=actor.id JOIN users target ON target_user_id=target.id ORDER BY created_at DESC LIMIT ? OFFSET ?
- [ ] T011 Implement `get_mod_log_for_user(int $user_id): array` in app/moderation/manager.php — same JOIN query but WHERE target_user_id = ? ORDER BY created_at DESC

**Checkpoint**: All manager functions implemented and returning correct data shapes. Login flow hardening can now proceed.

---

## Phase 3: User Stories 1, 2 & 3 — Suspend / Ban / Reinstate (Priority: P1) 🎯 MVP

**Goal**: Admin can suspend, ban, and reinstate users from their admin profile page.

**Independent Test**: Navigate to any user's admin profile at `/admin/users/view.php?id=N`. Confirm: (1) current moderation_state badge is shown, (2) Suspend button opens form requesting reason and optional duration, (3) submitting suspend changes state to 'suspended' in DB and creates a mod_log record, (4) Reinstate button appears when user is suspended/banned and restores state to 'active'.

### Implementation

- [ ] T012 [US1] [US2] [US3] Extend public/admin/users/view.php with Moderation section — current state badge (active=green, suspended=yellow, banned=red, muted=orange); action forms (admin role check: system_admin only): Suspend form (reason textarea + duration select: 1 day / 7 days / 30 days / custom hours input), Ban form (reason textarea), Mute button (reason textarea), Reinstate button (reason textarea, visible when suspended/banned/muted) — each form is a POST with CSRF token
- [ ] T013 [US1] [US2] [US3] Add POST handler to public/admin/users/view.php — switch on $_POST['action'] (suspend/ban/reinstate/mute/unmute), call appropriate manager function, redirect with flash message on success/error (PRG pattern)

**Checkpoint**: Suspend, ban, mute, and reinstate all functional from the user admin profile.

---

## Phase 4: Login Flow Hardening (Priority: P1 — blocks suspended/banned users)

**Goal**: Suspended and banned users cannot log in. Expired suspensions auto-reinstate on login.

**Independent Test**: Suspend a test user account. Attempt to log in as that user. Confirm they see "Your account is suspended" with the reason. Then set expires_at to a past time in the DB. Attempt login again and confirm auto-reinstate allows them in.

### Implementation

- [ ] T014 Update app/auth/login.php — after password verification, call check_and_auto_reinstate($user_id), then re-read moderation_state from DB; if 'suspended': show error "Your account is suspended: [reason from most recent mod_log]"; if 'banned': show error "Your account has been banned"
- [ ] T015 Add banned email check to registration flow (public/register.php or app/auth/register.php) — before creating the user, query: SELECT id FROM users WHERE email = ? AND moderation_state = 'banned'; if found, reject with generic message "Registration is unavailable for this email address"

**Checkpoint**: Suspended/banned users blocked at login; auto-reinstate works for expired suspensions.

---

## Phase 5: User Story 4 — View Moderation Log (Priority: P1)

**Goal**: Admin or moderator can view the full paginated moderation history.

**Independent Test**: Navigate to `/admin/moderation/`. Confirm the mod_log table shows actor, target, action, reason, and timestamp. Confirm moderator role users can view but have no action buttons.

### Implementation

- [ ] T016 [US4] Create public/admin/moderation/index.php — role check (admin or moderator), paginated mod_log table using get_mod_log($limit=50, $offset), columns: actor (linked to admin profile), target (linked to admin profile), action badge (styled by action type), reason, timestamp; ?page=N pagination; no action buttons on this page

**Checkpoint**: Moderation log accessible and paginated.

---

## Phase 6: User Story 5 — Per-User Moderation History (Priority: P2)

**Goal**: Admin sees all moderation actions for a specific user on their profile page.

**Independent Test**: Navigate to a user who has mod_log entries. Confirm the "Moderation History" section at the bottom of their profile lists all their mod_log entries.

### Implementation

- [ ] T017 [US5] Add "Moderation History" section to public/admin/users/view.php — call get_mod_log_for_user($user_id), render a table of all mod_log entries: actor, action badge, reason, duration (if set), timestamp; if no entries, show "No moderation history"

**Checkpoint**: Per-user moderation history visible on the admin user profile.

---

## Phase 7: Navigation & Polish

- [ ] T018 Add "Moderation Log" link to admin sidebar/nav pointing to /admin/moderation/
- [ ] T019 [P] Verify all moderation actions write to audit_log — check that T005–T009 each INSERT an audit_log record with appropriate action_type, target_type='user', target_id
- [ ] T020 [P] Verify muted users are excluded from next config generation — `get_whitelist_eligible_dmr_ids()` in app/devices/manager.php already filters moderation_state='active'; confirm by checking the SQL

---

## Dependencies & Execution Order

- **Phase 1 (Setup)**: Verify schema — no code changes
- **Phase 2 (Manager)**: T003–T011 sequential; blocks all user story phases
- **Phase 3 (US1-US3)**: Depends on Phase 2; T012 → T013 sequential (same file)
- **Phase 4 (Login hardening)**: Depends on Phase 2; T014 and T015 are [P] (different files)
- **Phase 5 (US4)**: Depends on T010 (get_mod_log); independent of Phase 3 and 4
- **Phase 6 (US5)**: Depends on T011 (get_mod_log_for_user); independent
- **Phase 7 (Polish)**: Depends on all above

### Parallel Opportunities

Within Phase 2 (manager.php):
- T005, T006, T007, T008, T009 [P] — all independent functions (but same file, so implement sequentially to avoid conflicts)

After Phase 2 completes:
- Phase 3 (user profile forms), Phase 4 (login flow), Phase 5 (mod log page) can start in parallel
- Phase 6 (US5) can start immediately after T011

---

## Implementation Strategy

### MVP (Phase 1 → 2 → 3 → 4)
1. Verify schema (Phase 1)
2. Build manager.php (Phase 2) — foundation for everything
3. User profile moderation section (Phase 3) — suspend/ban/reinstate UI
4. Login hardening (Phase 4) — enforcement
5. **Validate**: Suspend user, try to log in, confirm blocked

### Full Feature
- Add mod log page (Phase 5)
- Add per-user history (Phase 6)
- Add nav link and polish (Phase 7)
