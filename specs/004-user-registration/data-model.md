# Data Model: F3 — User Registration & Accounts

## Changes to Existing Tables

### `users` — ALTER (Migration 005)

Add two new columns:

```sql
ALTER TABLE users
    ADD COLUMN `callsign` VARCHAR(10)   NULL AFTER `username`,
    ADD COLUMN `email_verified_at` DATETIME NULL AFTER `email`,
    ADD UNIQUE KEY `uq_callsign` (`callsign`);
```

**Backfill** (same migration):

```sql
-- Mark all existing users as email-verified (migrated admin accounts)
UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL;
```

**Column notes:**
- `callsign`: nullable in DB; NOT NULL enforced at application layer during registration. `utf8mb4_unicode_ci` collation makes the unique constraint case-insensitive. Stored uppercase.
- `email_verified_at`: NULL = not yet verified; populated by `/verify-email.php` or admin manual-verify action.

---

## New Tables

### `email_verifications`

Stores single-use email verification tokens. One active token per user at a time; old tokens are invalidated (used_at set) before a new one is issued.

```sql
CREATE TABLE `email_verifications` (
    `id`         INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED      NOT NULL,
    `token`      VARCHAR(64)       NOT NULL,
    `created_at` DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME          NOT NULL,
    `used_at`    DATETIME              NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_ev_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field notes:**
- `token`: 64-character lowercase hex string from `bin2hex(random_bytes(32))`.
- `expires_at`: set to `created_at + INTERVAL 24 HOUR` at insert time.
- `used_at`: NULL = unused (active or expired-but-not-used); non-NULL = consumed.
- FK cascade on user delete ensures tokens are removed when the account is deleted.

**Rate-limit query** (one resend per email per 5 minutes):
```sql
SELECT MAX(ev.created_at)
  FROM email_verifications ev
  JOIN users u ON u.id = ev.user_id
 WHERE u.email = ?
```
If result > NOW() - INTERVAL 5 MINUTE → reject resend.

---

### `system_settings`

Key-value store for admin-configurable flags and settings. Created in this feature; will be extended by subsequent features.

```sql
CREATE TABLE `system_settings` (
    `key`         VARCHAR(64)  NOT NULL,
    `value`       TEXT         NOT NULL,
    `description` TEXT             NULL,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Seed data** (same migration):
```sql
INSERT INTO system_settings (`key`, `value`, `description`) VALUES
    ('radioid_validation_enabled', '1',
     'Validate callsign/DMR ID against RadioID.net API on registration. Set to 0 to disable.');
```

**Access pattern**: `SELECT value FROM system_settings WHERE key = 'radioid_validation_enabled'` — returns `'1'` or `'0'`.

---

## Migration File Index

| File | Purpose |
|------|---------|
| `migrations/005_add_callsign_email_verified.sql` | ALTER users (callsign, email_verified_at + backfill), CREATE email_verifications, CREATE system_settings, seed radioid_validation_enabled |

---

## Entity Relationships

```
users (1) ──< email_verifications (many)
              [FK: user_id → users.id, CASCADE DELETE]

system_settings
  [standalone key-value; no FK relationships]
```

---

## `.env` Changes

| Variable | Value | Purpose |
|----------|-------|---------|
| `EMAIL_DEV_MODE` | `true` | Write verification emails to log instead of sending |

Add to `.env` and `.env.example`.
