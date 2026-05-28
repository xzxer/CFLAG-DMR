# Feature Specification: User Profiles

**Feature Branch**: `011-user-profiles`

**Created**: 2026-05-26

**Status**: Draft

**Input**: F4: User Profiles — Registered users can view and edit their own profile: display name, email address, password change, and their primary DMR ID. Users see their account status, registration date, and can request a callsign update if their license changes. Admins can view any user's profile and see their full account history.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View & Edit Own Profile (Priority: P1)

A logged-in user navigates to their profile page and sees their current display name, username, email address, account status, and registration date. They can update their display name and change their password. Changes take effect immediately after saving.

**Why this priority**: A profile page is the minimum expected self-service for any account system. Display name and password are the most frequently updated fields and the highest user expectation.

**Independent Test**: Can be fully tested by logging in, visiting the profile page, updating the display name, logging out, and confirming the new name appears on the dashboard greeting.

**Acceptance Scenarios**:

1. **Given** a user is logged in, **When** they visit their profile page, **Then** their current display name, username, email, and registration date are shown
2. **Given** a user enters a new display name and saves, **When** they return to the dashboard, **Then** the greeting uses the new display name
3. **Given** a user submits a password change with a mismatched confirmation, **When** the form is submitted, **Then** an error is shown and the password is NOT changed
4. **Given** a user submits a valid password change (current + new + confirmation all match), **When** the form is submitted, **Then** the password is updated and the user remains logged in
5. **Given** a user attempts to set a password under 8 characters, **When** the form is submitted, **Then** a validation error is shown
6. **Given** a user views their profile, **When** they have a suspended or banned account status, **Then** their account state is clearly shown on the profile page

---

### User Story 2 - Update Email Address (Priority: P2)

A user can change their email address from their profile page. Because email is used for verification and potential password reset, a change triggers a re-verification flow: the new address receives a confirmation email, and the change does not take effect until confirmed. The old email remains active until the new one is verified.

**Why this priority**: Email changes are a distinct flow with security implications (re-verification). Separating this from US1 allows US1 to be simpler and ship first. Email changes are less frequent than display name updates.

**Independent Test**: Can be tested by requesting an email change, confirming the verification email arrives, clicking the link, and verifying the profile now shows the new address.

**Acceptance Scenarios**:

1. **Given** a user enters a new email address and submits, **When** the request is processed, **Then** a verification email is sent to the new address and a pending-change notice is shown on the profile
2. **Given** a pending email change exists, **When** the user clicks the confirmation link, **Then** the email address is updated and the profile shows the new address
3. **Given** a pending email change exists, **When** the user requests a second email change, **Then** the first pending change is cancelled and a new verification is sent to the second address
4. **Given** a user submits an email address already used by another account, **When** the request is processed, **Then** an error is shown and no verification email is sent
5. **Given** a verification link expires (after 24 hours), **When** the user clicks it, **Then** an error page explains the link has expired with an option to request a new one

---

### User Story 3 - Callsign Update Request (Priority: P3)

A user whose amateur radio callsign has changed (license upgrade, vanity call, new license) can submit a callsign update request from their profile page. The request includes their new callsign and a brief explanation. Admins review and approve or deny the request, after which the callsign is updated across their devices.

**Why this priority**: Callsign changes are infrequent but important for regulatory compliance. Placing them in a request/approval workflow (rather than self-service) prevents abuse. P3 because it is less urgent than core profile editing.

**Independent Test**: Can be tested by submitting a callsign change request, approving it as admin, and verifying the user's devices reflect the new callsign.

**Acceptance Scenarios**:

1. **Given** a user submits a callsign update request with a new callsign, **When** the request is saved, **Then** the user sees a "pending review" status on their profile and cannot submit another request while one is open
2. **Given** an admin approves a callsign update request, **When** the approval is saved, **Then** the user's username and all their device callsign fields are updated to the new callsign
3. **Given** an admin denies a callsign update request, **When** the denial is saved, **Then** the user sees the denial with the admin's reason and can submit a new request
4. **Given** a user tries to submit a callsign already in use by another account, **When** the form is submitted, **Then** an error is shown and the request is NOT created

---

### User Story 4 - Admin Views Any User Profile (Priority: P4)

A system admin can navigate to any user's profile to see their full account details: display name, username, email, roles, registration date, moderation state, devices, and recent activity. Admins can also see moderation history (suspensions, bans, role changes) for the user from this view.

**Why this priority**: Admin visibility into user accounts is an operational need for handling support requests and moderation. P4 because it builds on the user-facing profile and on the existing admin user management pages.

**Independent Test**: Can be tested by navigating to the admin user list, clicking a user, and verifying full account details and device list are shown. Verified that non-admins cannot access other users' profiles.

**Acceptance Scenarios**:

1. **Given** a system admin navigates to a user's profile, **When** the page loads, **Then** the full account record is shown including roles, devices, and moderation state
2. **Given** a user has moderation history (suspensions or bans), **When** an admin views their profile, **Then** the moderation log entries are shown with timestamps and reasons
3. **Given** a non-admin user attempts to access another user's profile URL, **When** the page is requested, **Then** a 403 response is returned and the user is redirected to their own dashboard
4. **Given** an admin views a user's profile, **When** the user has no devices, **Then** the devices section shows "No devices registered"

---

### Edge Cases

- What if a user has no display name set (only username)? (Profile page shows username as display name fallback; form allows setting a display name for the first time)
- What if the password change form is submitted without the current password? (Current password is required and missing it produces a validation error, not an authentication bypass)
- What if a callsign update request is submitted but the new callsign fails format validation? (Form-level validation rejects invalid callsign formats before submission)
- What happens to pending email change requests if the user is banned? (Pending verification links are invalidated; no email change can complete while banned)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow logged-in users to view their own display name, username, email, account status, and registration date
- **FR-002**: System MUST allow users to update their display name with immediate effect
- **FR-003**: System MUST require users to provide their current password when setting a new password
- **FR-004**: System MUST enforce a minimum password length of 8 characters
- **FR-005**: System MUST send a verification email to a new address before applying an email change
- **FR-006**: System MUST keep the old email active until the new email is verified
- **FR-007**: Email change verification links MUST expire after 24 hours
- **FR-008**: System MUST allow users to submit a callsign update request with a new callsign and explanation
- **FR-009**: System MUST prevent users from having more than one open callsign update request at a time
- **FR-010**: System MUST update all device callsign records when a callsign update request is approved by an admin
- **FR-011**: System MUST allow system admins to view any user's full profile including roles, devices, and moderation history
- **FR-012**: System MUST reject attempts by non-admin users to view other users' profiles with a 403 response

### Key Entities

- **UserProfile**: The display surface of a user record — display name, username, email, status, registration date, roles
- **EmailChangeRequest**: A pending email address change with new address, verification token, expiry timestamp, and status
- **CallsignUpdateRequest**: A pending callsign change with old callsign, requested callsign, explanation, status, and admin review notes

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can update their display name in under 30 seconds from profile page load to saved confirmation
- **SC-002**: Email change verification links are sent within 60 seconds of the user submitting the request
- **SC-003**: A callsign update request flows from user submission to admin decision without requiring any out-of-band communication (all tracking is in-app)
- **SC-004**: Non-admin users cannot view any other user's profile — verified by attempting direct URL access
- **SC-005**: All profile forms provide clear, specific validation errors for every invalid input before any database write occurs

## Assumptions

- Username (used as login identifier) is NOT changeable via this feature; only display name and callsign are editable
- The existing email verification system (from F3 User Registration) is reused for the email change verification flow
- Callsign is stored as a field on the user record and propagated to all device records on approval; the username remains the login identifier and is not affected by callsign changes
- Password hashing uses the same mechanism already in place from F3 (bcrypt via PHP password_hash/password_verify)
- Admins viewing user profiles use the existing user management pages at `/admin/users/` as the entry point; the profile detail view is an extension of that area
- Email change tokens are stored in the database (similar to email verification tokens from F3) and expire after 24 hours
- The `users` table already has `display_name`, `username`, `email`, `moderation_state`, and `created_at` columns from F3
