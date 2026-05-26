# Quickstart: F9 — Last-Heard & Activity Log

**Manual verification scenarios for all three user stories.**

**Prerequisites**:
- Dev server running at `http://localhost` (or configured hostname)
- `system_admin` account available
- Migration 007 applied (`lastheard_log_path`, `public_lastheard_enabled` in system_settings)
- HBMonv2 running — or a test fixture file placed at the configured log path

**Test fixture** (place at `/opt/HBMonv2/log/lastheard.log` if no live traffic):
```
2026-05-25 14:00:01,5.3,GROUP VOICE,END,CFLAG-MASTER,3171001,W7ABC,TS1,TG91,Worldwide,3171001,W7ABC
2026-05-25 14:05:22,3.1,GROUP VOICE,END,CFLAG-MASTER,3171002,KD9XYZ,TS2,TG3100,North America,3171002,KD9XYZ
2026-05-25 14:10:45,8.7,GROUP VOICE,END,CFLAG-MASTER,3171003,VE3ABC,TS1,TG91,Worldwide,3171003,VE3ABC
2026-05-25 14:15:00,2.5,GROUP VOICE,END,CFLAG-MASTER,3171001,W7ABC,TS1,TG3,TAC 3,3171001,W7ABC
2026-05-25 14:20:11,4.0,GROUP VOICE,END,CFLAG-MASTER,3171004,N0CALL,TS2,TG91,Worldwide,3171004,N0CALL
```

---

## US1: Public Last-Heard Page

### Scenario 1.1 — Public view loads without login

1. Open a private/incognito browser window (no session)
2. Navigate to `http://localhost/last-heard.php`
3. **Expected**: Page loads (HTTP 200) with a table showing entries. No login prompt. Title "Last Heard" visible.

### Scenario 1.2 — Correct columns displayed

1. Load `/last-heard.php` (not logged in)
2. **Expected**: Table has columns: Callsign, TG Name, TG ID, Slot, System, Date/Time, Duration
3. **Expected**: Callsign shows `W7ABC` (not `3171001`); TG ID shows `91` (not `TG91`); Slot shows `1` (not `TS1`)
4. **Expected**: Duration shows a value in seconds (e.g., `5s`)

### Scenario 1.3 — Limited to 20 entries when not logged in

1. Add 25+ lines to the test fixture file
2. Load `/last-heard.php` not logged in
3. **Expected**: Table shows exactly 20 rows (the 20 most recent); no pagination controls visible

### Scenario 1.4 — Auto-refresh present

1. Load `/last-heard.php` not logged in
2. View page source
3. **Expected**: Contains `<meta http-equiv="refresh" content="30">` (or JS 30-second reload)

### Scenario 1.5 — Empty/missing log file

1. Rename `lastheard.log` to `lastheard.log.bak` (or point `lastheard_log_path` to a nonexistent path)
2. Load `/last-heard.php`
3. **Expected**: Page loads without PHP errors; shows "No activity recorded yet" message (or similar); no file path exposed

### Scenario 1.6 — Public view disabled redirects to login

1. Log in as system_admin
2. In MariaDB: `UPDATE system_settings SET value='0' WHERE key='public_lastheard_enabled';`
3. Open private/incognito window, navigate to `/last-heard.php`
4. **Expected**: Redirected to `/login.php`

### Scenario 1.7 — Malformed log line skipped

1. Add a malformed line to the fixture: `2026-05-25,broken,line` (only 3 fields)
2. Load `/last-heard.php`
3. **Expected**: Page loads normally; malformed line does not appear in the table; no PHP error

---

## US2: Authenticated Full History with Filters

### Scenario 2.1 — Authenticated view shows more rows and pagination

1. Add 60+ entries to the fixture file
2. Log in as any registered user (or system_admin)
3. Navigate to `/last-heard.php`
4. **Expected**: Shows 50 rows in the table; pagination controls visible (Next/Prev or page numbers); row count greater than public 20-entry limit

### Scenario 2.2 — Callsign filter works

1. Log in; navigate to `/last-heard.php`
2. Enter `W7ABC` in the Callsign filter field; submit
3. **Expected**: URL contains `callsign=W7ABC`; only rows with callsign `W7ABC` shown; other callsigns absent

### Scenario 2.3 — Talkgroup filter works

1. Log in; enter `91` in the TG filter field; submit
2. **Expected**: Only rows with TG ID `91` shown

### Scenario 2.4 — Date range filter works

1. Log in; enter date range `2026-05-25` to `2026-05-25` 
2. **Expected**: Only entries from that date shown; entries from other dates excluded

### Scenario 2.5 — Pagination preserves filters

1. Log in; enter a callsign filter; submit to get 60+ results
2. Click Next page
3. **Expected**: URL contains both `callsign=W7ABC` and `page=2`; filter still applied on page 2

### Scenario 2.6 — Auto-refresh on authenticated view

1. Log in; navigate to `/last-heard.php?callsign=W7ABC&page=2`
2. Wait 30 seconds
3. **Expected**: Page reloads to the same URL (same filter and page preserved)

---

## US3: Admin Dashboard Last-Heard Card

### Scenario 3.1 — Card appears on dashboard

1. Log in as system_admin
2. Navigate to `/admin/`
3. **Expected**: A "Last Heard" card appears on the dashboard showing up to 5 entries (callsign, talkgroup, time)
4. **Expected**: Card contains a link to `/last-heard.php`

### Scenario 3.2 — Non-system_admin does not see card

1. Log in as a user with `admin` role (not `system_admin`)
2. Navigate to `/admin/`
3. **Expected**: Last Heard card is absent from the dashboard (same as HBLink card behavior)

### Scenario 3.3 — Card shows "No activity" when log is empty

1. Log in as system_admin; point `lastheard_log_path` to a nonexistent file
2. Navigate to `/admin/`
3. **Expected**: Last Heard card shows "No activity recorded yet" (not a PHP error; not the file path)

---

## Cross-Cutting Checks

### Mobile viewport (375px)

1. Open browser DevTools; set viewport to 375px width
2. Load `/last-heard.php` (not logged in)
3. **Expected**: Table is horizontally scrollable (not overflowing the viewport); columns remain readable; no horizontal scrollbar on the page body itself

### Security: unauthenticated access blocked when disabled

Already covered in Scenario 1.6.

### Security: path traversal blocked

1. In DB: `UPDATE system_settings SET value='/etc/passwd' WHERE key='lastheard_log_path';`
2. Load `/last-heard.php`
3. **Expected**: Page shows error "Activity log is currently unavailable" (path `/etc/passwd` is outside `LASTHEARD_ALLOWED_DIRS` — blocked by `validate_lastheard_path()`)

### Permissions: www-data can read log file

```bash
sudo -u www-data cat /opt/HBMonv2/log/lastheard.log
```
**Expected**: Output shows log content (directory is 0755, file is 0644 — world-readable confirmed).
