# Feature Specification: F14 — Subscriber ID Import

**Feature ID**: F14  
**Branch**: `016-operational-features` | **Spec Dir**: `specs/016-subscriber-id-import/`  
**Created**: 2026-05-27 | **Status**: Planning

---

## Overview

The Last-Heard page currently shows raw DMR IDs with no callsign or name information, because there is no local subscriber database. V1 had a `subscriber_ids` table populated from RadioID.net. This feature adds that table, an admin import workflow, and integrates the lookup into the Last-Heard display.

---

## User Stories

### US1 — Admin imports subscriber data (P1)
**As an admin**, I want to import the RadioID.net subscriber database from the admin panel, so that last-heard entries show callsigns and operator names instead of bare DMR IDs.

**Acceptance criteria:**
- Given I click "Import from RadioID.net" on the admin subscriber page, the system downloads the CSV and imports it into the local DB
- Given the import completes, I see a count of records imported/updated
- Given the import fails (network error, bad format), I see a clear error message and the existing data is unchanged
- Given an existing record has the same radio_id, it is updated (upsert), not duplicated

### US2 — Last-heard shows callsign and name (P1)
**As any logged-in user**, I want last-heard entries to show the operator's callsign and name alongside their DMR ID, so I can identify who is on the network.

**Acceptance criteria:**
- Given a DMR ID appears in last-heard that exists in subscriber_ids, the callsign and name are shown
- Given a DMR ID that is not in subscriber_ids, the DMR ID is shown alone (graceful fallback)
- The callsign display does not require a page reload after an import

### US3 — Admin can set a local override (P2)
**As an admin**, I want to manually set a callsign and name for a DMR ID, so I can correct errors in the RadioID.net data or add private/club IDs not in the public database.

**Acceptance criteria:**
- Given I enter a DMR ID and callsign on the override form, a local override record is saved
- Given a local override exists for a DMR ID, it takes precedence over the imported RadioID.net data in all lookups
- Given I delete an override, the system falls back to the RadioID.net data

---

## Functional Requirements

1. `subscriber_ids` table: radio_id (PK), callsign, name, city, state, country, source ENUM('radioid','local'), last_updated_at
2. Import source: RadioID.net user CSV (publicly available, no API key required)
3. Import is initiated manually by an admin; no automatic scheduled import in MVP
4. Import runs synchronously for MVP (no background job); show a progress indicator while running
5. Upsert on import: INSERT … ON DUPLICATE KEY UPDATE for all non-primary-key fields
6. Local overrides (source='local') are never overwritten by an import
7. Lookup function: `get_subscriber(int $dmr_id): array|null` — returns record or null
8. Last-heard page updated to call `get_subscriber()` for each DMR ID in the result set
9. Admin page at `/admin/subscribers/` showing: record count, last import time, import button, override management table

---

## Out of Scope

- Automatic scheduled imports (cron — add in a later polish pass)
- Searching/browsing the full subscriber database (admin sees count + overrides only for MVP)
- Public-facing callsign lookup page
- Integration with other external callsign databases (QRZ, etc.)

---

## Assumptions

- RadioID.net user CSV URL is stable and publicly accessible without authentication
- Import volume is approximately 250,000–500,000 records; PHP's execution time limit must be extended for the import operation (set_time_limit)
- The import runs in a single PHP request for MVP; a background job can be added later if needed

---

## Success Criteria

- Last-heard shows callsigns for all DMR IDs that exist in the RadioID.net database
- An admin can complete a fresh import in under 5 minutes
- Import failures leave existing data intact
