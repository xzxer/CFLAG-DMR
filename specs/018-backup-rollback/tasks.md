# Tasks: F16 — Backup & Rollback

**Input**: Design documents from `specs/018-backup-rollback/`

**Dependency**: F13 (Controlled Restart) is **fully implemented**. Migration 020 exists, `apply_hblink_config()` is in `app/config/generator.php`, and the live test passed.

**Organization**: US1 (history view), US2 (download), and US3 (rollback) are all P1 and share `history.php`. US4 (running config snapshot) is P2 and independent. The PHP layer refactor (Phase 3) must complete before the UI (Phase 4).

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup (Prerequisite Verification)

**Purpose**: Confirm F13 is in place before writing any F16 code.

- [X] T001 Verify F13 complete — apply_hblink_config() exists in app/config/generator.php, migration 020 columns (applied, apply_success, backup_path) exist in config_generation_history, and at least one history record with applied=1 is present

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration 023 adds `rolled_back_from_id` to `config_generation_history`. Required before PHP layer and UI work.

**⚠️ CRITICAL**: Run migration before implementing T003+.

- [ ] T002 Write migration 023_add_rolled_back_from_id.sql in migrations/ — ALTER TABLE config_generation_history ADD COLUMN rolled_back_from_id INT UNSIGNED NULL DEFAULT NULL AFTER backup_path, ADD CONSTRAINT fk_cgh_rollback_from FOREIGN KEY (rolled_back_from_id) REFERENCES config_generation_history (id) ON DELETE SET NULL

**Checkpoint**: Schema ready — PHP generator additions can now begin.

---

## Phase 3: PHP Layer Refactor (Blocking for US3)

**Purpose**: Refactor `apply_hblink_config()` into `apply_hblink_config_text()` to accept any config_text, enabling rollback to pass historical config directly. Existing behavior must be fully preserved.

- [ ] T003 Refactor app/config/generator.php — extract `apply_hblink_config_text(string $config_text, int $actor_id, ?int $rolled_back_from_id = null): array` by moving the backup/write/restart/poll/audit logic from apply_hblink_config() into this new internal function; add rolled_back_from_id to the history INSERT
- [ ] T004 Update `apply_hblink_config(int $actor_id): array` in app/config/generator.php to call generate_hblink_config() then pass the resulting config_text to apply_hblink_config_text($config_text, $actor_id, null) — behavior must match the pre-refactor version exactly
- [ ] T005 Add `rollback_to_generation(int $generation_id, int $actor_id): array` to app/config/generator.php — SELECT config_text FROM config_generation_history WHERE id=? (return error if not found), call apply_hblink_config_text($config_text, $actor_id, $generation_id), return ['ok', 'error', 'new_generation_id']
- [ ] T006 [P] Add `get_config_generation_by_id(int $id): array|null` to app/config/generator.php — SELECT all columns including config_text WHERE id=? — used for download and diff-expand
- [ ] T007 [P] Update `get_generation_history(int $limit, int $offset)` in app/config/generator.php — add rolled_back_from_id to SELECT; add a LEFT JOIN or subquery to fetch the rolled_back source record's generated_at as rollback_source_generated_at
- [ ] T008 [P] Add `get_running_hblink_config(): string|null` to app/config/generator.php — file_get_contents(HBLINK_CONFIG_PATH from _get_config_output_path()); return content string or null if unreadable

**Checkpoint**: Verify apply_hblink_config() still works correctly after refactor — a live apply must succeed and produce a new history record with rolled_back_from_id = NULL.

---

## Phase 4: User Stories 1, 2 & 3 — History View, Download, Rollback (Priority: P1) 🎯 MVP

**Goal**: Admin can view the full paginated config history, download any historical config as a .cfg file, and roll back to a previous generation with a single button.

**Independent Test**:
1. Ensure at least 2 `config_generation_history` records with `applied=1` exist.
2. Navigate to `/admin/config/history.php`. Verify paginated table shows all records with date, actor, applied/success badges.
3. Click Download on any row — confirm a .cfg file downloads containing that row's `config_text`.
4. Click Roll Back to This on an older record — confirm: new history record appears with `rolled_back_from_id` set, `/etc/hblink3/hblink.cfg` matches that record's `config_text`, HBLink restarts successfully.

### Implementation

- [ ] T009 [US1] [US2] [US3] Create public/admin/config/history.php — replace the existing file entirely: role check (admin or system_admin for list; system_admin for rollback/download); dispatch on $_GET['action'] for download and running-config; dispatch on $_POST['action'] for rollback (POST with CSRF, PRG pattern); GET list view: call get_generation_history(20, $offset) with ?page=N pagination; render table with columns: # (id), date/time (generated_at), actor, applied badge, apply success/failure badge, changed badge, rolled_back_from indicator (↩ from #N linked to that row if rolled_back_from_id set); per-row buttons: Download (GET link), Roll Back to This (POST form with CSRF)
- [ ] T010 [US2] Add download action handler inside public/admin/config/history.php — GET ?action=download&id=NNN: system_admin role check, call get_config_generation_by_id($id) (404 redirect if null), send headers: Content-Type text/plain, Content-Disposition attachment filename=hblink-{YYYYMMDD-HHMMSS}.cfg using generated_at formatted as date, output config_text, exit
- [ ] T011 [US3] Add rollback action handler inside public/admin/config/history.php — POST ?action=rollback: system_admin role check, verify_csrf, intval $_POST['id'], call rollback_to_generation($id, $actor_id), PRG redirect to history.php with flash: success "Rolled back to config from [generated_at] — HBLink restarted at HH:MM:SS", failure "Rollback failed: [error detail]"
- [ ] T012 [US1] Add diff expand toggle to history list rows — for rows where changed=1: add a "Show Diff" toggle button that reveals a hidden div containing the diff_text rendered in a `<pre>` block with `+` lines colored green (var(--green)) and `-` lines colored red (var(--red)); implement as onclick toggle (no page reload, no AJAX)

**Checkpoint**: History page functional with pagination, download, rollback, and inline diff expand. Verify rolled_back_from_id is set correctly after rollback.

---

## Phase 5: User Story 4 — Current Running Config Snapshot (Priority: P2)

**Goal**: Admin can view the config currently on disk at `/etc/hblink3/hblink.cfg`, even if applied outside the portal.

**Independent Test**: Click "Show Running Config" button. Confirm the current file contents are displayed. Temporarily rename the file and reload — confirm an error message appears.

### Implementation

- [ ] T013 [US4] Add "Show Running Config" GET action and button to public/admin/config/history.php — GET ?action=running: system_admin role check, call get_running_hblink_config(); if null render error "Config file not found or could not be read at [HBLINK_CONFIG_PATH]"; if string render a modal overlay (CSS-only: hidden div with fixed overlay, close button) containing the config in a `<pre>` block with copy button; add "Show Running Config" button to the page header that links to ?action=running

**Checkpoint**: Running config displayed correctly; error shown when file unavailable.

---

## Phase 6: Navigation & Polish

- [ ] T014 Add "View History" link to public/admin/config/index.php aside panel — already has a "Generation History" link; update it to point to the new paginated history.php (it currently works but verify the link text and destination are correct after the rewrite)
- [ ] T015 Verify config_text is NOT in the list query — read the get_generation_history() SELECT in app/config/generator.php and confirm config_text column is absent from the list query (only fetched by get_config_generation_by_id())
- [ ] T016 Manual rollback validation — apply config A (generate + apply), then apply config B, then roll back to A; verify in DB: three history records where record 3 has rolled_back_from_id = record 1's id; verify /etc/hblink3/hblink.cfg content matches record 1's config_text

---

## Dependencies & Execution Order

- **T001 (Verify F13)**: Already done — F13 is implemented
- **Phase 2 (Migration 023)**: T002 must run before T003–T013
- **Phase 3 (PHP refactor)**: T003 → T004 sequential (extract then update caller); T005 depends on T003; T006, T007, T008 [P] with each other after T003
- **Phase 4 (UI)**: Depends on T003–T008 complete; T009 first (page scaffold), then T010, T011, T012 (all same file, add in order)
- **Phase 5 (US4)**: Depends on T008; add T013 as an additional action handler to history.php after T009
- **Phase 6 (Polish)**: After all above

### Parallel Opportunities

Within Phase 3 (after T003 is done):
- T006, T007, T008 [P] — independent new functions in generator.php

Within Phase 4:
- T010, T011, T012 can be added to history.php after T009 scaffolds the file; they do not depend on each other

---

## Parallel Example: Phase 3

```
T003 (extract apply_hblink_config_text) → T004 (update apply_hblink_config)
                                         → T005 (add rollback_to_generation)
                                         → T006 [P] (get_config_generation_by_id)
                                         → T007 [P] (update get_generation_history)
                                         → T008 [P] (get_running_hblink_config)
```

---

## Implementation Strategy

### MVP (US1 + US2 + US3)
1. T001: Already done (F13 verified)
2. T002: Migration 023
3. T003–T008: PHP layer refactor and new functions
4. **Verify refactor**: `apply_hblink_config()` still works after refactor
5. T009–T012: History page with pagination, download, rollback, diff expand
6. **Validate rollback end-to-end on live server**

### Full Feature
- T013: Running config viewer (US4)
- T014–T016: Polish and final validation
