INSERT INTO system_settings (`key`, `value`) VALUES
    ('subscriber_last_import_at',           ''),
    ('subscriber_last_modified',            ''),
    ('subscriber_import_count',             '0'),
    ('subscriber_min_import_interval_hours','23')
ON DUPLICATE KEY UPDATE `key` = `key`;
