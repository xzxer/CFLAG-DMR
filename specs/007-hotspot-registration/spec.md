# Feature Specification: F5 — Hotspot & Repeater Registration

**Feature Branch**: `007-hotspot-registration`

**Created**: 2026-05-26

**Status**: Draft

**Input**: Registered users can add their hotspots and repeaters to the network, manage device settings, and submit connection requests for admin approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Register a Device (Priority: P1)

A registered, email-verified user can add their hotspot or repeater to the network by submitting a device registration form. The device enters a pending state awaiting admin approval. Once approved, the device's DMR ID is included in the next generated network configuration and the user receives a confirmation.

**Why this priority**: Core value proposition — without device registration there is nothing to approve, manage, or connect. Everything else depends on this flow.

**Independent Test**: A logged-in user can navigate to their device management page, submit a new device form (callsign, DMR ID, type, hardware description), and see the device appear in their list with "Pending Approval" status. Admin can see the pending device in the approval queue.

**Acceptance Scenarios**:

1. **Given** a logged-in, email-verified user with no devices, **When** they submit the device registration form with valid callsign, DMR ID, device type, and hardware description, **Then** the device appears in their device list with "Pending Approval" status and an admin sees it in the pending queue.
2. **Given** a device registration form, **When** the user submits with a DMR ID that is already registered by another user, **Then** the form is rejected with a clear error message explaining the conflict.
3. **Given** a device registration form, **When** the user submits with an invalid DMR ID (non-numeric, out of valid range), **Then** the form is rejected with a validation error.
4. **Given** a user whose account is suspended or banned, **When** they attempt to register a device, **Then** the action is blocked and an appropriate error is shown.

---

### User Story 2 — Admin Approval Workflow (Priority: P2)

A system_admin can view the queue of pending device registrations, approve or deny individual requests, and add an optional note when denying. Approved devices are immediately eligible for inclusion in the next generated network configuration.

**Why this priority**: Without approval, no device can connect to the network. This is the critical path for network access.

**Independent Test**: An admin can load the device approval queue, approve a pending device (status changes to "Approved"), deny a pending device with a note (status changes to "Denied"), and confirmed-approved devices show as active in the device list.

**Acceptance Scenarios**:

1. **Given** a pending device registration, **When** a system_admin approves it, **Then** the device status changes to "Approved" and the device is eligible for whitelist inclusion in the next config generation.
2. **Given** a pending device registration, **When** a system_admin denies it with a note, **Then** the device status changes to "Denied" and the denial reason is recorded.
3. **Given** no pending devices, **When** an admin visits the approval queue, **Then** an empty-state message is shown.
4. **Given** a non-admin user, **When** they attempt to access the admin device approval page, **Then** they are redirected with a 403 or equivalent access denial.

---

### User Story 3 — Device Management (Priority: P3)

A registered user can view all their devices, see each device's current approval status, edit the hardware description and display label, and delete a device they no longer use. Deleting a device removes it from the pending whitelist inclusion.

**Why this priority**: Users need to maintain their device list as equipment changes over time. Editing and deletion prevent orphaned records.

**Independent Test**: A user with one approved device can edit its hardware description field and save it successfully. A user can delete a device and it disappears from their list.

**Acceptance Scenarios**:

1. **Given** an approved device, **When** the user edits the hardware description and saves, **Then** the updated description is stored and displayed.
2. **Given** any device (pending, approved, or denied), **When** the user deletes it, **Then** the device is removed from their list and will not be included in future config generation.
3. **Given** a user with multiple devices, **When** they view their device list, **Then** each device shows its callsign, DMR ID, type, status, and last-updated date.
4. **Given** a user viewing their device list, **When** their device is approved and the network config has been generated with it included, **Then** the device shows an "Active" indicator.

---

### User Story 4 — Talkgroup Subscriptions per Device (Priority: P4)

An approved device can have static talkgroup subscriptions assigned. A user can add or remove talkgroup subscriptions for their approved devices, selecting from the list of open talkgroups. Static talkgroups are included in the generated HBLink routing rules for that device's timeslots.

**Why this priority**: Talkgroup subscriptions are the mechanism by which users receive DMR traffic. This depends on F6 (talkgroup list) so it is lower priority.

**Independent Test**: A user with an approved device can navigate to its talkgroup subscription screen, add an open talkgroup (e.g., TG91 Worldwide), and see it appear in the device's talkgroup list. Subscriptions for private talkgroups require a join request.

**Acceptance Scenarios**:

1. **Given** an approved device, **When** the user adds an open talkgroup subscription, **Then** the talkgroup appears in the device's subscription list.
2. **Given** an approved device, **When** the user attempts to subscribe to a private talkgroup, **Then** a join request is created rather than an immediate subscription.
3. **Given** an active talkgroup subscription, **When** the user removes it, **Then** the talkgroup is removed from the device's subscription list and excluded from next config generation.
4. **Given** a pending device (not yet approved), **When** a user attempts to add talkgroup subscriptions, **Then** the subscription management is disabled with a message explaining approval is required first.

---

### Edge Cases

- What happens when a user registers a device with a DMR ID they own but that was previously denied? (Allow resubmission — creates a new pending entry)
- How does the system handle a device deletion when it is already included in the current generated config? (Delete from DB; exclusion takes effect on next config generation)
- What if the same DMR ID is submitted by two users simultaneously? (First to be approved wins; second is flagged as conflicting)
- What happens if a user's account is suspended after a device is already approved? (Device remains in DB but user's suspended status prevents config inclusion per moderation state rules from F2)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a logged-in, email-verified user to register a new device by providing: callsign, DMR ID, device type (hotspot / repeater), and hardware description.
- **FR-002**: System MUST enforce that DMR IDs are unique across all registered devices (no two users can have the same DMR ID approved).
- **FR-003**: System MUST place newly registered devices in a "Pending Approval" status until a system_admin approves or denies them.
- **FR-004**: System MUST provide a system_admin interface to view, approve, and deny pending device registrations with an optional denial reason.
- **FR-005**: System MUST update device status to "Approved" or "Denied" upon admin action, and record the acting admin and timestamp.
- **FR-006**: System MUST allow users to edit the hardware description of any of their own devices regardless of approval status.
- **FR-007**: System MUST allow users to delete their own devices; deletion removes the device from the pending whitelist inclusion pool.
- **FR-008**: System MUST display a device list per user showing callsign, DMR ID, device type, current status, and last-updated date.
- **FR-009**: System MUST allow users to add and remove open talkgroup subscriptions on approved devices (dependent on F6 talkgroup list).
- **FR-010**: System MUST treat a private talkgroup subscription attempt as a join request rather than an immediate subscription.
- **FR-011**: System MUST prevent suspended or banned users from registering new devices.
- **FR-012**: System MUST record the whitelist-eligible set of DMR IDs (all approved, non-suspended-user devices) in a form that config generation (F7) can consume.

### Key Entities

- **Device**: A hotspot or repeater registered by a user. Attributes: id, user_id (FK), callsign, dmr_id (unique), device_type (hotspot/repeater), hardware_description, status (pending/approved/denied), approved_by (FK user_id, nullable), denied_reason (nullable), created_at, updated_at.
- **DeviceTalkgroupSubscription**: A static talkgroup assigned to a device. Attributes: id, device_id (FK), talkgroup_id (FK), timeslot (1/2), created_at.
- **TalkgroupJoinRequest**: A request from a device to join a private talkgroup. Attributes: id, device_id (FK), talkgroup_id (FK), status (pending/approved/denied), created_at.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A registered user can complete a device registration submission in under 2 minutes from navigating to the page.
- **SC-002**: An admin can process (approve or deny) a pending device in under 30 seconds.
- **SC-003**: Device status updates are reflected on the user-facing device list without requiring a hard reload within one page refresh cycle (30s or less).
- **SC-004**: 100% of approved devices for active (non-suspended) users appear in the whitelist-eligible set available to config generation.
- **SC-005**: 0% of denied or deleted devices appear in the whitelist-eligible set.
- **SC-006**: DMR ID uniqueness is enforced — no two approved devices share the same DMR ID.

## Assumptions

- Email-verified user accounts (F3) exist and the session/auth system is fully operational.
- The Role & Permission System (F2) is complete — `user_has_role()` and moderation state checks are available.
- A user's DMR ID on their account (registered during F3) and a device's DMR ID are distinct concepts — a user may register multiple devices, each with its own DMR ID (e.g., a personal hotspot and a club repeater they co-administer).
- Config generation (F7) is a separate downstream feature; this feature only produces the data F7 consumes (the approved DMR ID list and talkgroup subscription records).
- Device connection status (connected / not connected via HBLink report socket) is out of scope for this feature and deferred to a future enhancement or F7.
- Auto-approve policy is out of scope for MVP — all registrations require explicit admin approval.
- US4 (talkgroup subscriptions) depends on F6 delivering a queryable talkgroup list; if F6 is not yet complete, US1–US3 are independently deliverable.
