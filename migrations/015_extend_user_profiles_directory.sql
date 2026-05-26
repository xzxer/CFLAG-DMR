-- F11: Extended User Profiles + F12: Public User Directory
-- Adds optional operator detail fields and directory/visibility preferences to users table.
ALTER TABLE users
    ADD COLUMN first_name        VARCHAR(64)     NULL        AFTER display_name,
    ADD COLUMN last_name         VARCHAR(64)     NULL        AFTER first_name,
    ADD COLUMN grid_square       VARCHAR(8)      NULL        AFTER last_name,
    ADD COLUMN bio               TEXT            NULL        AFTER grid_square,
    ADD COLUMN phone             VARCHAR(32)     NULL        AFTER bio,
    ADD COLUMN show_name_publicly TINYINT(1)     NOT NULL    DEFAULT 0 AFTER phone,
    ADD COLUMN show_in_directory  TINYINT(1)     NOT NULL    DEFAULT 1 AFTER show_name_publicly;
