# Implementation Plan: User Profiles (F4)

**Branch**: `011-user-profiles` | **Date**: 2026-05-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/011-user-profiles/spec.md`

## Summary

F4 adds user self-service profile management. Users can update their display name and change their password (US1). Email address changes require re-verification via a token-based flow (US2). Callsign changes go through an admin-approval request (US3). Admins can view any user's full profile with moderation history (US4). The `users` table already has the required columns. New migrations add email_change_requests and callsign_update_requests tables.

## Technical Context

**Language/Version**: PHP 8.3, strict types

**Primary Dependencies**: Existing `app/auth/` (session, roles, login), existing `app/email/` (email sending), PDO/MariaDB

**Storage**: Two new tables: `email_change_requests`, `callsign_update_requests`. All other data is in the existing `users` table.

**Testing**: Manual browser testing per quickstart.md scenarios

**Target Platform**: Linux server (Apache/PHP 8.3)

**Project Type**: Web application (user profile + admin view)

**Constraints**: Username is immutable (login identifier). Password min 8 characters (FR-004). Email change token expires in 24 hours (FR-007). Callsign updates cascade to all `devices` rows (FR-010). Only one open callsign_update_request per user (FR-009).

**Scale/Scope**: Per-user self-service. Admin view integrates with existing `/admin/users/` area.

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Simplicity | ✅ Pass | Plain PHP. New `app/profile/` module. No framework. |
| II. Security First | ✅ Pass | Current password required for password change (FR-003). Email change uses secure tokens (same pattern as F3). No CSRF bypass. |
| III. Public Directory Isolation | ✅ Pass | Profile logic in `app/profile/`. Pages in `public/user/profile.php` and `public/admin/users/profile.php`. |
| IV. Database Integrity | ✅ Pass | Two new tables as numbered migrations (013, 014). |
| V. Feature Quality | ✅ Pass | Each US has testable acceptance scenarios. |
| VI. Branch Strategy | ✅ Pass | Branched from dev as `011-user-profiles`. |
| VII. Mobile-First | ✅ Pass | Profile forms use existing card/field-row CSS pattern. |
| VIII. Scale/Observability | ✅ Pass | Email change and callsign requests are DB-tracked. |

## Project Structure

### Documentation (this feature)

```text
specs/011-user-profiles/
├── plan.md              ← this file
├── spec.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── profile.md
└── checklists/
    └── requirements.md
```

### Source Code

```text
migrations/
├── 013_create_email_change_requests.sql
└── 014_create_callsign_update_requests.sql

app/
└── profile/
    ├── manager.php      ← get_profile(), update_display_name(), change_password()
    ├── email_change.php ← request_email_change(), confirm_email_change(), get_pending_email_change()
    └── callsign.php     ← submit_callsign_request(), approve_callsign_request(), deny_callsign_request(), get_pending_callsign_requests()

public/
├── user/
│   └── profile.php      ← US1+US2+US3: view/edit own profile
├── verify-email-change.php ← US2: email change confirmation landing page
└── admin/
    └── users/
        └── profile.php  ← US4: admin view of any user's profile (extends existing area)
```

**Structure Decision**: New `app/profile/` module following the existing app/ subdirectory pattern. Profile page extends the existing `public/user/` area. Admin profile view extends existing `public/admin/users/`.

## Phase 0: Research

See `research.md`

Key decisions:
- Email change token: same table-based token pattern as F3 (`email_verification_tokens`) — new `email_change_requests` table with token, expiry, and new_email
- Callsign cascade: `UPDATE devices SET callsign = ? WHERE user_id = ?` on approval (within a transaction with the user record update)
- Password change: `password_verify($current, $hash)` then `password_hash($new, PASSWORD_BCRYPT)` — same as existing F3 pattern

## Phase 1: Design & Contracts

See `data-model.md` and `contracts/profile.md`
