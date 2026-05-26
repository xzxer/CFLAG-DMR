# Tasks: F9 — Last-Heard & Activity Log

**Input**: Design documents from `specs/006-last-heard-activity-log/`

**Prerequisites**: plan.md ✅ | spec.md ✅ | research.md ✅ | data-model.md ✅ | quickstart.md ✅

**Tests**: Not requested. Manual verification via quickstart.md scenarios.

**Organization**: Tasks grouped by user story. Each story is independently implementable and testable.

---

## Phase 1: Setup

**Purpose**: Create directory, add CSS table styles, and place the test fixture file used by all quickstart scenarios.

- [x] T001 Create directory `app/lastheard/` (mkdir -p)
- [x] T002 Add `.lh-table-wrap`, `.lh-table`, `.lh-table th`, `.lh-table td`, `.lh-table .callsign` CSS rules to `public/assets/css/app.css` — `overflow-x: auto` on wrapper, dark background table matching existing app dark theme, callsign in `#60a5fa`
- [x] T003 Place test fixture at `/opt/HBMonv2/log/lastheard.log` with at least 25 valid CSV lines in the 12-field format (`datetime,duration,call_type,action,system_name,src_id,callsign,TSN,TGNNN,tg_name,sub_id,short_name`) so manual quickstart scenarios can be run before live traffic occurs

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration, service file, and `www-data` access — required by all user stories.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T004 Write `migrations/007_seed_lastheard_settings.sql` — INSERT 2 rows into `system_settings`: `lastheard_log_path` (`/opt/HBMonv2/log/lastheard.log`) and `public_lastheard_enabled` (`1`)
- [x] T005 Apply migration 007 to dev database: `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/007_seed_lastheard_settings.sql`
- [x] T006 Create `app/lastheard/reader.php` with `declare(strict_types=1)`:
  - `const LASTHEARD_ALLOWED_DIRS = ['/opt/HBMonv2/', '/var/log/hbmon/']`
  - `get_lastheard_setting(string $key): string` — PDO SELECT from `system_settings` using existing `app/database/` connection pattern
  - `validate_lastheard_path(string $path): string|false` — `realpath($path)` then prefix-check against `LASTHEARD_ALLOWED_DIRS`
  - `parse_lh_line(string $line): array|null` — `str_getcsv($line)` → 12-field check → strip "TS" prefix from `[7]`, strip "TG" prefix from `[8]` → validate `strtotime($row[0])` is not false → return assoc array with keys: `datetime, duration, call_type, action, system_name, src_id, callsign, timeslot, tgid, tg_name, sub_id, short_name`; return `null` on any parse failure
  - `load_lastheard(int $limit = 0, int $offset = 0, array $filters = []): array` — returns `['rows' => array, 'total' => int, 'error' => string|null]`; if file does not exist return `['rows'=>[], 'total'=>0, 'error'=>null]`; if file unreadable return error string; for `$limit <= 20` and no filters use `SplFileObject` tail read (newest-first, avoid loading full file); for larger/filtered reads use `file($path)` + `array_reverse()` + filter loop + `array_slice($offset, $limit)`; filter keys: `callsign` (case-insensitive substr of `$row['callsign']`), `tg` (numeric → exact match on `$row['tgid']`; text → `stripos($row['tg_name'])`), `date_from` / `date_to` (compare `strtotime($row['datetime'])` against `strtotime()` of filter value)

**Checkpoint**: Foundation ready — migration applied, `app/lastheard/reader.php` exists, `php -l` passes. User story implementation can begin.

---

## Phase 3: User Story 1 — Public Last-Heard Page (Priority: P1) 🎯 MVP

**Goal**: Any visitor can load `/last-heard.php` and see the 20 most recent transmissions. Auto-refreshes every 30 seconds. Redirects to login when `public_lastheard_enabled = 0`.

**Independent Test**: Load `/last-heard.php` logged out — confirm table with 20 rows, columns Callsign/TG Name/TG ID/Slot/System/Date-Time/Duration, "TS"/"TG" prefixes stripped, `<meta refresh>` present. Set `public_lastheard_enabled=0` in DB, reload — confirm redirect to `/login.php`.

- [x] T007 [US1] Create `public/last-heard.php` — `require_once` `app/auth/roles.php` and `app/lastheard/reader.php`; call `start_session()`; check `isset($_SESSION['user_id'])` for `$is_authed`; read `public_lastheard_enabled` via `get_lastheard_setting()`; if `!$is_authed && !$public_enabled` do `header('Location: /login.php'); exit`; call `load_lastheard(20)` for public view; render HTML page with title "Last Heard — CFLAG DMR", `<meta http-equiv="refresh" content="30">` in `<head>`, a `<div class="lh-table-wrap"><table class="lh-table">` with `<thead>` columns Date/Time, Callsign, DMR ID, TG Name, TG ID, Slot, System, Duration; for each row in `$result['rows']` render a `<tr>` with `htmlspecialchars()` on every cell value; display duration as `round((float)$row['duration']) . 's'`; show "No activity recorded yet" paragraph when `$result['rows']` is empty; show user-friendly error div when `$result['error']` is non-null (do NOT expose file path); add `<a href="/" class="nav-link">← Home</a>` navigation

**Checkpoint**: US1 functional — unauthenticated visitors see last 20 entries, auto-refresh works, redirect fires when disabled.

---

## Phase 4: User Story 2 — Authenticated Full History with Filters (Priority: P2)

**Goal**: Logged-in users see full history with callsign/talkgroup/date filters, 50-row pagination, and 30-second auto-refresh that preserves filter state.

**Independent Test**: Log in, load `/last-heard.php` — confirm >20 rows with pagination. Enter callsign filter — confirm URL updates and results narrow. Verify auto-refresh reloads the same URL (filter preserved). Navigate to page 2, wait 30s — confirm reload returns to same page.

- [x] T008 [US2] Extend `public/last-heard.php` — add authenticated branch after the `$is_authed` check: parse `$_GET` for `callsign`, `tg`, `date_from`, `date_to` (each trimmed, max 100 chars); parse `$_GET['page']` as `max(1, (int)$_GET['page'])`; compute `$offset = ($page - 1) * 50`; call `load_lastheard(50, $offset, array_filter($filters))`; render the same table but with all columns visible; add a `<form method="get">` filter bar above the table with text inputs for Callsign (name=`callsign`) and Talkgroup (name=`tg`) and date inputs for From (name=`date_from`) and To (name=`date_to`), plus a Submit button and a "Clear" link to `/last-heard.php`; populate inputs with current filter values via `htmlspecialchars()`; add pagination nav below the table showing Prev/Next links that append `page=N` and preserve all current filter GET params; replace `<meta http-equiv="refresh">` with `<script>setTimeout(() => location.reload(), 30000);</script>` when authenticated; add `<a href="/admin/" class="nav-link">← Dashboard</a>` nav when `$is_authed`

**Checkpoint**: US2 functional — full history visible, filters work, pagination works, auto-refresh preserves filter state in URL.

---

## Phase 5: User Story 3 — Admin Dashboard Last-Heard Card (Priority: P3)

**Goal**: System_admin dashboard shows last 5 transmissions in a compact card with a link to the full log.

**Independent Test**: Log in as system_admin, load `/admin/` — confirm Last Heard card with up to 5 rows (callsign, tg name, time) and a "View Full Log" link to `/last-heard.php`. Log in as non-system_admin admin — confirm card is absent.

- [x] T009 [US3] Update `public/admin/index.php` — add `require_once $root . '/app/lastheard/reader.php'`; after the `$hblink_status` assignment, add `$lh_result = user_has_role((int)$_SESSION['user_id'], 'system_admin') ? load_lastheard(5) : null`; after the HBLink status card section, add a new `<section class="card">` with eyebrow "Activity", heading "Last Heard", compact 3-column table (Callsign, Talkgroup, Time) showing `$lh_result['rows']`, "No activity recorded yet" when rows are empty, and `<a href="/last-heard.php" class="nav-link">View Full Log →</a>` — wrap the table in `<div class="lh-table-wrap">` for horizontal scroll; only render the card when `$lh_result !== null`

**Checkpoint**: US3 functional — dashboard shows live last-heard summary for system_admin; absent for other roles.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Documentation, navigation links, mobile verification, and full acceptance check.

- [x] T010 Update `docs/install-dependencies.md` — add note under Post-Install Configuration that `/opt/HBMonv2/log/` is already world-readable (`0755`/`0644`) so no `chown` is required for `www-data` to read `lastheard.log`; add note that if the log path is changed via `system_settings`, the new path must be readable by `www-data` and under one of the `LASTHEARD_ALLOWED_DIRS` (`/opt/HBMonv2/` or `/var/log/hbmon/`); add two checklist items: "lastheard.log path accessible by www-data" and "`public_lastheard_enabled` set to desired default"
- [x] T011 Update `specs/000-project-overview/spec.md` — change F8 status from "🔄 In Progress" to "✅ Complete" and F9 from "Not started" to "🔄 In Progress" in the Feature Delivery Status table
- [x] T012 Verify mobile responsiveness — confirm `.lh-table-wrap` has `overflow-x: auto` so wide tables scroll horizontally on 375px viewport; confirm filter form inputs are full-width on mobile; confirm buttons meet 44px touch target
- [x] T013 Run all quickstart.md test scenarios on the dev server — Scenarios 1.1–1.7 (US1), 2.1–2.6 (US2), 3.1–3.3 (US3), plus cross-cutting mobile/security checks

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1; T006 (`reader.php`) can begin once T001 (dir) is done
- **User Stories (Phase 3–5)**: All depend on Phase 2 (T006 reader.php + T005 migration applied)
  - US1 (T007) depends only on T006
  - US2 (T008) extends T007 — must follow US1
  - US3 (T009) depends only on T006
- **Polish (Phase 6)**: Depends on all desired user stories being complete

### User Story Dependencies

| Story | Depends on Foundational | Depends on other stories |
|-------|------------------------|--------------------------|
| US1 (P1) | T006 only | None |
| US2 (P2) | T006 only | Extends T007 (US1 page) |
| US3 (P3) | T006 only | None |

### Within US2 (sequential)
- T007 (base page) → T008 (add auth branch + filters + pagination to same file)

---

## Parallel Opportunities

### Phase 2 (after T001 done)
```
T004 (migration SQL)   ←→   T006 (app/lastheard/reader.php)
```
T005 (apply migration) must follow T004.

### Phase 3–5 (after Phase 2 complete)
```
T007–T008 (US1+US2 last-heard.php)   ←→   T009 (US3 dashboard card)
```
T008 must follow T007 (same file). T009 is independent.

### Phase 6 (after stories complete)
```
T010 (docs)   ←→   T011 (project overview)   ←→   T012 (mobile check)
```

---

## Implementation Strategy

### MVP (User Story 1 only)

1. Phase 1: Setup (T001–T003)
2. Phase 2: Foundational (T004–T006)
3. Phase 3: US1 public page (T007)
4. **STOP and VALIDATE**: Load `/last-heard.php` logged out, confirm 20 rows, meta refresh, redirect when disabled
5. Ship US1 — public visitors can see live DMR activity immediately

### Incremental Delivery

1. Setup + Foundational → service layer ready
2. US1 (public view) → **MVP**
3. US2 (auth + filters + pagination) → registered users get full history
4. US3 (dashboard card) → admin convenience
5. Polish → docs, mobile, acceptance check

---

## Notes

- `[P]` = parallelizable (different files, no cross-task dependencies)
- `[US1]`–`[US3]` = maps to user story in spec.md
- US2 (T008) extends the same file as US1 (T007) — sequential, not parallel
- The test fixture (T003) is critical: `lastheard.log` won't exist until first live transmission > 2s
- `LASTHEARD_ALLOWED_DIRS` is hardcoded — not DB-configurable — intentionally (same pattern as F8)
- Duration display: `round((float)$row['duration']) . 's'` — integer display, no decimal
- TG filter is dual-mode: numeric string → match `$row['tgid']`; text → `stripos($row['tg_name'])`
