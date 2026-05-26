CREATE TABLE device_talkgroup_subscriptions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id    INT UNSIGNED NOT NULL,
    talkgroup_id INT UNSIGNED NOT NULL,
    timeslot     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_tg_slot (device_id, talkgroup_id, timeslot),
    CONSTRAINT fk_dts_device    FOREIGN KEY (device_id)    REFERENCES devices    (id) ON DELETE CASCADE,
    CONSTRAINT fk_dts_talkgroup FOREIGN KEY (talkgroup_id) REFERENCES talkgroups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
