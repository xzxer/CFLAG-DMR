# Feature Specification: F8 — HBLink Config Visibility

**Feature Branch**: `005-hblink-config-view`

**Created**: 2026-05-25

**Status**: Draft

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — View HBLink Configuration File (Priority: P1)

A system admin navigates to the HBLink config viewer and sees the full contents of `hblink.cfg` rendered in a readable, line-numbered code block. Sensitive values (passphrases, passwords, secrets) are masked by default and can be revealed individually with a click. A warning banner appears if the file has been modified since HBLink was last started.

**Why this priority**: This is the core visibility need — admins currently must SSH in to check the running config. Even alone, this page delivers immediate value.

**Independent Test**: Load `/admin/hblink/config.php`, confirm file content renders with line numbers, confirm at least one passphrase field shows `••••••••` by default and reveals on click, confirm last-modified timestamp is shown.

**Acceptance Scenarios**:

1. **Given** the admin is logged in as system_admin, **When** they navigate to `/admin/hblink/config.php`, **Then** the full content of `hblink.cfg` is displayed in a styled, line-numbered, read-only code block.
2. **Given** `hblink.cfg` contains a `PASSPHRASE` key, **When** the page loads, **Then** its value is shown as `••••••••` and a "Show" toggle is visible next to it.
3. **Given** the admin clicks "Show" on a masked value, **When** the toggle is clicked, **Then** the actual value replaces the mask without a page reload.
4. **Given** `hblink.cfg` was modified after HBLink started, **When** the page loads, **Then** a visible warning banner states the config has been changed since the last start.
5. **Given** the config file does not exist at the configured path, **When** the page loads, **Then** an error message is shown explaining the file was not found — no raw PHP error is exposed.

---

### User Story 2 — View HBLink Rules File (Priority: P2)

A system admin views the current `rules.py` routing file in the browser to understand the active talkgroup bridging rules without SSH access.

**Why this priority**: Rules are frequently referenced when debugging routing issues, but contain no sensitive values and require no masking.

**Independent Test**: Load `/admin/hblink/rules.php`, confirm file content renders with line numbers and last-modified timestamp.

**Acceptance Scenarios**:

1. **Given** the admin navigates to `/admin/hblink/rules.php`, **When** the page loads, **Then** the full content of `rules.py` is displayed in a line-numbered code block with last-modified timestamp.
2. **Given** `rules.py` does not exist at the configured path, **When** the page loads, **Then** a clear error message is shown.

---

### User Story 3 — Check HBLink Process Status (Priority: P3)

A system admin opens the status page to confirm whether HBLink is running, see the PID and uptime, and check whether the config has drifted from what HBLink loaded at startup.

**Why this priority**: Completes the visibility picture — admins need to know if the running process reflects the file they just viewed.

**Independent Test**: Load `/admin/hblink/status.php`, confirm running/stopped indicator, PID, uptime, and config-drift warning are all displayed correctly.

**Acceptance Scenarios**:

1. **Given** HBLink is running, **When** the admin views `/admin/hblink/status.php`, **Then** a "Running" indicator, PID, and human-readable uptime (e.g., "2 days, 4 hours") are displayed.
2. **Given** HBLink is not running, **When** the admin views the status page, **Then** a "Stopped" indicator is shown and PID/uptime fields are absent.
3. **Given** `hblink.cfg` was modified after HBLink started, **When** the status page loads, **Then** a "Config modified since start" warning is shown.

---

### User Story 4 — Admin Dashboard HBLink Status Card (Priority: P4)

The admin dashboard shows a compact HBLink status card so admins get an at-a-glance health check without navigating away.

**Why this priority**: Convenience — the data is available from US3, this surfaces it on the dashboard without an extra click.

**Independent Test**: Load `/admin/`, confirm HBLink card shows running/stopped state and links to config, rules, and status pages.

**Acceptance Scenarios**:

1. **Given** the admin loads the dashboard and HBLink is running, **When** the page renders, **Then** the card shows a "Running" indicator and the uptime.
2. **Given** HBLink is stopped, **When** the admin loads the dashboard, **Then** the card shows a "Stopped" indicator.
3. **Given** the config has been modified since HBLink started, **When** the dashboard loads, **Then** the card shows a "Config drift" warning.

---

### Edge Cases

- What if `hblink.cfg` is unreadable due to file permissions? Show a permission-denied error message — not a blank page or PHP warning.
- What if the HBLink process is a zombie (present in the process table but not functional)? Report the PID and uptime as the OS reports them — process health-checking is out of scope.
- What if a config key matching the sensitive pattern appears in a comment line? Comments are rendered as-is — masking applies only to parsed key=value pairs, not comment text.
- What if `system_settings` contains a path outside the allowed base directories? The page must reject it with a clear error rather than reading an arbitrary file.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST display the full contents of `hblink.cfg` in a read-only, line-numbered code block on `/admin/hblink/config.php`.
- **FR-002**: The system MUST mask the values of any INI keys whose name contains `PASSPHRASE`, `PASSWORD`, or `SECRET` (case-insensitive) with `••••••••` on initial page load.
- **FR-003**: Admins MUST be able to reveal a masked value individually by clicking a "Show" toggle — without triggering a server request.
- **FR-004**: The system MUST display the last-modified timestamp of `hblink.cfg`.
- **FR-005**: The system MUST display a warning banner on the config and status pages when `hblink.cfg` has been modified after the HBLink process was last started.
- **FR-006**: The system MUST display the full contents of `rules.py` in a read-only, line-numbered code block on `/admin/hblink/rules.php`.
- **FR-007**: The system MUST display the last-modified timestamp of `rules.py`.
- **FR-008**: The system MUST show whether the HBLink process is currently running on `/admin/hblink/status.php`.
- **FR-009**: When HBLink is running, the system MUST show the PID and a human-readable uptime.
- **FR-010**: All three viewer pages MUST be restricted to users with the `system_admin` role.
- **FR-011**: File paths used by the viewer MUST be read from `system_settings` and validated against an allowlist of permitted base directories before use — an invalid path must produce an error, not a file read.
- **FR-012**: The admin dashboard MUST include an HBLink status card showing running/stopped state and links to the three viewer pages.
- **FR-013**: If a config or rules file cannot be read, the page MUST display a user-friendly error — no raw PHP errors or stack traces visible to the user.

### Key Entities

- **HBLink Config** (`hblink.cfg`): INI-format file on disk. Contains MASTER, PEER, LOGGER, REPORTS sections. Sensitive keys masked in UI.
- **HBLink Rules** (`rules.py`): Python-format file on disk. Contains talkgroup bridging definitions. No sensitive values.
- **HBLink Process**: OS-level process identified by name. Attributes used: PID, start time, running/stopped state.
- **System Settings**: Key-value store in the database holding configurable file paths and process name.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can view the full HBLink config in the browser within 2 seconds of page load under normal conditions.
- **SC-002**: Every INI value whose key name matches the sensitive pattern is masked on page load — zero false negatives for the defined patterns.
- **SC-003**: The config-drift warning appears whenever the file modification time is newer than the process start time — no manual refresh required.
- **SC-004**: An admin can determine HBLink running/stopped status without SSH access.
- **SC-005**: No file path outside the permitted base directory allowlist is ever readable through this feature, regardless of what is stored in system_settings.

---

## Assumptions

- HBLink3 is installed at `/opt/hblink3/` on this server; paths are configurable in `system_settings` for other deployments.
- The web server process has read permission on `hblink.cfg` and `rules.py`. Granting that permission is an installation step documented in `docs/install-dependencies.md`.
- Process start time is read from `/proc/[pid]/stat` — this feature is Linux-specific.
- The `system_settings` table already exists (migration 005). New path/process settings for this feature are seeded in a new migration.
- No third-party syntax highlighting library is introduced — plain monospace rendering with CSS line numbers is sufficient.
- Masking sensitive values is a UI convenience, not a security boundary. The raw values are in the PHP process memory and accessible to anyone with system_admin role and browser dev tools. This is acceptable — system_admin already has full server access.

---

## Out of Scope

- Editing `hblink.cfg` or `rules.py` from the browser (F7)
- Starting, stopping, or restarting HBLink (F13)
- Generating `hblink.cfg` from the database (AD-1 / F7)
- Viewing HBLink log files in the browser (F9)
- Multi-node / remote server support (F12)
- Client-side syntax highlighting
