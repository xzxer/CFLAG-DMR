-- Migration 004: Migrate admin_users records into the users table
-- Part of F2: Role & Permission System
-- Applied after 003_seed_system_roles.sql
--
-- NOTE: Migrated accounts receive a placeholder email ({username}@migrated.local)
-- because admin_users has no email column. System admins should update their
-- email address via profile settings after this migration is applied.

INSERT INTO users (username, email, password_hash, display_name, moderation_state, created_at, updated_at)
SELECT
    au.username,
    CONCAT(au.username, '@migrated.local'),
    au.password_hash,
    au.display_name,
    'active',
    COALESCE(au.created_at, NOW()),
    NOW()
FROM admin_users au
WHERE NOT EXISTS (
    SELECT 1 FROM users u WHERE u.username = au.username
);

-- Assign system_admin role to all migrated users (self-assigned during migration)
INSERT INTO user_roles (user_id, role_id, assigned_by_user_id, assigned_at)
SELECT u.id, r.id, u.id, NOW()
FROM users u
JOIN roles r ON r.name = 'system_admin'
JOIN admin_users au ON au.username = u.username
WHERE NOT EXISTS (
    SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = r.id
);
