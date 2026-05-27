# Feature Specification: F15 — Moderation Tools

**Feature ID**: F15  
**Branch**: `016-operational-features` | **Spec Dir**: `specs/017-moderation-tools/`  
**Created**: 2026-05-27 | **Status**: Planning

---

## Overview

Admins and moderators need tools to manage disruptive network users: suspend accounts, ban accounts, mute users on the network, and maintain an audit trail of all moderation actions. The `mod_log` table and `moderation_state` column already exist. This feature builds the admin UI and PHP layer that uses them.

---

## User Stories

### US1 — Suspend a user (P1)
**As an admin**, I want to suspend a user account with a reason and optional expiry, so the user cannot log in during the suspension period.

**Acceptance criteria:**
- Given I click "Suspend" on a user's admin profile, enter a reason and optional duration, the user's `moderation_state` becomes 'suspended' and a `mod_log` record is created
- Given a suspended user tries to log in, they see "Your account is suspended" with the reason shown
- Given a suspension has an expiry and it has passed, the user can log in again (checked on login)

### US2 — Ban a user (P1)
**As an admin**, I want to permanently ban a user, so they cannot log in or register again with the same email.

**Acceptance criteria:**
- Given I click "Ban" on a user's admin profile, enter a reason, the user's `moderation_state` becomes 'banned' and a `mod_log` record is created
- Given a banned user tries to log in, they see "Your account has been banned"
- Given a banned user's email tries to register, registration is rejected

### US3 — Reinstate a user (P1)
**As an admin**, I want to reinstate a suspended or banned user, so I can reverse a moderation decision.

**Acceptance criteria:**
- Given a suspended or banned user, when I click "Reinstate", their `moderation_state` returns to 'active' and a `mod_log` record is created with action='reinstated'

### US4 — View moderation log (P1)
**As an admin or moderator**, I want to view the full moderation history for the network, so I can audit who took what action and when.

**Acceptance criteria:**
- Given I navigate to Admin → Moderation Log, I see a paginated table of all mod_log records: actor, target user, action, reason, timestamp
- Given I click on a user's name in the log, I go to their admin profile
- Moderators can view the log but cannot take actions (read-only for moderator role)

### US5 — Per-user moderation history (P2)
**As an admin**, I want to see all moderation actions for a specific user on their admin profile page, so I have full context when making a decision.

**Acceptance criteria:**
- Given I view a user's admin profile, a "Moderation History" section shows all mod_log entries targeting that user

---

## Functional Requirements

1. Suspension and banning use `moderation_state` ENUM already on the `users` table
2. Every moderation action writes a `mod_log` record (actor, target, action, reason, duration_hours, expires_at)
3. Login flow checks `moderation_state` and `expires_at` before granting session
4. Banned emails are checked at registration (query users WHERE email = ? AND moderation_state = 'banned')
5. Moderators can view all moderation log entries but cannot take actions — actions are admin-only
6. `muted_on_network` state: user can log in but their DMR ID is removed from the whitelist (excluded from next config generation). Not to be confused with account suspension.
7. All moderation actions are also recorded in `audit_log`
8. No "appeal" workflow in MVP — admin reinstates manually

---

## Out of Scope

- Automated expiry checking via cron (expiry checked on login attempt only for MVP)
- User-facing appeal form
- Moderator-initiated bans (moderators can request, admins approve — deferred)
- IP-based bans

---

## Assumptions

- The existing `moderation_state` ENUM already covers: 'active', 'suspended', 'banned', 'muted_on_network'
- The `mod_log` table schema (actor_user_id, target_user_id, action, reason, duration_hours, expires_at) is sufficient as-is
- Login flow already checks `moderation_state` for 'suspended' and 'banned' (if not, this feature adds those checks)

---

## Success Criteria

- An admin can suspend, ban, or reinstate any user in under 30 seconds from the user's profile page
- All moderation actions are visible in the moderation log within 1 second of being taken
- Suspended/banned users cannot log in; banned emails cannot register
