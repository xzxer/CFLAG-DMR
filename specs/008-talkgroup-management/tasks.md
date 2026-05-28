# Tasks: F6 — Talkgroup Management

**Input**: Design documents from `specs/008-talkgroup-management/`

**Prerequisites**: plan.md ✅ | spec.md ✅ | research.md ✅ | data-model.md ✅ | quickstart.md ✅

**Tests**: Not requested. Manual verification via quickstart.md scenarios.

**Organization**: Tasks grouped by user story. Each story is independently implementable and testable.

**Cross-feature dependency**: Migration 010 (`device_talkgroup_subscriptions`) bridges F5 and F6 — it requires both migration 008 (devices, F5) and migration 009 (talkgroups, F6) to exist. Phase 1 of this plan writes and applies it; F5 must have applied migration 008 first. US4 additionally requires F5's `public/user/devices.php` to exist.

---

## Phase 1: Setup

**Purpose**: Write and apply all F6 migrations including the cross-feature join table.

- [x] T001 Write `migrations/009_create_talkgroups.sql` — 4 tables: `talkgroups` (TGID unique, tg_type ENUM, ownership_tier ENUM, owner FK), `talkgroup_requests` (requester FK, status ENUM), `talkgroup_access_lists` (talkgroup FK, dmr_id, list_type allow/block, UNIQUE on talkgroup+dmr+type), `talkgroup_ownership_upgrade_requests` (talkgroup FK, requester FK, status ENUM) — full DDL from data-model.md
- [x] T002 Apply migration 009 to dev database: `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/009_create_talkgroups.sql`
- [x] T003 Write `migrations/010_create_device_talkgroup_subscriptions.sql` — `device_talkgroup_subscriptions` table with FKs to `devices` (migration 008) and `talkgroups` (migration 009), UNIQUE on (device_id, talkgroup_id, timeslot), ON DELETE CASCADE on both FKs — full DDL from data-model.md
- [x] T004 Apply migration 010 (requires 008 from F5 and 009 from T002 both applied): `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/010_create_device_talkgroup_subscriptions.sql`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Service file with validation and read-only functions needed by all user stories.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T005 Create `app/talkgroups/manager.php` with `declare(strict_types=1)`, `require_once __DIR__ . '/../database/connection.php'`, and these functions: `validate_tgid(string $raw): int|false` (ctype_digit + range 1–16777215), `get_all_talkgroups(bool $include_inactive = false): array` (SELECT all, optionally filter WHERE active=1), `get_talkgroup(int $id): array|null` (by PK), `get_talkgroup_by_tgid(int $tgid): array|null` (by tgid), `get_public_talkgroups(): array` (WHERE active=1 AND tg_type='open' ORDER BY tgid), `search_talkgroups(string $q): array` (WHERE active=1 AND (name LIKE ? OR CAST(tgid AS CHAR) = ?))

**Checkpoint**: Foundation ready — migration applied, `app/talkgroups/manager.php` exists, `php -l` passes.

---

## Phase 3: User Story 1 — Admin Catalog Management (Priority: P1) 🎯 MVP

**Goal**: System_admin can create, edit, enable, disable, and delete talkgroups. Public visitors can browse active open talkgroups.

**Independent Test**: Admin creates TG 91 "Worldwide" (open), sees it in catalog, edits name, disables it. Navigate to `/talkgroups.php` without login — confirm TG 91 visible while enabled, hidden after disabled.

- [x] T006 [US1] Add write functions to `app/talkgroups/manager.php`: `create_talkgroup(array $data, int $admin_id): array` (unique TGID check via get_talkgroup_by_tgid, INSERT), `update_talkgroup(int $id, array $data, int $actor_id): array` (ownership check: system_admin OR owner; validates allowed fields per tier: system_admin can change any field, user_partial can change only name/description), `set_talkgroup_active(int $id, bool $active, int $admin_id): bool` (UPDATE SET active=?), `delete_talkgroup(int $id, int $admin_id): array` (check no active device_talkgroup_subscriptions rows; hard DELETE or return error)
- [x] T007 [US1] Create `public/admin/talkgroups/index.php` — `require_once` `app/auth/roles.php` + `app/talkgroups/manager.php`; `require_role('system_admin')`; handle POSTs: action=create (validate+create_talkgroup), action=edit (update_talkgroup), action=disable (set_talkgroup_active false), action=enable (set_talkgroup_active true), action=delete (delete_talkgroup); render talkgroup catalog table with columns TGID | Name | Type | Owner | Tier | Status | Actions; search form with GET param `q`; "New Talkgroup" inline form (TGID, name, type inputs); CSRF token on all forms; `htmlspecialchars()` on all cell values
- [x] T008 [P] [US1] Create `public/talkgroups.php` — `require_once` `app/auth/session.php` + `app/talkgroups/manager.php`; `start_session()`; no login required; `$talkgroups = get_public_talkgroups()`; render HTML page title "Talkgroups — CFLAG DMR"; table with columns TGID | Name | Description; "No active talkgroups" empty state; `<a href="/" class="nav-link">← Home</a>` nav

**Checkpoint**: US1 functional — admin manages catalog, public page shows open talkgroups.

---

## Phase 4: User Story 2 — User Requests Talkgroup (Priority: P2)

**Goal**: Registered users can submit new talkgroup proposals. Admins approve or deny from a dedicated queue. Approved requests create talkgroup records.

**Independent Test**: Regular user submits a request for TG 3172 "Pacific NW". Admin sees it in `/admin/talkgroups/requests.php`, approves with final TGID 3172 and tier user_partial. TG 3172 appears in catalog and user's request shows "Approved".

- [x] T009 [US2] Add to `app/talkgroups/manager.php`: `submit_talkgroup_request(int $user_id, array $data): array` (check no existing pending request from same user for same proposed_tgid; INSERT into talkgroup_requests), `get_user_talkgroup_requests(int $user_id): array` (SELECT all requests by user), `get_pending_talkgroup_requests(): array` (SELECT talkgroup_requests JOIN users WHERE status='pending' ORDER BY created_at ASC; include user display_name, email for admin view), `approve_talkgroup_request(int $request_id, int $admin_id, int $final_tgid, string $tier): array` (unique TGID check on final_tgid; INSERT talkgroup row; UPDATE request status='approved'; set reviewed_by, reviewed_at), `deny_talkgroup_request(int $request_id, int $admin_id, string $reason): bool` (UPDATE status='denied', denial_reason, reviewed_by, reviewed_at)
- [x] T010 [US2] Create `public/admin/talkgroups/requests.php` — `require_role('system_admin')`; handle POSTs: action=approve (validate final_tgid + tier, call approve_talkgroup_request), action=deny (require reason, call deny_talkgroup_request); render pending requests table (User | Proposed TGID | Name | Type | Description | Date | Actions); approve form inline (final TGID input pre-filled with proposed, ownership tier select: admin/user_partial/user_full); deny form inline (reason textarea); empty state "No pending talkgroup requests"; link back to `/admin/talkgroups/`; CSRF on all forms
- [x] T011 [US2] Create `public/user/talkgroups.php` — `require_login()`; handle POSTs: action=request (validate fields, call submit_talkgroup_request); render "My Talkgroup Requests" section (table: Proposed TGID | Name | Type | Status | Date; status badges pending/approved/denied); "Request a New Talkgroup" section with form (proposed_tgid, name, type select open/private/club, description textarea); CSRF; `<a href="/user/" class="nav-link">← Dashboard</a>`

**Checkpoint**: US2 functional — users can request talkgroups; admin can approve/deny.

---

## Phase 5: User Story 3 — User Manages Owned Talkgroup (Priority: P3)

**Goal**: Talkgroup owners can edit name/description (all tiers). user_full owners can additionally manage allow/block lists. Any user_partial owner can request upgrade to user_full.

**Independent Test**: User with user_partial-owned TG 3172 edits description — saved. Attempt to add access list entry — blocked. Submit upgrade request — visible in admin queue. Admin approves upgrade — owner can now manage access lists.

- [x] T012 [US3] Add to `app/talkgroups/manager.php`: `get_access_list(int $talkgroup_id): array` (SELECT all talkgroup_access_lists WHERE talkgroup_id=?), `add_access_entry(int $talkgroup_id, int $dmr_id, string $list_type, int $actor_id): bool` (INSERT IGNORE), `remove_access_entry(int $talkgroup_id, int $dmr_id, string $list_type, int $actor_id): bool` (DELETE WHERE talkgroup_id=? AND dmr_id=? AND list_type=?), `check_talkgroup_access(int $talkgroup_id, int $dmr_id): bool` (deny-wins: if block entry → false; if allow list non-empty AND no allow entry → false; else true), `submit_ownership_upgrade(int $talkgroup_id, int $requester_id, string $reason): array` (validate caller is user_partial owner; INSERT), `approve_ownership_upgrade(int $request_id, int $admin_id): bool` (UPDATE talkgroups SET ownership_tier='user_full'; UPDATE request status='approved'), `deny_ownership_upgrade(int $request_id, int $admin_id): bool` (UPDATE request status='denied')
- [x] T013 [US3] Extend `public/user/talkgroups.php` — add "My Talkgroups" section at top: query owned talkgroups; for each talkgroup show card with name, TGID, type, tier; edit name/description form (action=edit_owned); if ownership_tier='user_full' show access list table (DMR ID | List Type | Actions) + add-entry form (dmr_id input, list_type allow/block select, action=add_access) + remove entry button (action=remove_access); if ownership_tier='user_partial' show "Request Full Ownership" form (reason textarea, action=request_upgrade); all POSTs verified against ownership server-side; CSRF on all forms
- [x] T014 [US3] Extend `public/admin/talkgroups/requests.php` — add "Ownership Upgrade Requests" section below talkgroup creation requests: query pending ownership upgrades (requester name, talkgroup name, reason, date); approve (action=approve_upgrade) and deny (action=deny_upgrade) POST handlers; empty state "No pending upgrade requests"

**Checkpoint**: US3 functional — owners can self-service; admin handles upgrade queue.

---

## Phase 6: User Story 4 — Device Subscriptions (Priority: P4)

**Goal**: Users with approved devices can subscribe to open talkgroups and request access to private ones. F7 config generation can query the full device→talkgroup→timeslot mapping.

**⚠️ Cross-feature dependency**: Requires migration 010 applied (T004) AND F5 US1 complete (`public/user/devices.php` exists with device list).

**Independent Test**: User with approved device adds TG 91 Worldwide on TS1 — subscription appears in device list. Attempts to add private TG — join request created. Removes subscription — gone from list. `get_device_subscriptions_for_config()` returns correct mapping.

- [x] T015 [US4] Add to `app/talkgroups/manager.php`: `get_subscriptions_for_device(int $device_id): array` (SELECT dts.* JOIN talkgroups WHERE device_id=?), `add_subscription(int $device_id, int $talkgroup_id, int $timeslot, int $actor_user_id): array` (verify device approved + caller owns device; check talkgroup active + type='open'; INSERT IGNORE device_talkgroup_subscriptions), `remove_subscription(int $device_id, int $talkgroup_id, int $timeslot, int $actor_user_id): bool` (verify caller owns device; DELETE), `submit_join_request(int $device_id, int $talkgroup_id, int $actor_user_id): array` (verify device approved; talkgroup active + type in private/club; INSERT into talkgroup_join_requests — note: uses device_id as FK)
- [x] T016 [US4] Add `get_device_subscriptions_for_config(): array` to `app/talkgroups/manager.php` — SELECT dts.device_id, d.dmr_id, dts.talkgroup_id, tg.tgid, tg.name AS tg_name, dts.timeslot FROM device_talkgroup_subscriptions dts JOIN talkgroups tg ON tg.id=dts.talkgroup_id JOIN devices d ON d.id=dts.device_id JOIN users u ON u.id=d.user_id WHERE tg.active=1 AND d.status='approved' AND u.moderation_state='active' — returns flat array for F7 consumption
- [x] T017 [US4] Extend `public/user/devices.php` (F5 file) — add talkgroup subscription subsection below each device row: list active subscriptions as compact table (TG Name | TGID | Slot | Remove button); "Add Subscription" form for approved devices only (select of open talkgroups from get_public_talkgroups(), timeslot radio 1/2, action=add_sub); "Request Access" link for private talkgroups (action=request_join with talkgroup_id); disabled/hidden subscription UI for pending/denied devices with tooltip "Approval required"; POST handlers at top of file for action=add_sub, action=remove_sub, action=request_join calling functions from app/talkgroups/manager.php; CSRF on all forms

**Checkpoint**: US4 functional — full subscription flow works end-to-end.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Navigation updates, admin dashboard integration, status update, mobile check, acceptance.

- [x] T018 Update `public/admin/index.php` — add Talkgroup Management card section (after Last Heard card, before HBLink card): eyebrow "Talkgroups", heading "Management", show count of pending talkgroup requests (query talkgroup_requests WHERE status='pending'); link to `/admin/talkgroups/` and to `/admin/talkgroups/requests.php` with pending count; visible to system_admin only
- [x] T019 Update `public/talkgroups.php` — add "Last Heard" link (`<a href="/last-heard.php" class="nav-link">Last Heard</a>`) alongside ← Home nav so public pages cross-link
- [x] T020 Update `specs/000-project-overview/spec.md` — change F6 status from "🔄 In Progress" to "✅ Complete"
- [ ] T021 Verify mobile responsiveness — confirm lh-table-wrap overflow-x on talkgroup tables; filter/search inputs full-width on 375px; buttons 44px touch target minimum; admin talkgroup table scrollable on tablet
- [ ] T022 Run all quickstart.md scenarios 1.1–F7.1 on dev server and confirm expected outcomes

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001–T002 have no dependencies. T003 depends on T002 (migration 009 must exist before writing 010 — for correctness review). T004 requires both migration 008 (F5, external) and T002 applied.
- **Foundational (Phase 2)**: Depends on Phase 1 (migration 009 must be applied; migration 010 needed for US4 only).
- **User Stories (Phase 3–6)**: All depend on T005 (manager.php foundation).
  - US1 (T006–T008): depends only on T005
  - US2 (T009–T011): depends on T005; T010 may link back to T007 (same admin area)
  - US3 (T012–T014): extends T011 (same user page) and T010 (same admin page) — follow US2
  - US4 (T015–T017): requires T004 (migration 010), T005, and F5 US1 complete
- **Polish (Phase 7)**: Depends on all desired stories complete

### User Story Dependencies

| Story | Depends on Foundational | Depends on other stories |
|-------|------------------------|--------------------------|
| US1 (P1) | T005 only | None |
| US2 (P2) | T005 only | None (extends different pages from US1) |
| US3 (P3) | T005 only | Extends same pages as US2 — follow US2 |
| US4 (P4) | T004 + T005 | Extends F5 devices.php; depends on US1 talkgroup catalog |

### Within US3 (sequential)
T011 (create user page) → T013 (extend user page) — same file
T010 (create requests page) → T014 (extend requests page) — same file

---

## Parallel Opportunities

### Phase 1
```
T001 (write 009 SQL) ←→ independent — then T002 (apply 009) → T003 (write 010) → T004 (apply 010)
```

### Phase 3 (after T005 complete)
```
T006 (write functions) → T007 (admin page)
T008 [P] (public page) ← can be written concurrently with T007 (different file)
```

### Phase 4 (after T005)
```
T009 (write functions) → T010 (admin page) ←→ T011 (user page) [P — different files]
```

---

## Implementation Strategy

### MVP (US1 only)

1. Phase 1: Setup (T001–T004)
2. Phase 2: Foundational (T005)
3. Phase 3: US1 (T006–T008)
4. **STOP and VALIDATE**: Admin creates TG 91, public page shows it
5. Ship US1 — talkgroup catalog is live

### Incremental Delivery

1. Setup + Foundational → service layer ready
2. US1 (catalog + public page) → **MVP**
3. US2 (user requests + admin queue) → community-driven growth
4. US3 (owner self-service + access lists) → reduced admin burden
5. US4 (device subscriptions) → end-to-end DMR routing data ready for F7
6. Polish → docs, mobile, acceptance

---

## Notes

- `[P]` = parallelizable (different files, no cross-task dependencies)
- `[US1]`–`[US4]` = maps to user story in spec.md
- US3 extends same files as US2 — sequential within those files
- Migration 010 is a cross-feature migration; F5 migration 008 must be applied first
- US4 T017 modifies `public/user/devices.php` (F5's file) — coordinate merge carefully
- deny-wins in check_talkgroup_access: blocked DMR ID is never allowed regardless of allow list state
- `get_device_subscriptions_for_config()` is the F7 entry point — keep it clean and tested
