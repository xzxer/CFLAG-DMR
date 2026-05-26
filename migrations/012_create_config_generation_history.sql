-- F7: Network Config & Peer Management
-- Immutable record of each HBLink config generation event

CREATE TABLE IF NOT EXISTS config_generation_history (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    generated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    generated_by_user_id    INT UNSIGNED NULL,
    config_text             MEDIUMTEXT NOT NULL,
    changed                 TINYINT(1) NOT NULL DEFAULT 1,
    diff_text               MEDIUMTEXT NULL,
    PRIMARY KEY (id),
    KEY idx_generated_at (generated_at),
    CONSTRAINT fk_cgh_user FOREIGN KEY (generated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
