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

### US4 — Automatic update check (P2)
**As an admin**, I want the system to automatically detect when RadioID.net has published a new database and import it, so I don't have to remember to manually trigger imports.

**Acceptance criteria:**
- Given the system has a configured minimum import interval (default 24 hours), it will not re-import more frequently than that interval even if triggered multiple times
- Given a Linux cron job runs the import script at a scheduled time, the script checks whether the RadioID.net file has changed since the last import using HTTP conditional GET (If-Modified-Since), and only imports if there is new data
- Given the remote file has not changed, the script exits without importing and logs "no update available"
- Given the remote file has changed, the script imports and logs the count of records updated
- Given the import fails, the existing data is preserved and the error is logged; the next scheduled run will retry

---

## Functional Requirements

1. `subscriber_ids` table: radio_id (PK), callsign, name, city, state, country, source ENUM('radioid','local'), last_updated_at
2. Import source: `https://radioid.net/static/user.csv` — publicly accessible, no API key, Last-Modified header present (updated daily ~05:00 UTC)
3. Import uses HTTP conditional GET: store the `Last-Modified` value from each successful download; on subsequent requests, send `If-Modified-Since` to skip the download if unchanged (304 Not Modified)
4. Minimum re-import interval enforced in code (default 24h, configurable via system_settings); prevents hammering RadioID.net even if triggered repeatedly
5. Import runs synchronously for in-portal use; an identical CLI-compatible function is also callable from a cron script
6. Upsert on import: INSERT … ON DUPLICATE KEY UPDATE for all non-PK fields, except source='local' rows which are never overwritten
7. Lookup function: `get_subscriber(int $dmr_id): array|null` — returns record or null
8. Last-heard page updated to use batch lookup `get_subscribers_for_ids()` for all DMR IDs in the result set
9. Admin page at `/admin/subscribers/` showing: record count, last import time, last-modified timestamp from RadioID.net, import button, update check button, override management table
10. A standalone CLI script at `scripts/import_subscribers.php` safe to invoke from a system cron job; exits 0 on success/no-change, exits 1 on failure

---

## Out of Scope

- Searching/browsing the full subscriber database (admin sees count + overrides only)
- Public-facing callsign lookup page
- Integration with other external callsign databases (QRZ, etc.)
- Per-ID lookup against the RadioID.net JSON API at registration time (deferred — bulk CSV is sufficient for MVP)

---

## Assumptions

- `https://radioid.net/static/user.csv` (singular "user") is the correct current URL; the former `users.csv` path is 404
- The file is approximately 15.9 MB uncompressed, ~306,000 records; PHP execution time limit must be extended for the import
- The server returns a `Last-Modified` header enabling conditional GET; if it stops doing so, the fallback is time-based interval checking only
- The import script sends a descriptive `User-Agent` header per RadioID.net API policy

---

## Success Criteria

- Last-heard shows callsigns for all DMR IDs that exist in the RadioID.net database
- An admin can complete a fresh import in under 5 minutes
- Import failures leave existing data intact
