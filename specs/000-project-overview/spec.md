# Feature Specification: CFLAG DMR — Project Overview

**Feature Branch**: `dev` (project-level document; not feature-branch-scoped)

**Created**: 2026-05-24

**Status**: Active — in use as the canonical product scope reference

**Input**: Derived from project constitution, architecture direction in CLAUDE.md, and team decisions

---

## Purpose of This Document

This is the top-level product specification for CFLAG DMR. It defines the full scope of what the system needs to do, organized as independently deliverable feature areas. Each feature area listed here will be broken out into its own numbered feature spec (`specs/NNN-*/spec.md`) when that feature enters the implementation queue.

**Feature delivery status**:

| ID | Feature Area | Status | Feature Spec |
|----|--------------|--------|--------------|
| F1 | Admin Authentication | In progress | [001-admin-login](../001-admin-login/plan.md) |
| F2 | HBLink Configuration Visibility | Not started — next after F1 | — |
| F3 | Peer and Hotspot Management | Not started | — |
| F4 | Talkgroup and Timeslot Management | Not started | — |
| F5 | Last-Heard and Activity Log | Not started | — |
| F6 | Safer Configuration Editing | Not started | — |
| F7 | Controlled Reload and Restart | Not started | — |
| F8 | Backup and Rollback | Not started | — |

---

## Product Description

CFLAG DMR is a web-based management dashboard for a live DMR (Digital Mobile Radio) amateur radio repeater network. The network runs on an HBLink-compatible master server. CFLAG DMR provides a protected, browser-based control plane that lets a small group of licensed amateur radio operators manage the network without direct server access.

The system sits alongside HBLink — it reads HBLink configuration files, manages its own database of admin accounts and activity records, and (when authorized) triggers reload operations on the HBLink process. It is intentionally decoupled from HBLink internals so that the underlying engine could be replaced without rewriting the control plane.

**Primary actors**: Admin operators (licensed amateur radio operators with admin credentials). No public-facing users in the current scope.

---

## User Scenarios & Testing

---

### F1: Admin Authentication (Priority: P1) — IN PROGRESS

Admins must be able to securely log in and out. All management pages are protected and require authentication.

**Why this priority**: Every other feature depends on this. No admin functionality is accessible without it.

**Independent Test**: An admin can log in with valid credentials and reach the dashboard. Visiting any protected page while logged out redirects to the login page. Logging out destroys the session.

**Acceptance Scenarios**:

1. **Given** I am a logged-out admin, **When** I visit any `/admin/` page, **Then** I am redirected to the login page with no privileged content shown.
2. **Given** I am on the login page, **When** I submit valid credentials, **Then** I am redirected to the admin dashboard.
3. **Given** I am on the login page, **When** I submit invalid credentials (wrong password, nonexistent username, or disabled account), **Then** I see a single generic error message and no information about which condition failed.
4. **Given** I am logged in, **When** I click log out, **Then** my session is fully destroyed and I am redirected to the login page.
5. **Given** my session has just been destroyed, **When** I visit `/admin/`, **Then** I am redirected to the login page and cannot access any protected content.

**Feature spec**: [specs/001-admin-login/plan.md](../001-admin-login/plan.md)

---

### F2: HBLink Configuration Visibility (Priority: P2) — NEXT

Admins can view the current HBLink master configuration and routing rules in a safe, read-only display inside the dashboard — without needing SSH access to the server.

**Why this priority**: Visibility is the foundation of control. Admins need to understand the current server state before making any changes. Read-only display carries no risk of misconfiguration.

**Independent Test**: An authenticated admin can navigate to a config viewer page and see the current HBLink master config and rules in a readable format. No editing is possible from this view.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I visit the configuration viewer, **Then** I can see the current HBLink master configuration displayed in a structured, readable format.
2. **Given** I am logged in, **When** I visit the rules viewer, **Then** I can see the current talkgroup routing rules in a readable format.
3. **Given** I am logged in, **When** I view either config page, **Then** no editing controls are present — the view is strictly read-only.
4. **Given** I am not logged in, **When** I visit any config viewer URL directly, **Then** I am redirected to the login page.
5. **Given** the HBLink config file is missing or unreadable, **When** I visit the config viewer, **Then** I see a clear error message rather than a blank or broken page.

---

### F3: Peer and Hotspot Management (Priority: P3)

Admins can view all configured peers and hotspots, see their status, and enable or disable them from the dashboard.

**Why this priority**: Peer management is the most common day-to-day administrative action. Admins currently need SSH and manual file edits to change peer status.

**Independent Test**: An authenticated admin can view the full peer list, toggle a peer's enabled/disabled status, and verify the change persists to the configuration file without any SSH access.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I visit the peer management page, **Then** I see a list of all configured peers and hotspots with their callsign, mode, and current enabled/disabled status.
2. **Given** I am viewing the peer list, **When** I disable a peer, **Then** the peer's status changes to disabled, the change is written to the configuration in a structured way, and I receive confirmation.
3. **Given** I am viewing the peer list, **When** I enable a disabled peer, **Then** the peer's status changes to enabled and the change is written to the configuration.
4. **Given** a peer status change has been saved, **When** I reload the peer list, **Then** the change is reflected accurately.
5. **Given** the configuration file is not writable, **When** I attempt to change a peer's status, **Then** I see a clear error message and the configuration is not partially modified.

**Edge cases**:
- What if a peer's callsign contains special characters that could corrupt the config format? (Config writer must escape/sanitize output.)
- What if two admins edit the same peer simultaneously? (Last write wins for this slice; no locking required in F3.)

---

### F4: Talkgroup and Timeslot Management (Priority: P4)

Admins can view, add, edit, and remove talkgroup routing rules from the dashboard. Changes are written to a staging state and require an explicit apply step before taking effect on the live server.

**Why this priority**: Talkgroup routing is the core network function. Changes here have direct impact on radio traffic, so they must go through a deliberate apply step rather than taking immediate effect.

**Independent Test**: An authenticated admin can view all routing rules, add a new rule, edit an existing rule, and remove a rule. None of these changes affect live routing until the admin explicitly applies them. After applying, the routing config reflects the changes.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I visit talkgroup management, **Then** I see a list of all current routing rules showing talkgroup IDs, systems, and timeslots.
2. **Given** I am viewing talkgroup rules, **When** I add a new rule with valid fields, **Then** the rule appears in the staged list but the live routing is not yet changed.
3. **Given** I have staged changes, **When** I click "Apply Changes", **Then** the routing config file is updated with all staged changes and I receive confirmation.
4. **Given** I have staged changes, **When** I click "Discard Changes", **Then** the staged changes are discarded and the current live rules are shown.
5. **Given** I submit a talkgroup rule with an invalid or duplicate talkgroup ID, **When** I try to save, **Then** I see a validation error and the staged config is not changed.

---

### F5: Last-Heard and Activity Log (Priority: P5)

Admins can view a recent last-heard list showing which callsigns have been active, on which talkgroup, timeslot, and system, and can filter the list.

**Why this priority**: Last-heard visibility is a key operational tool for monitoring network health and verifying that radio traffic is flowing correctly.

**Independent Test**: An authenticated admin can view a last-heard list that updates on page load, and can filter by callsign or talkgroup to find specific activity.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I visit the last-heard page, **Then** I see a list of recent radio activity entries showing callsign, talkgroup, timeslot, system, and timestamp for each.
2. **Given** I am viewing last-heard, **When** I filter by callsign, **Then** only entries matching that callsign are shown.
3. **Given** I am viewing last-heard, **When** I filter by talkgroup, **Then** only entries for that talkgroup are shown.
4. **Given** no recent activity has been ingested, **When** I visit last-heard, **Then** I see an empty state message rather than a blank page.
5. **Given** I am not logged in, **When** I visit the last-heard URL directly, **Then** I am redirected to the login page.

**Note**: The ingestion method (HBLink log parsing vs. structured data source) is to be determined during F5 feature planning after HBLink is studied.

---

### F6: Safer Configuration Editing (Priority: P6)

Admins can edit key HBLink configuration values through a structured form, with validation before saving and an automatic backup created on every save.

**Why this priority**: Raw config file editing is error-prone. A structured form with validation reduces the risk of misconfiguration that could take the network offline.

**Independent Test**: An authenticated admin can edit a key master config value through a form, see a validation error if the value is invalid, and upon successful save confirm that a backup of the previous config was automatically created.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I visit the config editor, **Then** I see a structured form with current config values pre-populated.
2. **Given** I change a value to something invalid, **When** I submit the form, **Then** I see a specific validation error and the config file is not changed.
3. **Given** I submit valid changes, **When** the save completes, **Then** the config file is updated and a timestamped backup of the previous config is automatically created.
4. **Given** a save has completed, **When** I re-open the config editor, **Then** the form shows the newly saved values.
5. **Given** the config file is not writable, **When** I try to save, **Then** I see a clear error and neither the config nor any backup file is partially written.

---

### F7: Controlled Reload and Restart (Priority: P7)

Admins can trigger an HBLink reload or restart from the dashboard after making configuration changes, and can see whether the operation succeeded or failed.

**Why this priority**: Without a controlled reload mechanism, config changes made through the dashboard have no way to take effect on the live server without SSH access.

**Independent Test**: An authenticated admin can trigger a reload, see a success or failure result, and verify the action was recorded in the audit log with their identity and a timestamp.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I trigger a reload, **Then** the HBLink process receives the reload signal and I see a success or failure status within a reasonable time.
2. **Given** a reload has been triggered, **When** I view the audit log, **Then** I can see the reload action, the admin who triggered it, and the timestamp.
3. **Given** the reload fails (process not running, permission denied, etc.), **When** the result is displayed, **Then** I see a clear failure message rather than a false success confirmation.
4. **Given** I am not logged in, **When** I attempt to reach the reload endpoint directly, **Then** I am redirected to the login page and no action is taken.

---

### F8: Backup and Rollback (Priority: P8)

Admins can view a list of configuration backups, download a backup for review, and restore a previous backup — which replaces the active config and requires a reload to take effect.

**Why this priority**: Backup and rollback is the safety net for all config editing features (F6). It allows recovery from mistakes without SSH access.

**Independent Test**: An authenticated admin can view the backup list, select a previous backup, restore it, and verify the active config now matches the restored backup.

**Acceptance Scenarios**:

1. **Given** I am logged in, **When** I visit the backup page, **Then** I see a list of available backups with their timestamps and which config they cover.
2. **Given** I select a backup and choose "Restore", **When** the restore completes, **Then** the active config file is replaced with the backup contents and I receive confirmation.
3. **Given** a backup has been restored, **When** I view the config editor or viewer, **Then** it reflects the restored values.
4. **Given** the backup directory is empty, **When** I visit the backup page, **Then** I see an informative empty-state message.
5. **Given** a backup file has been corrupted or is unreadable, **When** it appears in the list, **Then** it is clearly marked as unrestorable and cannot be selected.
6. **Given** backup files exist on disk, **When** I attempt to access the backup directory via the browser (direct URL), **Then** I receive a 403 or 404 — backups are not web-accessible.

---

### Edge Cases (cross-cutting)

- What if an admin's session expires mid-operation (form submit, reload trigger, restore)? The server-side action should be rejected and the admin should be redirected to the login page.
- What if two admins are editing configuration simultaneously? For the current scope, last write wins. Concurrent editing conflicts are not handled in this phase.
- What if a config file grows very large? Display should paginate or truncate with a clear indicator rather than timing out.
- What if HBLink is not running when a reload is triggered? The system must report the failure clearly and not hang.

---

## Requirements

### Functional Requirements

- **FR-001**: All management pages MUST be inaccessible to unauthenticated visitors; any unauthenticated request MUST redirect to the login page.
- **FR-002**: Login MUST use username/password credentials validated against a stored admin account.
- **FR-003**: Login failures MUST always produce a single generic message with no detail about which condition failed.
- **FR-004**: Sessions MUST be fully destroyed on logout, including clearing the session cookie.
- **FR-005**: The system MUST display HBLink master configuration and routing rules in a read-only formatted view.
- **FR-006**: The system MUST display a list of configured peers/hotspots with their enabled/disabled status.
- **FR-007**: Admins MUST be able to toggle peer/hotspot enabled status; changes MUST be written to the config file in a structured, sanitized format.
- **FR-008**: Talkgroup routing rule changes MUST be staged before applying; staged changes MUST NOT affect live routing until explicitly applied.
- **FR-009**: The system MUST display a last-heard activity list and MUST support filtering by callsign and talkgroup.
- **FR-010**: Structured config editing MUST validate values before saving; invalid values MUST be rejected with a specific error.
- **FR-011**: Every config save MUST automatically create a timestamped backup of the previous config before overwriting.
- **FR-012**: Admins MUST be able to trigger an HBLink reload or restart from the dashboard.
- **FR-013**: Every reload/restart action MUST be recorded in an audit log with the acting admin's identity and a timestamp.
- **FR-014**: Admins MUST be able to view available backups, restore a previous backup, and see confirmation that the restore succeeded.
- **FR-015**: Backup files MUST be stored outside the web-accessible directory and MUST NOT be directly retrievable via browser URL.
- **FR-016**: All admin-supplied values rendered to any page MUST be HTML-escaped before display.
- **FR-017**: All database queries MUST use parameterized statements; no user input may be interpolated into query strings.

### Key Entities

- **Admin account**: An operator authorized to log in. Has username, hashed password, display name, active/inactive status, last login timestamp.
- **Admin session**: Tracks an authenticated admin's identity during a browser session. Destroyed on logout or inactivity expiry.
- **Peer / Hotspot**: A DMR network node configured in HBLink. Has callsign, mode, enabled/disabled status, and associated config attributes. Stored in HBLink config files; managed via the dashboard.
- **Talkgroup routing rule**: Maps a talkgroup ID to one or more systems and timeslots. Stored in the HBLink rules file; managed via staged editing.
- **Activity log entry** (last-heard): A record of a radio transmission event: callsign, talkgroup, timeslot, system, start time, duration. Source is HBLink logs or a derived data store.
- **Configuration backup**: A timestamped copy of a config file created automatically before any save. Stored in a protected directory.
- **Audit log entry**: A record of an admin action (reload, restore, config edit). Includes actor identity, action type, timestamp, and outcome.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: An admin can complete the login flow in under 30 seconds on a standard device.
- **SC-002**: All protected pages redirect unauthenticated visitors within one page load — no partial content is shown before redirect.
- **SC-003**: An admin can view the current HBLink config and peer list without any SSH access to the server.
- **SC-004**: Peer enable/disable changes are reflected in the configuration within 5 seconds of confirmation.
- **SC-005**: Talkgroup rule changes take effect on the live server only after an explicit apply action — accidental immediate application is not possible.
- **SC-006**: Every config save produces a restorable backup — zero data-loss scenarios from a single failed save.
- **SC-007**: A reload action produces a visible success or failure result within 15 seconds.
- **SC-008**: An admin can restore a previous configuration backup and apply it to the live server without SSH access.
- **SC-009**: All audit-logged actions (reloads, restores, config edits) are traceable to a specific admin and timestamp within the dashboard.
- **SC-010**: No configuration backup file is accessible via a direct browser URL — verified by attempting a GET request to the backup path.

---

## Assumptions

- Admin accounts are created manually (or via a future admin management feature); no self-registration exists.
- The HBLink process runs on the same server as the CFLAG DMR web application and is accessible via filesystem paths and process signals.
- HBLink configuration files use a known, stable format (INI-style for `hblink.cfg`, Python dict/list for `rules.py`) — parsing strategy will be confirmed during F2 feature planning.
- The dashboard is HTTP-only in the development environment; HTTPS will be enabled in production. Cookie security settings are environment-controlled.
- A single admin role is sufficient for the current scope. No read-only vs. read-write distinction between admin accounts.
- The last-heard data source (log file parsing vs. HBLink-provided data socket vs. other) will be determined during F5 feature planning after HBLink is studied.
- The system will be used by a small number of admins (under 10 concurrent sessions). No high-traffic scaling considerations are required.
- All admin operators are licensed amateur radio operators operating within their legal authority. The system does not need to enforce licensing checks.

---

## Out of Scope (Current Phase)

The following are explicitly excluded from this product scope and must not be designed into any current feature:

- Public-facing registration or self-service hotspot enrollment
- Rewriting or replacing HBLink (CFLAG DMR manages HBLink, it does not replace it)
- Role-based access control beyond simple admin/non-admin (all admins have equal access)
- Email notifications or external alerting integrations
- Mobile application
- Multi-server or multi-network management (single HBLink instance only)
- Automated certificate or TLS management

---

## Feature Delivery Order

| Priority | Feature | Rationale |
|----------|---------|-----------|
| 1 | F1 Admin Authentication | Foundation for all other features |
| 2 | F2 HBLink Configuration Visibility | Read-only, low risk, high operational value |
| 3 | F3 Peer and Hotspot Management | Most common admin action; depends on F2 understanding of config format |
| 4 | F4 Talkgroup and Timeslot Management | Higher complexity writes; depends on F3 patterns |
| 5 | F5 Last-Heard and Activity Log | Requires HBLink log study; independent of F3/F4 |
| 6 | F6 Safer Configuration Editing | Depends on F2 config understanding; higher-risk writes |
| 7 | F7 Controlled Reload and Restart | Depends on F6 (reload needed after config edits) |
| 8 | F8 Backup and Rollback | Depends on F6 (backups created by F6 saves) |
