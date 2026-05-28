# Data Model: Admin Login — 001-admin-login

## Entities

### admin_users

Stores administrator accounts. No public registration; rows are created manually or via a future admin management interface.

| Column          | Type                        | Constraints                              | Notes                                              |
|-----------------|-----------------------------|-----------------------------------------|----------------------------------------------------|
| `id`            | `INT UNSIGNED`              | PK, AUTO_INCREMENT, NOT NULL            |                                                    |
| `username`      | `VARCHAR(64)`               | NOT NULL, UNIQUE                        | Case-sensitive login identifier                    |
| `password_hash` | `VARCHAR(255)`              | NOT NULL                                | Must be ≥255 chars to accommodate bcrypt and future algorithms |
| `display_name`  | `VARCHAR(128)`              | NOT NULL                                | Shown in dashboard UI                              |
| `is_active`     | `TINYINT(1)`                | NOT NULL, DEFAULT 1                     | 0 = disabled; account persists but cannot log in  |
| `last_login_at` | `DATETIME`                  | NULL                                    | Updated on each successful login                   |
| `created_at`    | `DATETIME`                  | NOT NULL, DEFAULT CURRENT_TIMESTAMP     |                                                    |
| `updated_at`    | `DATETIME`                  | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | |

**Indexes**: PRIMARY on `id`, UNIQUE on `username`.

### Session Data (PHP native — not persisted to DB)

Stored in `$_SESSION` after successful login:

| Key                  | Type     | Description                        |
|----------------------|----------|------------------------------------|
| `admin_id`           | int      | `admin_users.id`                   |
| `admin_username`     | string   | `admin_users.username`             |
| `admin_display_name` | string   | `admin_users.display_name`         |
| `authenticated_at`   | int      | Unix timestamp of login            |
| `csrf_token`         | string   | 64-char hex token for form CSRF    |

## Validation Rules

### Login form (POST `/login.php`)
- `username`: required, non-empty string, max 64 chars.
- `password`: required, non-empty string. No max enforced on input (bcrypt truncates at 72 bytes internally, which is acceptable for this admin panel).
- `csrf_token`: required, must match `$_SESSION['csrf_token']` using `hash_equals()`.

### Admin account creation (manual, CLI)
- `username`: unique, ≤64 chars.
- `password_hash`: generated via `password_hash($plaintext, PASSWORD_BCRYPT)`.
- `display_name`: ≤128 chars, non-empty.
- `is_active`: defaults to 1.

## State Transitions

```
Unauthenticated visitor
    │  POST /login.php (valid credentials + CSRF)
    ▼
Authenticated admin session
    │  GET /logout.php
    ▼
Session destroyed → redirect /login.php
```

```
Admin account
    is_active=1 → can log in
    is_active=0 → login attempt returns generic error; account remains in DB
```

## Environment Variables

Required in `.env` (public contract defined in `.env.example`):

| Variable               | Example value         | Purpose                                            |
|------------------------|-----------------------|----------------------------------------------------|
| `APP_NAME`             | `"CFLAG DMR"`        | Application display name                           |
| `APP_ENV`              | `development`         | Environment identifier                             |
| `APP_DEBUG`            | `true`                | Debug mode flag                                    |
| `APP_URL`              | `http://...`          | Base URL                                           |
| `DB_HOST`              | `localhost`           | MariaDB host                                       |
| `DB_NAME`              | `cflag_dmr_dev`       | Database name                                      |
| `DB_USER`              | `cflag_dmr_user`      | Database user                                      |
| `DB_PASS`              | `...`                 | Database password                                  |
| `SESSION_SECURE_COOKIE`| `false`               | Set `true` in production (HTTPS); `false` in dev   |
