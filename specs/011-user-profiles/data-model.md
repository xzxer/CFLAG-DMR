# Data Model: User Profiles (F4)

## Existing Table Used

### users (no changes)

Relevant columns already present:
- `id`, `username` (immutable login identifier)
- `callsign` VARCHAR(10) UNIQUE NULL
- `email` VARCHAR(255) UNIQUE NOT NULL
- `email_verified_at` DATETIME NULL
- `password_hash` VARCHAR(255) NOT NULL
- `display_name` VARCHAR(128) NOT NULL
- `dmr_id` INT UNSIGNED UNIQUE NULL
- `moderation_state` ENUM('active','suspended','banned','muted_on_network')
- `created_at`, `updated_at`

## New Tables

### email_change_requests

One active email change request per user.

```sql
CREATE TABLE email_change_requests (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    new_email       VARCHAR(255) NOT NULL,
    token           CHAR(64) NOT NULL,
    expires_at      DATETIME NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user (user_id),
    UNIQUE KEY uq_token (token),
    CONSTRAINT fk_ecr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### callsign_update_requests

One active request per user; status tracks lifecycle.

```sql
CREATE TABLE callsign_update_requests (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    old_callsign        VARCHAR(10) NULL,
    requested_callsign  VARCHAR(10) NOT NULL,
    explanation         TEXT NULL,
    status              ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    reviewed_by_user_id INT UNSIGNED NULL,
    review_notes        TEXT NULL,
    reviewed_at         DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_status (user_id, status),
    KEY idx_status (status),
    CONSTRAINT fk_cur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_cur_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Notes

- The UNIQUE KEY on `email_change_requests.user_id` enforces one active request per user; replacing requests uses `INSERT ... ON DUPLICATE KEY UPDATE` or DELETE+INSERT
- `callsign_update_requests` keeps history (multiple rows per user) with status tracking. Only one `pending` request per user is enforced at the application layer (check before INSERT)
- Callsign format validation (uppercase letters + optional digits, typical amateur callsign pattern) is enforced at the application layer, not as a DB constraint, to allow for international callsign variability
