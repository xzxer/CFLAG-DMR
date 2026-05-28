-- F3: User Registration & Accounts
-- Adds callsign and email_verified_at to users; creates email_verifications
-- and system_settings tables; backfills existing accounts as verified.

ALTER TABLE `users`
    ADD COLUMN `callsign` VARCHAR(10)  NULL
        COLLATE utf8mb4_unicode_ci
        AFTER `username`,
    ADD COLUMN `email_verified_at` DATETIME NULL
        AFTER `email`,
    ADD UNIQUE KEY `uq_callsign` (`callsign`);

-- Backfill: existing migrated admin accounts are treated as email-verified
-- from their creation date so they are not locked out after this migration.
UPDATE `users`
   SET `email_verified_at` = `created_at`
 WHERE `email_verified_at` IS NULL;

CREATE TABLE `email_verifications` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `token`      VARCHAR(64)   NOT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME      NOT NULL,
    `used_at`    DATETIME          NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_ev_user_id` (`user_id`),
    CONSTRAINT `fk_ev_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
    `key`         VARCHAR(64)  NOT NULL,
    `value`       TEXT         NOT NULL,
    `description` TEXT             NULL,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
    ('radioid_validation_enabled', '1',
     'Validate callsign/DMR ID against RadioID.net API on registration. Set to 0 to disable.');
