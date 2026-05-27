# Tasks: F13 — Controlled Restart / Reload

**Input**: Design documents from `specs/015-controlled-restart/`

**Organization**: Tasks grouped by user story. US3 (status indicator) is P1 and independent — implement it before US1 (apply pipeline) since the status chip is needed by the apply UI.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup (Server Prerequisites)

**Purpose**: Manual deployment steps required before code can run. Document these as a deployment note but complete them before testing.

- [ ] T001 Create /etc/hblink3/backups/ directory owned by www-data: `mkdir -p /etc/hblink3/backups && chown www-data:www-data /etc/hblink3/backups`
- [ ] T002 Add sudoers rule allowing www-data to run `docker compose -f /etc/hblink3/docker-compose.yml restart hblink` without password — add to /etc/sudoers.d/cflag-hblink

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema migration that must exist before any PHP code is written.

**⚠️ CRITICAL**: Run this migration before implementing any Phase 3+ tasks.

- [ ] T003 Write migration 020_extend_config_generation_history.sql in migrations/ — add columns: applied TINYINT(1) DEFAULT 0, applied_at DATETIME NULL, apply_success TINYINT(1) NULL, apply_error TEXT NULL, backup_path VARCHAR(512) NULL

**Checkpoint**: Migration applied — generator.php additions can now begin.

---

## Phase 3: User Story 3 — Restart Status Indicator (Priority: P1)

**Goal**: Admin can see whether HBLink is currently running or stopped on the Network Config page.

**Independent Test**: Load `/admin/config/` and verify a green or red status chip appears showing the Docker container state. The chip must reflect actual container state (run `docker stop hblink` and reload to confirm it turns red).

### Implementation

- [ ] T004 [US3] Add `get_hblink_status(): string` to app/config/generator.php — runs `docker inspect --format='{{.State.Status}}' hblink` via shell_exec(), returns 'running', 'stopped', or 'unknown'
- [ ] T005 [US3] Add HBLink status chip to public/admin/config/index.php — call get_hblink_status() at page load, render a colored badge (green=running, red=stopped/unknown) in the page header near the Generate button

**Checkpoint**: Status indicator visible and accurate on the Network Config page.

---

## Phase 4: User Story 1 — Apply and Restart (Priority: P1) 🎯 MVP

**Goal**: Admin clicks "Apply & Restart" and the DB config is safely written to disk and HBLink restarts, with a full audit trail.

**Independent Test**: Click Apply & Restart on `/admin/config/`. Verify: (1) `/etc/hblink3/hblink.cfg` is updated, (2) a backup file exists in `/etc/hblink3/backups/`, (3) a new row in `config_generation_history` has `applied=1` and `apply_success=1`, (4) HBLink container restarts (check `docker ps` or the status chip turning red then green).

### Implementation

- [ ] T006 [US1] Add filesystem constants to app/config/generator.php: HBLINK_CONFIG_PATH, HBLINK_COMPOSE_FILE, HBLINK_CONTAINER, HBLINK_BACKUP_DIR, HBLINK_BACKUP_KEEP
- [ ] T007 [US1] Implement `apply_hblink_config(int $actor_id): array` in app/config/generator.php — orchestrates: generate_hblink_config() → validate (non-empty, required sections) → backup current file with timestamp → atomic write (temp file + rename) → shell_exec docker restart → poll container status up to 10s → update config_generation_history (applied, applied_at, apply_success, apply_error, backup_path) → write audit_log → return ['ok', 'error', 'generation_id']
- [ ] T008 [US1] Add backup rotation helper inside app/config/generator.php — after writing backup, delete oldest backups if count exceeds HBLINK_BACKUP_KEEP
- [ ] T009 [US1] Extend `get_config_generation_history(int $limit = 20): array` in app/config/generator.php to SELECT the new applied/apply_success/applied_at/apply_error columns
- [ ] T010 [US1] Add "Apply & Restart" POST form to public/admin/config/index.php — CSRF token, system_admin role check, calls apply_hblink_config($actor_id), redirects with flash message on success/failure
- [ ] T011 [US1] Render flash messages for apply result on public/admin/config/index.php — success (green): "Config applied and HBLink restarted at HH:MM:SS"; failure (red): error message from apply_hblink_config()

**Checkpoint**: Apply & Restart workflow fully functional and audited.

---

## Phase 5: User Story 2 — Config Diff Preview (Priority: P2)

**Goal**: Admin can preview what will change before clicking Apply.

**Independent Test**: On `/admin/config/`, click "Preview Changes". If config has changed since last apply, a unified diff is shown. If unchanged, "No changes pending" is shown.

### Implementation

- [ ] T012 [US2] Add "Preview Changes" section to public/admin/config/index.php — button triggers a GET to the same page with `?preview=1`; server-side: generate config in-memory (no write), compare to last applied config_text using similar_text diff or PHP's native diff; render in a `<pre>` block with CSS classes for `+` lines (green) and `-` lines (red); if no diff, show "No changes pending" message

**Checkpoint**: Diff preview visible before committing an apply.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T013 Update public/admin/config/history.php to show apply status columns — add applied badge (Applied/Not applied), apply success badge (Success/Failed), applied_at timestamp, and apply_error text (expandable on click) to the history table
- [ ] T014 Manual end-to-end validation on dev server — apply a config change, verify backup created, verify history record complete, verify HBLink restarts; also test: invalid config (empty config_text) aborts without writing file

---

## Dependencies & Execution Order

- **Phase 1 (Setup)**: Manual deployment steps — complete before any testing
- **Phase 2 (Migration 020)**: Must run before T004–T013
- **Phase 3 (US3)**: Independent of US1 — can implement status indicator first (simpler)
- **Phase 4 (US1)**: Depends on T003 (migration) and T006 (constants); T007–T011 are sequential within US1
- **Phase 5 (US2)**: Depends on Phase 4 being functional; diff preview uses the same generate path
- **Phase 6 (Polish)**: Depends on Phase 4; T013 depends on T009

### Within Phase 4

- T006 → T007 → T008 (backup rotation is part of apply, implement together)
- T009 can run in parallel with T007 (different functions, same file)
- T010 → T011 (form before flash messages)

---

## Parallel Example: Phase 4 (US1)

```
T006 + T009 can run in parallel (different functions in generator.php)
T007 + T008 together (backup rotation part of apply)
T010 → T011 sequential
```

---

## Implementation Strategy

### MVP (US3 + US1)
1. Phase 1: Server setup
2. Phase 2: Migration 020
3. Phase 3: Status indicator (quick win, needed by apply UI)
4. Phase 4: Apply pipeline
5. **Validate on live server**

### Full Feature
- Add US2 (diff preview) after US1 is confirmed working
- Add T013 (history page polish) last
