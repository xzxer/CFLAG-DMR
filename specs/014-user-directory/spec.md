# Feature Specification: Public User Directory

**Feature Branch**: `014-user-directory`

**Created**: 2026-05-26

**Status**: Draft

**Input**: F12: Public User Directory — A searchable directory of registered network users, accessible to all logged-in users. Shows callsign, display name, grid square, and device count per user. Supports search by callsign, username, or display name. Links to individual user profile pages. Users can opt out of the directory while still using the network.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Browse the User Directory (Priority: P1)

A logged-in user navigates to the User Directory page and sees a paginated list of all active registered users who have not opted out. Each row shows callsign, display name, grid square (if set), and how many approved devices they have on the network. Clicking a user's callsign or name opens their profile page.

**Why this priority**: The browse/list view is the foundation of the directory. It delivers value immediately even without search.

**Independent Test**: Log in, navigate to /users, confirm the list renders with at least the current logged-in user, confirm clicking a row links to a profile page.

**Acceptance Scenarios**:

1. **Given** a logged-in user visits the directory, **When** the page loads, **Then** they see a list of active users with callsign, display name, grid square, and device count columns
2. **Given** the directory has more than 50 users, **When** the first page loads, **Then** pagination controls are shown and the second page shows the next batch of users
3. **Given** a user has opted out of the directory, **When** any other user browses the directory, **Then** the opted-out user does not appear in the list
4. **Given** a non-authenticated visitor accesses /users, **When** the page loads, **Then** they are redirected to login
5. **Given** a user in the directory has no approved devices, **When** the directory renders, **Then** their device count shows 0 (not hidden)
6. **Given** a user has no callsign set, **When** they appear in the directory, **Then** their username is shown in place of callsign

---

### User Story 2 - Search the Directory (Priority: P2)

A logged-in user types a search term into the directory search bar and sees filtered results matching users by callsign, username, or display name. The search is case-insensitive and supports partial matches.

**Why this priority**: Search makes the directory useful for networks with many users. Partial-match search is the minimum expected behavior for any people-finder feature.

**Independent Test**: Search for the first 3 characters of a known callsign — confirm matching users appear and non-matching users disappear.

**Acceptance Scenarios**:

1. **Given** a user enters a partial callsign in the search box, **When** results load, **Then** only users whose callsign contains the search term (case-insensitive) are shown
2. **Given** a user searches by display name, **When** results load, **Then** users whose display name matches (partial, case-insensitive) are returned
3. **Given** a user searches for a term that matches no user, **When** results load, **Then** an empty state message is shown ("No users match your search")
4. **Given** a user clears the search box, **When** the form is submitted or cleared, **Then** the full directory list returns
5. **Given** a search matches an opted-out user, **When** results load, **Then** the opted-out user does NOT appear in results

---

### User Story 3 - Opt Out of Directory (Priority: P3)

A logged-in user can opt out of the user directory from their profile page. When opted out, they no longer appear in directory listings or search results. They can re-enable directory visibility at any time. Opting out does not affect their ability to use the network.

**Why this priority**: Privacy control is important but less urgent than the directory itself. Most users will not use this setting, but its absence would be a meaningful gap.

**Independent Test**: Opt out via the profile page, browse the directory as another user — confirm the opted-out user is absent. Re-enable, confirm they reappear.

**Acceptance Scenarios**:

1. **Given** a user visits their profile page, **When** they look for directory settings, **Then** they see a "Show me in the user directory" checkbox (default: checked)
2. **Given** a user unchecks the directory opt-in and saves, **When** another user browses the directory, **Then** the opted-out user does not appear
3. **Given** a user is opted out, **When** they re-check the directory opt-in and save, **Then** they reappear in the directory for other users
4. **Given** a user opts out, **When** they try to directly access their own profile URL, **Then** they can still view their own profile (opt-out only hides from directory, not direct links)

---

### Edge Cases

- A user with no callsign set appears in the directory with their username displayed in the callsign column.
- A suspended or banned user does not appear in the directory regardless of their opt-in setting.
- The directory shows only users with `moderation_state = 'active'`.
- An admin can see all active users in the directory including those who opted out (admins need the full picture).
- Search with very short terms (1 character) is allowed but may return many results; no minimum length enforced.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a paginated user directory page at /users accessible to all logged-in users
- **FR-002**: Directory MUST display per user: callsign (or username if no callsign), display name, grid square (if set), approved device count
- **FR-003**: Directory MUST only show users with active moderation state and directory opt-in enabled
- **FR-004**: Directory MUST support search by callsign, username, and display name (case-insensitive, partial match)
- **FR-005**: Each directory row MUST link to the user's profile page
- **FR-006**: System MUST add a "show in directory" boolean preference to the user record, defaulting to true
- **FR-007**: Users MUST be able to toggle their directory opt-in from their profile settings page
- **FR-008**: Opted-out users MUST be excluded from both browse and search results for non-admin users
- **FR-009**: Admins MUST see all active users in the directory regardless of opt-in setting
- **FR-010**: Directory page MUST require authentication; unauthenticated visitors are redirected to login
- **FR-011**: Directory MUST paginate at 50 users per page with page navigation controls

### Key Entities

- **User** (extended): Gains a `show_in_directory` boolean column (default true).
- **Directory Row**: A read-only projection of user data — callsign, display name, grid square, device count — assembled for display.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Directory page loads and displays results in under 2 seconds for a network with up to 500 users
- **SC-002**: Search results update and display in under 1 second for typical query lengths
- **SC-003**: Opted-out users never appear in directory or search results for non-admin users — 100% enforcement
- **SC-004**: A user can locate any other active network member by callsign partial-match search

## Assumptions

- The directory is only accessible to authenticated users (no public-facing unauthenticated view)
- Pagination size of 50 users per page is sufficient for the current network scale
- The `show_in_directory` field is added to the `users` table in migration 015 alongside F11's extended fields (single migration covers both features)
- Sorting defaults to callsign alphabetically; no user-configurable sort order in this iteration
- No profile photo/avatar display in the directory (out of scope until F11 avatar work, if added)
- Direct profile URL links are not blocked by opt-out — opt-out only removes the user from the directory listing
