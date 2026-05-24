# HTTP Route Contracts: Admin Login — 001-admin-login

Plain PHP server-rendered routes. No REST API; all interactions are form POST + redirect.

---

## GET /login.php

**Purpose**: Render the admin login form.

**Auth required**: No. Redirects to `/admin/` if already authenticated.

**Response**: HTML page with login form.

**Session side-effect**: Generates `$_SESSION['csrf_token']` if not already set.

---

## POST /login.php

**Purpose**: Process login credentials.

**Auth required**: No.

**Request body** (`application/x-www-form-urlencoded`):

| Field        | Required | Description                        |
|--------------|----------|------------------------------------|
| `username`   | Yes      | Admin username                     |
| `password`   | Yes      | Plain-text password                |
| `csrf_token` | Yes      | Must match `$_SESSION['csrf_token']` |

**Success** (valid credentials, active account, valid CSRF):
- Calls `session_regenerate_id(true)`
- Stores `admin_id`, `admin_username`, `admin_display_name`, `authenticated_at` in `$_SESSION`
- Updates `last_login_at` in `admin_users`
- HTTP 302 redirect → `/admin/`

**Failure** (any condition: bad credentials, inactive account, CSRF mismatch, missing fields):
- HTTP 200 re-render of login form
- Displays: `"Invalid username or password."`
- No detail about which condition failed

---

## GET /logout.php

**Purpose**: Destroy the admin session and redirect to login.

**Auth required**: No (safe to call when already logged out).

**Side-effects**:
- `$_SESSION = []`
- `session_destroy()`
- Cookie cleared

**Response**: HTTP 302 redirect → `/login.php`

---

## GET /admin/

**Purpose**: Protected admin dashboard.

**Auth required**: Yes — calls `require_admin()` which redirects to `/login.php` if not authenticated.

**Response**: HTML dashboard showing `admin_display_name` (or `admin_username` as fallback) and a logout link.

---

## Access Control Summary

| Route         | Unauthenticated         | Authenticated           |
|---------------|-------------------------|-------------------------|
| GET /login.php  | Show login form         | Redirect → /admin/      |
| POST /login.php | Process login attempt   | (same — idempotent)     |
| GET /logout.php | Redirect → /login.php   | Destroy session + redirect |
| GET /admin/     | Redirect → /login.php   | Show dashboard          |
