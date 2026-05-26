# Tasks: User Profiles (F4)

**Branch**: `011-user-profiles`
**Input**: specs/011-user-profiles/plan.md, spec.md, data-model.md, contracts/profile.md

## Phase 1: Setup

**Purpose**: Migrations and app directory structure

- [ ] T001 Create migrations/013_create_email_change_requests.sql per data-model.md and apply to cflag_dmr_dev
- [ ] T002 Create migrations/014_create_callsign_update_requests.sql per data-model.md and apply to cflag_dmr_dev
- [ ] T003 Create app/profile/ directory with stub files: manager.php, email_change.php, callsign.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core service functions before any profile pages can be built

- [ ] T004 Implement app/profile/manager.php: get_profile(), update_display_name(), change_password() per contracts/profile.md
- [ ] T005 [P] Implement app/profile/email_change.php: request_email_change(), confirm_email_change(), get_pending_email_change() per contracts/profile.md
- [ ] T006 [P] Implement app/profile/callsign.php: submit_callsign_request(), get_user_callsign_requests(), get_pending_callsign_requests(), approve_callsign_request(), deny_callsign_request(), get_open_callsign_request() per contracts/profile.md

**Checkpoint**: All contract functions callable — verify with DB query after seeding test data

---

## Phase 3: User Story 1 — View & Edit Own Profile (P1) 🎯 MVP

**Goal**: Logged-in users can view their profile and update display name + password

**Independent Test**: log in, visit /user/profile.php, update display name, confirm greeting changes on dashboard; change password, log out, log in with new password

- [ ] T007 [US1] Create public/user/profile.php — require_login(); show current display name, username, email, callsign, account status, registration date; display name update form (POST action=update_name); password change form (POST action=change_password, fields: current_password, new_password, confirm_password); CSRF on all forms; flash messages for success/error
- [ ] T008 [US1] Add "My Profile" link to public/index.php (unified dashboard) for all authenticated users

**Checkpoint**: US1 independently testable — display name update and password change both working

---

## Phase 4: User Story 2 — Email Address Update (P2)

**Goal**: Users can request an email change; old email stays active until new one is verified

**Independent Test**: submit email change request; confirm pending notice shown on profile; simulate token verification; confirm email updated

- [ ] T009 [US2] Add email change section to public/user/profile.php — shows current email; POST action=request_email_change with new_email field; if pending request exists, show pending notice with new_email and option to cancel; CSRF
- [ ] T010 [US2] Create public/verify-email-change.php — accepts ?token= GET parameter; calls confirm_email_change($token); redirects to /user/profile.php with success flash or shows error for expired/invalid tokens

**Checkpoint**: US2 independently testable — email change flow from request to confirmation working

---

## Phase 5: User Story 3 — Callsign Update Request (P3)

**Goal**: Users can submit a callsign update request; admins approve/deny; approved request updates all device callsigns

**Independent Test**: submit callsign request, confirm pending status; as admin approve it; confirm user's devices show new callsign

- [ ] T011 [US3] Add callsign update section to public/user/profile.php — shows current callsign; if no open request: show request form (new_callsign, explanation fields, POST action=request_callsign); if open request: show pending status with requested callsign; if past requests: show status history table; CSRF
- [ ] T012 [US3] Create public/admin/users/callsign-requests.php — list all pending callsign_update_requests; POST action=approve (with notes) and action=deny (with notes); CSRF; links back to admin/users/
- [ ] T013 [US3] Add "Callsign Requests" link with pending count badge to public/admin/users/index.php (if pending requests > 0)

**Checkpoint**: US3 independently testable — callsign request submitted, admin approves, user and devices reflect new callsign

---

## Phase 6: User Story 4 — Admin Views Any User Profile (P4)

**Goal**: system_admin users can view full account details and history for any user

**Independent Test**: as system_admin, navigate to /admin/users/profile.php?id=N; confirm full details including devices and moderation history shown; as regular user, confirm 403 redirect

- [ ] T014 [US4] Create public/admin/users/profile.php — require_role('system_admin'); accept GET id; fetch user record, roles, devices, mod_log entries; render full account detail: display name, username, email, callsign, DMR ID, roles, registration date, moderation state, devices table, moderation history table; link back to /admin/users/
- [ ] T015 [US4] Add "View Profile →" link to each user row in public/admin/users/index.php

**Checkpoint**: US4 independently testable — admin sees full user profile; non-admin redirected

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T016 [P] Verify all profile forms have min-height:44px touch targets on mobile viewport
- [ ] T017 [P] Ensure public/user/profile.php back-link points to / (not /user/)
- [ ] T018 Run quickstart.md scenarios 1.1 through W.1 and verify all pass

---

## Dependencies & Execution Order

- T001, T002, T003 → T004, T005, T006 (phases sequential)
- T005 and T006 can be implemented in parallel (different files)
- T007, T008 depend on T004 complete
- T009, T010 depend on T005 complete
- T011, T012, T013 depend on T006 complete
- T014, T015 depend on T004 complete (needs get_profile()) and existing admin/users/ area
- T016, T017, T018 after all phases

## Implementation Strategy

**MVP**: T001–T008 (US1 — profile view and edit for all users)
**US2**: T009–T010 (email change — adds verification flow)
**US3**: T011–T013 (callsign requests — admin workflow)
**US4**: T014–T015 (admin profile view — extends admin area)
