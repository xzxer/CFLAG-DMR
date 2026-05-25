# Feature Specification: F2 — Role & Permission System

**Feature Branch**: `003-role-permissions`

**Created**: 2026-05-25

**Status**: Draft

---

## Overview

CFLAG DMR currently uses a single flat table where every record has full administrative access. As the platform grows into a multi-tier network management system — serving registered users, moderators, node admins, and global administrators — a proper role and permission foundation is required. This feature replaces the flat model with an extensible role system and introduces a separate moderation state concept that controls both website access and radio network transmission access.

Every subsequent feature (user registration, hotspot management, talkgroup management, moderation tools, and all others) depends on this system being in place first.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Protected Pages Enforce Role Requirements (Priority: P1)

Any page or action that requires a specific privilege level must correctly allow or deny access based on the requesting user's role and moderation state, enforced server-side on every request.

**Why this priority**: This is the foundational correctness guarantee. All other user stories depend on the enforcement machinery being reliable. If access control is broken, every feature built on top of it is compromised.

**Independent Test**: Can be fully tested by creating test user accounts with different roles and moderation states, then verifying which pages each can and cannot reach — without needing any UI for managing roles.

**Acceptance Scenarios**:

1. **Given** a user with only the `user` role is logged in, **When** they attempt to access a page that requires `moderator` or higher, **Then** they are redirected to an appropriate error page or back to their dashboard.

2. **Given** a user with the `moderator` role is logged in, **When** they attempt to access a page that requires `admin` or higher, **Then** they are redirected and not granted access.

3. **Given** a user whose account is in `suspended` or `banned` state attempts to log in, **When** they submit valid credentials, **Then** login is denied and a generic message is shown ("Your account is not active.").

4. **Given** a user in `muted_on_network` state is logged in, **When** they access any page their role permits, **Then** they are allowed through with full website access; only their radio network access is affected.

5. **Given** a user with `system_admin` role is logged in, **When** they access any page in the system, **Then** they are granted access.

---

### User Story 2 — System Admin Assigns and Revokes Roles (Priority: P2)

A system admin can view any user's current roles and change them — promoting a regular user to moderator, demoting a moderator, or assigning admin privileges — and the change takes effect on the target user's next page load.

**Why this priority**: Role assignment is what makes the system operational. Once enforcement works, admins need to be able to configure who holds what role.

**Independent Test**: Can be fully tested by a system admin adding the `moderator` role to a test user account and verifying the test user can then access moderator-only pages.

**Acceptance Scenarios**:

1. **Given** a system admin is viewing a user's account page, **When** they add the `moderator` role, **Then** the role appears in the user's role list, the audit log records the change with actor, target, role, and timestamp, and the target user can access moderator-only pages on their next request.

2. **Given** a system admin is viewing a user's account page, **When** they remove the `moderator` role, **Then** the role is no longer listed, the audit log records the removal, and the target user is redirected away from moderator-only pages on their next request.

3. **Given** a user with the `admin` role attempts to assign the `system_admin` role to another user, **When** they submit the assignment, **Then** the system denies the action — admins cannot assign roles above their own level.

4. **Given** a role change is made to a currently logged-in user, **When** that user loads their next page, **Then** their session reflects the updated roles without requiring them to log out and back in.

---

### User Story 3 — Moderator Applies a Timed Network Mute (Priority: P3)

A moderator can place a user in the `muted_on_network` state for a fixed duration, silencing them on the radio network without affecting their website access. The mute automatically expires and the system flags that network config needs regeneration.

**Why this priority**: This is the first operational moderation capability. It is lower risk than suspension (website access is unaffected) and covers the most common moderation scenario (RF interference).

**Independent Test**: Can be fully tested by a moderator applying a 1-hour network mute to a test user and verifying the test user's moderation state changes to `muted_on_network`, the action appears in the moderation log, and the network regen flag is set.

**Acceptance Scenarios**:

1. **Given** a moderator is viewing a user's account page, **When** they apply a "Mute on network — 1 hour" action with a written reason, **Then** the user's moderation state changes to `muted_on_network`, the action is recorded in the moderation log (actor, target, reason, duration, timestamp), and a network config regeneration flag is set.

2. **Given** a user is in `muted_on_network` state and their mute has expired, **When** the expiry check runs, **Then** their state returns to `active` and the network regen flag is set again so their DMR ID is restored to the whitelist on next config apply.

3. **Given** a user in `muted_on_network` state is logged in, **When** they navigate any page their role permits, **Then** they experience no restriction — website access is fully normal.

---

### User Story 4 — Admin Suspends or Bans a User (Priority: P4)

An admin or system admin can directly change a user's moderation state to `suspended` or `banned`, immediately blocking their website login and flagging their DMR ID for removal from the network whitelist.

**Why this priority**: Suspension and bans are escalated actions. The lower-risk timed mute ships first (P3); this covers the higher-stakes scenarios that require direct admin action.

**Independent Test**: Can be fully tested by an admin suspending a test user and confirming the test user cannot log in, and the moderation log records the action.

**Acceptance Scenarios**:

1. **Given** an admin is viewing a user's account page, **When** they apply the `suspended` state with a required written reason, **Then** the user is immediately blocked from logging in, their DMR ID is flagged for whitelist removal on next regen, and the action is recorded in the moderation log.

2. **Given** an admin is viewing a suspended user's account, **When** they lift the suspension, **Then** the user's state returns to `active`, they can log in again, and the network regen flag is set so their DMR ID is restored.

3. **Given** an admin applies a `banned` state to a user, **When** a moderator or admin (non-system-admin) attempts to reverse the ban, **Then** the reversal is denied — only a system admin may reverse a permanent ban.

4. **Given** a banned user's account exists in the database, **When** any report or audit query references that user, **Then** the account record is visible in the audit log with its full history; it is not deleted.

---

### Edge Cases

- A user holds both `user` and `moderator` roles simultaneously. Access is granted at the highest applicable level (`moderator`).
- An admin tries to remove the last `system_admin` role from the only system admin account. The system must prevent this, leaving at least one system admin always present.
- A user is simultaneously `muted_on_network` and `suspended`. The most restrictive state (`suspended`) wins for website access. The DMR whitelist exclusion applies regardless.
- A role change is made to a user while their session is active. The change takes effect at the user's very next server-side request without requiring a re-login.
- A non-system-admin attempts to delete a system role (e.g., `moderator` from the roles table). The system rejects the deletion; system roles are immutable.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST store roles in a dedicated roles table with a flag distinguishing system roles (which cannot be deleted) from custom roles.
- **FR-002**: Role assignment MUST use a join table so a single user can hold multiple roles simultaneously.
- **FR-003**: The system MUST seed four system roles on migration: `user`, `moderator`, `admin`, `system_admin`. These roles MUST NOT be deletable.
- **FR-004**: Every protected page or action MUST verify the user's roles server-side on each request. Session-stored role claims alone are insufficient; the authoritative source is the database.
- **FR-005**: A reusable permission check MUST be available to all subsequent features, accepting a user identity and a minimum required role, returning a pass/fail result.
- **FR-006**: Users MUST be able to hold multiple roles simultaneously. The most permissive applicable role determines the access level.
- **FR-007**: Admins MUST be able to assign roles up to and including `admin` to other users. Only system admins may assign or revoke the `system_admin` role.
- **FR-008**: The system MUST prevent any action that would remove the `system_admin` role from the last remaining system admin account.
- **FR-009**: All role assignment and revocation actions MUST be recorded in the audit log with: actor user ID, target user ID, role name, action (assigned/revoked), and timestamp.
- **FR-010**: The users table MUST include a `moderation_state` field with four possible values: `active`, `suspended`, `banned`, `muted_on_network`. Default is `active`.
- **FR-011**: Users in `suspended` or `banned` state MUST be denied login regardless of valid credentials. The error message shown MUST NOT reveal which state applies.
- **FR-012**: Users in `muted_on_network` state MUST be able to log in and use all website features their role permits. No website restriction is applied.
- **FR-013**: Any change to `moderation_state` that affects radio network access (`suspended`, `banned`, or `muted_on_network`) MUST set a network config regeneration flag (a record or flag indicating a regen is pending). The actual restart is out of scope for this feature.
- **FR-014**: Timed network mutes MUST record an expiry timestamp. A background check MUST automatically return timed-out mutes to `active` and set the regen flag.
- **FR-015**: All moderation state changes MUST be recorded in a moderation log with: actor user ID, target user ID, state applied, reason (required text field), duration (for timed mutes), and timestamp.
- **FR-016**: The existing `admin_users` table records MUST be migrated into the new `users` table with `system_admin` role assigned. Passwords MUST carry over without requiring a reset.
- **FR-017**: After migration, all authentication and authorization checks MUST use the new `users` table. The `admin_users` table is retained but no longer checked.

### Key Entities

- **Role**: A named permission level. Has a unique name, display name, description, and a flag indicating whether it is a system role. System roles cannot be deleted. Four system roles exist: `user`, `moderator`, `admin`, `system_admin`.
- **User**: A person with an account on the platform. Has a unique identifier, credentials (inherited from admin_users migration for existing records), a `moderation_state` (active/suspended/banned/muted_on_network), and zero or more assigned roles via the role assignment relationship.
- **Role Assignment**: A relationship between a user and a role. Records which user assigned it and when. A user may have multiple active role assignments.
- **Moderation Log Entry**: A record of every moderation state change. Captures who changed what, to what state, with what reason, at what time, and with what expiry (for timed actions).
- **Audit Log Entry**: A record of every administrative action (including role changes). Captures actor, target, action type, and timestamp.
- **Network Regen Flag**: An indicator that the network configuration is stale and needs to be regenerated before the next HBLink restart. Set whenever a moderation state change affects DMR whitelist membership.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A system admin can assign or revoke a role on any user account within 3 clicks from the user's account page, with the change visible in the audit log immediately after.
- **SC-002**: A user with an incorrect role attempting to access a restricted page is denied access within the same page load — no secondary round-trip is required.
- **SC-003**: A suspended or banned user attempting to log in receives a denial response; at no point can they access any authenticated page.
- **SC-004**: A timed network mute correctly expires — the user's moderation state returns to `active` and the network regen flag is set — within 5 minutes of the scheduled expiry time.
- **SC-005**: All four user stories are verifiable in the development environment without external dependencies: role assignment, access enforcement, timed mute, and direct suspension/ban.
- **SC-006**: The migration from `admin_users` to the new `users` table is completed without any existing admin losing the ability to log in.
- **SC-007**: The permission check helper is used successfully by at least one subsequently built feature (F3 or later) without modification to this feature's code.

---

## Assumptions

- The feature ships on the `dev` branch alongside a migration that creates the new `users`, `roles`, `user_roles`, `mod_log`, and `audit_log` tables and migrates existing `admin_users` data.
- The `admin_users` table is retained after migration for historical reference but is no longer the active authentication source.
- The network regen flag is implemented as a row in a `pending_actions` table or a simple flag column on a `network_state` table. The exact implementation is determined in the plan phase. What matters in the spec is that the flag exists and is queryable by F13.
- Timed mute expiry is checked by a scheduled job (cron). The cron interval is determined in the plan phase; the spec requires only that expiry happens within 5 minutes of the scheduled time.
- The audit log and moderation log are separate concerns: the audit log is for all administrative actions system-wide; the moderation log is the specific record of user moderation events (mutes, suspensions, bans).
- A user's active session is not forcibly invalidated when their role changes. The role change takes effect on their next page load. For moderation state changes (suspension, ban), the next login attempt is blocked; an active session is not immediately killed in this feature (session invalidation can be added in a future security hardening pass).
- This feature does not implement any UI beyond what is needed to verify the four user stories — specifically: a basic user list page for admins, an account detail page with role and moderation controls, and the access-denied redirect behavior.

---

## Out of Scope

- User registration form and signup flow (F3)
- Email sending or email verification (F3)
- Radio network whitelist regeneration and HBLink restart (F13)
- Club-level roles — those are per-club memberships, not network-wide roles (F19)
- Moderation request queue UI where moderators submit suspension/ban requests for admin approval (F11)
- Feature tier (free/gold/premium) gating — the `tier` column is added to the users table but no tier-based access control is enforced in this feature
- Password reset or account recovery flows (future feature)
- Forced session invalidation when a user is suspended mid-session (future security hardening)
