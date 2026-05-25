-- Migration 003: Seed the four immutable system roles
-- Part of F2: Role & Permission System
-- Applied after 002_create_users_roles_tables.sql

INSERT INTO roles (name, display_name, description, is_system_role, sort_order) VALUES
    ('user',         'User',           'Registered network user. Can manage own profile, hotspots, and talkgroup subscriptions.',                                                     1, 1),
    ('moderator',    'Moderator',      'Can apply temporary network mutes, submit suspension/ban requests, and view the moderation log.',                                             1, 2),
    ('admin',        'Administrator',  'Server administrator. Full config access, talkgroup and peer management, can apply all moderation states.',                                   1, 3),
    ('system_admin', 'System Admin',   'Global administrator. Full access to all nodes, system settings, user management, role assignment, and reversal of any moderation action.', 1, 4);
