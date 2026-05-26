-- F4: User Profiles — callsign update request table
CREATE TABLE IF NOT EXISTS callsign_update_requests (
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
