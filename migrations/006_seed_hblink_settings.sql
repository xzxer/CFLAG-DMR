-- F8: HBLink Config Visibility
-- Seeds system_settings keys for HBLink file paths and process name.
-- Paths default to /etc/hblink3/ (Docker deployment). Update via system_settings
-- table if your installation uses a different path.

INSERT INTO `system_settings` (`key`, `value`, `description`) VALUES
    ('hblink_cfg_path',
     '/etc/hblink3/hblink.cfg',
     'Absolute path to hblink.cfg. Must be under an allowed base directory.'),

    ('hblink_rules_path',
     '/etc/hblink3/rules.py',
     'Absolute path to rules.py. Must be under an allowed base directory.'),

    ('hblink_pid_path',
     '/etc/hblink3/hblink.pid',
     'Absolute path to HBLink PID file. Falls back to pgrep if file does not exist.'),

    ('hblink_process_name',
     'bridge.py',
     'Process name used with pgrep -f for fallback PID lookup. Set to hblink.py for standard installs, bridge.py for Docker-based deployments.');
