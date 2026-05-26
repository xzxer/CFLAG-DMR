# Feature Specification: Extended User Profiles

**Feature Branch**: `013-extended-profiles`

**Created**: 2026-05-26

**Status**: Draft

**Input**: F11: Extended User Profiles — Registered users can enrich their public profile with optional amateur radio operator details: first name, last name, grid square locator, a short bio, and a phone number (private, admin-visible only). All new fields are optional and editable from the existing profile page. Profile visibility follows a simple rule: callsign, grid square, and bio are shown to any logged-in user; phone is never shown publicly; first/last name is shown to logged-in users only if the user opts in. A new migration adds these columns to the users table. Admins can see all fields including phone on the admin user view page.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Edit Extended Profile Fields (Priority: P1)

A logged-in user visits their profile page and finds a new "Extended Profile" section with optional fields: first name, last name, grid square, bio, and phone number. They can fill in any or all fields and save. A separate opt-in checkbox controls whether their first and last name are visible to other logged-in users. All fields are optional — leaving them blank is valid. Changes take effect immediately.

**Why this priority**: This is the data-entry foundation everything else depends on. Without it, there is no extended profile data to display or search.

**Independent Test**: Log in, visit the profile page, fill in grid square and bio, save, log out, log back in — confirm the values persisted and appear on the profile page.

**Acceptance Scenarios**:

1. **Given** a logged-in user visits their profile page, **When** they scroll to the extended profile section, **Then** they see editable fields for first name, last name, grid square, bio, and phone number, plus a "Show my name publicly" checkbox
2. **Given** a user fills in a grid square value, **When** they save, **Then** the value is stored and displayed back on their profile page
3. **Given** a user leaves all extended fields blank, **When** they save, **Then** the save succeeds with no errors and existing required fields are unaffected
4. **Given** a user enters an invalid grid square format, **When** they save, **Then** a validation error is shown and nothing is saved
5. **Given** a user fills in a bio over 500 characters, **When** they save, **Then** a validation error is shown indicating the character limit
6. **Given** a user checks "Show my name publicly" and enters first/last name, **When** they save, **Then** the opt-in preference is persisted alongside the name values

---

### User Story 2 - Profile Visibility to Other Users (Priority: P2)

When a logged-in user views another user's public profile (via the directory or a direct link), they see the extended profile data according to visibility rules: callsign, grid square, and bio are always shown to logged-in users; first and last name are shown only if the profile owner opted in; phone number is never visible to other users.

**Why this priority**: Visibility rules are what make the extended profile meaningful to the community. Without them the data has no public-facing value.

**Independent Test**: Create two test accounts — fill in extended fields on one with name opt-in enabled, view from the second account, confirm bio/grid/name visible; then disable name opt-in, confirm name no longer appears.

**Acceptance Scenarios**:

1. **Given** User A has a bio and grid square set, **When** User B views User A's profile, **Then** User B sees the bio and grid square
2. **Given** User A has name opt-in enabled with first/last name filled in, **When** User B views User A's profile, **Then** User B sees the full name
3. **Given** User A has name opt-in disabled, **When** User B views User A's profile, **Then** User B does NOT see first or last name
4. **Given** User A has a phone number set, **When** any non-admin user views User A's profile, **Then** the phone number is NOT shown
5. **Given** User A has left bio and grid square blank, **When** User B views User A's profile, **Then** those fields are simply absent (not shown as empty placeholders)
6. **Given** a non-authenticated visitor accesses a profile URL, **When** the page loads, **Then** they are redirected to login

---

### User Story 3 - Admin View of Full Profile (Priority: P3)

An admin viewing a user's detail page in the admin panel sees all extended profile fields including phone number, regardless of the user's visibility preferences.

**Why this priority**: Admin access to contact information is a moderation and support requirement. Lower priority than user-facing flows.

**Independent Test**: Set a phone number as a test user, view that user's admin page as system_admin — confirm phone is visible there but not on the non-admin profile view.

**Acceptance Scenarios**:

1. **Given** a user has a phone number saved, **When** a system_admin views that user's admin detail page, **Then** the phone number is displayed
2. **Given** a user has name opt-in disabled, **When** a system_admin views that user's admin detail page, **Then** first and last name are still shown (admin sees everything)
3. **Given** a user has no extended fields set, **When** an admin views their detail page, **Then** the extended fields section shows blank/empty values rather than being hidden

---

### Edge Cases

- A grid square entered in lowercase is normalized to uppercase on save.
- A user who unchecks name opt-in retains their stored first/last name — it is hidden from others but not deleted.
- Submitting an empty string for a previously-set field clears it (stores NULL).
- Saving the extended profile form does not touch password, email, callsign, or display name.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST add optional extended profile fields to the user record: first name (max 64 chars), last name (max 64 chars), grid square (max 8 chars, Maidenhead format), bio (max 500 chars), phone (max 32 chars)
- **FR-002**: System MUST add a boolean "show name publicly" preference to the user record, defaulting to false
- **FR-003**: The existing profile editing page MUST include a new section for extended profile fields with its own save action
- **FR-004**: Grid square MUST be validated as a Maidenhead locator: 4-character (field + square) or 6-character (field + square + subsquare); input is case-insensitive and stored uppercase
- **FR-005**: All extended fields MUST be optional — no extended field is required to save the form
- **FR-006**: When displaying a profile to another logged-in user, visibility rules MUST be enforced: bio and grid square always shown if set; first/last name shown only when opt-in is true; phone never shown
- **FR-007**: Admin user detail pages MUST display all extended fields including phone, ignoring visibility preferences
- **FR-008**: Saving extended profile fields MUST NOT modify existing required profile fields (display name, email, password, callsign)
- **FR-009**: Submitting an empty value for a previously-set field MUST clear it (store NULL) and remove it from display

### Key Entities

- **User** (extended): Gains optional columns — first_name, last_name, grid_square, bio, phone, show_name_publicly. All nullable except show_name_publicly (boolean, default false).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can fill in all extended profile fields and save in under 60 seconds
- **SC-002**: Phone number is never shown to non-admin users in any view — 100% enforcement
- **SC-003**: Zero regressions in existing profile functionality for users who never fill in extended fields
- **SC-004**: Grid square validation rejects all non-Maidenhead inputs and accepts all valid 4-character and 6-character locators

## Assumptions

- Extended fields are added as columns to the existing `users` table via migration 015 (not a separate profile table)
- Avatar/photo upload is out of scope for this feature
- Profile pages require authentication; no unauthenticated profile view
- Phone number is stored as plain text with no formatting or verification — it is an operator convenience field only
- The "show name publicly" preference defaults to false for all existing users (privacy-safe migration default)
- Grid square accepts 4-character and 6-character Maidenhead formats; 2-character field-only is rejected as too imprecise
