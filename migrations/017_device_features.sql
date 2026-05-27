-- Migration 017: per-device feature flags (TG rewrite)
ALTER TABLE devices
    ADD COLUMN tg_rewrite_enabled  TINYINT(1)   NOT NULL DEFAULT 0  AFTER device_passphrase,
    ADD COLUMN tg_rewrite_prefix   VARCHAR(10)  NULL                 AFTER tg_rewrite_enabled;
