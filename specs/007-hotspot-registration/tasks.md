# Tasks: F5 — Hotspot & Repeater Registration

**Input**: Design documents from `specs/007-hotspot-registration/`

**Prerequisites**: plan.md ✅ | spec.md ✅ | research.md ✅ | data-model.md ✅ | quickstart.md ✅

**Tests**: Not requested. Manual verification via quickstart.md scenarios.

**Organization**: Tasks grouped by user story. Each story is independently implementable and testable.

**Cross-feature dependency**: US4 (talkgroup subscriptions per device) requires F6's migration 009 (`talkgroups` table) and migration 010 (`device_talkgroup_subscriptions` join table) to be applied, and F6's `add_subscription()` / `remove_subscription()` functions in `app/talkgroups/manager.php` to exist. US1–US3 are fully independent of F6.

---

## Phase 1: Setup

**Purpose**: Create user directory, write migration, apply to dev database.

- [ ] T001 Create `public/user/` directory and `public/user/index.php` — `require_once` `app/auth/roles.php`; `start_session()`; `require_login()`; render simple user dashboard page with title "My Account — CFLAG DMR", link to `<a href="/user/devices.php" class="nav-link">My Devices</a>`, and `<a href="/logout.php" class="nav-link">Log out</a>`
- [ ] T002 Write `migrations/008_create_devices.sql` — devices table with full DDL: id, user_id FK (ON DELETE CASCADE), callsign VARCHAR(16), dmr_id INT UNSIGNED, device_type ENUM('hotspot','repeater'), hardware_desc VARCHAR(255) DEFAULT '', status ENUM('pending','approved','denied') DEFAULT 'pending', approved_by FK (ON DELETE SET NULL), denied_reason TEXT, reviewed_at DATETIME, created_at, updated_at ON UPDATE CURRENT_TIMESTAMP; keys: idx_user_id, idx_status, idx_dmr_id; full DDL from data-model.md
- [ ] T003 Apply migration 008 to dev database: `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/008_create_devices.sql`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Service file with validation and read functions needed by all user stories.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T004 Create `app/devices/manager.php` with `declare(strict_types=1)`, `require_once __DIR__ . '/../database/connection.php'`, and these functions: `validate_dmr_id(string $raw): int|false` — `ctype_digit()`; cast to int; range check 1000000–9999999; return int or false; `validate_callsign(string $raw): string|false` — trim; length 3–16; `preg_match('/^[A-Z0-9\/]+$/i', $raw)`; return trimmed string or false; `get_user_devices(int $user_id): array` — SELECT all devices WHERE user_id=? ORDER BY created_at DESC; `get_device(int $device_id, int $user_id): array|null` — SELECT one WHERE id=? AND user_id=? (ownership baked in)

**Checkpoint**: Foundation ready — migration applied, `app/devices/manager.php` exists, `php -l` passes.

---

## Phase 3: User Story 1 — Register a Device (Priority: P1) 🎯 MVP

**Goal**: A registered, email-verified user can submit a new device (callsign, DMR ID, type, hardware). Device enters pending state, visible to admins.

**Independent Test**: Log in as regular user, navigate to `/user/devices.php`, submit valid device form — device appears in list as "Pending Approval". As admin, confirm it appears in `/admin/devices/`.

- [ ] T005 [US1] Add `register_device(int $user_id, array $data): array` to `app/devices/manager.php` — returns `['ok'=>bool, 'error'=>string|null, 'device_id'=>int|null]`; checks: user's moderation_state is 'active' (SELECT from users); validate callsign via validate_callsign(); validate dmr_id via validate_dmr_id(); check no existing pending/approved device with same dmr_id by any user (SELECT WHERE dmr_id=? AND status IN ('pending','approved')); if clear, INSERT into devices with status='pending'
- [ ] T006 [US1] Create `public/user/devices.php` — `require_once` `app/auth/roles.php` + `app/devices/manager.php`; `start_session()`; `require_login()`; check `current_user()['moderation_state']` — if suspended/banned redirect to `/login.php` with error; handle POST action=register (validate CSRF, call register_device, redirect with flash); `$devices = get_user_devices($user_id)`; render HTML: title "My Devices — CFLAG DMR"; device list table (columns: Callsign | DMR ID | Type | Status | Hardware | Date) — status shown as badge (.badge-active approved, .badge-pending pending, .badge-banned denied); "Register a Device" form section (callsign text, dmr_id text, device_type select hotspot/repeater, hardware_desc text, CSRF hidden field, submit button); "No devices registered yet" empty state; `htmlspecialchars()` on all cell values; `<a href="/user/" class="nav-link">← Dashboard</a>`

**Checkpoint**: US1 functional — user can register a device; admin can see it in pending queue.

---

## Phase 4: User Story 2 — Admin Approval Workflow (Priority: P2)

**Goal**: System_admin views pending device registrations and approves or denies each with an optional note. Approved devices become whitelist-eligible.

**Independent Test**: Pending device exists. Log in as `cflagadmin`, load `/admin/devices/` — see device in queue. Approve it — status becomes "Approved". Create another pending device, deny with reason — status becomes "Denied" with reason stored.

- [ ] T007 [US2] Add to `app/devices/manager.php`: `get_pending_devices(): array` — SELECT devices JOIN users (display_name, email) WHERE status='pending' ORDER BY created_at ASC; `approve_device(int $device_id, int $admin_id): array` — returns `['ok'=>bool, 'error'=>string|null]`; SELECT device by id; check no other status='approved' device with same dmr_id for an active user (SELECT WHERE dmr_id=? AND status='approved' JOIN users WHERE moderation_state='active'); if clear UPDATE status='approved', approved_by=$admin_id, reviewed_at=NOW(); `deny_device(int $device_id, int $admin_id, string $reason): bool` — UPDATE status='denied', approved_by=$admin_id, denied_reason=$reason, reviewed_at=NOW()
- [ ] T008 [US2] Create `public/admin/devices/index.php` — `require_once` `app/auth/roles.php` + `app/devices/manager.php`; `require_role('system_admin')`; handle POST action=approve (validate CSRF, call approve_device, redirect), action=deny (validate CSRF + reason non-empty, call deny_device, redirect); `$pending = get_pending_devices()`; render: title "Device Approvals — CFLAG DMR"; pending table (columns: User | Callsign | DMR ID | Type | Hardware | Submitted | Actions); each row: "Approve" button form (hidden device_id, action=approve, CSRF) + "Deny" inline form (device_id, reason textarea, action=deny, CSRF); empty state "No pending device registrations"; `htmlspecialchars()` on all values; `<a href="/admin/" class="nav-link">← Dashboard</a>`

**Checkpoint**: US2 functional — admin can approve/deny pending devices.

---

## Phase 5: User Story 3 — Device Management (Priority: P3)

**Goal**: Users can edit hardware description and delete devices they no longer use. Ownership enforced server-side.

**Independent Test**: User with approved device edits hardware_desc — change saved and displayed. User deletes a pending device — it disappears from list and from admin queue.

- [ ] T009 [US3] Add to `app/devices/manager.php`: `update_device_hardware(int $device_id, int $user_id, string $hardware_desc): bool` — trim $hardware_desc, max 255 chars; UPDATE devices SET hardware_desc=?, updated_at=NOW() WHERE id=? AND user_id=? (ownership enforced by WHERE clause); return affected rows > 0; `delete_device(int $device_id, int $user_id): bool` — DELETE FROM devices WHERE id=? AND user_id=? (ownership enforced); return affected rows > 0
- [ ] T010 [US3] Extend `public/user/devices.php` — add POST handlers for action=edit_hw (validate CSRF, call update_device_hardware, redirect) and action=delete (validate CSRF, call delete_device with confirm=1, redirect); in device list table, add "Edit" inline form per row (hardware_desc text input pre-filled, action=edit_hw, hidden device_id, CSRF) and "Delete" button form per row (confirm via onclick or hidden confirm field, action=delete, hidden device_id, CSRF)

**Checkpoint**: US3 functional — users can edit and delete their devices.

---

## Phase 6: User Story 4 — Talkgroup Subscriptions per Device (Priority: P4)

**Goal**: Approved devices can be subscribed to open talkgroups (immediate) or request access to private talkgroups (join request).

**⚠️ Dependency**: Requires F6 complete: migration 009 (`talkgroups`), migration 010 (`device_talkgroup_subscriptions`), and `app/talkgroups/manager.php` with `get_public_talkgroups()`, `add_subscription()`, `remove_subscription()`, `submit_join_request()` all implemented (F6 tasks T005, T015, T017).

**Independent Test**: User with approved device loads `/user/devices.php`, sees subscription section, adds TG 91 on TS1, subscription appears. Attempts private TG — join request created. Removes subscription — gone.

- [ ] T011 [US4] Extend `public/user/devices.php` — add `require_once` `app/talkgroups/manager.php` at top; add POST handlers for action=add_sub (validate CSRF, validate timeslot in [1,2], call add_subscription from talkgroups/manager.php), action=remove_sub (validate CSRF, call remove_subscription), action=request_join (validate CSRF, call submit_join_request); in device list, for each approved device add a talkgroup subscription subsection: compact table of active subscriptions (TG Name | TGID | Slot | Remove button); "Add Subscription" form (select of open talkgroups from get_public_talkgroups(), timeslot radio buttons 1/2, submit); note below "For private talkgroups, use 'Request Access'"; for pending/denied devices show "Talkgroup subscriptions available after approval" message instead of forms

**Checkpoint**: US4 functional — full subscription flow works.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: F7 data contract, admin dashboard link, CSS badge, status update, mobile check, acceptance.

- [ ] T012 Add `get_whitelist_eligible_dmr_ids(): array` to `app/devices/manager.php` — SELECT d.dmr_id FROM devices d JOIN users u ON u.id=d.user_id WHERE d.status='approved' AND u.moderation_state='active'; returns flat array of DMR ID integers (F7 data contract)
- [ ] T013 Update `public/admin/index.php` — after Last Heard card, add "Devices" card section (`$pending_device_count` queried inline): eyebrow "Devices", heading "Pending Approvals", show count or "No pending registrations"; link to `/admin/devices/`; visible to system_admin only (wrap in `if ($is_sysadmin)`)
- [ ] T014 Add `.badge-pending { background: #854d0e; color: #fef9c3; border-radius: 4px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; }` CSS rule to `public/assets/css/app.css` after existing `.badge` rules
- [ ] T015 Update `specs/000-project-overview/spec.md` — change F5 status from "🔄 In Progress" to "✅ Complete"
- [ ] T016 Verify mobile responsiveness — confirm device list table wrapped in `<div class="lh-table-wrap">` for horizontal scroll on 375px; confirm registration form inputs are full-width; confirm action buttons meet 44px touch target
- [ ] T017 Run all quickstart.md scenarios 1.1–W.2 on dev server and confirm expected outcomes

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 (mkdir + stub page) can start immediately; T002 (write SQL) can start immediately; T003 (apply migration) depends on T002
- **Foundational (Phase 2)**: T004 (manager.php) depends on T003 (migration applied)
- **User Stories (Phase 3–5)**: All depend on T004 (manager.php foundation)
  - US1 (T005–T006): depends on T004 only
  - US2 (T007–T008): depends on T004 only; T008 links to T006 page for reference but is a separate file
  - US3 (T009–T010): extends T006 (same file) — follow US1
  - US4 (T011): extends T006 (same file); also requires F6 complete — follow US3 + F6 US4
- **Polish (Phase 7)**: Depends on US1–US3 complete; US4 optional for MVP

### User Story Dependencies

| Story | Depends on Foundational | Depends on other stories |
|-------|------------------------|--------------------------|
| US1 (P1) | T004 only | None |
| US2 (P2) | T004 only | None (different file from US1) |
| US3 (P3) | T004 only | Extends US1 file — follow US1 |
| US4 (P4) | T004 + F6 complete | Extends US1 file; requires F6 US4 |

### Within US3 (sequential)
T006 (create devices.php) → T010 (extend devices.php) — same file

### Within US4 (sequential)
T010 (US3 extends devices.php) → T011 (US4 extends devices.php) — same file

---

## Parallel Opportunities

### Phase 1
```
T001 (public/user/index.php) [P] ←→ T002 (write migration SQL) [P]
T003 (apply migration) must follow T002
```

### Phase 3–4 (after T004 complete)
```
T005 + T006 (US1)   ←→   T007 + T008 (US2)   [P — different files]
```

---

## Implementation Strategy

### MVP (US1 + US2 only)

1. Phase 1: Setup (T001–T003)
2. Phase 2: Foundational (T004)
3. Phase 3: US1 (T005–T006)
4. Phase 4: US2 (T007–T008)
5. **STOP and VALIDATE**: Register device, approve it, verify whitelist-eligible state
6. Ship US1+US2 — the core registration and approval flow is live

### Incremental Delivery

1. Setup + Foundational → service layer ready
2. US1+US2 in parallel → **MVP** — register + approve devices
3. US3 (device management) → edit + delete
4. Polish (T012–T017) → F7 data contract, dashboard link, CSS
5. US4 (subscriptions) → after F6 complete

---

## Notes

- `[P]` = parallelizable (different files, no cross-task dependencies)
- `[US1]`–`[US4]` = maps to user story in spec.md
- US3 and US4 both extend `public/user/devices.php` — implement sequentially
- DMR ID uniqueness is enforced at application level in approve_device(), not by DB unique constraint — allows re-registration after denial
- `.badge-pending` CSS (T014) is also needed by F6's talkgroup request pages — will be available once this task runs
- `get_whitelist_eligible_dmr_ids()` (T012) is the F7 entry point; keep query simple and in manager.php
- `public/user/` directory is new — no `.htaccess` changes needed (Apache already serves all of `public/`)
