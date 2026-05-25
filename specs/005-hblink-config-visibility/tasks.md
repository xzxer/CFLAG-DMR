# Tasks: F8 — HBLink Config Visibility

**Input**: Design documents from `specs/005-hblink-config-visibility/`

**Prerequisites**: plan.md ✅ | spec.md ✅ | research.md ✅ | data-model.md ✅ | quickstart.md ✅

**Tests**: Not requested. Manual verification via quickstart.md scenarios.

**Organization**: Tasks grouped by user story. Each story is independently implementable and testable.

---

## Phase 1: Setup

**Purpose**: Create directory structure and shared CSS styles needed across all stories.

- [x] T001 Create directories `public/admin/hblink/` and `app/hblink/` (mkdir -p)
- [x] T002 Add `.code-viewer`, `.code-viewer ol`, `.code-viewer li`, `.masked-value`, `.show-btn`, `.drift-warning`, `.status-badge` CSS rules to `public/assets/css/app.css`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Service layer and migration that all user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T003 Write `migrations/006_seed_hblink_settings.sql` — INSERT 4 rows into `system_settings`: `hblink_cfg_path` (`/opt/hblink3/hblink.cfg`), `hblink_rules_path` (`/opt/hblink3/rules.py`), `hblink_pid_path` (`/opt/hblink3/hblink.pid`), `hblink_process_name` (`hblink.py`)
- [x] T004 Apply migration 006 to dev database: `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/006_seed_hblink_settings.sql`
- [x] T005 [P] Create `app/hblink/reader.php` — `get_hblink_setting(string $key): string` reads a key from system_settings via PDO; `validate_hblink_path(string $path): string|false` resolves with realpath() and checks against `HBLINK_ALLOWED_DIRS` constant (`['/opt/hblink3/', '/etc/hblink/', '/opt/hblink/']`); `load_hblink_file(string $setting_key): array` returns `['content' => string|null, 'modified_at' => int|null, 'error' => string|null]`
- [x] T006 [P] Create `app/hblink/process.php` — `get_btime(): ?int` reads boot time from `/proc/stat`; `get_process_start_time(int $pid): ?int` reads field 21 from `/proc/{pid}/stat` and converts clock ticks to epoch; `resolve_pid(string $pid_path, string $process_name): ?int` tries PID file first then falls back to `pgrep -f` via `shell_exec()`; `format_uptime(int $seconds): string` returns human-readable string (e.g. "2 days, 4 hours"); `get_hblink_status(): array` returns `['running' => bool, 'pid' => int|null, 'started_at' => int|null, 'uptime_seconds' => int|null, 'cfg_modified_at' => int|null, 'config_drifted' => bool]`

**Checkpoint**: Foundation ready — service files exist, migration applied, user story implementation can begin.

---

## Phase 3: User Story 1 — View HBLink Configuration File (Priority: P1) 🎯 MVP

**Goal**: Admin can view the full contents of `hblink.cfg` in the browser with line numbers, passphrase masking, and a config-drift warning.

**Independent Test**: Load `/admin/hblink/config.php` — confirm file renders with line numbers, at least one `PASSPHRASE` value is masked, "Show" toggle reveals it without page reload, last-modified timestamp shown.

- [x] T007 [US1] Create `public/admin/hblink/config.php` — `require_once` `app/auth/roles.php` and `app/hblink/reader.php`; call `require_role('system_admin')`; call `load_hblink_file('hblink_cfg_path')`; render page with `<div class="code-viewer"><ol>` where each `<li>` is one line of file content (htmlspecialchars'd); show last-modified timestamp formatted as `date('Y-m-d H:i:s', $modified_at)`; show user-friendly error message div if `$result['error']` is set
- [x] T008 [US1] Add passphrase masking to `public/admin/hblink/config.php` — in the line-rendering loop, detect lines matching `preg_match('/^\s*([^;#][^=]*)\s*=\s*(.*)/i', $line, $m)` where key matches `/PASSPHRASE|PASSWORD|SECRET/i`; replace value span with `<span class="masked-value" data-val="...">••••••••</span> <button class="btn btn-sm btn-secondary show-btn" onclick="toggleMask(this)">Show</button>`; add inline `<script>` with `toggleMask(btn)` function that reads `data-val` from the sibling span and swaps display/text
- [x] T009 [US1] Add config-drift warning banner to `public/admin/hblink/config.php` — `require_once app/hblink/process.php`; call `get_hblink_status()`; if `$status['config_drifted']` is true, render `<div class="drift-warning">` before the code block stating "Config file has been modified since HBLink was last started"

**Checkpoint**: US1 fully functional — load config page, see masked passphrases, toggle them, see drift warning if applicable.

---

## Phase 4: User Story 2 — View HBLink Rules File (Priority: P2)

**Goal**: Admin can view the full contents of `rules.py` in the browser with line numbers and last-modified timestamp.

**Independent Test**: Load `/admin/hblink/rules.php` — confirm file renders with line numbers and last-modified timestamp. No masking applied.

- [x] T010 [US2] Create `public/admin/hblink/rules.php` — `require_once` `app/auth/roles.php` and `app/hblink/reader.php`; call `require_role('system_admin')`; call `load_hblink_file('hblink_rules_path')`; render page with `<div class="code-viewer"><ol>` line-numbered block (htmlspecialchars on each line); show last-modified timestamp; show user-friendly error if file not found/unreadable; no masking needed

**Checkpoint**: US2 functional — rules.py viewable with line numbers in browser.

---

## Phase 5: User Story 3 — Check HBLink Process Status (Priority: P3)

**Goal**: Admin can see whether HBLink is running, its PID, uptime, and whether the config has drifted.

**Independent Test**: Load `/admin/hblink/status.php` — confirm Running/Stopped indicator, PID and uptime visible when running, config-drift warning shown when applicable.

- [x] T011 [US3] Create `public/admin/hblink/status.php` — `require_once` `app/auth/roles.php` and `app/hblink/process.php`; call `require_role('system_admin')`; call `get_hblink_status()`; render "Running" (green badge) or "Stopped" (red badge) based on `$status['running']`; when running show PID in field-row, show `format_uptime($status['uptime_seconds'])` in field-row; show config-drift warning banner if `$status['config_drifted']`; when stopped omit PID and uptime field-rows entirely

**Checkpoint**: US3 functional — status page shows accurate process state without SSH access.

---

## Phase 6: User Story 4 — Admin Dashboard HBLink Status Card (Priority: P4)

**Goal**: The admin dashboard shows a compact HBLink status card so admins get at-a-glance health without navigating away.

**Independent Test**: Load `/admin/` — confirm HBLink card shows running/stopped state and links to config, rules, and status pages.

- [x] T012 [US4] Update `public/admin/index.php` — `require_once app/hblink/process.php`; call `get_hblink_status()`; add an HBLink status card `<section>` to the dashboard below the welcome message showing: "HBLink" heading, Running/Stopped badge, uptime when running, config-drift warning when applicable, and three `<a class="nav-link">` links to `/admin/hblink/config.php`, `/admin/hblink/rules.php`, `/admin/hblink/status.php`

**Checkpoint**: US4 functional — dashboard shows live HBLink state without extra page load.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Navigation, mobile verification, documentation, and final acceptance checks.

- [x] T013 Add HBLink navigation back-links to `public/admin/hblink/config.php`, `rules.php`, and `status.php` — each page includes `<a href="/admin/" class="nav-link">← Dashboard</a>` and sibling links to the other two HBLink pages so admins can navigate between them without going back to the dashboard
- [x] T014 Verify mobile responsiveness on all three HBLink pages — confirm `.code-viewer` has `overflow-x: auto` so long lines scroll horizontally without breaking layout, confirm Show/Hide buttons are ≥ 44px tall (btn-sm already meets this from existing CSS), test at 375px viewport width in browser DevTools
- [x] T015 Update `docs/install-dependencies.md` — add a note under Post-Install Configuration that `www-data` must have read access to `hblink.cfg` and `rules.py`: `chown root:www-data /opt/hblink3/hblink.cfg /opt/hblink3/rules.py && chmod 644 /opt/hblink3/hblink.cfg /opt/hblink3/rules.py`
- [x] T016 Run all 13 quickstart.md test scenarios on the dev server and confirm each passes — check Scenarios 1.1–1.7 (US1), 2.1–2.2 (US2), 3.1–3.3 (US3), 4.1–4.3 (US4)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — T005 and T006 can run in parallel after T003/T004
- **User Stories (Phase 3–6)**: All depend on Phase 2 completion
  - US1 (T007–T009) depends on both T005 (reader.php) and T006 (process.php) for drift detection
  - US2 (T010) depends only on T005 (reader.php)
  - US3 (T011) depends only on T006 (process.php)
  - US4 (T012) depends only on T006 (process.php)
- **Polish (Phase 7)**: Depends on all desired user stories being complete

### User Story Dependencies

| Story | Depends on Foundational | Depends on other stories |
|-------|------------------------|--------------------------|
| US1 (P1) | T005 + T006 | None |
| US2 (P2) | T005 only | None |
| US3 (P3) | T006 only | None |
| US4 (P4) | T006 only | None (links to pages but doesn't require them to exist) |

### Within US1 (sequential)
- T007 → T008 → T009 (masking and drift warning build on the base page)

---

## Parallel Opportunities

### Phase 2 (after T004 applied)
```
T005 (app/hblink/reader.php)   ←→   T006 (app/hblink/process.php)
```

### Phase 3–6 (after Phase 2 complete)
```
T007–T009 (US1 config.php)
T010      (US2 rules.php)      ← all four stories can proceed in parallel
T011      (US3 status.php)
T012      (US4 index.php card)
```

### Phase 7 (after stories complete)
```
T013 (nav links)   ←→   T014 (mobile check)   ←→   T015 (docs update)
```

---

## Implementation Strategy

### MVP (User Story 1 only)

1. Phase 1: Setup (T001–T002)
2. Phase 2: Foundational (T003–T006)
3. Phase 3: US1 config viewer (T007–T009)
4. **STOP and VALIDATE**: Load config.php, confirm masking + drift warning work
5. Ship US1 — delivers immediate value (admin can see live config in browser)

### Incremental Delivery

1. Setup + Foundational → service layer ready
2. US1 (config viewer) → **MVP** — core value delivered
3. US2 (rules viewer) → rules.py visible in browser
4. US3 (status page) → process health visible
5. US4 (dashboard card) → at-a-glance status on every admin login
6. Polish → navigation, mobile, docs, acceptance check

---

## Notes

- `[P]` = different files, no cross-task dependencies — safe to work in parallel
- `[US1]`–`[US4]` = maps to user story in spec.md
- No tests are generated (not requested in spec)
- US1–US4 each have a clear independent test in quickstart.md
- Masking is a UI convenience (spec explicitly notes: system_admin already has full server access via dev tools)
- The `HBLINK_ALLOWED_DIRS` constant in reader.php is hardcoded — not a system_settings value — intentionally
