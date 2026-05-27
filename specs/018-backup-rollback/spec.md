# Feature Specification: F16 — Backup & Rollback

**Feature ID**: F16  
**Branch**: `016-operational-features` | **Spec Dir**: `specs/018-backup-rollback/`  
**Created**: 2026-05-27 | **Status**: Planning

---

## Overview

Every time a config is applied to HBLink (F13), a record is stored in `config_generation_history`. This feature surfaces that history in the admin UI and allows admins to roll back to any previous config version by re-applying it. This is the safety net that makes controlled restarts safe to use in production.

---

## User Stories

### US1 — View config history (P1)
**As an admin**, I want to see a list of every config that has been applied to HBLink, with the date, who applied it, and whether it succeeded, so I can understand the history of changes.

**Acceptance criteria:**
- Given I navigate to Admin → Network Config → History, I see a table of all generation records ordered newest-first
- Each row shows: date/time, applied-by user, whether it was applied, apply success/failure, and whether it differed from the previous generation
- Given a row has a diff, I can expand it to see what changed

### US2 — Download a historical config (P1)
**As an admin**, I want to download the raw config text from any history record, so I can inspect it outside the portal or use it for manual recovery.

**Acceptance criteria:**
- Given I click "Download" on any history row, I receive the `config_text` as a plain text file download (`.cfg`)

### US3 — Roll back to a previous config (P1)
**As an admin**, after a bad apply I want to immediately roll back to the last known-good config, so I can restore the network without manually editing files.

**Acceptance criteria:**
- Given I click "Roll Back to This" on a history row, the system re-applies that config's text and restarts HBLink using the same F13 apply pipeline
- Given the rollback succeeds, a new history record is created referencing the original generation it was rolled back from
- Given the rollback fails, I see a clear error and the system attempts to preserve the current running config

### US4 — Current config snapshot (P2)
**As an admin**, I want to see what config is currently running on HBLink, even if it wasn't applied through the portal, so I can compare against the DB-generated version.

**Acceptance criteria:**
- Given I click "Show Running Config", the system reads the current config file from disk and displays it
- Given the file doesn't exist or can't be read, a clear error is shown

---

## Functional Requirements

1. History page reads from `config_generation_history` with apply status columns added by F13 (migration 020)
2. Download action streams `config_text` with `Content-Disposition: attachment; filename=hblink-YYYYMMDD-HHMMSS.cfg`
3. Rollback re-uses the `apply_hblink_config()` function from F13, passing the historical `config_text` directly instead of regenerating from DB
4. A rollback creates a new `config_generation_history` record with a `rolled_back_from_id` reference
5. The "currently running" snapshot reads `/etc/hblink3/hblink.cfg` directly from disk
6. Only system admins can trigger rollback or view running config
7. History is paginated (20 per page) — config_text can be large

---

## Dependencies

- **F13 (Controlled Restart)** must be implemented first — rollback re-uses its apply pipeline and the migration 020 schema extensions

---

## Out of Scope

- Automated backup scheduling (cron export of config to external storage)
- Database backup (this covers config file history only)
- Comparing two arbitrary history entries against each other (compare against previous only)

---

## Success Criteria

- Admin can roll back to a known-good config within 60 seconds of deciding to do so
- History page shows all applies since the feature launched
- Downloaded config files are valid and can be applied manually if needed
