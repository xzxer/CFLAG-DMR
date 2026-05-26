# Feature Specification: F9 — Last-Heard & Activity Log

**Feature Branch**: `006-lastheard-log`

**Created**: 2026-05-25

**Status**: Draft

---

## Overview

Registered users and the public can view a live feed of recent DMR transmission activity on the network — who was on the air, on which talkgroup, when, and for how long. The feed is read directly from the activity log written by the running network monitor service. No editing. No account data exposed.

---

## User Scenarios & Testing

### User Story 1 — Public Last-Heard View (Priority: P1)

A visitor arrives at the network's last-heard page without logging in. They see the 20 most recent transmissions on the network: callsign, talkgroup name, slot, system, time, and duration. The page refreshes automatically every 30 seconds so the visitor always sees current activity without reloading manually.

If the network administrator has disabled the public last-heard feature, the visitor is redirected to the login page instead.

**Why this priority**: Highest public visibility feature. DMR callsigns are publicly licensed information. This is standard practice for DMR networks and provides immediate value to visitors evaluating whether to join.

**Independent Test**: Navigate to `/last-heard.php` without logging in — confirm the last 20 transmissions appear in a table with callsign, talkgroup, slot, system, time, and duration. Wait 30 seconds and confirm the page refreshes. Log in as system_admin, set `public_lastheard_enabled = false`, return to the page logged out — confirm redirect to login.

**Acceptance Scenarios**:

1. **Given** `public_lastheard_enabled = true` and I am not logged in, **When** I navigate to `/last-heard.php`, **Then** I see a table of the 20 most recent transmissions with columns: callsign, talkgroup, slot, system, time, duration — and no login prompt.
2. **Given** the page is open and 30 seconds pass, **When** the auto-refresh fires, **Then** the table updates with the latest entries without a full page reload (or with a transparent reload).
3. **Given** `public_lastheard_enabled = false` and I am not logged in, **When** I navigate to `/last-heard.php`, **Then** I am redirected to `/login.php`.
4. **Given** the log file is empty or contains no valid entries, **When** I load the page, **Then** I see a "No activity recorded yet" message instead of an empty table.

---

### User Story 2 — Authenticated Full History with Filters (Priority: P2)

A logged-in user navigates to the same last-heard page and sees the full transmission history rather than the 20-entry public limit. They can filter by callsign, talkgroup name or ID, and date range. Results paginate at 50 rows per page. The first page auto-refreshes every 30 seconds.

**Why this priority**: Registered users need to track network activity for their own callsign and talkgroup subscriptions. The filter capability is essential for finding specific sessions in a large log.

**Independent Test**: Log in as a registered user, navigate to `/last-heard.php` — confirm the full history is shown with pagination controls. Enter a callsign in the filter field, submit — confirm results narrow to that callsign only. Enter a date range — confirm results respect the range. Verify 50 rows per page with next/prev controls.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I navigate to `/last-heard.php`, **Then** I see the full history (all available log entries) with pagination showing 50 rows per page, not limited to 20.
2. **Given** I am on the last-heard page, **When** I enter a callsign filter and submit, **Then** only entries matching that callsign are shown.
3. **Given** I am on the last-heard page, **When** I enter a date range filter and submit, **Then** only entries within that date range are shown.
4. **Given** I am on the last-heard page, **When** I enter a talkgroup name or ID filter and submit, **Then** only entries for that talkgroup are shown.
5. **Given** results span multiple pages, **When** I click Next, **Then** I see the next 50 entries in the same sort order.

---

### User Story 3 — Admin Dashboard Last-Heard Card (Priority: P3)

The admin dashboard shows a compact card with the 5 most recent transmissions, so system admins can see live network activity at a glance without navigating away. The card links to the full last-heard page.

**Why this priority**: Low effort for high admin convenience. Follows the same pattern as the HBLink status card already on the dashboard.

**Independent Test**: Log in as system_admin, load `/admin/` — confirm a Last Heard card appears showing up to 5 recent entries and a link to `/last-heard.php`.

**Acceptance Scenarios**:

1. **Given** I am logged in as system_admin and HBMonv2 has recorded activity, **When** I load the admin dashboard, **Then** a "Last Heard" card shows the 5 most recent transmissions (callsign, talkgroup, time) and a link to `/last-heard.php`.
2. **Given** no activity has been logged yet, **When** I load the dashboard, **Then** the card shows "No activity recorded yet" rather than an empty table.

---

### Edge Cases

- What happens when the log file does not exist or is not readable? → Show a user-friendly error message ("Activity log is currently unavailable."); do not expose the file path or system error.
- What happens when a log line has fewer fields than expected (malformed CSV)? → Skip that line silently; do not crash the page.
- What happens when the log file is very large (thousands of entries)? → For the public view, read only the last N lines rather than the whole file. For the authenticated view, apply pagination so the browser is never sent unbounded data.
- What if `public_lastheard_enabled` is not set in system_settings? → Default to **enabled** (on). DMR activity is public by nature.
- What if the filter inputs contain special characters? → All filter values are sanitized before comparison; no injection possible.

---

## Requirements

### Functional Requirements

- **FR-001**: The system MUST display the 20 most recent transmissions to unauthenticated visitors when `public_lastheard_enabled` is true.
- **FR-002**: The system MUST redirect unauthenticated visitors to the login page when `public_lastheard_enabled` is false.
- **FR-003**: The page MUST auto-refresh every 30 seconds to show current activity.
- **FR-004**: The system MUST display the full transmission history to authenticated users (any role), paginated at 50 rows per page.
- **FR-005**: Authenticated users MUST be able to filter results by callsign, talkgroup (name or numeric ID), and date range.
- **FR-006**: Each transmission entry MUST show: callsign, DMR ID, talkgroup name, talkgroup ID, slot, system name, date/time, and duration in seconds.
- **FR-007**: The system MUST read activity data from the HBMonv2 log file. The file path MUST be configurable via a `system_settings` key (`lastheard_log_path`).
- **FR-008**: The log file path MUST be validated against an allowlist of permitted base directories before use, to prevent path traversal.
- **FR-009**: The admin dashboard MUST display a Last Heard card showing the 5 most recent transmissions (system_admin only) with a link to the full page.
- **FR-010**: The system MUST display a user-friendly message when the log file is unavailable or contains no valid entries — no raw error messages or file paths exposed.
- **FR-011**: Malformed log lines (wrong field count, unparseable date) MUST be skipped silently without breaking the page.
- **FR-012**: For large log files, the public view MUST read only the tail of the file rather than loading it entirely into memory.

### Key Entities

- **Transmission Entry**: A single DMR call session parsed from one log line. Fields: datetime, callsign, dmr_id, tg_id, tg_name, slot, system_name, duration_seconds. No persistent storage for MVP — read from log file on demand.
- **Log File**: The HBMonv2 CSV activity log. Source of truth for all transmission data in this feature. Path stored in `system_settings.lastheard_log_path`.
- **System Setting — `public_lastheard_enabled`**: Boolean flag controlling unauthenticated access to the last-heard page. Default: true.
- **System Setting — `lastheard_log_path`**: Absolute path to the HBMonv2 lastheard.log file. Default: `/opt/HBMonv2/log/lastheard.log`.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: A visitor can load the last-heard page and see current network activity within 3 seconds on a standard connection.
- **SC-002**: The page reflects activity from the last 30 seconds after each auto-refresh cycle — visitors never see data more than 60 seconds stale.
- **SC-003**: A logged-in user can filter 10,000+ log entries by callsign and receive paginated results without the page timing out or becoming unresponsive.
- **SC-004**: All three user stories (public view, authenticated filtered view, dashboard card) are independently functional and testable.
- **SC-005**: When the log file is unavailable, users see a clear message and the rest of the site continues to function normally.

---

## Assumptions

- HBMonv2 is already running and writing to its log file at the configured path. This feature only reads the log — it does not manage or restart the monitor service.
- The CSV log format is stable: `datetime, callsign, dmrid, tgid, tgname, slot, system, duration`. If the format changes, parsing must be updated.
- Callsigns and talkgroup IDs are public DMR metadata. No privacy masking is applied in either the public or authenticated views.
- `www-data` (the web server process) has read access to the log file. This is a deployment prerequisite documented in install-dependencies.md.
- The auto-refresh interval of 30 seconds is acceptable latency for both public visitors and logged-in users. Real-time push (sub-second) is out of scope.
- Client-side column sorting is sufficient for MVP. Server-side sort is not required.
- The log file is written in server local time. Timestamps are displayed as-is (no timezone conversion) for MVP.
- No database table is seeded by this feature beyond the two new `system_settings` keys.
