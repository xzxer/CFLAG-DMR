CREATE TABLE IF NOT EXISTS subscriber_ids (
    radio_id        INT UNSIGNED    NOT NULL,
    callsign        VARCHAR(16)     NOT NULL,
    name            VARCHAR(128)    NOT NULL DEFAULT '',
    city            VARCHAR(128)    NOT NULL DEFAULT '',
    state           VARCHAR(64)     NOT NULL DEFAULT '',
    country         VARCHAR(64)     NOT NULL DEFAULT '',
    source          ENUM('radioid','local') NOT NULL DEFAULT 'radioid',
    last_updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (radio_id),
    INDEX idx_callsign (callsign),
    INDEX idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
