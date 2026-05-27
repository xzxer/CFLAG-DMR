# Feature Specification: F13 — Controlled Restart / Reload

**Feature ID**: F13  
**Branch**: `016-operational-features` | **Spec Dir**: `specs/015-controlled-restart/`  
**Created**: 2026-05-27 | **Status**: Planning

---

## Overview

Admins need a reliable, safe workflow for applying pending network configuration changes to the live HBLink server. Currently the config generation page (F7) can write files but the apply + restart path is broken and unvalidated. This feature makes config application a first-class, audited operation.

---

## User Stories

### US1 — Apply and restart (P1)
**As an admin**, I want to click a single "Apply & Restart" button on the Network Config page, so that the current DB-sourced config is generated, validated, backed up, atomically written, and HBLink is restarted — all with clear status feedback.

**Acceptance criteria:**
- Given a valid DB config state, when I click Apply, the new config is written and HBLink restarts within 15 seconds
- Given a config validation failure (syntax error, missing required field), when I click Apply, the operation is aborted and the current running config is untouched
- Given a successful apply, when I view the config history page, I see a new generation record with timestamp and actor
- Given a failed apply, when I view the page, I see a clear error message explaining what failed

### US2 — Config diff preview (P2)
**As an admin**, before applying I want to see a diff between the currently-running config and the pending generated config, so I can confirm what will change before committing the restart.

**Acceptance criteria:**
- Given I'm on the config page, when I click "Preview Changes", I see a side-by-side or unified diff of the pending vs current config
- Given no changes exist, the diff view shows "No changes pending"

### US3 — Restart status indicator (P1)
**As an admin**, I want to see the current HBLink process status (running / stopped / restarting) on the Network Config page, so I know whether the server is healthy.

**Acceptance criteria:**
- Given HBLink is running, the status indicator shows green/running
- Given HBLink is stopped, the status indicator shows red/stopped
- Status is read from the Docker container state, not a cached value

---

## Functional Requirements

1. Config generation uses the existing `generate_hblink_config()` function
2. Before applying, validate the generated output (non-empty, required sections present)
3. Back up the current live config file with a timestamp before overwriting
4. Write the new config to a temp file, then atomically rename into place
5. Trigger HBLink restart via Docker: `docker compose -f /etc/hblink3/docker-compose.yml restart hblink`
6. Wait up to 10 seconds for the container to return to running state; report timeout as a warning
7. Record every apply attempt in `config_generation_history` regardless of success/failure, with a `success` column and `error_message`
8. Bump `routing_config_state.version` on successful apply
9. Only system admins can trigger apply
10. All apply attempts are logged to `audit_log`

---

## Out of Scope

- Automatic scheduled applies (cron-based restarts)
- Multi-node coordinated restarts (one node at a time is sufficient for now)
- Rolling restart with zero-downtime (HBLink doesn't support this)

---

## Assumptions

- HBLink runs in a Docker container named `hblink` managed by docker-compose at `/etc/hblink3/docker-compose.yml`
- The web process has permission to run `docker compose restart` (via sudo rule or docker group membership)
- Config files live at a path accessible from the web process (already the case from F7)
- A 5–15 second restart window is acceptable; operators know to expect brief disconnections

---

## Success Criteria

- Admin can apply a config change in under 30 seconds end-to-end from click to running
- Failed applies never corrupt the running config
- Every apply is traceable to an actor and timestamp in the audit log
- The restart workflow is tested against the live HBLink container on the dev server
