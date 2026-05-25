# Tasks: F3 — User Registration & Accounts

**Branch**: `004-user-registration`
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Data model**: [data-model.md](data-model.md)

**Format**: `- [ ] [TaskID] [P?] [Story?] Description with file path`
- **[P]**: Parallelizable — different files, no dependency on an incomplete sibling task
- **[USn]**: User story this task belongs to

---

## Phase 1: Setup

**Purpose**: Create directories, log file, and env variable stubs needed before any code runs.

- [x] T001 Create directories `app/registration/` and `app/email/`; create `/var/log/cflag-dmr-email-dev.log` via `touch /var/log/cflag-dmr-email-dev.log && chmod 644 /var/log/cflag-dmr-email-dev.log`
- [x] T002 Add `EMAIL_DEV_MODE=true` to `.env` (after existing vars); add `EMAIL_DEV_MODE=` (blank) to `.env.example`

**Checkpoint**: Directories exist, log file writable, env var documented. Proceed to foundational work.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migration file, auth layer update, registration helpers, and email abstraction. ALL must complete before any user story work begins.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T003 [P] Write `migrations/005_add_callsign_email_verified.sql` — (1) `ALTER TABLE users ADD COLUMN callsign VARCHAR(10) NULL AFTER username, ADD COLUMN email_verified_at DATETIME NULL AFTER email, ADD UNIQUE KEY uq_callsign (callsign)` (collation utf8mb4_unicode_ci makes the unique constraint case-insensitive); (2) `UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL` (backfill existing admin accounts so they are not locked out); (3) CREATE TABLE `email_verifications` (id UNSIGNED INT PK AUTO_INCREMENT, user_id UNSIGNED INT NOT NULL FK→users.id CASCADE, token VARCHAR(64) NOT NULL UNIQUE, created_at DATETIME DEFAULT NOW(), expires_at DATETIME NOT NULL, used_at DATETIME NULL) ENGINE=InnoDB; (4) CREATE TABLE `system_settings` (key VARCHAR(64) PK, value TEXT NOT NULL, description TEXT NULL, updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()) ENGINE=InnoDB; (5) INSERT INTO system_settings VALUES ('radioid_validation_enabled', '1', 'Validate callsign/DMR ID against RadioID.net API on registration. 0 to disable.')

- [x] T004 [P] Create `app/email/mailer.php` — `declare(strict_types=1)`; `require_once __DIR__ . '/../config/env.php'`; implement `send_verification_email(string $to_email, string $display_name, string $token): void`: if `env('EMAIL_DEV_MODE') === 'true'`: append line `[date] TO: {email} | LINK: http://{HTTP_HOST}/verify-email.php?token={token}\n` to `/var/log/cflag-dmr-email-dev.log` using `file_put_contents(..., FILE_APPEND | LOCK_EX)`; else: `mail($to_email, 'Verify your CFLAG DMR email address', $body, "From: noreply@{$_SERVER['HTTP_HOST']}")`  where $body includes the verify URL

- [x] T005 [P] Create `app/registration/register.php` — `declare(strict_types=1)`; require env.php, connection.php; implement five functions: (1) `validate_registration(array $data): array` — validate callsign against `/^[A-Za-z]{1,2}[0-9][A-Za-z]{1,3}$/`, dmr_id `ctype_digit && strlen == 7`, email `filter_var(FILTER_VALIDATE_EMAIL)`, password `strlen >= 12`, display_name `strlen >= 2 && <= 64`; for uniqueness run single query `SELECT username, email FROM users WHERE UPPER(username) = ? OR email = ? OR dmr_id = ? LIMIT 1` and on match return `['callsign' => 'That callsign, email, or DMR ID is already in use.']`; return array of field→error (empty = valid); (2) `is_radioid_validation_enabled(): bool` — `SELECT value FROM system_settings WHERE \`key\` = 'radioid_validation_enabled' LIMIT 1`; return value === '1'; (3) `lookup_radioid(int $dmr_id): ?array` — cURL GET `https://www.radioid.net/api/dmr/user/?id={$dmr_id}` with CURLOPT_TIMEOUT=5, CURLOPT_RETURNTRANSFER=true; on HTTP 200 + non-empty `results`: return `['callsign' => $results[0]['callsign']]`; on empty results: return null (not found); on cURL error or non-200: throw `\RuntimeException('RadioID.net lookup failed: ' . $err)`; (4) `register_user(array $data): int` — `$callsign = strtoupper(trim($data['callsign']))`, `$hash = password_hash($data['password'], PASSWORD_BCRYPT)`, INSERT INTO users (username, callsign, email, password_hash, display_name, dmr_id, moderation_state, tier) VALUES (?,?,?,?,?,?,'active','free') with prepared statement; return `(int)$db->lastInsertId()`; (5) `create_verification_token(int $user_id): string` — `$token = bin2hex(random_bytes(32))`; INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR)); return $token

- [x] T006 [P] Update `app/auth/login.php` — add three string constants at file top: `const LOGIN_OK = 'ok'; const LOGIN_INVALID = 'invalid'; const LOGIN_UNVERIFIED = 'unverified';`; change `attempt_login(string $username, string $password): bool` signature to `attempt_login(string $identifier, string $password): string`; detect email vs username via `str_contains($identifier, '@')`; update SELECT query to `SELECT id, username, display_name, password_hash, moderation_state, email_verified_at FROM users WHERE ` + ($by_email ? `email = ?` : `username = ?`) + ` LIMIT 1`; after `password_verify` passes: check `$row['email_verified_at'] === null` → return `LOGIN_UNVERIFIED`; suspended/banned check → return `LOGIN_INVALID`; success path: `session_regenerate_id(true)`, set `$_SESSION['user_id']`/`username`/`display_name`, UPDATE last_login_at, return `LOGIN_OK`

- [x] T007 Apply `migrations/005_add_callsign_email_verified.sql` to `cflag_dmr_dev` — run `mysql -u cflag_dmr_user -p'MaxRadioPower99!' cflag_dmr_dev < migrations/005_add_callsign_email_verified.sql`; verify with: `DESCRIBE users` (confirm callsign + email_verified_at columns exist); `SHOW CREATE TABLE email_verifications`; `SELECT * FROM system_settings`; `SELECT id, username, email_verified_at FROM users` (confirm admin account has email_verified_at backfilled)

**Checkpoint**: Migration applied, mailer ready, registration functions ready, login returns string codes. Existing admin account can still log in. Proceed to user story work.

---

## Phase 3: User Story 1 — Public Registration (Priority: P1) 🎯 MVP

**Goal**: Any visitor can register a new account via `/register.php`. Fields are validated, RadioID.net is checked when enabled, and a verification token is written to the dev log.

**Independent Test**: Navigate to `/register.php`, submit valid registration data (RadioID.net disabled), confirm user row in `users` with `email_verified_at = NULL`, confirm token in `/var/log/cflag-dmr-email-dev.log`. (Quickstart Scenarios 1, 7.)

- [x] T008 [US1] Create `public/register.php` — `declare(strict_types=1)`; require env.php, session.php, roles.php, `app/registration/register.php`, `app/email/mailer.php`; `start_session()`; if `is_logged_in()`: `redirect('/admin/')`; **GET ?success=1**: render confirmation card: "Registration submitted — check your email for a verification link."; **GET**: render registration form with fields callsign (text), dmr_id (text), email (email), password (password), display_name (text), hidden csrf_token; repopulate field values from `$_SESSION['reg_old_input']` if set, then unset it; show per-field errors from `$_SESSION['reg_errors']` if set, then unset it; **POST handler**: `verify_csrf($_POST['csrf_token'])` or redirect back with generic error; `$errors = validate_registration($_POST)`; if `is_radioid_validation_enabled()`: `try { $rid = lookup_radioid((int)$_POST['dmr_id']); if ($rid === null) { $errors['dmr_id'] = 'DMR ID not found in RadioID.net database.'; } elseif (strtoupper($rid['callsign']) !== strtoupper(trim($_POST['callsign'] ?? ''))) { $errors['callsign'] = 'The callsign and DMR ID do not match RadioID.net records.'; } } catch (\RuntimeException $e) { error_log($e->getMessage()); /* fallback: skip RadioID check */ }`; if `$errors` non-empty: store in `$_SESSION['reg_errors']` and `$_SESSION['reg_old_input']` = $_POST, `redirect('/register.php')`; `$user_id = register_user($_POST)`; `$token = create_verification_token($user_id)`; `send_verification_email($_POST['email'], $_POST['display_name'] ?? '', $token)`; `redirect('/register.php?success=1')` (PRG pattern); all output via `htmlspecialchars(ENT_QUOTES, 'UTF-8')`

**Checkpoint**: US1 complete. Verify Quickstart Scenarios 1 and 7 before proceeding.

---

## Phase 4: User Story 2 — Email Verification (Priority: P2)

**Goal**: A user who visits the verification link in the dev log activates their account and is redirected to the login page.

**Independent Test**: Copy token URL from dev log, visit it in browser, confirm `email_verified_at` is set, confirm redirect to `/login.php?verified=1`, confirm login succeeds. (Quickstart Scenarios 2, 3, 8.)

- [x] T009 [US2] Create `public/verify-email.php` — `declare(strict_types=1)`; require env.php, connection.php, session.php; `start_session()`; `$token = trim($_GET['token'] ?? '')`; if empty: render "invalid link" page; `$stmt = $db->prepare('SELECT ev.id, ev.user_id, ev.expires_at, ev.used_at FROM email_verifications ev WHERE ev.token = ? LIMIT 1')`; execute with [$token]; `$row = $stmt->fetch()`; if `!$row`: render "This verification link is invalid." page with link to `/resend-verification.php`; if `$row['used_at'] !== null`: render "Your email is already verified." page with link to `/login.php`; if `strtotime($row['expires_at']) < time()`: render "This verification link has expired." page with link to `/resend-verification.php`; valid path: `$db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$row['user_id']])`; `$db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')->execute([$row['id']])`; `redirect('/login.php?verified=1')`; all error pages use existing `.card` layout

**Checkpoint**: US2 complete. Verify Quickstart Scenarios 2, 3, and 8.

---

## Phase 5: User Story 3 — Resend Verification + Login Update (Priority: P3)

**Goal**: Users who miss the verification email can request a resend. The login page shows the correct message for unverified accounts. Login accepts email address as well as username.

**Independent Test**: Attempt login with an unverified account — confirm specific message and resend link appear. Request resend, confirm new token in dev log, old token invalidated. Log in via email address — confirm success. (Quickstart Scenarios 4, 5, 6, 9.)

- [x] T010 [P] [US3] Create `public/resend-verification.php` — `declare(strict_types=1)`; require env.php, connection.php, session.php, `app/email/mailer.php`; `start_session()`; **GET**: render form with email input, csrf_token, submit button; **POST handler**: `verify_csrf` or re-render; `$email = trim($_POST['email'] ?? '')`; **rate limit check**: `$last = $db->prepare('SELECT MAX(ev.created_at) FROM email_verifications ev JOIN users u ON u.id = ev.user_id WHERE u.email = ?')->...fetchColumn()`; if `$last !== null && strtotime($last) > time() - 300`: fall through to show success without issuing new token; else: `$user = $db->prepare('SELECT id, email, display_name FROM users WHERE email = ? AND email_verified_at IS NULL LIMIT 1')->...fetch()`; if `$user`: `$db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$user['id']])`; `$token = bin2hex(random_bytes(32))`; `$db->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')->execute([$user['id'], $token])`; `send_verification_email($user['email'], $user['display_name'], $token)`; **always render**: "If that email address is registered and unverified, a new verification link has been sent. Check your inbox."

- [x] T011 [P] [US3] Update `public/login.php` — (1) change `<label for="username">Username</label>` to `<label for="username">Username or Email</label>`; (2) change `attempt_login($_POST['username'], $_POST['password'])` call to capture string result: `$result = attempt_login(...)` and update the conditional: `if ($result === LOGIN_OK) { redirect('/admin/'); }` else if `$result === LOGIN_UNVERIFIED`: set `$error = 'Please verify your email address before logging in.'` and set `$show_resend = true`; else `$error = 'Invalid username or password.'`; (3) in HTML: below the error div, if `$show_resend` is true render `<p><a href="/resend-verification.php" class="nav-link">Resend verification email</a></p>`; (4) handle `$_GET['verified']`: if `'1'` render a `<div class="alert-success">Email verified! You can now log in.</div>` above the form; all additions use `htmlspecialchars`

**Checkpoint**: US3 complete. Verify Quickstart Scenarios 4, 5, 6, and 9.

---

## Phase 6: User Story 4 — Admin Manages Unverified Accounts (Priority: P4)

**Goal**: System admins can view verification status, manually verify accounts, resend tokens, and delete unverified users from the admin UI.

**Independent Test**: Log in as system admin. Open `/admin/users/view.php?id={unverified_user}`, confirm verification status section, use each admin action. Filter user list to unverified. (Quickstart Scenarios 10, 11, 12.)

- [x] T012 [P] [US4] Update `public/admin/users/view.php` — (1) In field rows section: add a "Verification" field row showing `$user['email_verified_at']` formatted as date, or a `<span class="badge badge-unverified">Not Verified</span>` when null; (2) If `$is_system_admin && $user['email_verified_at'] === null`: render two CSRF-protected action forms: "Manually Verify" (POST action=manual_verify, btn-secondary btn-sm, shown in action-form div) and "Resend Verification Email" (POST action=resend_verify, btn-secondary btn-sm); (3) For system_admin only, add a "Delete Account" action form (POST action=delete_user, btn-danger btn-sm, confirm via onclick="return confirm('Delete this account? This cannot be undone.')") — shown regardless of verification state; (4) POST handler additions: **manual_verify**: `$db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$user_id])`; `log_audit_action($actor_id, 'account_verified', 'user', $user_id, ['method' => 'manual'])`; `$flash_success = 'Account manually verified.'`; **resend_verify**: invalidate old unused tokens `UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL`; create new token `bin2hex(random_bytes(32))`; INSERT into email_verifications; `send_verification_email($user['email'], $user['display_name'], $token)` (require mailer.php at top); `log_audit_action(...)`; `$flash_success = 'Verification email resent.'`; **delete_user**: guard self-delete `if ($user_id === (int)$_SESSION['user_id']): $flash_error = 'You cannot delete your own account.'; break;`; `$db->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id])`; `log_audit_action(..., 'account_deleted', ...)`; `redirect('/admin/users/')`;

- [x] T013 [P] [US4] Update `public/admin/users/index.php` — (1) read `$filter_verified = $_GET['verified'] ?? null`; (2) add a WHERE condition to the existing user SELECT: if `$filter_verified === '0'` append `AND u.email_verified_at IS NULL` (build the query string conditionally before `$db->prepare()`); adjust COUNT(*) query with same filter; (3) add filter navigation above the table: `<p style="..."><a href="/admin/users/" class="nav-link">All users</a> | <a href="/admin/users/?verified=0" class="nav-link">Unverified only</a></p>`; (4) in the tbody loop, after the moderation state badge: if `$u['email_verified_at'] === null` also render `<span class="badge badge-unverified">Unverified</span>`; (5) add `u.email_verified_at` to the SELECT column list; all new output via `htmlspecialchars`

**Checkpoint**: US4 complete. Verify Quickstart Scenarios 10, 11, and 12.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: CSS additions, output escaping audit, and final scenario validation.

- [x] T014 [P] Add `.badge-unverified` CSS class to `public/assets/css/app.css` — add after the existing badge definitions (around line 190): `.badge-unverified { background: #1c3151; color: #7dd3fc; }` (light blue, distinct from all existing badge colours)

- [x] T015 [P] Audit all new and modified `public/` files for output escaping — verify every variable rendered to HTML passes through `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`; check: `public/register.php`, `public/verify-email.php`, `public/resend-verification.php`, `public/login.php`, `public/admin/users/view.php`, `public/admin/users/index.php`; fix any unescaped output found

- [x] T016 Run all 13 manual test scenarios from `specs/004-user-registration/quickstart.md` — confirm each passes; document any failures and fix before marking feature complete

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
    └── Phase 2 (Foundational) ← BLOCKS all user stories
            ├── Phase 3 (US1 P1) 🎯 MVP
            │       └── (US1 independent; verify before proceeding)
            ├── Phase 4 (US2 P2) ← independent of US1
            ├── Phase 5 (US3+US5 P3) ← independent of US1/US2
            ├── Phase 6 (US4 P4) ← independent of US1/US2/US3
            └── Phase 7 (Polish) ← needs all stories done
```

### Within Phase 2

T003, T004, T005, T006 can run in parallel (all different files).
T007 depends on T003 only (must apply migration before testing).

### Within Phase 5

T010 and T011 can run in parallel (different files).

### Within Phase 6

T012 and T013 can run in parallel (different files).

### Within Phase 7

T014 and T015 can run in parallel (different files).
T016 depends on all previous tasks.

---

## Parallel Opportunities

```
# Phase 2 — all can be drafted in parallel:
T003  Write migrations/005_add_callsign_email_verified.sql
T004  Create app/email/mailer.php
T005  Create app/registration/register.php
T006  Update app/auth/login.php

# After T007 (migration applied), all user stories can start:
T008  public/register.php         (US1 P1 — MVP)
T009  public/verify-email.php     (US2 P2)
T010  public/resend-verification.php  (US3 P3)
T011  public/login.php update     (US3 P3)
T012  admin/users/view.php update (US4 P4)
T013  admin/users/index.php update (US4 P4)

# Phase 7:
T014  CSS badge addition
T015  Output escaping audit
```

---

## Implementation Strategy

### MVP: US1 + US2 Only (Phases 1–4)

1. Phase 1: Setup (directories, env)
2. Phase 2: Migration, mailer, register.php functions, login.php update
3. Phase 3: public/register.php
4. Phase 4: public/verify-email.php
5. **STOP AND VALIDATE**: Run Quickstart Scenarios 1, 2, 3 — full registration→verify→login cycle works
6. Result: Users can self-register and verify. Login still works for admin.

### Incremental Delivery

1. Phases 1–3 → US1 live: registration form working ✓
2. Phase 4 → US2 live: email verification working ✓
3. Phase 5 → US3+US5 live: resend flow and email login working ✓
4. Phase 6 → US4 live: admin can manage unverified accounts ✓
5. Phase 7 → Feature complete: polished and all 13 scenarios passing ✓

---

## Summary

| Phase | Tasks | Parallelizable |
|-------|-------|----------------|
| 1 — Setup | T001–T002 (2 tasks) | — |
| 2 — Foundational | T003–T007 (5 tasks) | T003/T004/T005/T006 |
| 3 — US1 P1 (Registration) | T008 (1 task) | — |
| 4 — US2 P2 (Verification) | T009 (1 task) | — |
| 5 — US3+US5 P3 (Resend+Login) | T010–T011 (2 tasks) | T010/T011 |
| 6 — US4 P4 (Admin Controls) | T012–T013 (2 tasks) | T012/T013 |
| 7 — Polish | T014–T016 (3 tasks) | T014/T015 |
| **Total** | **16 tasks** | |
