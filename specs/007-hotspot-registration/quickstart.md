# Quickstart: F5 — Hotspot & Repeater Registration

**Branch**: `007-hotspot-registration` | **Date**: 2026-05-26

Manual verification scenarios. Run these after implementation on the dev server. No automated tests required — verified manually via browser.

---

## Prerequisites

- Dev server running at `dmrdev.cflag.net`
- Migration 008 applied to `cflag_dmr_dev`
- At least one registered, email-verified, active user account
- At least one `system_admin` account (existing `cflagadmin`)
- No existing devices in the `devices` table (clean state for first run)

---

## US1 Scenarios — Register a Device

### Scenario 1.1 — Successful device registration (happy path)

1. Log in as a regular user
2. Navigate to `/user/devices.php`
3. Fill in: Callsign = `W7TEST`, DMR ID = `3171234`, Type = `Hotspot`, Hardware = `OpenSPOT4 Pro`
4. Submit

**Expected**: Device appears in list with status "Pending Approval". Row exists in `devices` table with `status='pending'`.

---

### Scenario 1.2 — Duplicate DMR ID (already pending/approved by another user)

1. As user A, register DMR ID `3171234` (creates pending entry)
2. Log in as user B
3. Attempt to register DMR ID `3171234`
4. Submit

**Expected**: Form rejects with error "DMR ID is already registered by another user." No duplicate row created.

---

### Scenario 1.3 — Invalid DMR ID

1. Log in as a regular user
2. Submit device with DMR ID = `12345` (6 digits, out of range)

**Expected**: Form rejects with validation error about DMR ID format.

---

### Scenario 1.4 — Suspended user blocked

1. Set user's `moderation_state = 'suspended'` in DB directly
2. Log in as that user
3. Navigate to `/user/devices.php`

**Expected**: Redirected away from the page or shown "account suspended" message. Cannot register a device.

---

### Scenario 1.5 — Unauthenticated access blocked

1. Log out
2. Navigate to `/user/devices.php` directly

**Expected**: Redirected to `/login.php`.

---

## US2 Scenarios — Admin Approval

### Scenario 2.1 — Approve a pending device

1. Register a device as a regular user (creates pending entry)
2. Log in as `cflagadmin`
3. Navigate to `/admin/devices/`
4. Click "Approve" on the pending device

**Expected**: Device status changes to "Approved". `approved_by`, `reviewed_at` set in DB. Device appears as approved in user's device list.

---

### Scenario 2.2 — Deny a pending device with reason

1. Register a device as a regular user
2. Log in as admin, navigate to `/admin/devices/`
3. Click "Deny", enter reason "DMR ID not found in registry", confirm

**Expected**: Device status = "Denied". `denied_reason` stored. User's device list shows "Denied" with the reason visible.

---

### Scenario 2.3 — Empty approval queue

1. Approve or deny all pending devices
2. Reload `/admin/devices/`

**Expected**: "No pending device registrations" empty-state message shown.

---

### Scenario 2.4 — Non-admin cannot access approval queue

1. Log in as regular user
2. Navigate to `/admin/devices/` directly

**Expected**: 403 response or redirect to `/admin/` (role gate fires).

---

### Scenario 2.5 — Prevent duplicate approval (concurrent DMR ID conflict)

1. User A registers DMR ID `3171234` — pending
2. User B registers DMR ID `3171234` — pending
3. Admin approves User A's device
4. Admin attempts to approve User B's device

**Expected**: Second approval is blocked with "DMR ID 3171234 is already approved for another device." User B's device remains pending or is auto-denied.

---

## US3 Scenarios — Device Management

### Scenario 3.1 — Edit hardware description

1. Log in as user with an existing device (any status)
2. Click "Edit" on a device
3. Change hardware description to `Shark RF OpenSPOT5`
4. Save

**Expected**: Updated description shown in device list. `updated_at` timestamp refreshes.

---

### Scenario 3.2 — Delete a device

1. Log in as user with a pending device
2. Click "Delete" and confirm

**Expected**: Device removed from list. Row deleted from `devices` table. Not shown in admin approval queue.

---

### Scenario 3.3 — Multi-device list view

1. Register 3 devices as the same user (all pending)
2. View device list

**Expected**: All 3 devices shown with callsign, DMR ID, type, status, date. Sorted by created_at descending (newest first).

---

### Scenario 3.4 — Re-register after denial

1. Register a device, have admin deny it
2. Delete the denied device
3. Register the same DMR ID again

**Expected**: New pending entry created. Old denied row is gone (was deleted). Admin sees fresh pending entry.

---

## Cross-cutting Security Checks

### Scenario S.1 — User cannot edit another user's device

1. Log in as user A with device ID 1
2. Manually POST to edit endpoint with `device_id=1` while logged in as user B

**Expected**: Action rejected (403 or redirect). Ownership verified server-side.

### Scenario S.2 — User cannot approve their own device

1. Register a device as user A
2. Attempt to POST to the admin approval endpoint while logged in as user A (non-admin)

**Expected**: 403 / role gate fires. No status change.

### Scenario S.3 — CSRF protection on all POST forms

1. Submit a device registration POST without a valid CSRF token

**Expected**: Request rejected. No device created.

---

## Whitelist Eligibility Check (for F7)

### Scenario W.1 — Approved active user device appears in whitelist set

1. Approve a device for an active user
2. Call `get_whitelist_eligible_dmr_ids()` (or test via a diagnostic endpoint)

**Expected**: That device's DMR ID is in the returned array.

### Scenario W.2 — Suspended user's device excluded from whitelist

1. Approve a device, then suspend the device owner
2. Call `get_whitelist_eligible_dmr_ids()`

**Expected**: That device's DMR ID is NOT in the returned array.
