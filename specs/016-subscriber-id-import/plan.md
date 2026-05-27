# Implementation Plan: F14 — Subscriber ID Import

**Branch**: `016-operational-features` | **Date**: 2026-05-27 | **Spec**: [spec.md](spec.md)

## Summary

Add a `subscriber_ids` table populated from RadioID.net CSV, an admin import page, and integrate callsign/name display into the Last-Heard page. Upsert-on-import with local override protection. No background jobs — synchronous import with extended timeout for MVP.

## Technical Context

**Language/Version**: PHP 8.3, strict types  
**Primary Dependencies**: PDO (MariaDB), fopen() URL stream for CSV download  
**Storage**: MariaDB — `subscriber_ids` (new), `system_settings` (2 new keys)  
**Target Platform**: Linux/Apache  
**Constraints**: allow_url_fopen must be On; set_time_limit(300) for import; ~300k rows upserted in batches  
**Scale/Scope**: ~300k subscriber records; last-heard page joins on subset

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I — Simplicity | ✅ | Plain PHP CSV streaming; no new libraries |
| II — Security | ✅ | URL is a deployment constant, not user input; all output escaped |
| III — Public isolation | ✅ | New manager in app/subscribers/, admin page in public/admin/subscribers/ |
| IV — Migrations | ✅ | Migrations 021 and 022 |
| V — Acceptance criteria | ✅ | Defined in spec.md |
| VI — Branch strategy | ✅ | Feature branch |
| VII — Mobile | ✅ | Admin import page is a single button; last-heard callsign display is inline |
| VIII — Engine separation | ✅ | No HBLink involvement |

## Project Structure

```text
specs/016-subscriber-id-import/

migrations/
├── 021_create_subscriber_ids.sql
└── 022_subscriber_import_settings.sql

app/subscribers/
└── manager.php       ← new

public/admin/subscribers/
└── index.php         ← new (import trigger + override management)

public/user/          ← update last-heard to use get_subscribers_for_ids()
public/admin/         ← update nav to include Subscribers link
```

## Implementation Phases

### Phase 1: Migration
- Create `subscriber_ids` table (migration 021)
- Seed system_settings import metadata keys (migration 022)

### Phase 2: Manager
- `app/subscribers/manager.php` with all functions per data-model.md

### Phase 3: Admin page
- `public/admin/subscribers/index.php`
- POST action: trigger import, show count/error result
- Table of local overrides with add/delete forms

### Phase 4: Last-heard integration
- Update last-heard query/display to join subscriber_ids
- Callsign shown as accent-colored monospace, name as secondary text

### Phase 5: Nav integration
- Add "Subscribers" to admin nav sidebar under Users section

## Open Questions

- Should the import URL be a system_setting (admin-configurable) or a hardcoded constant? Given that it's a well-known stable URL with no auth, hardcode it — avoids the risk of an admin accidentally breaking it.
- Import timeout: 300 seconds should be sufficient for a ~60MB file on a reasonable connection. Make it a PHP constant so it can be adjusted per-deployment.
