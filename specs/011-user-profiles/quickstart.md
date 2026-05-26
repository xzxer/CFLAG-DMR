# Quickstart: User Profiles (F4)

## Prerequisites

- Registered user account (not banned or suspended)
- Email verification complete (can log in)

## Scenario 1.1 — View Profile

1. Log in as a regular user
2. Navigate to `/user/profile.php`
3. Confirm display name, username, email, and registration date are shown
4. Confirm account status (Active) is shown

## Scenario 1.2 — Update Display Name

1. On the profile page, change the display name to "Test Display Name" and save
2. Navigate to `/` (dashboard)
3. Confirm the greeting says "Welcome, Test Display Name"

## Scenario 1.3 — Change Password

1. On the profile page, enter incorrect current password → confirm error
2. Enter correct current password, new password "short" (under 8 chars) → confirm validation error
3. Enter correct current password, new password "newpassword123", confirm "differentpassword" → confirm mismatch error
4. Enter correct current password, new password "newpassword123", confirm "newpassword123" → confirm success
5. Log out, log in with new password → confirm success

## Scenario 2.1 — Request Email Change

1. On the profile page, submit a new email address
2. Confirm "Verification email sent" notice appears with the pending new address shown
3. Confirm no actual email change has occurred yet (old email still works for login)
4. Check email inbox for verification link

## Scenario 2.2 — Confirm Email Change

1. Click the verification link from the email
2. Confirm redirect to profile page with success message
3. Confirm profile now shows the new email address
4. Confirm login works with the new email address

## Scenario 2.3 — Expired Token

1. Submit an email change request
2. Manually expire the token in the DB (`UPDATE email_change_requests SET expires_at=NOW()-INTERVAL 1 HOUR ...`)
3. Click the verification link
4. Confirm error page shows "link has expired" message

## Scenario 3.1 — Callsign Update Request

1. Log in as a user with callsign W7ABC
2. On the profile page, submit a callsign update request for W7XYZ with explanation "Vanity call granted"
3. Confirm "Pending review" status shown on profile; submit button is disabled
4. Log in as system_admin, navigate to callsign requests
5. Approve the request with notes
6. Log back in as user, navigate to profile
7. Confirm callsign now shows W7XYZ

## Scenario 3.2 — Callsign Cascade to Devices

1. After approving a callsign update (Scenario 3.1)
2. Navigate to `/admin/users/profile.php?id=N` for that user
3. Confirm the device list shows the updated callsign W7XYZ (not the old W7ABC)

## Scenario 4.1 — Admin Views User Profile

1. Log in as system_admin
2. Navigate to `/admin/users/` and click on a user
3. Confirm full profile: display name, username, email, roles, devices, moderation state, registration date
4. If the user has moderation history, confirm mod log entries are shown

## Scenario W.1 — Non-Admin Cannot View Other Profiles

1. Log in as regular user
2. Attempt to navigate to `/admin/users/profile.php?id=2` (another user)
3. Confirm redirect to `/` with 403 response
