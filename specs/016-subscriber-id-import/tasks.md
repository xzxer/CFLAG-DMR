# Tasks: F14 — Subscriber ID Import

**Input**: Design documents from `specs/016-subscriber-id-import/`

**Key facts from research**:
- CSV URL: `https://radioid.net/static/user.csv` (singular — the old `users.csv` is 404)
- Size: ~15.9 MB, ~306k records, updated daily at ~05:00 UTC
- `Last-Modified` header confirmed → conditional GET (If-Modified-Since) works
- Minimum 23-hour interval enforced in code; "Import Now" bypasses it, "Check for Updates" respects it

**Organization**: US1 (import) and US2 (last-heard display) are both P1. US1 provides the manager file used by everything else. US2 depends on the lookup functions from US1. US3 (overrides) and US4 (cron auto-update) are P2 and independent of each other.

## Format: `[ID] [P?] [Story] Description`

---

## Phase 1: Setup

- [ ] T001 Verify `allow_url_fopen` is enabled in PHP: `php -r "echo ini_get('allow_url_fopen');"` — must return 1; if not, document the workaround (curl_exec fallback) before proceeding
- [ ] T002 Verify RadioID.net CSV is reachable and returns Last-Modified header: `curl -sI "https://radioid.net/static/user.csv" | grep -i "last-modified\|content-length\|http/"` — confirm HTTP 200 and note Last-Modified value

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: Both migrations must run before any PHP code is written.

- [ ] T003 Write migrations/021_create_subscriber_ids.sql — CREATE TABLE subscriber_ids (radio_id INT UNSIGNED NOT NULL, callsign VARCHAR(16) NOT NULL, name VARCHAR(128) NOT NULL DEFAULT '', city VARCHAR(128) NOT NULL DEFAULT '', state VARCHAR(64) NOT NULL DEFAULT '', country VARCHAR(64) NOT NULL DEFAULT '', source ENUM('radioid','local') NOT NULL DEFAULT 'radioid', last_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (radio_id), INDEX idx_callsign (callsign), INDEX idx_source (source)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
- [ ] T004 Write migrations/022_subscriber_import_settings.sql — INSERT INTO system_settings (setting_key, setting_value) VALUES ('subscriber_last_import_at',''), ('subscriber_last_modified',''), ('subscriber_import_count','0'), ('subscriber_min_import_interval_hours','23') ON DUPLICATE KEY UPDATE setting_key = setting_key

**Checkpoint**: Both migrations applied — `DESCRIBE subscriber_ids` and `SELECT setting_key FROM system_settings WHERE setting_key LIKE 'subscriber_%'` must return expected results.

---

## Phase 3: User Story 1 — Admin Imports Subscriber Data (Priority: P1) 🎯 MVP

**Goal**: Admin can trigger a RadioID.net import from the admin panel and see import counts. Conditional GET prevents unnecessary 15.9 MB downloads. Minimum interval prevents abuse.

**Independent Test**:
1. Navigate to `/admin/subscribers/`. Click "Import Now". After completion, run `SELECT COUNT(*) FROM subscriber_ids` — expect ~306k rows. Page shows count, last import time, and Last-Modified value from RadioID.net.
2. Immediately click "Check for Updates" — expect "too soon" message (within 23h window).
3. Temporarily set `subscriber_last_modified` in system_settings to an old date — click "Check for Updates" — expect import to run again.

### Implementation

- [ ] T005 [US1] Create app/subscribers/manager.php — declare(strict_types=1); require database/connection.php; define the file structure with all function stubs
- [ ] T006 [US1] Implement `get_subscriber_import_stats(): array` in app/subscribers/manager.php — SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('subscriber_last_import_at','subscriber_last_modified','subscriber_import_count','subscriber_min_import_interval_hours'); return structured array with 'count', 'last_import_at', 'last_modified', 'min_interval_hours', 'next_import_at' (computed: last_import_at + min_interval_hours)
- [ ] T007 [US1] Implement `import_subscribers_from_radioid(bool $force = false): array` in app/subscribers/manager.php with these steps:
  (a) Check minimum interval: if !$force AND last_import_at + min_interval_hours > now, return ['status'=>'too_soon', 'next_at'=>...]
  (b) Build HTTP stream context: array('http'=>['method'=>'GET','header'=>"If-Modified-Since: {$last_modified}\r\nUser-Agent: CFLAG-DMR/1.0 (contact: admin@cflag.net)\r\n","ignore_errors"=>true])
  (c) Open stream with fopen(); read $http_response_header; if status line contains '304', return ['status'=>'not_modified']
  (d) If 200: parse new Last-Modified from $http_response_header
  (e) set_time_limit(300); fgetcsv() row loop: skip header row, batch INSERT...ON DUPLICATE KEY UPDATE every 500 rows; skip rows where source='local' by using WHERE clause: UPDATE only WHERE source != 'local'
  (f) On loop end: UPDATE system_settings for last_import_at, last_modified, import_count; return ['status'=>'ok','imported'=>int,'batches_failed'=>int,'error'=>null]
- [ ] T008 [US1] Create public/admin/subscribers/index.php — system_admin role check; GET: show import stats panel (record count, last import at, last modified, next allowed import at); "Import Now" POST form (force=true, CSRF); "Check for Updates" POST form (force=false, CSRF); POST handler: call import_subscribers_from_radioid($force), PRG redirect with flash message; show 'allow_url_fopen' warning if disabled; show cron setup instructions in a collapsed section with sample crontab line: `0 6 * * * www-data php /opt/cflag-dmr/scripts/import_subscribers.php >> /var/log/cflag-subscriber-import.log 2>&1`

**Checkpoint**: Admin import functional end-to-end. Verify DB row count, verify conditional GET returns 304 on second immediate import attempt.

---

## Phase 4: User Story 4 — Cron Auto-Update Script (Priority: P2)

**Goal**: System cron job can invoke the import script without web session. Script logs one line per run, exits 0 on success/no-change, exits 1 on error.

**Independent Test**: Run `php scripts/import_subscribers.php` from the CLI. Expect one log line to stdout showing status. Run immediately again within 23h — expect "too_soon" message and exit 0.

### Implementation

- [ ] T009 [US4] Create scripts/import_subscribers.php — bootstrap: define CFLAG_ROOT (dirname(__DIR__)), require app/config/env.php, app/database/connection.php, app/auth/roles.php (for log_audit_action if needed), app/subscribers/manager.php; call import_subscribers_from_radioid(false); output log line: "[{datetime}] status={status} imported={n} batches_failed={n}"; exit(0) on ok/not_modified/too_soon; exit(1) on error; check allow_url_fopen at top and exit(1) with error if disabled

**Checkpoint**: `php scripts/import_subscribers.php` runs cleanly from CLI as root and as www-data.

---

## Phase 5: User Story 2 — Last-Heard Shows Callsign and Name (Priority: P1)

**Goal**: Last-heard entries show callsign and name for known DMR IDs, with graceful fallback for unknown IDs.

**Independent Test**: After a full import, load `/admin/last-heard/` and `/last-heard/`. Confirm: (1) DMR IDs in subscriber_ids show callsign + name, (2) DMR IDs not in subscriber_ids show only the raw ID, (3) no PHP errors when subscriber_ids table is empty.

### Implementation

- [ ] T010 [US2] Implement `get_subscriber(int $dmr_id): array|null` in app/subscribers/manager.php — SELECT * FROM subscriber_ids WHERE radio_id = ?; return row array or null
- [ ] T011 [US2] Implement `get_subscribers_for_ids(array $dmr_ids): array` in app/subscribers/manager.php — if empty array, return []; build IN (?,?,?) placeholders; SELECT * FROM subscriber_ids WHERE radio_id IN (...); return [radio_id => record] keyed array for O(1) lookup in display loop
- [ ] T012 [US2] Locate the admin last-heard page (check public/admin/last-heard/index.php or public/admin/activity/) — add require_once for app/subscribers/manager.php; after fetching last-heard rows, collect all unique DMR IDs into an array; call get_subscribers_for_ids($dmr_ids); in the row render loop, look up each DMR ID in the subscriber map and display callsign + name alongside the ID if found; graceful fallback: show raw DMR ID if not found
- [ ] T013 [US2] Apply same subscriber lookup to the user-facing last-heard page (check public/last-heard/index.php or public/activity/) — same pattern as T012

**Checkpoint**: Last-heard shows callsigns for known IDs. No visual change for unknown IDs.

---

## Phase 6: User Story 3 — Admin Local Overrides (Priority: P2)

**Goal**: Admin can create/delete manual callsign+name overrides that survive bulk imports.

**Independent Test**: Create an override for a known DMR ID (one that exists in RadioID.net data) with a different callsign. Reload last-heard and confirm the override callsign is shown. Run import again and confirm the override survives. Delete the override and confirm the RadioID.net callsign reappears.

### Implementation

- [ ] T014 [US3] Implement `upsert_subscriber_override(int $radio_id, string $callsign, string $name): void` in app/subscribers/manager.php — INSERT INTO subscriber_ids (radio_id, callsign, name, source) VALUES (?,?,?,'local') ON DUPLICATE KEY UPDATE callsign=VALUES(callsign), name=VALUES(name), source='local'
- [ ] T015 [US3] Implement `delete_subscriber_override(int $radio_id): bool` in app/subscribers/manager.php — DELETE FROM subscriber_ids WHERE radio_id = ? AND source = 'local'; return true if rowCount() > 0
- [ ] T016 [US3] Implement `get_subscriber_overrides(int $limit = 50, int $offset = 0): array` in app/subscribers/manager.php — SELECT * FROM subscriber_ids WHERE source = 'local' ORDER BY radio_id LIMIT ? OFFSET ?
- [ ] T017 [US3] Add override management section to public/admin/subscribers/index.php — paginated table of local overrides (columns: DMR ID, callsign, name, delete button); add override form above table: DMR ID input (integer), callsign input (max 16 chars), name input (max 128 chars), submit button; POST handlers for 'add_override' (calls upsert_subscriber_override) and 'delete_override' (calls delete_subscriber_override); all forms have CSRF tokens; PRG pattern with flash messages

**Checkpoint**: Override created, visible in last-heard with overridden callsign, survives a re-import.

---

## Phase 7: Navigation & Polish

- [ ] T018 Add "Subscribers" link to admin sidebar navigation (check public/admin/ layout or header include)
- [ ] T019 Verify source='local' rows are never overwritten by import — review the ON DUPLICATE KEY UPDATE clause in T007 step (e): it must NOT update source column; rows where source='local' should retain their callsign/name after an import run over them
- [ ] T020 Verify import handles malformed CSV rows gracefully — add try/catch around each batch; on exception, log the error, increment batches_failed counter, and continue to the next batch rather than aborting the entire import

---

## Dependencies & Execution Order

- **Phase 1 (Setup)**: Verify prerequisites — do not proceed if allow_url_fopen=0
- **Phase 2 (Migrations)**: T003 → T004 (different files, can run in parallel); both must run before T005+
- **Phase 3 (US1)**: T005 → T006 → T007 → T008 (sequential, manager.php then UI)
- **Phase 4 (US4 cron)**: T009 depends on T007 being complete (calls the same function)
- **Phase 5 (US2)**: T010 → T011 (sequential, same file) then T012 and T013 [P] (different page files)
- **Phase 6 (US3)**: T014, T015, T016 [P] (independent functions) → T017 (UI depends on all three)
- **Phase 7 (Polish)**: Depends on all above

### Parallel Opportunities

```
T003 + T004 in parallel (different migration files)
T012 + T013 in parallel (different page files, both depend on T011)
T014 + T015 + T016 in parallel (independent functions in manager.php)
```

---

## Parallel Examples

```
Phase 2: T003 ║ T004
Phase 5: T012 ║ T013 (after T011)
Phase 6: T014 ║ T015 ║ T016 (before T017)
```

---

## Implementation Strategy

### MVP (US1 + US2 only)
1. Phase 1: Verify prerequisites
2. Phase 2: Migrations 021 + 022
3. Phase 3: manager.php import core + admin UI
4. Phase 5: Lookup functions + last-heard integration
5. **Validate**: Import ~306k rows, check last-heard shows callsigns

### Full Feature
- Phase 4: Cron script (US4)
- Phase 6: Overrides (US3)
- Phase 7: Nav + polish

### Cron Setup (after US4 complete)
```
0 6 * * * www-data php /opt/cflag-dmr/scripts/import_subscribers.php >> /var/log/cflag-subscriber-import.log 2>&1
```
