# Feature Specification: User Registration & Accounts

**Feature Branch**: `004-user-registration`

**Created**: 2026-05-25

**Status**: Draft

**Depends on**: F2 (Role & Permission System) — `users` table and auth layer must exist

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Public Registration (Priority: P1)

A licensed amateur radio operator visits the CFLAG DMR website, fills in the registration form with their callsign, DMR ID, email, password, and display name, and submits it. If all fields are valid (and the callsign/DMR ID match RadioID.net records when validation is enabled), an account is created and a verification email is sent (or logged in dev mode). The user sees a confirmation page telling them to check their email.

**Why this priority**: This is the entire point of the feature — without it, no user accounts can be created and no downstream features are accessible.

**Independent Test**: Navigate to `/register.php`, submit valid registration details, confirm the account is created in the `users` table with `email_verified_at = NULL` and `moderation_state = 'active'`, and confirm the verification token appears in the dev log.

**Acceptance Scenarios**:

1. **Given** a visitor at `/register.php`, **When** they submit a form with a valid callsign, 7-digit DMR ID, unique email, password ≥ 12 characters, and display name 2–64 characters, **Then** an account is created, a verification token is written to the dev log, and the user sees a confirmation page.
2. **Given** a registration form submission, **When** any required field is missing or invalid, **Then** the form is redisplayed with a per-field error message and no account is created.
3. **Given** a registration form submission, **When** the email is already registered, **Then** a generic "already in use" error is shown without confirming whether it is the email, callsign, or DMR ID that conflicts.
4. **Given** RadioID.net validation is enabled and the submitted DMR ID is found, **When** the callsign on the RadioID.net record does not match the submitted callsign, **Then** registration is rejected with a message stating the callsign and DMR ID do not match RadioID.net records.
5. **Given** RadioID.net validation is enabled, **When** the RadioID.net API is unreachable, **Then** registration proceeds on format-only validation and the lookup failure is written to the application log.
6. **Given** a valid callsign submitted in mixed case, **When** the account is created, **Then** the callsign is stored in uppercase.

---

### User Story 2 — Email Verification (Priority: P2)

After registering, the user receives a verification link (in dev: copied from the log). When they visit the link, their account is marked verified and they are redirected to the login page with a success message. They can then log in normally.

**Why this priority**: Without verification, the registration flow has no exit ramp to a working account. Users who register but cannot verify are permanently locked out.

**Independent Test**: Retrieve the verification token from the dev log, visit `/verify-email.php?token=...`, confirm `email_verified_at` is set in `users`, confirm redirect to `/login.php` with success message, confirm login succeeds.

**Acceptance Scenarios**:

1. **Given** an unverified account, **When** the user visits `/verify-email.php` with a valid unused token, **Then** `email_verified_at` is set, the token is marked used, and the user is redirected to `/login.php` with a success flash message.
2. **Given** a verification token that has expired (older than 24 hours), **When** the user visits the link, **Then** they see an error message and a link to request a new verification email.
3. **Given** a verification token that has already been used, **When** the user visits the link again, **Then** they see an informational message directing them to log in.
4. **Given** a token that does not exist, **When** the user visits the link, **Then** they see a generic invalid-token error.

---

### User Story 3 — Login with Unverified Account / Resend Verification (Priority: P3)

A user who registered but has not yet verified their email attempts to log in. The login page shows a specific message: "Please verify your email address before logging in," with a link to request a new verification email. The resend page accepts their email address and sends (or logs) a fresh token, always showing the same success message regardless of whether the address was found.

**Why this priority**: Without this path, users who lose their verification email have no way forward without admin intervention.

**Independent Test**: Attempt login with an unverified account, confirm the specific message and resend link appear. Submit the resend form, confirm a new token appears in the dev log and the old token is invalidated.

**Acceptance Scenarios**:

1. **Given** a registered but unverified account, **When** the user submits correct credentials on the login form, **Then** login is rejected with "Please verify your email address before logging in" and a resend link — not the generic "Invalid username or password" message.
2. **Given** a user on the resend page, **When** they submit an email address that belongs to an unverified account, **Then** any existing unused tokens for that account are invalidated, a new token is created, and the token is written to the dev log.
3. **Given** a user on the resend page, **When** they submit an email address that is not registered, **Then** the page shows the same success message as a valid email (no enumeration).
4. **Given** a user who requested a resend 2 minutes ago, **When** they attempt another resend for the same email, **Then** the request is rejected with a "please wait" message (rate limit: one resend per email per 5 minutes).

---

### User Story 4 — Admin Manages Unverified Accounts (Priority: P4)

A system admin can view the verification status of any account on the user view page, manually mark an unverified account as verified, resend the verification email, or delete unverified accounts.

**Why this priority**: Operational necessity. Admins need the ability to unblock users who cannot complete verification (e.g., email delivery issues).

**Independent Test**: On `/admin/users/view.php?id={id}` for an unverified user, confirm the verification status section is shown. Use the "Manually verify" action and confirm `email_verified_at` is set. Use "Resend verification" and confirm a new token appears in the dev log. Delete an unverified account from the user list.

**Acceptance Scenarios**:

1. **Given** an unverified account on the admin user view page, **When** the admin clicks "Manually verify," **Then** `email_verified_at` is set to the current time and the account is marked verified.
2. **Given** an unverified account, **When** the admin clicks "Resend verification email," **Then** any existing unused tokens are invalidated, a new token is created, and the link is written to the dev log.
3. **Given** the admin user list, **When** the admin filters by "Unverified," **Then** only accounts with `email_verified_at = NULL` are shown.
4. **Given** an unverified account on the admin user view page, **When** the admin deletes it, **Then** the account and all associated tokens are removed.

---

### User Story 5 — Login by Email or Username (Priority: P5)

The existing login form accepts either a username or an email address in the username field. If the input contains `@`, it is treated as an email address; otherwise it is treated as a username.

**Why this priority**: Convenience improvement to an existing flow; does not block any other story but improves usability for all registered users.

**Independent Test**: Log in using the email address of a verified account and confirm success. Log in using the username of the same account and confirm success.

**Acceptance Scenarios**:

1. **Given** a verified account, **When** the user enters their email address in the username field and correct password, **Then** login succeeds.
2. **Given** a verified account, **When** the user enters their username and correct password, **Then** login succeeds (existing behaviour, unchanged).

---

### Edge Cases

- What happens when a user registers with a callsign that is already taken? → Generic "already in use" message; does not specify which field conflicts.
- What happens when two users submit registrations with the same email simultaneously? → Database unique constraint prevents duplicate; the second request receives a validation error.
- What happens when a verification token expires while the user has the verification page open? → The POST to verify fails with an "expired token" error; resend link shown.
- What happens when the RadioID.net API returns an unexpected response format? → Treated as unreachable; format-only validation applied; failure logged.
- What happens if an admin deletes a user whose verification token link is still active? → Token lookup finds no matching user; treated as invalid token.
- What happens when a `suspended` or `banned` user tries to log in? → Generic "Invalid username or password." (unchanged from F2 behaviour).

---

## Requirements *(mandatory)*

### Functional Requirements

**Registration**

- **FR-001**: The system MUST provide a public registration form at `/register.php` collecting callsign, DMR ID, email, password, and display name.
- **FR-002**: The system MUST validate callsign format as 1–2 letter prefix, 1 digit, 1–3 letter suffix (case-insensitive; stored uppercase).
- **FR-003**: The system MUST validate that DMR ID is a 7-digit integer.
- **FR-004**: The system MUST enforce uniqueness of callsign, DMR ID, and email across the `users` table.
- **FR-005**: The system MUST require passwords of at least 12 characters and store them as bcrypt hashes.
- **FR-006**: The system MUST require display names between 2 and 64 characters.
- **FR-007**: Validation errors MUST be shown per-field inline; uniqueness conflicts MUST use a generic "already in use" message that does not identify which field is taken.
- **FR-008**: On successful registration, the system MUST create the user with `moderation_state = 'active'`, `tier = 'free'`, and `email_verified_at = NULL`.

**RadioID.net Validation**

- **FR-009**: When `system_settings.radioid_validation_enabled = 1`, the system MUST look up the submitted DMR ID against the RadioID.net API server-side before creating the account.
- **FR-010**: If the RadioID.net lookup succeeds, the callsign on the returned record MUST match the submitted callsign (case-insensitive); a mismatch MUST reject registration.
- **FR-011**: If the RadioID.net API is unreachable, the system MUST fall back to format-only validation and log the failure; it MUST NOT reject the registration solely due to API unavailability.

**Email Verification**

- **FR-012**: On successful registration, the system MUST generate a single-use 64-character hex token stored in `email_verifications` with a 24-hour expiry and send (or log in dev mode) the verification link.
- **FR-013**: The system MUST mark a token as used and set `users.email_verified_at` when a valid, unexpired, unused token is presented at `/verify-email.php`.
- **FR-014**: Expired tokens MUST show an error with a link to request a new verification email.
- **FR-015**: Already-used tokens MUST show an informational message directing the user to log in.
- **FR-016**: In the dev environment, the verification link MUST be written to `/var/log/cflag-dmr-email-dev.log` instead of sending an email.

**Login Changes**

- **FR-017**: The login form MUST accept either a username or an email address in the identifier field (email detected by presence of `@`).
- **FR-018**: An unverified account that presents correct credentials MUST be rejected with "Please verify your email address before logging in" and a resend link — NOT the generic error message.
- **FR-019**: `suspended` and `banned` accounts MUST continue to receive the generic "Invalid username or password." message (unchanged from F2).

**Resend Verification**

- **FR-020**: `/resend-verification.php` MUST be POST-only, CSRF-protected, and accept an email address.
- **FR-021**: On a valid resend request, the system MUST invalidate any existing unused tokens for the account before creating a new one.
- **FR-022**: The resend endpoint MUST always return the same success message regardless of whether the submitted email belongs to an unverified account (prevent enumeration).
- **FR-023**: The system MUST rate-limit resend requests to one per email address per 5 minutes.

**Admin Controls**

- **FR-024**: The admin user view page MUST display the email verification status and `email_verified_at` timestamp (or "Not verified") for each account.
- **FR-025**: System admins MUST be able to manually verify an account (sets `email_verified_at = NOW()`).
- **FR-026**: System admins MUST be able to trigger a resend of the verification email from the admin view page.
- **FR-027**: The admin user list MUST support filtering by verification status (verified / unverified).
- **FR-028**: System admins MUST be able to delete unverified accounts from the admin interface.

### Key Entities

- **User** (extends F2 `users` table): gains `callsign` (unique, stored uppercase), `email_verified_at` (nullable timestamp). `dmr_id` (already present) must be enforced as unique integer.
- **Email Verification Token**: single-use token record with `user_id`, `token` (64-char hex), `created_at`, `expires_at`, `used_at` (nullable). Linked to a user; invalidated on use or when a new token is issued.
- **System Setting** (`radioid_validation_enabled`): boolean flag in `system_settings` table controlling whether RadioID.net API validation is enforced at registration.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new user can complete the registration form and receive a verification link in under 30 seconds on a standard connection, assuming RadioID.net responds within 3 seconds.
- **SC-002**: A user who has received a verification link can activate their account and reach the login page within 3 clicks (visit link → verify → login page).
- **SC-003**: A user who has lost their verification email can request a resend and receive a new link within 1 minute, subject to the 5-minute rate limit after the first request.
- **SC-004**: An admin can manually verify or delete an unverified account in a single page interaction without leaving the user view page.
- **SC-005**: All uniqueness conflicts and validation errors are surfaced per-field without revealing information about other existing registrations.
- **SC-006**: RadioID.net API unavailability does not block any registration; the fallback is seamless to the user.
- **SC-007**: No unverified account can log in to the website under any circumstance.

---

## Assumptions

- Email delivery infrastructure is out of scope for this feature; the dev log file is the only output mechanism and is sufficient for testing.
- A `callsign` column does not yet exist on the `users` table and must be added via migration. `dmr_id` exists but currently lacks a database-level unique constraint — the migration must add one.
- `email_verified_at` column does not yet exist on `users` and must be added via migration.
- The `system_settings` table (referenced in the project overview) exists or will be created as part of this feature's migrations; `radioid_validation_enabled` is a row in that table defaulting to `1`.
- RadioID.net API is treated as a third-party service with an assumed p95 response time of ≤ 3 seconds; registration requests will apply a 5-second timeout.
- Existing migrated admin accounts (from F2) have `email_verified_at = NULL`; the F3 migration must backfill `email_verified_at = created_at` for all existing records so they are not locked out.
- The login form's identifier field label will be updated from "Username" to "Username or Email" to reflect the new dual-input behaviour.
- Password strength is enforced by length (≥ 12 characters) only; no complexity rules for this feature.
- Account deletion from the admin UI cascades to `email_verifications` and `user_roles` (FK cascade already defined in F2 schema).
