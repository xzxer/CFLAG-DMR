# Quickstart: Admin Login — 001-admin-login

Bootstrap guide for bringing up the admin login on the dev server after implementation.

## Prerequisites

- Apache serving `public/` as document root
- MariaDB running, database `cflag_dmr_dev` exists, user `cflag_dmr_user` has full access
- PHP 8.x with PDO and pdo_mysql extensions enabled
- `.env` file at project root with all required variables (see `.env.example`)

## 1. Add SESSION_SECURE_COOKIE to .env

```
SESSION_SECURE_COOKIE=false
```

Also add the same key to `.env.example`:

```
SESSION_SECURE_COOKIE=false
```

## 2. Apply the migration

Log in to MariaDB as a user with CREATE TABLE privileges and run:

```bash
mysql -u cflag_dmr_user -p cflag_dmr_dev < migrations/001_create_admin_users_table.sql
```

Verify the table was created:

```sql
DESCRIBE admin_users;
```

## 3. Create the initial admin user

Generate a bcrypt hash in PHP:

```bash
php -r "echo password_hash('your-chosen-password', PASSWORD_BCRYPT) . PHP_EOL;"
```

Insert the admin user (replace values as needed):

```sql
INSERT INTO admin_users (username, password_hash, display_name, is_active)
VALUES ('admin', '$2y$...paste-hash-here...', 'Site Admin', 1);
```

## 4. Verify PHP syntax

```bash
php -l app/config/env.php
php -l app/database/connection.php
php -l app/auth/session.php
php -l app/auth/login.php
php -l public/login.php
php -l public/logout.php
php -l public/admin/index.php
```

All files should report `No syntax errors detected`.

## 5. Test login flow

1. Visit `http://<dev-server>/admin/` — expect redirect to `/login.php`
2. Submit bad credentials — expect generic error message
3. Submit valid credentials — expect redirect to `/admin/`
4. Confirm admin display name shown in dashboard
5. Click logout — expect redirect to `/login.php`
6. Visit `/admin/` again — expect redirect to `/login.php`

## 6. Check Apache error log

```bash
sudo tail -50 /var/log/apache2/error.log
```

No PHP fatal errors should appear.
