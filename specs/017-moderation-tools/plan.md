# Implementation Plan: F15 — Moderation Tools

**Branch**: `016-operational-features` | **Date**: 2026-05-27 | **Spec**: [spec.md](spec.md)

## Summary

Build the admin moderation UI on top of the already-existing `mod_log`, `audit_log`, and `moderation_state` schema. New `app/moderation/manager.php` with suspend/ban/reinstate/mute functions. Login flow extended to check state and auto-reinstate expired suspensions. New admin moderation log page. User view page extended with moderation actions and history.

## Technical Context

**Language/Version**: PHP 8.3, strict types  
**Primary Dependencies**: PDO (MariaDB) — no new dependencies  
**Storage**: MariaDB — `mod_log`, `users.moderation_state`, `audit_log` (all existing)  
**No migration required**  
**Scale/Scope**: Low-frequency admin action; log page up to ~1000 entries paginated

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I — Simplicity | ✅ | Plain PHP; no new tables or dependencies |
| II — Security | ✅ | Role checks before all moderation actions; reason required |
| III — Public isolation | ✅ | New manager in app/moderation/; admin pages in public/admin/ |
| IV — Migrations | ✅ | No migration needed — schema already complete |
| V — Acceptance criteria | ✅ | Defined in spec.md US1-US5 |
| VI — Branch strategy | ✅ | Feature branch |
| VII — Mobile | ✅ | Action buttons and log table must be usable on tablet |
| VIII — Engine separation | ✅ | Mute integrates via existing whitelist filter, no HBLink direct coupling |

## Project Structure

```text
specs/017-moderation-tools/

app/moderation/
└── manager.php          ← new

app/auth/
├── login.php            ← update: add moderation_state check + auto-reinstate
└── session.php          ← update if needed

public/admin/moderation/
└── index.php            ← new: full mod_log with pagination

public/admin/users/
└── view.php             ← extend: moderation section with action forms + per-user history

public/admin/
└── (nav)                ← add Moderation Log link
```

## Implementation Phases

### Phase 1: Manager
- `app/moderation/manager.php` — all functions per data-model.md
- Verify `moderation_state` ENUM values match expected set

### Phase 2: Login flow hardening
- Audit `app/auth/login.php` and `session.php`
- Add `check_and_auto_reinstate()` call before state check
- Add suspended/banned rejection with reason message

### Phase 3: Admin moderation log page
- `public/admin/moderation/index.php` — paginated log table
- Moderators: read-only; admins: no extra buttons needed here (actions are on the user page)

### Phase 4: User view page extension
- Extend `public/admin/users/view.php` with moderation section
- Current state badge, action forms (suspend with reason+duration, ban with reason, mute, reinstate)
- Per-user mod_log history table below

### Phase 5: Nav
- Add "Moderation Log" link to admin sidebar

## Open Questions

- Should the registration email ban check be a hard block ("email unavailable") or a silent reject (act like it succeeded to prevent enumeration)? Leaning toward a vague "we couldn't complete your registration" message — avoids confirming the banned email exists.
- Duration for suspension: offer presets (1 day, 7 days, 30 days, custom) or free-form hours field? Presets are more user-friendly — implement as a `<select>` with a "custom" option that reveals an hours input.
