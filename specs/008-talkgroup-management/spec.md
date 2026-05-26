# Feature Specification: F6 — Talkgroup Management

**Feature Branch**: `008-talkgroup-management`

**Created**: 2026-05-26

**Status**: Draft

**Input**: Admins manage the canonical list of talkgroups available on the network. Users can request new talkgroups, manage ones they own, and request upgraded ownership privileges. The talkgroup record is the foundation for device subscriptions (F5) and network routing (F7).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin Manages Talkgroup Catalog (Priority: P1)

A system_admin can create, edit, enable, disable, and delete talkgroups from the admin dashboard. Creating a talkgroup requires assigning a Talkgroup ID (TGID), a name, a type (open/private/club), and optionally an owner. This talkgroup catalog is the authoritative list used by config generation and device subscriptions.

**Why this priority**: The talkgroup catalog must exist before users can subscribe to talkgroups or routing rules can be generated. All downstream features depend on admin-managed talkgroup records.

**Independent Test**: A system_admin can load the talkgroup management page, create a new talkgroup with TGID 91, name "Worldwide", type "open", and see it appear in the talkgroup list. Admin can edit its name and disable it, and the list reflects the changes.

**Acceptance Scenarios**:

1. **Given** a system_admin on the talkgroup management page, **When** they create a talkgroup with a unique TGID, name, and type, **Then** the talkgroup appears in the catalog as active with the provided details.
2. **Given** an existing talkgroup, **When** a system_admin edits its name or description, **Then** the changes are saved and visible to all users.
3. **Given** an active talkgroup, **When** a system_admin disables it, **Then** it no longer appears in the list of subscribable talkgroups for users, but the record is retained.
4. **Given** a TGID already in use, **When** a system_admin attempts to create another talkgroup with the same TGID, **Then** the form is rejected with a duplicate TGID error.
5. **Given** an admin viewing the talkgroup list, **When** they filter or search by name or TGID, **Then** matching results are shown.

---

### User Story 2 — User Requests a New Talkgroup (Priority: P2)

A registered user can submit a new talkgroup request, proposing a TGID, name, type, and description. The request enters a pending queue visible to system_admins. An admin reviews the request and either approves it (assigning the final TGID and ownership tier) or denies it with a reason. The user is notified of the outcome via their account notifications.

**Why this priority**: Community-driven talkgroup creation grows the network's utility. The approval gate ensures admins control TGID space and prevents conflicts.

**Independent Test**: A logged-in user can navigate to "Request Talkgroup", fill in a proposed TGID, name, type, and description, and submit. The request appears in their "My Requests" list as pending. An admin sees it in the pending talkgroup requests queue and can approve or deny it.

**Acceptance Scenarios**:

1. **Given** a logged-in user, **When** they submit a talkgroup request with a proposed TGID, name, type, and description, **Then** the request appears in the admin's pending queue and the user's own requests list with "Pending" status.
2. **Given** a pending talkgroup request, **When** an admin approves it with a final TGID and ownership tier, **Then** the talkgroup is created in the catalog, the user is notified, and the request status changes to "Approved".
3. **Given** a pending talkgroup request, **When** an admin denies it with a reason, **Then** the user is notified and the request shows "Denied" with the admin's reason.
4. **Given** a user who already has a pending request for the same TGID, **When** they attempt to submit another request for that TGID, **Then** the duplicate is rejected with an explanation.

---

### User Story 3 — User Manages Their Owned Talkgroup (Priority: P3)

A user who owns a talkgroup (ownership tier: user_partial or user_full) can edit the talkgroup's name and description. Users with full ownership can additionally manage an allowed/blocked DMR ID list for their talkgroup. Ownership tier changes require admin approval.

**Why this priority**: Ownership enables community-managed talkgroups and reduces admin burden for routine name/description edits.

**Independent Test**: A user with a user_partial-owned talkgroup can load the "My Talkgroups" page, click edit on their talkgroup, update the description, and save. A user with user_full ownership can additionally add a DMR ID to the allowed list and see it reflected.

**Acceptance Scenarios**:

1. **Given** a user with user_partial ownership, **When** they update the talkgroup name or description, **Then** the changes are saved and visible on the talkgroup detail page.
2. **Given** a user with user_partial ownership, **When** they attempt to modify access control settings (allowed/blocked DMR IDs), **Then** the action is blocked with an explanation that full ownership is required.
3. **Given** a user with user_full ownership, **When** they add a DMR ID to the blocked list, **Then** that DMR ID is excluded from routing for the talkgroup in the next generated config.
4. **Given** a talkgroup owner, **When** they submit a request to upgrade from user_partial to user_full ownership, **Then** the request appears in the admin queue for approval.

---

### User Story 4 — User Subscribes to a Talkgroup (Priority: P4)

A registered user (via their approved device) can browse available talkgroups and subscribe to open ones immediately. For private talkgroups, the user submits a join request. For club talkgroups, access requires club membership (out of scope for this feature — treated same as private for MVP).

**Why this priority**: Subscription is the end-to-end link between a user's device and traffic on a talkgroup. Requires both F5 (approved device) and this feature's talkgroup catalog.

**Independent Test**: A logged-in user with an approved device can view the open talkgroup list, click "Subscribe" on TG91 Worldwide, and see it appear in their device's talkgroup subscriptions. Clicking "Request Access" on a private talkgroup creates a pending join request.

**Acceptance Scenarios**:

1. **Given** a user with an approved device, **When** they subscribe to an open talkgroup, **Then** the subscription is immediately active and appears on the device's subscription list.
2. **Given** a user with an approved device, **When** they request access to a private talkgroup, **Then** a join request is created (pending approval by the talkgroup owner or admin).
3. **Given** an approved join request for a private talkgroup, **When** the talkgroup owner or admin approves it, **Then** the device gains an active subscription.
4. **Given** a talkgroup with a user_full-owner allowed list, **When** a device's DMR ID is on the blocked list, **Then** that device cannot subscribe even if they request access.
5. **Given** a disabled talkgroup, **When** a user attempts to subscribe, **Then** the action is blocked and the talkgroup is not shown in the subscribable list.

---

### Edge Cases

- What if a TGID is requested by a user but an admin wants to use the same TGID for a different purpose? (Admin's direct creation takes precedence; pending user request for same TGID is auto-denied with explanation)
- What happens to existing subscriptions when a talkgroup is disabled? (Subscriptions are retained in DB but excluded from config generation until the talkgroup is re-enabled)
- What if the talkgroup owner's account is suspended? (Their owned talkgroups revert to admin-owned pending review; access control lists are preserved)
- What if a DMR ID is on both the allowed and blocked lists for a talkgroup? (Blocked takes precedence — deny-wins principle)
- What happens to open talkgroup subscriptions when a talkgroup is changed from open to private? (Existing subscriptions remain active; new subscriptions require a request)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST maintain a canonical talkgroup catalog with fields: TGID (unique integer), name, description, type (open/private/club), owner_user_id (nullable), ownership_tier (admin/user_partial/user_full), active status, created_at, updated_at.
- **FR-002**: System MUST enforce TGID uniqueness across all talkgroups regardless of status.
- **FR-003**: System MUST allow system_admins to create, edit, enable, disable, and delete talkgroup records.
- **FR-004**: System MUST allow registered users to submit talkgroup creation requests containing a proposed TGID, name, type, and description.
- **FR-005**: System MUST route talkgroup requests to an admin approval queue; approved requests create a talkgroup record; denied requests record the denial reason.
- **FR-006**: System MUST allow talkgroup owners with user_partial tier to edit only the name and description of their owned talkgroups.
- **FR-007**: System MUST allow talkgroup owners with user_full tier to additionally manage an allowed/blocked DMR ID list for their talkgroup.
- **FR-008**: System MUST enforce that blocked DMR IDs take precedence over allowed DMR IDs (deny-wins) when resolving access for a talkgroup.
- **FR-009**: System MUST provide a public-facing talkgroup listing showing active open talkgroups (name, TGID, type) visible without login.
- **FR-010**: System MUST allow users with approved devices to subscribe to open talkgroups immediately and to request access to private/club talkgroups.
- **FR-011**: System MUST allow talkgroup owners (user_full) and system_admins to approve or deny join requests for private talkgroups.
- **FR-012**: System MUST allow talkgroup owners to request an ownership upgrade (user_partial → user_full); upgrade requires admin approval.
- **FR-013**: System MUST expose the full subscription state (device → talkgroup → timeslot mappings) in a form that config generation (F7) can consume.
- **FR-014**: System MUST prevent non-admin users from modifying another user's talkgroup or accessing admin management pages.

### Key Entities

- **Talkgroup**: The canonical network talkgroup record. Attributes: id, tgid (unique integer), name, description, type (open/private/club), owner_user_id (FK, nullable), ownership_tier (enum: admin/user_partial/user_full), active (boolean), created_at, updated_at.
- **TalkgroupRequest**: A user's proposal for a new talkgroup. Attributes: id, requester_user_id (FK), proposed_tgid, name, type, description, status (pending/approved/denied), reviewed_by (FK, nullable), denial_reason (nullable), created_at, reviewed_at.
- **TalkgroupAccessList**: Explicit allow/block entries for user_full-owned talkgroups. Attributes: id, talkgroup_id (FK), dmr_id, list_type (allow/block), added_by (FK user_id), created_at.
- **TalkgroupOwnershipUpgradeRequest**: A request to escalate from user_partial to user_full ownership. Attributes: id, talkgroup_id (FK), requester_user_id (FK), reason, status (pending/approved/denied), reviewed_by (FK, nullable), created_at.
- **DeviceTalkgroupSubscription** (from F5): device_id (FK), talkgroup_id (FK), timeslot (1/2), status (active/pending_join/denied).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can create a new talkgroup and have it appear in the catalog in under 1 minute.
- **SC-002**: A user can request a new talkgroup and receive an admin decision (approval or denial) visible in their account within the same session the admin acts on it.
- **SC-003**: 100% of active open talkgroups are visible in the public talkgroup listing without login.
- **SC-004**: 0% of disabled talkgroups appear in the subscribable talkgroup list for users.
- **SC-005**: TGID uniqueness is enforced with 0 duplicates permitted across any talkgroup status.
- **SC-006**: The deny-wins rule for talkgroup access lists produces correct access decisions in 100% of cases (blocked DMR ID never gains subscription regardless of allowed list state).
- **SC-007**: The subscription state exposed for config generation accurately reflects all active subscriptions with correct timeslot assignments.

## Assumptions

- The Role & Permission System (F2) is complete — admin-only pages use `require_role('admin')` and ownership checks use `user_has_role()`.
- F5 (Device Registration) delivers the `devices` table and approved-device concept that talkgroup subscriptions attach to; if F5 is incomplete, US1–US3 of this feature are independently deliverable, and US4 depends on F5.
- TGID space management (avoiding conflicts with regional/international talkgroups) is the admin's responsibility; the system enforces uniqueness but does not validate TGIDs against external DMR ID registries.
- Club talkgroup type is defined and stored but club membership gating (F19 dependency) is out of scope for this feature — club talkgroups behave identically to private talkgroups for MVP.
- Config generation (F7) is a downstream consumer of the subscription data this feature produces; this feature does not generate HBLink config files.
- Talkgroup deletion by admin is a hard delete only when no active subscriptions exist; talkgroups with active subscriptions can only be disabled, not deleted, to preserve historical data integrity.
- Notifications to users (approval/denial outcomes) are in-app status updates for MVP; email notifications are a future enhancement.
