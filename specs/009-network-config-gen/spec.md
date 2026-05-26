# Feature Specification: Network Config & Peer Management

**Feature Branch**: `009-network-config-gen`

**Created**: 2026-05-26

**Status**: Draft

**Input**: F7: Network Config & Peer Management — Admins manage the full HBLink network configuration from the database: master server settings, peer/hotspot connections, OpenBridge links, and the DMR ID whitelist. Configuration is stored in the DB and materialized into HBLink-compatible YAML/config files on demand. The whitelist (REG_ACL) is generated from approved devices. Peer entries are generated from approved device subscriptions. Config changes are queued and applied as a batch before a controlled HBLink reload.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generate HBLink Config from Database (Priority: P1)

A system admin triggers config generation, which reads master server settings, approved devices, and active talkgroup subscriptions from the database to produce HBLink-compatible configuration files (hblink.cfg and rules). The generated files replace the previous configuration files and are immediately available for HBLink to use on the next reload.

**Why this priority**: Config generation is the core deliverable of this feature. Without it, peer management and OpenBridge settings have nowhere to go. This story produces a working, complete HBLink configuration from the current database state.

**Independent Test**: Can be fully tested by seeding the database with master settings, approved devices, and subscriptions, triggering generation, and verifying the output files contain correct REG_ACL entries and peer stanzas.

**Acceptance Scenarios**:

1. **Given** approved devices exist with valid DMR IDs, **When** admin triggers config generation, **Then** the output hblink.cfg REG_ACL section lists every approved device's DMR ID
2. **Given** devices have active talkgroup subscriptions, **When** config is generated, **Then** peer stanzas in the rules file reflect the correct talkgroup-to-timeslot mappings for each device
3. **Given** no approved devices exist, **When** config is generated, **Then** REG_ACL is empty and no peer stanzas are written
4. **Given** a device has been denied or is pending, **When** config is generated, **Then** that device's DMR ID does NOT appear in REG_ACL
5. **Given** generation completes successfully, **When** admin views the status page, **Then** a timestamp of last generation is shown alongside the diff from the previous config

---

### User Story 2 - Admin Manages Master Server Settings (Priority: P2)

A system admin can view and edit the HBLink master server settings (bind address, port, passphrase, reporting port, etc.) through a web form. Changes are stored in the database and included in the next config generation cycle. The passphrase field enforces a maximum of 15 characters to maintain compatibility with openSPOT4 Pro devices.

**Why this priority**: Master server settings are required for config generation to produce a valid hblink.cfg. Without correct settings, generated configs won't be usable. This story is a prerequisite for P1 to produce correct output.

**Independent Test**: Can be tested by navigating to the master settings page, editing fields, saving, triggering a config generation, and verifying the generated hblink.cfg reflects the saved values.

**Acceptance Scenarios**:

1. **Given** the admin opens the master settings page, **When** the page loads, **Then** current settings are shown pre-populated in an editable form
2. **Given** the admin submits a passphrase longer than 15 characters, **When** the form is submitted, **Then** an error is shown and the passphrase is NOT saved
3. **Given** the admin saves valid settings, **When** config generation runs next, **Then** the generated hblink.cfg contains the saved bind address, port, and passphrase
4. **Given** a required field is left blank, **When** the form is submitted, **Then** a validation error identifies the missing field

---

### User Story 3 - Manage OpenBridge Connections (Priority: P3)

A system admin can define OpenBridge link entries (remote address, port, passphrase, network ID) through the admin interface. Each OpenBridge entry is stored in the database and included in the next generated config as an OPENBRIDGE stanza. Entries can be enabled or disabled without deleting them.

**Why this priority**: OpenBridge connections are optional infrastructure links. They extend reach but are not required for the core config generation story to work. Admins need this to interconnect with other DMR networks.

**Independent Test**: Can be tested by adding an OpenBridge entry, generating config, and verifying an OPENBRIDGE stanza appears in the output with the correct parameters.

**Acceptance Scenarios**:

1. **Given** an admin adds a new OpenBridge entry with valid fields, **When** config is generated, **Then** an OPENBRIDGE stanza appears in hblink.cfg with the correct remote address, port, and passphrase
2. **Given** an OpenBridge entry is disabled, **When** config is generated, **Then** no stanza for that entry appears in the generated config
3. **Given** an OpenBridge passphrase exceeds 15 characters, **When** the form is submitted, **Then** an error is shown and the entry is NOT saved
4. **Given** multiple OpenBridge entries exist, **When** config is generated, **Then** all enabled entries appear as separate stanzas

---

### User Story 4 - Config Generation History & Diff (Priority: P4)

A system admin can view a history of config generation events: when each generation ran, what triggered it, and whether the resulting config differed from the previous version. For each generation, the admin can view the diff between the new and previous config to understand what changed.

**Why this priority**: Audit trail and change visibility are safety features. Admins need to know what changed and when, especially before performing a controlled HBLink reload. This story builds trust and reduces risk of unknown configuration drift.

**Independent Test**: Can be tested by running two consecutive generations with a device approval in between and verifying the diff view shows the new DMR ID added to REG_ACL.

**Acceptance Scenarios**:

1. **Given** config has been generated at least twice, **When** admin views generation history, **Then** a list of generation events shows timestamp, trigger type, and whether the config changed
2. **Given** a generation event produced changes, **When** admin views the diff for that event, **Then** added and removed lines are clearly distinguished
3. **Given** a generation event produced no changes (config identical to previous), **When** admin views history, **Then** that entry is marked as "no change"
4. **Given** no previous config exists, **When** admin views the first generation's diff, **Then** the entire new config is shown as added

---

### Edge Cases

- What happens when the database contains a DMR ID that is not a valid 7-digit integer? (Blocked at device registration; generation skips invalid entries and logs a warning)
- What happens when config generation is triggered while a previous generation is still running? (Second trigger is rejected with a "generation already in progress" message)
- What happens if the output directory is not writable? (Generation fails with a clear error message; no partial file is written)
- What happens when OpenBridge passphrase contains special characters? (Passphrase is written verbatim; generation validates it is under 16 chars, no other character restriction)
- What if master server settings have never been saved? (Config generation fails with a prompt to configure master settings first)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST generate an HBLink-compatible configuration file from current database state when an admin requests generation
- **FR-002**: The generated REG_ACL section MUST include exactly the DMR IDs of all currently approved, active devices and no others
- **FR-003**: Peer stanzas in the generated rules file MUST reflect the active talkgroup subscriptions (device → talkgroup → timeslot) for each approved device
- **FR-004**: System MUST store master server settings (bind address, port, passphrase, reporting address, reporting port) in the database
- **FR-005**: System MUST enforce a maximum passphrase length of 15 characters for both master server and OpenBridge passphrase fields
- **FR-006**: System MUST allow admins to create, edit, enable, disable, and delete OpenBridge connection entries
- **FR-007**: System MUST record a generation history entry for each config generation event, including timestamp, triggering admin, and whether the output differed from the previous generation
- **FR-008**: System MUST store the full text of each generated config version to enable diff computation
- **FR-009**: System MUST present a diff view comparing any two consecutive config versions to an admin
- **FR-010**: Config generation MUST be an atomic operation — either a complete valid config is written, or the existing config is left unchanged
- **FR-011**: System MUST source device whitelist data exclusively from the approved-device data contract (`get_whitelist_eligible_dmr_ids()`) and subscription data from (`get_device_subscriptions_for_config()`)
- **FR-012**: The config file output path MUST be hardcoded in server-side configuration and NOT configurable via the database or admin UI

### Key Entities

- **MasterServerSettings**: Singleton record holding HBLink bind configuration (address, port, passphrase, report address, report port)
- **OpenBridgeConnection**: Named link to a remote DMR network with address, port, passphrase, network ID, enabled flag
- **ConfigGeneration**: An immutable record of one generation event: timestamp, actor user ID, full config text, change flag, and diff against previous
- **ConfigChangeQueue**: Pending change records that accumulate between generation runs (already implemented in F5/F6 as `config_change_queue`)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can trigger a complete config generation and have ready-to-use output files within 5 seconds for any realistic network size (up to 500 approved devices)
- **SC-002**: The generated REG_ACL is 100% accurate — zero false inclusions (denied/pending devices) and zero false exclusions (approved devices)
- **SC-003**: An admin can review the diff between any two consecutive config versions without leaving the web interface
- **SC-004**: A passphrase validation error is shown immediately on form submission, before any database write, for passphrases exceeding the length limit
- **SC-005**: Config generation history is retained for at minimum the last 50 generation events and is accessible without pagination for typical usage

## Assumptions

- HBLink config and rules file paths are hardcoded in PHP application configuration (via `.env`), not stored in the database — consistent with the existing `HBLINK_ALLOWED_DIRS` pattern
- The passphrase maximum is 15 characters (one less than 16) to ensure compatibility with openSPOT4 Pro devices, which reject 16-character passphrases
- F13 (or a future reload feature) handles the actual HBLink process restart/reload; this feature only generates the config files and records history
- Data contracts from F5 (`get_whitelist_eligible_dmr_ids()`) and F6 (`get_device_subscriptions_for_config()`) are already implemented and used as the sole source of truth for device and subscription data
- Only system_admin role users can trigger config generation or modify master/OpenBridge settings
- The `config_change_queue` table already exists (created in F5/F6) and is used to signal that a new generation may be needed; this feature reads that queue but does not own it
- Config generation writes to a staging path first, then atomically replaces the live config file to prevent partial writes
