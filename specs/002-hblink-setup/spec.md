# Feature Specification: HBLink + HBMonv2 Setup and Analysis (P0)

**Feature Branch**: `002-hblink-setup`

**Created**: 2026-05-25

**Status**: Draft — ready for planning

**Input**: Project overview spec `specs/000-project-overview/spec.md`, installer repo at https://github.com/ShaYmez/hblink3-docker-install

---

## Purpose

This is Phase 0 — a prerequisite that must be completed before any CFLAG DMR application code is written. It has two outcomes:

1. The HBLink DMR server and its monitoring dashboard are installed, running, and verified on the dev server alongside the existing CFLAG DMR web application.
2. A written analysis of HBLink's internal structure is produced and committed to the repository, giving the team the knowledge needed to design every management feature (F2 through F8) correctly.

Without this phase, all subsequent features would be designed against assumptions about HBLink's config format, log format, reload behaviour, and data structures. This phase replaces those assumptions with verified facts.

---

## User Scenarios & Testing

---

### User Story 1 — HBLink and HBMonv2 are installed and verified running (Priority: P1)

The dev server hosts a live HBLink DMR master and its monitoring dashboard. Both services start automatically, survive a reboot, and produce no fatal errors under normal operating conditions.

**Why this priority**: Nothing else can be built or tested until the environment is up. This is the foundation for all subsequent work.

**Independent Test**: An admin can confirm HBLink is running by checking its process status and viewing its log output. An admin can confirm HBMonv2 is running by checking its service status. Config files are present and readable.

**Acceptance Scenarios**:

1. **Given** the installer has been run to completion, **When** the HBLink service status is checked, **Then** the HBLink container reports as running with no fatal errors in its log output.
2. **Given** HBLink is running, **When** the HBLink log is inspected, **Then** a clean startup sequence is visible — no crash loops, no missing config errors, no fatal exceptions.
3. **Given** the installer has completed, **When** the HBMonv2 service status is checked, **Then** the service reports as active and running.
4. **Given** both services are running, **When** the server is rebooted, **Then** both HBLink and HBMonv2 start automatically without manual intervention.
5. **Given** both services are running, **When** the main HBLink config file and the routing rules file are read from the filesystem, **Then** both files are present, non-empty, and parseable.

---

### User Story 2 — CFLAG DMR and HBMonv2 are both accessible via the web server without conflict (Priority: P2)

The HBLink installer introduces a web server configuration for HBMonv2. CFLAG DMR already has its own web server configuration. Both must be accessible simultaneously from a browser with no errors or interference between them.

**Why this priority**: The HBLink installer modifies the shared web server. If this conflict is not resolved before any further CFLAG DMR work, subsequent development may break in unpredictable ways. It must be resolved as part of P0, not deferred.

**Independent Test**: An admin can open both the CFLAG DMR application and the HBMonv2 dashboard in a browser at the same time, and both load correctly with no errors.

**Acceptance Scenarios**:

1. **Given** both services are installed, **When** the CFLAG DMR application URL is visited in a browser, **Then** the CFLAG DMR page loads correctly with no errors — identical to its state before HBLink was installed.
2. **Given** both services are installed, **When** the HBMonv2 dashboard URL is visited in a browser, **Then** the HBMonv2 dashboard loads correctly.
3. **Given** both are accessible, **When** the web server configuration is inspected, **Then** each application has its own isolated configuration with no shared document roots or conflicting rules.
4. **Given** either application returns an error, **When** the error is investigated, **Then** the cause is identifiable and does not involve interference from the other application's configuration.

---

### User Story 3 — A complete written analysis of HBLink's internals is committed to the repository (Priority: P3)

An admin or developer can open a single analysis document in the repository and find everything they need to know about how HBLink stores configuration, processes routing rules, writes logs, manages its reload cycle, and exposes data — without needing to SSH into the server or read HBLink source code.

**Why this priority**: This document is the direct input to feature planning for F2 through F8. Every config viewer, peer editor, talkgroup manager, last-heard display, and reload button in CFLAG DMR will be designed based on what this analysis reveals. Incomplete analysis leads to features that have to be redesigned mid-implementation.

**Independent Test**: The analysis document exists at `specs/002-hblink-setup/analysis.md`, is committed to the repository, and contains a verified, non-empty entry for each of the required topics listed below.

**Acceptance Scenarios**:

1. **Given** HBLink is running, **When** the analysis document is read, **Then** it contains a documented breakdown of the main config file structure — all section types, the purpose of each section, and the key fields within each section.
2. **Given** HBLink is running, **When** the analysis document is read, **Then** it contains a documented breakdown of the routing rules file — how rules are structured, how talkgroups map to systems and timeslots, and what a typical rule looks like.
3. **Given** HBLink is running, **When** the analysis document is read, **Then** it contains a documented description of the log format — what a normal startup sequence looks like, what a transmission event looks like, and what fields are present in each log line.
4. **Given** HBMonv2 is running, **When** the analysis document is read, **Then** it contains a documented description of the last-heard data store — its location on the filesystem, its format, its schema (if structured), and what data it captures per transmission event.
5. **Given** the analysis is complete, **When** it is read, **Then** it documents the reload and restart behaviour — whether a reload applies config changes without a full restart, what happens to active calls during a restart, and the exact mechanism used to trigger each.
6. **Given** the analysis is complete, **When** it is read, **Then** it contains a full annotated directory listing of the HBLink and HBMonv2 installation — every file and directory with a plain-language description of its purpose.
7. **Given** the analysis is complete, **When** it is read, **Then** it documents every integration point that CFLAG DMR can use — files that can be read or written, sockets or APIs that expose data, log streams that can be tailed, and any other mechanism the management layer can hook into.

---

### Edge Cases

- What if the installer fails partway through? The installation should be verified at each stage before proceeding. If the installer fails, the failure reason should be recorded and the install retried from a clean state rather than patching a partial install.
- What if the HBLink installer's web server configuration overwrites or disables the existing CFLAG DMR web server configuration? The CFLAG DMR configuration must be backed up before the installer runs, and restored or merged during US2.
- What if HBMonv2 does not start after install? The HBMonv2 service logs should be checked and the failure reason documented before attempting to fix. If HBMonv2 cannot be brought up, the blocker must be documented in the analysis rather than silently skipped.
- What if the last-heard data store is empty after install (no radio traffic has occurred)? The schema and format can still be documented from the source code or empty database structure — live data is not required to complete the analysis.
- What if HBLink's config file format or rules file format differs from what is documented in the installer's README? The actual post-install files are authoritative. The analysis must reflect what is actually on disk, not what the documentation describes.

---

## Requirements

### Functional Requirements

- **FR-001**: HBLink MUST be installed and running as a persistent service that survives server reboots without manual intervention.
- **FR-002**: HBMonv2 MUST be installed and running as a persistent service that survives server reboots without manual intervention.
- **FR-003**: The HBLink log MUST be accessible and MUST show a clean startup with no fatal errors.
- **FR-004**: The HBLink main config file and routing rules file MUST both be present on the filesystem and readable by the admin user.
- **FR-005**: The existing CFLAG DMR web application MUST continue to function correctly after HBLink and HBMonv2 are installed — no regressions in availability or behaviour.
- **FR-006**: The HBMonv2 web dashboard MUST be accessible via a browser after installation.
- **FR-007**: Both web applications (CFLAG DMR and HBMonv2) MUST be served from the same web server without interfering with each other.
- **FR-008**: The analysis document MUST document the complete config file structure, including all section types and their key fields.
- **FR-009**: The analysis document MUST document the routing rules file structure, including how talkgroups, systems, and timeslots are defined.
- **FR-010**: The analysis document MUST document the log format, including a startup sequence example and a transmission event example.
- **FR-011**: The analysis document MUST document the last-heard data store — its location, format, and schema.
- **FR-012**: The analysis document MUST document the reload and restart behaviour, including the mechanism for triggering each and the impact on active calls.
- **FR-013**: The analysis document MUST include a full annotated directory listing of both the HBLink and HBMonv2 installations.
- **FR-014**: The analysis document MUST identify all integration points that CFLAG DMR can use to read or interact with HBLink data.
- **FR-015**: The analysis document MUST be committed to the repository at `specs/002-hblink-setup/analysis.md` before P0 is considered complete.

### Key Entities

- **HBLink service**: The running DMR master server process. Has an operational status (running/stopped), a log stream, and a config file it reads on startup or reload.
- **HBMonv2 service**: The monitoring dashboard process. Has an operational status, serves a web UI, and maintains a last-heard data store updated by cron or live feed.
- **HBLink config file** (`hblink.cfg`): The main configuration for the HBLink master. Defines the master server, all connected peers and hotspots, OpenBridge connections, and optional features like Parrot.
- **Routing rules file** (`rules.py`): Defines how radio traffic is routed — which talkgroups are bridged to which systems and timeslots.
- **Last-heard data store**: A persistent record of recent radio transmission events. Maintained by HBMonv2. Feeds the future Last-Heard display feature (F5).
- **Analysis document**: A committed Markdown file (`specs/002-hblink-setup/analysis.md`) containing all findings from this phase. This is the primary output of P0 and the primary input to F2–F8 planning.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: HBLink is confirmed running within one status check — no ambiguity about whether the service is up.
- **SC-002**: HBMonv2 is confirmed running within one status check — no ambiguity about whether the service is up.
- **SC-003**: Both the CFLAG DMR application and the HBMonv2 dashboard load in a browser without errors — verified by visiting each URL after installation.
- **SC-004**: The analysis document addresses all 7 required topic areas (config structure, rules structure, log format, last-heard store, reload behaviour, directory listing, integration points) — verified by reviewing the document against the checklist in this spec.
- **SC-005**: No CFLAG DMR feature spec (F2–F8) requires the author to SSH into the server to understand HBLink structure — everything needed is in the analysis document.
- **SC-006**: The analysis document is committed to the repository and readable by any team member without server access.

---

## Assumptions

- The dev server runs Debian 11/12/13 or Ubuntu 22.04/24.04 LTS, which is required by the installer.
- The server has internet access to pull Docker images and clone the installer repository.
- The installer is run as root on the dev server — this is a controlled dev environment, not a production hardened system.
- The existing CFLAG DMR Apache configuration will be backed up before the installer runs, so it can be restored or merged if the installer overwrites it.
- HBLink will be configured with a minimal working setup for the purposes of analysis — not production-ready peer and talkgroup config. Production configuration is out of scope for P0.
- If the last-heard database is empty at analysis time (no live radio traffic), the schema and format will be documented from the database structure itself rather than from live data.
- The analysis document will be written based on what is actually observed on the server post-install, not based on upstream HBLink documentation alone.

---

## Out of Scope

- Writing any CFLAG DMR application code (admin login, config viewers, peer management, etc.)
- Configuring HBLink for production use — adding real peers, real talkgroups, or connecting to a live network
- Setting up SSL/HTTPS on either application
- Tuning HBLink performance or security hardening
- Any HBLink feature or configuration not directly relevant to understanding integration points for CFLAG DMR
