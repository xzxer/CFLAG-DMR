-- Migration 008: Create devices table
-- Part of F5: Hotspot & Repeater Registration

CREATE TABLE devices (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED  NOT NULL,
    callsign      VARCHAR(16)   NOT NULL,
    dmr_id        INT UNSIGNED  NOT NULL,
    device_type   ENUM('hotspot','repeater') NOT NULL,
    hardware_desc VARCHAR(255)  NOT NULL DEFAULT '',
    status        ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    approved_by   INT UNSIGNED  NULL,
    denied_reason TEXT          NULL,
    reviewed_at   DATETIME      NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_status  (status),
    KEY idx_dmr_id  (dmr_id),
    CONSTRAINT fk_devices_user     FOREIGN KEY (user_id)     REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_devices_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
