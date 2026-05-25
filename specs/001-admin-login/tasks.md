---
description: "Implementation tasks for the admin login feature"
---

# Tasks: Admin Login

**Input**: Design documents from `specs/001-admin-login/`

**Sources**: [plan.md](plan.md) · [data-model.md](data-model.md) · [contracts/http-routes.md](contracts/http-routes.md) · [research.md](research.md) · [quickstart.md](quickstart.md)

**Tests**: No automated test framework in scope for this slice. Validation is manual (syntax checks + browser walkthrough).

---

## Phase 1: Setup

**Purpose**: Pre-implementation environment and schema artifacts. Both tasks are independent and can be done in parallel.

- [ ] T001 [P] Add `SESSION_SECURE_COOKIE=false` to `.env` (append after `DB_PASS` line; file is at project root, not committed)
- [ ] T002 [P] Create `migrations/001_create_admin_users_table.sql` — write the full `CREATE TABLE admin_users` statement per the Database Schema in plan.md: columns `id INT UNSIGNED AUTO_INCREMENT PK`, `username VARCHAR(64) NOT NULL UNIQUE`, `password_hash VARCHAR(255) NOT NULL`, `display_name VARCHAR(128) NOT NULL`, `is_active TINYINT(1) NOT NULL DEFAULT 1`, `last_login_at DATETIME NULL DEFAULT NULL`, `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`; ENGINE=InnoDB, CHARSET=utf8mb4, COLLATE=utf8mb4_unicode_ci

**Checkpoint**: `.env` has `SESSION_SECURE_COOKIE`, migration file is written and ready to apply.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure required by every subsequent file. Must complete in dependency order.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 Create `app/config/env.php` — declare strict_types=1; open `.env` at project root using `dirname(__DIR__)` path resolution; parse line by line: skip blank lines and lines starting with `#`; split on first `=`; strip surrounding single or double quotes from the value; store parsed pairs in a module-level array; expose `env(string $key, mixed $default = null): mixed` helper that returns the value or default; throw a `RuntimeException` with a clear message if `.env` is missing
- [ ] T004 [P] Create `app/database/connection.php` — declare strict_types=1; require `app/config/env.php`; define `get_db(): PDO` that builds DSN as `mysql:host={DB_HOST};dbname={DB_NAME};charset=utf8mb4` using `env()`; constructs PDO with DB_USER and DB_PASS from env; sets `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, `PDO::ATTR_EMULATE_PREPARES => false`; returns the PDO instance (simple function, not a class)
- [ ] T005 [P] Create `app/auth/session.php` — declare strict_types=1; require `app/config/env.php`; define these functions in order: (1) `start_session(): void` — calls `session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => (bool) env('SESSION_SECURE_COOKIE', false)])` then `session_start()`; (2) `csrf_token(): string` — if `$_SESSION['csrf_token']` not set, generate with `bin2hex(random_bytes(32))` and store it, then return it; (3) `verify_csrf(string $token): bool` — return `hash_equals($_SESSION['csrf_token'] ?? '', $token)`; (4) `is_admin(): bool` — return `isset($_SESSION['admin_id']) && is_int($_SESSION['admin_id'])`; (5) `require_admin(): void` — if not `is_admin()`, call `redirect('/login.php')`; (6) `redirect(string $url): never` — `header('Location: ' . $url); exit;`

**Checkpoint**: `env()`, `get_db()`, `start_session()`, `csrf_token()`, `verify_csrf()`, `is_admin()`, `require_admin()`, and `redirect()` are all defined and ready to require.

---

## Phase 3: User Story 1 — Admin Login Flow (Priority: P1) 🎯 MVP

**Goal**: An admin can submit credentials on `/login.php` and, if valid, be redirected to the protected `/admin/` dashboard.

**Independent Test**: Apply migration, seed one admin user, visit `/login.php`, submit valid credentials, confirm redirect to `/admin/`.

- [ ] T006 [US1] Create `app/auth/login.php` — declare strict_types=1; require `app/database/connection.php` and `app/auth/session.php`; define `attempt_login(string $username, string $password): bool`; use `get_db()` to prepare `SELECT id, username, password_hash, display_name, is_active FROM admin_users WHERE username = ?` with `$username` bound; if no row returned, call `password_verify($password, '$2y$10$invalidhashpadding000000000000000000000000000000000000000')` to consume constant time then return false; if `is_active` is 0, return false; call `password_verify($password, $row['password_hash'])`; on false return false; on true: call `session_regenerate_id(true)`, set `$_SESSION['admin_id'] = (int) $row['id']`, `$_SESSION['admin_username'] = $row['username']`, `$_SESSION['admin_display_name'] = $row['display_name']`, `$_SESSION['authenticated_at'] = time()`; prepare and execute `UPDATE admin_users SET last_login_at = NOW() WHERE id = ?` with the admin id; return true
- [ ] T007 [US1] Create `public/login.php` — declare strict_types=1; require `dirname(__DIR__) . '/app/auth/session.php'` and `dirname(__DIR__) . '/app/auth/login.php'`; call `start_session()`; if `is_admin()` call `redirect('/admin/')`; initialize `$error = ''`; on POST: call `verify_csrf($_POST['csrf_token'] ?? '')` — if false set `$error = 'Invalid username or password.'`; else if username or password empty set same error; else call `attempt_login($_POST['username'], $_POST['password'])` — on true call `redirect('/admin/')`, on false set `$error = 'Invalid username or password.'`; render HTML page with `<!doctype html>`, link `/assets/css/app.css`, form with `method="post" action="/login.php"`, inputs for `username` and `password`, hidden `csrf_token` field populated with `htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')`, display `$error` if non-empty using `htmlspecialchars`; keep styling consistent with existing `.card` layout in `app.css`

**Checkpoint**: Submit valid credentials at `/login.php` → redirect to `/admin/`. Submit bad credentials → generic error re-rendered. Authenticated admin visiting `/login.php` → redirect to `/admin/`.

---

## Phase 4: User Story 2 — Protected Dashboard & Logout (Priority: P2)

**Goal**: Authenticated admins see a protected dashboard at `/admin/`; unauthenticated visitors are redirected to `/login.php`; any admin can log out via `/logout.php`.

**Independent Test**: After completing US1, log in, visit `/admin/` — confirm display name shown; visit `/logout.php` — confirm redirect to `/login.php`; visit `/admin/` again — confirm redirect to `/login.php`.

- [ ] T008 [P] [US2] Create `public/admin/index.php` — declare strict_types=1; require `dirname(__DIR__, 2) . '/app/auth/session.php'`; call `start_session()`; call `require_admin()` (redirects and exits if not authenticated); resolve display name: `$name = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin'`; render HTML page with `<!doctype html>`, link `/assets/css/app.css`, show the display name escaped with `htmlspecialchars($name, ENT_QUOTES, 'UTF-8')` in an `<h1>` or similar heading, include a logout link `<a href="/logout.php">Log out</a>`; keep layout consistent with existing `.card` and `.page` structure from `app.css`
- [ ] T009 [P] [US2] Create `public/logout.php` — declare strict_types=1; require `dirname(__DIR__) . '/app/auth/session.php'`; call `start_session()`; clear session data with `$_SESSION = []`; if `ini_get('session.use_cookies')` is truthy, call `setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict'])` to expire the session cookie; call `session_destroy()`; call `redirect('/login.php')`

**Checkpoint**: Full round-trip works: log in → `/admin/` shows dashboard → log out → `/admin/` redirects to `/login.php`.

---

## Phase 5: Validation & Sign-Off

**Purpose**: Verify all files are syntactically correct, the database is seeded, and the full login/logout/access-control flow passes manual browser testing.

- [ ] T010 [P] Run PHP syntax check on all new files: execute `php -l app/config/env.php && php -l app/database/connection.php && php -l app/auth/session.php && php -l app/auth/login.php && php -l public/login.php && php -l public/logout.php && php -l public/admin/index.php` — all must report `No syntax errors detected`
- [ ] T011 [P] Apply migration: run `mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/001_create_admin_users_table.sql`; verify with `mysql -u cflag_dmr_user -p cflag_dmr_dev -e "DESCRIBE admin_users;"` — confirm all columns are present
- [ ] T012 Seed initial admin user: run `php -r "echo password_hash('admin', PASSWORD_BCRYPT) . PHP_EOL;"` to generate a hash (use a real password, not 'admin', in practice); run `mysql -u cflag_dmr_user -p cflag_dmr_dev -e "INSERT INTO admin_users (username, password_hash, display_name) VALUES ('admin', 'PASTE_HASH', 'Site Admin');"` substituting the actual hash; verify with `SELECT id, username, display_name, is_active FROM admin_users;`
- [ ] T013 Browser test — full login/logout/access-control sequence: (1) visit `http://<dev-server>/admin/` while logged out — confirm redirect to `/login.php`; (2) submit wrong credentials — confirm page reloads with "Invalid username or password." and no other detail; (3) submit correct credentials — confirm redirect to `/admin/`; (4) confirm `/admin/` shows the seeded admin display name and a logout link; (5) click logout — confirm redirect to `/login.php`; (6) visit `/admin/` again — confirm redirect to `/login.php`; (7) CSRF: load `/login.php`, note the hidden `csrf_token` in page source, POST with a tampered token — confirm generic error
- [ ] T014 [P] Check Apache error log: run `sudo tail -50 /var/log/apache2/error.log` — confirm no PHP fatal errors or warnings attributable to the new files

**Checkpoint**: All syntax checks pass. Admin user exists in DB. All 7 browser test steps pass. Apache log is clean. Feature is complete.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately. T001 and T002 are fully parallel.
- **Phase 2 (Foundational)**: Depends on Phase 1. T003 must complete first; T004 and T005 can run in parallel after T003.
- **Phase 3 (US1)**: Depends on Phase 2. T006 requires T003+T004+T005; T007 requires T006.
- **Phase 4 (US2)**: Depends on Phase 2 (T005). T008 and T009 are parallel with each other; can begin as soon as Phase 2 is done — does not need to wait for Phase 3.
- **Phase 5 (Validation)**: T010 can begin after all PHP files are written (end of Phase 4). T011 can begin any time after T002. T012 requires T011. T013 requires T010+T011+T012. T014 is parallel with T013.

### User Story Dependencies

- **US1 (Login Flow)**: Requires Phase 2 foundation. Independent of US2.
- **US2 (Dashboard & Logout)**: Requires Phase 2 foundation (`session.php` only). Can be implemented in parallel with US1.

### Within Each Phase

- T003 → T004 and T005 can run in parallel after T003
- T004 + T005 → T006
- T006 → T007
- T005 → T008 and T009 (parallel, independent of T006/T007)
- All PHP files done → T010 (syntax), T011 (migration) in parallel
- T011 → T012
- T010 + T012 → T013

### Parallel Opportunities

```
# Phase 1 — run together:
T001: Add SESSION_SECURE_COOKIE to .env
T002: Create migrations/001_create_admin_users_table.sql

# Phase 2 — after T003 completes:
T004: Create app/database/connection.php
T005: Create app/auth/session.php

# Phase 4 — after Phase 2 completes:
T008: Create public/admin/index.php
T009: Create public/logout.php

# Phase 5 — after all PHP files written:
T010: php -l syntax checks
T011: Apply migration

# Phase 5 — after T013 completes:
T014: Check Apache error log  (can also run alongside T013)
```

---

## Implementation Strategy

### MVP First

1. Complete Phase 1 — Setup
2. Complete Phase 2 — Foundational (blocks everything)
3. Complete Phase 3 — US1: Login Flow (login page + auth logic)
4. **STOP and validate**: seed a user, visit `/login.php`, confirm you can log in
5. Complete Phase 4 — US2: Dashboard + Logout
6. Complete Phase 5 — Validation (syntax + browser + log)

### Incremental Delivery

1. Phase 1 + Phase 2 → infrastructure ready
2. Phase 3 → login works; admin can authenticate
3. Phase 4 → dashboard and logout work; full auth cycle complete
4. Phase 5 → feature signed off; ready for PR to `dev`

---

## Notes

- `[P]` = different files, no blocking dependencies between those specific tasks — safe to run in parallel
- `[US1]` / `[US2]` = user story traceability
- All PHP files must begin with `declare(strict_types=1);`
- All HTML output from session/user data must use `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`
- `.env` is gitignored — T001 edits it in place; do not commit `.env`
- `SESSION_SECURE_COOKIE` must also be added to `.env.example` so future developers know the variable exists (this can be done alongside T001 since it's a committed file)
- Seed instructions (T012) are manual dev-time steps, not committed code
- Commit after each phase boundary, not after individual tasks
