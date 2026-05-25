# Quickstart: F2 — Role & Permission System

Manual test scenarios for verifying each user story in the development environment.

**Prerequisites**: Migrations 002, 003, and 004 applied. Dev server running. Existing admin account migrated to `users` table with `system_admin` role.

---

## Scenario 1: Login still works after migration (SC-006)

1. Navigate to `http://dmrdev.cflag.net/login.php`
2. Enter the existing admin credentials (same username and password as before)
3. **Expected**: Login succeeds, redirected to `/admin/`
4. **Expected**: Session contains `user_id` (not `admin_id`)

---

## Scenario 2: Suspended user cannot log in (US4, FR-011)

1. In the database: `UPDATE users SET moderation_state = 'suspended' WHERE username = 'testuser';`
2. Attempt to log in as `testuser`
3. **Expected**: Login is denied. Message shown: "Your account is not active."
4. **Expected**: No session created, no redirect to dashboard
5. Reset: `UPDATE users SET moderation_state = 'active' WHERE username = 'testuser';`

---

## Scenario 3: Role enforcement — user cannot reach admin page (US1/P1, FR-004)

1. Create a test user with only the `user` role:
   ```sql
   INSERT INTO users (username, email, password_hash, display_name)
   VALUES ('testuser', 'testuser@migrated.local', '<bcrypt hash>', 'Test User');
   INSERT INTO user_roles (user_id, role_id, assigned_by_user_id)
   SELECT u.id, r.id, u.id FROM users u, roles r
   WHERE u.username = 'testuser' AND r.name = 'user';
   ```
2. Log in as `testuser`
3. Navigate to `http://dmrdev.cflag.net/admin/`
4. **Expected**: Redirected to `/login.php` or shown a 403 page
5. Navigate to `http://dmrdev.cflag.net/admin/users/`
6. **Expected**: Same redirect/denial

---

## Scenario 4: System admin assigns moderator role (US2, FR-007)

1. Log in as the system admin
2. Navigate to `http://dmrdev.cflag.net/admin/users/`
3. Click on `testuser`
4. Select "Moderator" from the role assignment control and submit
5. **Expected**: Page reloads. `testuser`'s role list shows `user` and `moderator`.
6. **Expected**: Audit log entry visible: `role_assigned`, actor = admin, target = testuser, role = moderator
7. Log out. Log in as `testuser`.
8. Navigate to a moderator-only page
9. **Expected**: Access granted

---

## Scenario 5: Moderator applies timed network mute (US3, FR-013, FR-014)

1. Log in as the system admin (or a user with `moderator` role)
2. Navigate to the `testuser` account page
3. Apply "Mute on network — 1 hour" with reason "Test mute"
4. **Expected**: `testuser.moderation_state = 'muted_on_network'`
5. **Expected**: `testuser.mute_expires_at` is approximately 1 hour from now
6. **Expected**: `mod_log` has one entry: actor = admin, target = testuser, action = muted_on_network, duration_hours = 1
7. **Expected**: `config_change_queue` has one pending row (applied_at IS NULL), change_type = moderation_state_change
8. Log out. Log in as `testuser`.
9. **Expected**: Login succeeds (muted_on_network allows website access)
10. Navigate to any page the `user` role permits
11. **Expected**: Full access

---

## Scenario 6: Timed mute auto-expiry (FR-014, SC-004)

1. Set a short expiry manually:
   ```sql
   UPDATE users SET mute_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
   WHERE username = 'testuser' AND moderation_state = 'muted_on_network';
   ```
2. Run the cron script manually: `php /opt/cflag-dmr/scripts/expire-mutes.php`
3. **Expected**: `testuser.moderation_state = 'active'`, `mute_expires_at = NULL`
4. **Expected**: New `mod_log` entry: action = `auto_expired`
5. **Expected**: New `config_change_queue` row with `applied_at IS NULL`

---

## Scenario 7: Admin prevents self-demotion of last system_admin (edge case, FR-008)

1. Log in as system admin (the only system_admin account)
2. Navigate to own account page
3. Attempt to revoke the `system_admin` role from own account
4. **Expected**: Action is denied. Error message: "Cannot remove the last system admin role."
5. The `system_admin` role remains assigned. Audit log has no removal entry.

---

## Scenario 8: Admin cannot assign system_admin role (FR-007)

1. Create a user with `admin` role
2. Log in as that admin user
3. Navigate to another user's account page
4. Attempt to assign `system_admin` role
5. **Expected**: Action is denied. The `system_admin` option is either not shown or the form submission returns an error.
