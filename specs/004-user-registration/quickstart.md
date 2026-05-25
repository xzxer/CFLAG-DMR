# Quickstart: F3 — User Registration & Accounts

Manual test scenarios to verify F3 is working correctly. Run in sequence after migration 005 is applied.

**Prerequisites**: Migration 005 applied. `EMAIL_DEV_MODE=true` in `.env`. Dev server running.

---

## Scenario 1: Successful Registration (RadioID.net disabled)

1. In MariaDB: `UPDATE system_settings SET value='0' WHERE \`key\`='radioid_validation_enabled';`
2. Navigate to `http://dmrdev.cflag.net/register.php`
3. Fill in:
   - Callsign: `W1TEST`
   - DMR ID: `3109100`
   - Email: `w1test@example.com`
   - Password: `TestPassword123`
   - Display Name: `Test User One`
4. Submit.

**Expected**: Confirmation page: "Check your email." Token and link written to `/var/log/cflag-dmr-email-dev.log`. User row in `users` with `email_verified_at = NULL`, `callsign = 'W1TEST'`, `moderation_state = 'active'`.

---

## Scenario 2: Email Verification Flow

1. Open `/var/log/cflag-dmr-email-dev.log`, copy the verification URL.
2. Paste the URL in the browser.

**Expected**: Redirected to `http://dmrdev.cflag.net/login.php?verified=1` with "Email verified! You can now log in." message. `users.email_verified_at` is now set.

---

## Scenario 3: Login After Verification

1. On `/login.php`, enter username `w1test` and password `TestPassword123`.

**Expected**: Login succeeds. Redirected to `/admin/` (user role — sees limited dashboard).

---

## Scenario 4: Login with Unverified Account

1. Register a second user `W2TEST` / `3109101` / `w2test@example.com` / `TestPassword123` / `Test User Two`.
2. Do NOT follow the verification link.
3. Attempt login with `w2test` / `TestPassword123`.

**Expected**: Login page shows "Please verify your email address before logging in." with a "Resend verification email" link. NOT the generic error message.

---

## Scenario 5: Resend Verification

1. From Scenario 4, click "Resend verification email."
2. On the resend page, enter `w2test@example.com`.
3. Submit.

**Expected**: Success message shown (same message regardless of outcome). New token written to `/var/log/cflag-dmr-email-dev.log`. Old token in `email_verifications` has `used_at` set.

---

## Scenario 6: Rate Limit on Resend

1. Immediately submit the resend form again for `w2test@example.com`.

**Expected**: Page shows same success message but no new token appears in the log (rate limited; only one resend per email per 5 minutes).

---

## Scenario 7: RadioID.net Validation (Callsign Mismatch)

1. In MariaDB: `UPDATE system_settings SET value='1' WHERE \`key\`='radioid_validation_enabled';`
2. Navigate to `/register.php`.
3. Enter a real DMR ID with a mismatched callsign (e.g., DMR ID `3109999` with callsign `ZZZZZ`).
4. Submit.

**Expected**: Registration rejected with "The callsign and DMR ID do not match RadioID.net records." No account created.

---

## Scenario 8: Expired Verification Token

1. In MariaDB: `UPDATE email_verifications SET expires_at = NOW() - INTERVAL 1 HOUR WHERE user_id = (SELECT id FROM users WHERE username = 'w2test');`
2. Copy the token URL from the dev log for `w2test`.
3. Visit the URL.

**Expected**: Error page: "This verification link has expired." with a link to request a new one.

---

## Scenario 9: Login by Email Address

1. Ensure `w1test@example.com` (from Scenario 1) is verified.
2. On `/login.php`, enter `w1test@example.com` in the username field with password `TestPassword123`.

**Expected**: Login succeeds. (Email is detected by presence of `@`.)

---

## Scenario 10: Admin Manual Verification

1. Log in as the system admin.
2. Navigate to `/admin/users/` and find `W2TEST` (should show "Unverified" or be filterable).
3. Click through to `/admin/users/view.php?id={id}`.
4. Click "Manually verify."

**Expected**: `email_verified_at` is set. Success flash shown. `W2TEST` can now log in.

---

## Scenario 11: Admin Resend from Admin UI

1. Create a third test user `W3TEST` / `3109102` / `w3test@example.com` (do not verify).
2. In admin user view for `W3TEST`, click "Resend verification email."

**Expected**: New token written to dev log. Old tokens for `W3TEST` invalidated.

---

## Scenario 12: Admin Deletes Unverified Account

1. In admin user view for `W3TEST`, click "Delete account."

**Expected**: User row deleted. `email_verifications` rows for `W3TEST` also deleted (FK cascade). Cannot navigate to the deleted user's view page (404 or redirect).

---

## Scenario 13: Existing Admin Account Unaffected

1. Log out.
2. Log in as `admin` with the original migrated password.

**Expected**: Login succeeds. `email_verified_at` is populated (backfilled by migration 005).
