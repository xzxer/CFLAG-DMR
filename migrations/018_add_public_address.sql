-- Migration 018: add public_address to master_server_settings
-- Distinct from bind_address (local interface); this is what peers point at.
ALTER TABLE master_server_settings
    ADD COLUMN public_address VARCHAR(253) NOT NULL DEFAULT '' AFTER bind_address;
