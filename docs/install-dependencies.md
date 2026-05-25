# CFLAG DMR — Installation Dependencies

This file tracks every system-level dependency required to run CFLAG DMR.
It is the authoritative source for the eventual installer script.

---

## Operating System

Ubuntu 24.04 LTS (or Debian 12+). All package names below are `apt` packages.

---

## Packages

### Web Server

```bash
apt-get install -y apache2
```

Required Apache modules (enable with `a2enmod`):

```bash
a2enmod rewrite      # AllowOverride All / .htaccess support
```

### PHP

```bash
apt-get install -y php8.3 libapache2-mod-php8.3
```

Required PHP extensions:

```bash
apt-get install -y \
  php8.3-curl    \   # RadioID.net API lookup (curl_init)
  php8.3-mysql   \   # PDO + pdo_mysql + mysqlnd (database access)
  php8.3-ctype   \   # ctype_digit (DMR ID validation)
  php8.3-json        # json_encode/decode (audit log, API responses)
```

> `filter`, `openssl`, and `password_hash` (bcrypt) are built into PHP 8.3
> and do not require a separate extension package.

### Database

```bash
apt-get install -y mariadb-server   # 10.11 LTS or newer
```

Character set requirement: `utf8mb4` with `utf8mb4_unicode_ci` collation.
All migrations assume InnoDB engine.

---

## Post-Install Configuration

### Apache virtual host

- `DocumentRoot` must point to `<repo>/public/`
- `AllowOverride All` on the document root directory
- The vhost file lives at `/etc/apache2/sites-available/cflag-dmr.conf`

### Log file

Create the dev-mode email log and set ownership so Apache can write to it:

```bash
touch /var/log/cflag-dmr-email-dev.log
chown root:www-data /var/log/cflag-dmr-email-dev.log
chmod 664 /var/log/cflag-dmr-email-dev.log
```

### PHP configuration (`/etc/php/8.3/apache2/php.ini`)

```ini
display_errors = Off        # must be Off in production
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

### MariaDB

Create database, user, and grant privileges:

```sql
CREATE DATABASE cflag_dmr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cflag_dmr_user'@'localhost' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON cflag_dmr.* TO 'cflag_dmr_user'@'localhost';
FLUSH PRIVILEGES;
```

Run all migrations in order:

```bash
for f in migrations/*.sql; do
    mysql -u cflag_dmr_user -p'<password>' cflag_dmr < "$f"
done
```

### Environment file

Copy `.env.example` to `.env` and fill in all values before starting:

```bash
cp .env.example .env
```

Required keys: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_SECRET`,
`SESSION_SECURE_COOKIE`, `EMAIL_DEV_MODE`.

---

## Summary Checklist

- [ ] `apache2` installed and running
- [ ] `a2enmod rewrite` enabled
- [ ] `php8.3` + `libapache2-mod-php8.3` installed
- [ ] `php8.3-curl` installed
- [ ] `php8.3-mysql` installed
- [ ] `php8.3-ctype` installed
- [ ] `php8.3-json` installed
- [ ] `mariadb-server` installed and running
- [ ] Database + user created with utf8mb4 collation
- [ ] All migrations applied in order
- [ ] `/var/log/cflag-dmr-email-dev.log` created, owned by `root:www-data`, mode `664`
- [ ] Apache vhost configured with correct `DocumentRoot` and `AllowOverride All`
- [ ] `.env` populated from `.env.example`
- [ ] Apache restarted after PHP extension install
