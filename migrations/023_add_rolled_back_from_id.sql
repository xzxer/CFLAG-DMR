ALTER TABLE config_generation_history
    ADD COLUMN rolled_back_from_id INT UNSIGNED NULL DEFAULT NULL AFTER backup_path,
    ADD CONSTRAINT fk_cgh_rollback_from
        FOREIGN KEY (rolled_back_from_id) REFERENCES config_generation_history (id)
        ON DELETE SET NULL;
