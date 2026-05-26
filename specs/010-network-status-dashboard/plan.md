# Implementation Plan: Network Status Dashboard (F10)

**Branch**: `010-network-status-dashboard` | **Date**: 2026-05-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/010-network-status-dashboard/spec.md`

## Summary

F10 adds a network status page accessible to all authenticated users. It aggregates existing data: HBLink server state and connected peers (from `get_hblink_status()`), recent Last Heard entries (from `load_lastheard()`), and a config drift indicator (from `config_change_queue`). Admins see additional detail (IP addresses, raw peer metadata, server control links). No new data sources are introduced.

## Technical Context

**Language/Version**: PHP 8.3, strict types

**Primary Dependencies**: Existing `app/hblink/process.php` (`get_hblink_status()`), existing `app/lastheard/reader.php` (`load_lastheard()`), existing `app/auth/roles.php` (`user_has_role()`)

**Storage**: No new tables. Read-only from `hblink_settings`, `lastheard` (via reader), and `config_change_queue`.

**Testing**: Manual browser testing per quickstart.md scenarios

**Target Platform**: Linux server (Apache/PHP 8.3)

**Project Type**: Web application (read-only dashboard page)

**Performance Goals**: Page load under 2 seconds (SC-002)

**Constraints**: No new shell_exec surfaces beyond what already exists. Config drift check reads `config_change_queue` table — F7 must be merged before the drift indicator is fully meaningful, but the indicator degrades gracefully if F7 is not yet deployed (shows no drift).

**Scale/Scope**: Single HBLink instance. No polling/WebSocket — page refresh provides updates.

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Simplicity | ✅ Pass | Pure read-only aggregation of existing functions. New file: `public/network-status.php`. No new app/ modules needed. |
| II. Security First | ✅ Pass | IP addresses and raw peer data only shown to system_admin role. All output escaped. No new shell or filesystem access. |
| III. Public Directory Isolation | ✅ Pass | New file in `public/`. App logic in existing `app/` modules. |
| IV. Database Integrity | ✅ Pass | No schema changes. Read-only. |
| V. Feature Quality | ✅ Pass | Each US has testable scenarios and an independent test. |
| VI. Branch Strategy | ✅ Pass | Branched from dev as `010-network-status-dashboard`. |
| VII. Mobile-First | ✅ Pass | Status page uses card/table layout consistent with existing pages. |
| VIII. Scale/Observability | ✅ Pass | No new data; reuses existing observability data. |

## Project Structure

### Documentation (this feature)

```text
specs/010-network-status-dashboard/
├── plan.md              ← this file
├── spec.md
├── data-model.md
├── quickstart.md
└── checklists/
    └── requirements.md
```

### Source Code

```text
public/
└── network-status.php     ← new page: server state, peer list, recent activity, admin controls

app/hblink/
└── process.php            ← existing: get_hblink_status() — no changes needed

app/lastheard/
└── reader.php             ← existing: load_lastheard() — no changes needed
```

**No new app/ modules.** The entire feature is one public page that calls existing functions. Admin-only sections are conditionally rendered using `user_has_role()`.

**Structure Decision**: Single-file implementation. No new service layer needed because this is purely a read/aggregate/render page.

## Phase 0: Research

- **Peer list from HBLink**: `get_hblink_status()` returns `['running' => bool, 'uptime_seconds' => int|null, 'config_drifted' => bool]`. It does NOT currently return a peer list. The peer list must be read from the HBLink subscriber file (`sub.csv` or equivalent) or the HBMonV2 JSON status endpoint. Need to check which data source is available.
- **Config drift check**: `get_hblink_status()` already has `config_drifted` — this is the signal. No new table needed.
- **Last Heard**: `load_lastheard(int $limit)` already exists and returns rows.

**See research.md for resolved decisions.**

## Phase 1: Design & Contracts

No new tables. No new contracts beyond the existing function signatures.

See `quickstart.md` for test scenarios.
