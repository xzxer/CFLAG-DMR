# Tasks: F16 — Backup & Rollback

**Input**: Design documents from `specs/018-backup-rollback/`

**Dependency**: F13 (Controlled Restart) MUST be fully implemented before starting F16. Migration 020 must exist and apply_hblink_config() must be functional.

**Organization**: US1 (history view), US2 (download), and US3 (rollback) are all P1 and tightly related — they share the same history.php page. US4 (running config snapshot) is P2 and independent.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup (Prerequisite Verification)

**Purpose**: Verify F13 is implemented before starting. F16 cannot function without it.

- [ ] T001 Verify F13 is complete: confirm apply_hblink_config() exists in app/config/generator.php, migration 020 has been run (check config_generation_history has applied, apply_success, backup_path columns), and at least one history record exists with applied=1

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration 023 adds rolled_back_from_id. Required before any rollback logic can be written.

**⚠️ CRITICAL**: Run migration before implementing T004+.

- [ ] T002 Write migration 023_add_rolled_back_from_id.sql in migrations/ — ALTER TABLE config_generation_history ADD COLUMN rolled_back_from_id INT UNSIGNED NULL DEFAULT NULL AFTER backup_path, ADD CONSTRAINT fk_cgh_rollback_from FOREIGN KEY (rolled_back_from_id) REFERENCES config_generation_history (id) ON DELETE SET NULL

**Checkpoint**: Schema ready for rollback tracking.

---

## Phase 3: PHP Layer Refactor (Blocking for US3)

**Purpose**: Refactor apply_hblink_config() into apply_hblink_config_text() so rollback can pass historical config_text directly. This is an internal refactor — existing behavior must be preserved.

- [ ] T003 Refactor app/config/generator.php: extract `apply_hblink_config_text(string $config_text, int $actor_id, ?int $rolled_back_from_id = null): array` — move all the backup/write/restart/audit logic from the existing apply_hblink_config() into this new function, adding `rolled_back_from_id` to the history INSERT
- [ ] T004 Update `apply_hblink_config(int $actor_id): array` in app/config/generator.php to call generate_hblink_config() then pass the resulting config_text to apply_hblink_config_text($config_text, $actor_id, null) — behavior must be identical to before the refactor
- [ ] T005 Add `rollback_to_generation(int $generation_id, int $actor_id): array` to app/config/generator.php — SELECT config_text FROM config_generation_history WHERE id=? (error if not found), call apply_hblink_config_text($config_text, $actor_id, $generation_id), return ['ok', 'error', 'new_generation_id']
- [ ] T006 [P] Add `get_config_generation_by_id(int $id): array|null` to app/config/generator.php — SELECT all columns including config_text WHERE id=? — used for download and diff-expand
- [ ] T007 [P] Update `get_config_generation_history(int $limit = 20, int $offset = 0): array` in app/config/generator.php — add $offset parameter, add rolled_back_from_id to SELECT, add subquery or JOIN to get the rolled_back source's generated_at for display
- [ ] T008 [P] Add `get_running_hblink_config(): string|null` to app/config/generator.php — file_get_contents(HBLINK_CONFIG_PATH), return null if file is unreadable or doesn't exist

**Checkpoint**: Verify apply_hblink_config() still works correctly after refactor — run an apply and confirm new history record has rolled_back_from_id = NULL.

---

## Phase 4: User Stories 1, 2 & 3 — History View, Download, Rollback (Priority: P1) 🎯 MVP

**Goal**: Admin can view the paginated config history, download any historical config, and roll back to a previous generation.

**Independent Test**: 
- Apply two different config states so there are at least 2 history records.
- Navigate to `/admin/config/history.php`. Confirm: paginated table shows all records with date, actor, applied/success badges, and rolled_back_from indicator.
- Click Download on any row. Confirm a .cfg file downloads with the correct config_text content.
- Click Roll Back to This on the older record. Confirm: (1) apply completes, (2) new history record appears with rolled_back_from_id set to the older record's ID, (3) /etc/hblink3/hblink.cfg contains the rolled-back config_text.

### Implementation

- [ ] T009 [US1] [US2] [US3] Create public/admin/config/history.php — role check (admin or system_admin); GET list view: call get_config_generation_history(20, $offset) with ?page=N pagination; render table with columns: date/time (generated_at), actor (user display name), applied badge, apply success/failure badge, changed badge, rolled_back_from indicator (↩ from #N linked to that row if rolled_back_from_id set); per-row buttons: Download, Roll Back to This
- [ ] T010 [US2] Add download action handler to public/admin/config/history.php — GET ?action=download&id=NNN: system_admin role check, call get_config_generation_by_id($id), send headers Content-Type: text/plain, Content-Disposition: attachment; filename=hblink-{YYYYMMDD-HHMMSS}.cfg (from generated_at), output config_text, exit
- [ ] T011 [US3] Add rollback action handler to public/admin/config/history.php — POST ?action=rollback: system_admin role check, CSRF verify, call rollback_to_generation($id, $actor_id), PRG redirect with flash message (success: "Rolled back to config from [generated_at] — HBLink restarted"; failure: error detail)
- [ ] T012 [US1] Add expand-diff toggle to each row in public/admin/config/history.php — if changed=1: add an expand button that shows diff_text in a hidden `<div>` (toggled by onclick); `+` lines styled green, `-` lines red using CSS; use a `<pre>` block inside the toggle div

**Checkpoint**: History page functional with pagination, download, rollback, and diff expand.

---

## Phase 5: User Story 4 — Current Running Config Snapshot (Priority: P2)

**Goal**: Admin can view the config currently on disk, even if it was applied outside the portal.

**Independent Test**: Click "Show Running Config" on the history page. Confirm the content of `/etc/hblink3/hblink.cfg` is displayed. Then rename the file and reload — confirm an error message is shown.

### Implementation

- [ ] T013 [US4] Add "Show Running Config" button + modal to public/admin/config/history.php — GET ?action=running: system_admin role check, call get_running_hblink_config(), if null show error "Config file not found or unreadable", if string render in a `<pre>`-based modal with a close button

**Checkpoint**: Running config readable and displayed correctly.

---

## Phase 6: Navigation & Polish

- [ ] T014 Add "View History" link from public/admin/config/index.php pointing to history.php
- [ ] T015 Verify config_text is NOT in the list query — confirm get_config_generation_history() SELECT does not include config_text column (list view performance)
- [ ] T016 Manual rollback test: apply config A, then apply config B, rollback to A — verify history table shows: (1) apply record for A, (2) apply record for B, (3) rollback record with rolled_back_from_id = A's ID; verify hblink.cfg matches A's config_text

---

## Dependencies & Execution Order

- **F13 must be complete** before starting T001
- **Phase 1 (Verify F13)**: Gate check — do not proceed if F13 is incomplete
- **Phase 2 (Migration 023)**: Must run before T003–T008
- **Phase 3 (PHP refactor)**: T003 → T004 (sequential: extract then update wrapper); T005 depends on T003; T006, T007, T008 [P] with each other
- **Phase 4 (UI)**: Depends on T003–T008; T009 → T010 → T011 → T012 (same file, implement in order)
- **Phase 5 (US4)**: Depends on T008; T013 can run after T009 (same file, append)
- **Phase 6 (Polish)**: Depends on all above

### Parallel Opportunities

Within Phase 3:
- T006 (get_config_generation_by_id), T007 (update get_config_generation_history), T008 (get_running_hblink_config) are [P] — independent functions, no mutual dependencies

---

## Implementation Strategy

### MVP (US1 + US2 + US3)
1. Verify F13 complete (Phase 1)
2. Migration 023 (Phase 2)
3. PHP layer refactor (Phase 3) — careful: preserve apply_hblink_config() behavior
4. History page with download + rollback (Phase 4)
5. **Validate rollback end-to-end on live server**

### Full Feature
- Add US4 (running config viewer) after MVP validated
- Navigation polish last
