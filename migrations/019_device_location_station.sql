-- Migration 019: device location and station info
-- Populated by user manually or auto-filled from RPTC packet when hotspot connects.
ALTER TABLE devices
    ADD COLUMN lat              DECIMAL(10,6)       NULL               AFTER tg_rewrite_prefix,
    ADD COLUMN lon              DECIMAL(10,6)       NULL               AFTER lat,
    ADD COLUMN rx_freq          BIGINT UNSIGNED     NULL               AFTER lon,
    ADD COLUMN tx_freq          BIGINT UNSIGNED     NULL               AFTER rx_freq,
    ADD COLUMN tx_power         TINYINT UNSIGNED    NULL               AFTER tx_freq,
    ADD COLUMN height_m         SMALLINT            NULL DEFAULT 0     AFTER tx_power,
    ADD COLUMN location_desc    VARCHAR(255)        NULL               AFTER height_m,
    ADD COLUMN station_desc     VARCHAR(255)        NULL               AFTER location_desc,
    ADD COLUMN station_url      VARCHAR(512)        NULL               AFTER station_desc,
    ADD COLUMN rptc_updated_at  DATETIME            NULL               AFTER station_url;
