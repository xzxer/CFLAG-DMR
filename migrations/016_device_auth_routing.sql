-- Migration 016: Per-device auth, SSID suffix, routing config state, login audit

-- Per-device passphrase, SSID suffix, and computed peer connection ID
ALTER TABLE devices
    ADD COLUMN ssid_suffix        TINYINT UNSIGNED   NULL            COMMENT '01-99 hotspot suffix appended to base DMR ID' AFTER dmr_id,
    ADD COLUMN peer_id            INT UNSIGNED       NULL            COMMENT 'Full peer connection ID: base_dmr_id*100+suffix for hotspots, dmr_id for repeaters' AFTER ssid_suffix,
    ADD COLUMN device_passphrase  VARCHAR(15)        NULL            COMMENT 'Per-device generated passphrase for HBLink auth' AFTER peer_id,
    ADD UNIQUE KEY uq_peer_id     (peer_id);

-- Version counter for bridge routing change detection (polled by patched bridge.py)
CREATE TABLE routing_config_state (
    id          TINYINT UNSIGNED    NOT NULL DEFAULT 1 PRIMARY KEY,
    version     INT UNSIGNED        NOT NULL DEFAULT 0,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO routing_config_state (id, version) VALUES (1, 0);

-- HBLink peer login attempt log (written by patched hblink.py via JSON, imported by PHP)
CREATE TABLE hblink_login_attempts (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    peer_id       INT UNSIGNED     NOT NULL,
    remote_ip     VARCHAR(45)      NOT NULL DEFAULT '',
    success       TINYINT(1)       NOT NULL DEFAULT 0,
    deny_reason   VARCHAR(255)     NULL,
    attempted_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_peer_id     (peer_id),
    INDEX idx_attempted_at (attempted_at)
);
