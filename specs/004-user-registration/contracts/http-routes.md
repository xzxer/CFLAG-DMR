# HTTP Route Contracts: F3 — User Registration & Accounts

## New Public Routes

### `GET /register.php`
Renders the public registration form.

**Response**: 200 HTML — registration form with fields: callsign, dmr_id, email, password, display_name.

**Redirect**: If already logged in → `Location: /admin/` (or `/dashboard/` when F4 ships).

---

### `POST /register.php`
Processes a registration submission.

**Request body** (application/x-www-form-urlencoded):

| Field | Type | Constraints |
|-------|------|-------------|
| `csrf_token` | string | Must match session token |
| `callsign` | string | 1–2 letters + 1 digit + 1–3 letters; stored uppercase |
| `dmr_id` | string | 7-digit integer; unique |
| `email` | string | Valid email format; unique |
| `password` | string | ≥ 12 characters |
| `display_name` | string | 2–64 characters |

**Success response**: `302 Location: /register.php?success=1` (PRG pattern) → renders confirmation page.

**Failure response**: `200` — form re-rendered with per-field inline errors. Uniqueness conflicts show generic "already in use" message.

**Side effects on success**: Row inserted into `users` (moderation_state='active', tier='free', email_verified_at=NULL); row inserted into `email_verifications`; token written to dev log.

---

### `GET /register.php?success=1`
Renders registration confirmation page telling user to check their email.

---

### `GET /verify-email.php?token={token}`
Processes an email verification link.

**Success**: Sets `users.email_verified_at = NOW()`, marks token used. `302 Location: /login.php?verified=1`.

**Failure — expired**: `200` — error page with "token expired" message and link to `/resend-verification.php`.

**Failure — already used**: `200` — informational page directing user to `/login.php`.

**Failure — not found**: `200` — generic "invalid or expired link" error page.

---

### `GET /resend-verification.php`
Renders the resend verification email form (email address input).

---

### `POST /resend-verification.php`
Processes a resend request.

**Request body**:

| Field | Type | Constraints |
|-------|------|-------------|
| `csrf_token` | string | Must match session token |
| `email` | string | Email address |

**Response**: Always `200` with the same success message regardless of whether the email matched an unverified account (prevents enumeration).

**Rate limit**: Returns same success message but no new token if a token was issued for this email within the last 5 minutes.

**Side effects on valid match**: Old unused tokens invalidated (`used_at = NOW()`); new token created; link written to dev log.

---

## Modified Routes

### `POST /login.php`
Updated to handle three outcomes:

| Return code | Visible behaviour |
|-------------|------------------|
| `LOGIN_OK` | `302 Location: /admin/` |
| `LOGIN_INVALID` | `200` — "Invalid username or password." |
| `LOGIN_UNVERIFIED` | `200` — "Please verify your email address before logging in." + link to `/resend-verification.php` |

**Input field change**: The `username` field now accepts username OR email. Label updated to "Username or Email".

---

## Modified Admin Routes

### `GET /admin/users/view.php?id={id}`
Adds a new "Verification Status" section showing `email_verified_at` (or "Not verified").

**New POST actions** (CSRF-protected):

| action | Min role | Effect |
|--------|----------|--------|
| `manual_verify` | system_admin | Sets `email_verified_at = NOW()` |
| `resend_verify` | system_admin | Invalidates old tokens, creates new token, writes to dev log |
| `delete_user` | system_admin | Deletes user + cascades to email_verifications, user_roles, mod_log |

---

### `GET /admin/users/index.php`
Adds an optional `?verified=0` query parameter to filter the user list to unverified accounts only.
