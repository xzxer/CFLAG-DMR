-- F13: Controlled Restart — extend config_generation_history with apply tracking columns

ALTER TABLE config_generation_history
    ADD COLUMN applied          TINYINT(1)   NOT NULL DEFAULT 0    AFTER diff_text,
    ADD COLUMN applied_at       DATETIME     NULL                   AFTER applied,
    ADD COLUMN apply_success    TINYINT(1)   NULL                   AFTER applied_at,
    ADD COLUMN apply_error      TEXT         NULL                   AFTER apply_success,
    ADD COLUMN backup_path      VARCHAR(512) NULL                   AFTER apply_error;
