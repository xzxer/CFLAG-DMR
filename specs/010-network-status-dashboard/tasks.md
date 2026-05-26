# Tasks: Network Status Dashboard (F10)

**Branch**: `010-network-status-dashboard`
**Input**: specs/010-network-status-dashboard/plan.md, spec.md, data-model.md, research.md

## Phase 1: Setup

**Purpose**: No migrations needed. Add the helper function and create the page file.

- [ ] T001 Add get_recently_active_dmr_ids(int $minutes = 30): array to app/lastheard/reader.php — parses lastheard log for recent src_id values, joins against devices table (status='approved') to return [['dmr_id', 'callsign', 'last_seen']] per data-model.md

---

## Phase 2: User Story 1 — Connected Peers & Server Health (P1) 🎯 MVP

**Goal**: Logged-in users see server state, uptime, and recently active devices on the status page

**Independent Test**: navigate to /network-status.php as a regular user; confirm server state card and recently active devices list render without errors; confirm no IP addresses visible

- [ ] T002 [US1] Create public/network-status.php — require_login(), load get_hblink_status(), get_recently_active_dmr_ids(30), and load_lastheard(10); render server state card (running/stopped, uptime); render recently active devices table (callsign, DMR ID, last seen); admin-only sections gated on user_has_role($user_id, 'system_admin')
- [ ] T003 [US1] Add "Network Status" link to public/index.php (unified dashboard) for all authenticated users

**Checkpoint**: US1 independently testable — page loads, server state shown, recently active devices shown, no admin-only data visible to regular users

---

## Phase 3: User Story 2 — Recent Call Activity Feed (P2)

**Goal**: Status page shows 10 most recent Last Heard entries integrated into the page

**Independent Test**: confirm activity feed section renders with up to 10 entries; confirm "No recent activity" shown when log is empty

- [ ] T004 [US2] Add activity feed section to public/network-status.php — reuses $lh_result from load_lastheard(10) already loaded in T002; renders callsign, talkgroup name (falls back to TG {tgid}), and datetime; shows "No recent activity" when empty
- [ ] T005 [US2] Add "View Full Log →" link to activity feed section pointing to /last-heard.php

**Checkpoint**: US2 independently testable — activity feed renders correctly; talkgroup name shown where available

---

## Phase 4: User Story 3 — Admin Server Control Panel (P3)

**Goal**: system_admin users see config drift warning and server control links on the status page

**Independent Test**: log in as system_admin; confirm Server Controls section visible with admin links; trigger config drift condition and confirm warning banner appears

- [ ] T006 [US3] Add admin-only Server Controls section to public/network-status.php (gated on $is_sysadmin): config drift warning banner (from get_hblink_status()['config_drifted']), process PID/uptime detail, links to /admin/hblink/config.php, /admin/hblink/rules.php, /admin/hblink/status.php, and /admin/config/ (F7 link, shown only if /admin/config/ exists)
- [ ] T007 [US3] Verify admin-only sections are completely absent when viewed as non-admin (manual QA step documented in quickstart W.1)

**Checkpoint**: US3 independently testable — admin sees control panel; non-admin does not

---

## Phase 5: Polish & Cross-Cutting Concerns

- [ ] T008 [P] Verify mobile layout of all three sections (server state, recently active, activity feed) at 375px viewport
- [ ] T009 [P] Add "← Dashboard" back-link to bottom of public/network-status.php
- [ ] T010 Run quickstart.md scenarios 1.1 through W.2 and verify all pass

---

## Dependencies & Execution Order

- T001 must complete before T002 (helper function needed by page)
- T002 must complete before T004 (activity feed added to same page)
- T002 must complete before T006 (admin section added to same page)
- T004 and T006 can be worked in parallel after T002 is done (different sections of same file — coordinate to avoid conflicts)
- T003 is independent after T002
- T008, T009, T010 depend on all phases complete

## Implementation Strategy

**MVP**: T001 + T002 + T003 (US1 — status page live for all users)
**US2**: T004 + T005 (activity feed — small addition to existing page)
**US3**: T006 + T007 (admin controls — conditional section, non-breaking)
