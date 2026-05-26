# Quickstart: F6 — Talkgroup Management

**Branch**: `008-talkgroup-management` | **Date**: 2026-05-26

Manual verification scenarios. Run on the dev server after implementation.

---

## Prerequisites

- Migration 009 (talkgroups + related tables) applied to `cflag_dmr_dev`
- Migration 010 (`device_talkgroup_subscriptions`) applied (requires migration 008 from F5 also applied)
- At least one `system_admin` account (`cflagadmin`)
- At least one registered, email-verified regular user
- At least one approved device (from F5) for talkgroup subscription tests

---

## US1 Scenarios — Admin Manages Talkgroup Catalog

### Scenario 1.1 — Create a talkgroup (happy path)

1. Log in as `cflagadmin`
2. Navigate to `/admin/talkgroups/`
3. Click "New Talkgroup", fill in: TGID = `91`, Name = `Worldwide`, Type = `Open`
4. Submit

**Expected**: Talkgroup appears in catalog as active. Visible on public `/talkgroups.php`.

---

### Scenario 1.2 — Duplicate TGID rejected

1. Create talkgroup TGID 91 (Scenario 1.1)
2. Attempt to create another talkgroup with TGID 91

**Expected**: Form rejects with "TGID 91 is already in use."

---

### Scenario 1.3 — Edit talkgroup

1. As admin, click edit on TG 91
2. Change name to `Worldwide English`

**Expected**: Updated name visible in catalog and on public listing.

---

### Scenario 1.4 — Disable talkgroup

1. As admin, disable TG 91

**Expected**: TG 91 no longer appears in user-facing subscription list. Still visible in admin catalog with disabled indicator. Active subscriptions retained in DB.

---

### Scenario 1.5 — Delete talkgroup with no subscriptions

1. Create a new talkgroup (TG 9999), no subscriptions
2. Delete it

**Expected**: TG 9999 removed from catalog.

---

### Scenario 1.6 — Delete talkgroup with active subscriptions blocked

1. Create TG 9998, add a subscription from a device
2. Attempt to delete TG 9998

**Expected**: Deletion blocked with "Cannot delete a talkgroup with active subscriptions. Disable it instead."

---

### Scenario 1.7 — Filter/search talkgroup list

1. Create talkgroups: TG 91 "Worldwide", TG 3100 "North America"
2. Search for "North"

**Expected**: Only TG 3100 shown in results.

---

## US2 Scenarios — User Requests a New Talkgroup

### Scenario 2.1 — Submit talkgroup request

1. Log in as regular user
2. Navigate to `/user/talkgroups.php`
3. Click "Request Talkgroup", fill in: Proposed TGID = `3172`, Name = `Pacific NW`, Type = `Open`, Description = `Regional talkgroup`
4. Submit

**Expected**: Request appears in user's "My Requests" as pending. Admin sees it in `/admin/talkgroups/requests.php`.

---

### Scenario 2.2 — Admin approves talkgroup request

1. As admin, navigate to `/admin/talkgroups/requests.php`
2. Approve the Pacific NW request, assign TGID 3172, ownership tier = user_partial

**Expected**: Talkgroup 3172 "Pacific NW" created in catalog. Requester's request status = Approved. Talkgroup visible publicly.

---

### Scenario 2.3 — Admin denies talkgroup request

1. Submit a request for TGID 99 "Test TG"
2. As admin, deny with reason "TGID 99 is reserved for internal use"

**Expected**: Request shows "Denied" with reason in user's request list. No talkgroup created.

---

### Scenario 2.4 — Duplicate pending request blocked

1. Submit request for TGID 3172
2. Attempt to submit another request for TGID 3172 while first is still pending

**Expected**: Second submission rejected: "You already have a pending request for TGID 3172."

---

## US3 Scenarios — User Manages Owned Talkgroup

### Scenario 3.1 — user_partial owner edits name/description

1. Own a talkgroup with user_partial tier
2. Navigate to talkgroup edit page
3. Update description, save

**Expected**: Updated description visible. `updated_at` refreshed.

---

### Scenario 3.2 — user_partial cannot manage access lists

1. As user_partial owner, attempt to access the access list management section

**Expected**: Access list management is hidden or shows "Full ownership required" message.

---

### Scenario 3.3 — user_full owner blocks a DMR ID

1. Own a talkgroup with user_full tier
2. Add DMR ID `3171234` to the block list
3. Check access decision (manually query or via diagnostic): DMR ID 3171234 attempts subscription

**Expected**: Subscription denied. `talkgroup_access_lists` has a block entry. Even if 3171234 is on the allow list, deny-wins: blocked access cannot be subscribed.

---

### Scenario 3.4 — Ownership upgrade request

1. As user_partial owner, submit upgrade request to user_full with reason
2. As admin, approve the upgrade request

**Expected**: `talkgroups.ownership_tier` updated to `user_full`. Access list management now available to the owner.

---

## US4 Scenarios — Device Subscriptions

### Scenario 4.1 — Subscribe device to open talkgroup

1. Have an approved device (from F5 setup)
2. Navigate to device subscription management
3. Add TG 91 Worldwide on timeslot 1

**Expected**: Subscription row in `device_talkgroup_subscriptions`. Device's subscription list shows TG 91 TS1.

---

### Scenario 4.2 — Subscribe to private talkgroup creates join request

1. Create a private talkgroup TG 5000
2. As device owner, attempt to subscribe to TG 5000

**Expected**: Join request created (pending). No immediate subscription. Talkgroup owner or admin sees the join request.

---

### Scenario 4.3 — Disabled talkgroup not subscribable

1. Disable TG 91
2. Attempt to add TG 91 subscription from a device

**Expected**: TG 91 not shown in subscription selector. Or if submitted directly, rejected.

---

### Scenario 4.4 — Remove subscription

1. Have active subscription to TG 91 TS1
2. Remove it

**Expected**: Row deleted from `device_talkgroup_subscriptions`. No longer in device's list.

---

## Cross-cutting Security Checks

### Scenario S.1 — Non-admin cannot access admin talkgroup management

1. Log in as regular user
2. Navigate to `/admin/talkgroups/`

**Expected**: 403 / redirected.

### Scenario S.2 — User cannot edit another user's talkgroup

1. User A owns TG 3172
2. User B submits edit POST for TG 3172

**Expected**: Rejected — ownership verified server-side.

### Scenario S.3 — user_full owner cannot escalate own privileges beyond user_full

1. As user_full owner, attempt to POST ownership_tier=admin for their talkgroup

**Expected**: Rejected. Only system_admin can set ownership_tier=admin.

### Scenario S.4 — CSRF on all POST forms

**Expected**: All forms include CSRF token; requests without valid token rejected.

---

## F7 Data Contract Verification

### Scenario F7.1 — Subscription data available for config generation

1. Have device D1 subscribed to TG 91 TS1 and TG 3100 TS2
2. Call `get_device_subscriptions_for_config()` (or check DB directly)

**Expected**: Returns correct device→talkgroup→timeslot mappings for all active subscriptions on approved devices for active users.
