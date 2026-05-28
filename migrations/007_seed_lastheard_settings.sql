-- F9: Last-Heard & Activity Log
-- Seeds system_settings keys for HBMonv2 log path and public visibility toggle.
-- Default: public access enabled, path points to standard HBMonv2 log location.

INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
    ('lastheard_log_path',
     '/opt/HBMonv2/log/lastheard.log',
     'Absolute path to HBMonv2 lastheard.log. Must be under an allowed base directory.'),

    ('public_lastheard_enabled',
     '1',
     'When 1, last-heard page is accessible without login. When 0, login is required.');
