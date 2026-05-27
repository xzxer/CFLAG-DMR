# Tasks: F14 — Subscriber ID Import

**Input**: Design documents from `specs/016-subscriber-id-import/`

**Organization**: US1 (import) and US2 (last-heard display) are both P1 and partially parallel — the `subscriber_ids` table is shared, but last-heard integration depends on the lookup functions being ready. US3 (overrides) is P2 and fully independent.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

**Purpose**: Verify RadioID.net CSV URL is accessible from the server.

- [ ] T001 Verify RadioID.net CSV is reachable from the server: `curl -I "https://radioid.net/static/users.csv"` — confirm HTTP 200 and note Content-Length for progress estimation

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database migrations that both US1 and US2 depend on.

**⚠️ CRITICAL**: Run both migrations before writing any PHP.

- [ ] T002 Write migration 021_create_subscriber_ids.sql in migrations/ — CREATE TABLE subscriber_ids (radio_id INT UNSIGNED PRIMARY KEY, callsign VARCHAR(16) NOT NULL, name VARCHAR(128) NOT NULL DEFAULT '', city VARCHAR(128) NOT NULL DEFAULT '', state VARCHAR(64) NOT NULL DEFAULT '', country VARCHAR(64) NOT NULL DEFAULT '', source ENUM('radioid','local') NOT NULL DEFAULT 'radioid', last_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_callsign (callsign))
- [ ] T003 Write migration 022_subscriber_import_settings.sql in migrations/ — INSERT INTO system_settings (setting_key, setting_value) VALUES ('subscriber_last_import_at', ''), ('subscriber_import_count', '0') — for tracking import metadata

**Checkpoint**: Schema ready — PHP layer and last-heard integration can proceed.

---

## Phase 3: User Story 1 — Admin Imports Subscriber Data (Priority: P1) 🎯 MVP

**Goal**: Admin can trigger a RadioID.net import from the admin panel and see import counts.

**Independent Test**: Navigate to `/admin/subscribers/`, click "Import from RadioID.net". After completion, verify `SELECT COUNT(*) FROM subscriber_ids` returns ~250k+ rows and the page shows the count and last import timestamp.

### Implementation

- [ ] T004 [US1] Create app/subscribers/manager.php — define file with `declare(strict_types=1)` and `require_once` for get_db()
- [ ] T005 [US1] Implement `import_subscribers_from_radioid(): array` in app/subscribers/manager.php — uses fopen() with HTTP wrapper to stream CSV, set_time_limit(300), fgetcsv() row by row, batch INSERT … ON DUPLICATE KEY UPDATE every 500 rows, skip rows where source='local', update system_settings for last_import_at and count, return ['imported' => int, 'error' => string|null]
- [ ] T006 [US1] Implement `get_subscriber_import_stats(): array` in app/subscribers/manager.php — returns ['count' => int, 'last_import_at' => string|null] from system_settings
- [ ] T007 [US1] Create public/admin/subscribers/index.php — system_admin role check, shows import stats (record count, last import time), "Import from RadioID.net" POST button with CSRF, calls import_subscribers_from_radioid() on POST, renders success/error flash, nav link added to admin sidebar

**Checkpoint**: Admin can import subscriber data. Verify count and timestamp update after import.

---

## Phase 4: User Story 2 — Last-Heard Shows Callsign and Name (Priority: P1)

**Goal**: Last-heard entries show callsign and name for known DMR IDs.

**Independent Test**: After completing an import (Phase 3), load `/last-heard` or `/admin/last-heard`. Confirm DMR IDs present in `subscriber_ids` show their callsign and name; DMR IDs not in the table show only the raw ID (no error).

### Implementation

- [ ] T008 [US2] Implement `get_subscriber(int $dmr_id): array|null` in app/subscribers/manager.php — SELECT from subscriber_ids WHERE radio_id = ? — returns record array or null
- [ ] T009 [US2] Implement `get_subscribers_for_ids(array $dmr_ids): array` in app/subscribers/manager.php — SELECT WHERE radio_id IN (?) for a batch of IDs, returns keyed array [radio_id => record] for efficient bulk lookup
- [ ] T010 [US2] Update public/admin/last-heard/index.php (or the relevant last-heard page) to call get_subscribers_for_ids() with all DMR IDs in the current page result, then display callsign and name alongside the DMR ID — graceful fallback to DMR ID alone if not found
- [ ] T011 [US2] Update the user-facing last-heard page (public/last-heard/index.php or equivalent) with the same subscriber lookup and display as T010

**Checkpoint**: Last-heard shows callsigns for all known DMR IDs. Verify with a known DMR ID in the subscriber_ids table.

---

## Phase 5: User Story 3 — Admin Local Override (Priority: P2)

**Goal**: Admin can create/delete manual callsign+name overrides that take precedence over RadioID.net data.

**Independent Test**: Create a local override for a known DMR ID with a different callsign. Reload last-heard and confirm the override callsign is shown instead of the RadioID.net value. Delete the override and confirm the original callsign reappears.

### Implementation

- [ ] T012 [US3] Implement `upsert_subscriber_override(int $radio_id, string $callsign, string $name): void` in app/subscribers/manager.php — INSERT … ON DUPLICATE KEY UPDATE with source='local'
- [ ] T013 [US3] Implement `delete_subscriber_override(int $radio_id): bool` in app/subscribers/manager.php — DELETE WHERE radio_id = ? AND source = 'local'
- [ ] T014 [US3] Implement `get_subscriber_overrides(int $limit = 50, int $offset = 0): array` in app/subscribers/manager.php — SELECT WHERE source='local' ORDER BY radio_id
- [ ] T015 [US3] Add override management section to public/admin/subscribers/index.php — paginated table of local overrides (DMR ID, callsign, name, delete button), add override form (DMR ID + callsign + name fields) with CSRF, POST handlers for add and delete actions

**Checkpoint**: Local overrides created, shown in last-heard, and deletable.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T016 Add "Subscribers" link to admin sidebar navigation
- [ ] T017 Verify import is protected from partial failure — if fgetcsv encounters a malformed row, log a warning and continue (don't abort the whole import)
- [ ] T018 Confirm source='local' rows are never overwritten by import — add an explicit integration check: create a local override, run import, verify it survives

---

## Dependencies & Execution Order

- **Phase 1 (Setup)**: No dependencies — verify RadioID URL immediately
- **Phase 2 (Migrations)**: T002 → T003 (independent, but both needed before Phase 3+)
- **Phase 3 (US1)**: Depends on T002; T004 → T005 → T006 → T007 (sequential, same manager file)
- **Phase 4 (US2)**: T008 and T009 depend on T002 (schema); T010 and T011 depend on T009
- **Phase 5 (US3)**: Fully independent after T002; T012-T014 [P] → T015

### Parallel Opportunities

- T002 and T003 (different SQL files)
- T005 (import function) and T008+T009 (lookup functions) can run in parallel — different functions in manager.php, no dependency on each other
- T010 and T011 (different page files, both depend on T009)
- T012, T013, T014 within US3 (different functions)

---

## Implementation Strategy

### MVP (US1 + US2)
1. Phase 1: Verify RadioID URL
2. Phase 2: Migrations 021 + 022
3. Phase 3: Import workflow + admin page
4. Phase 4: Last-heard integration
5. **Validate**: Import, then check last-heard shows callsigns

### Full Feature
- Add US3 (overrides) after US1+US2 validated
- Phase 6 polish last
