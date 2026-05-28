-- F7: Network Config & Peer Management
-- master_server_settings (singleton) and openbridge_connections

CREATE TABLE IF NOT EXISTS master_server_settings (
    id                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    bind_address        VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
    port                SMALLINT UNSIGNED NOT NULL DEFAULT 62031,
    passphrase          VARCHAR(15) NOT NULL DEFAULT 'passphrase',
    report_address      VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
    report_port         SMALLINT UNSIGNED NOT NULL DEFAULT 4321,
    ping_time           SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    max_missed          SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    hblink_instance_id  INT UNSIGNED NULL DEFAULT NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by_user_id  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_mss_user FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO master_server_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS openbridge_connections (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(64) NOT NULL,
    remote_address      VARCHAR(45) NOT NULL,
    port                SMALLINT UNSIGNED NOT NULL DEFAULT 62035,
    passphrase          VARCHAR(15) NOT NULL,
    network_id          INT UNSIGNED NOT NULL,
    enabled             TINYINT(1) NOT NULL DEFAULT 1,
    hblink_instance_id  INT UNSIGNED NULL DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ob_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
