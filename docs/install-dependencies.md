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

### Mail server (Postfix + OpenDKIM)

```bash
apt-get install -y postfix opendkim opendkim-tools
```

**Postfix** (`/etc/postfix/main.cf`) — key settings:

```ini
myhostname = mail.cflag.net
mydomain   = cflag.net
myorigin   = /etc/mailname          # contains "mail.cflag.net"
inet_interfaces = loopback-only     # only accept mail from PHP on localhost
inet_protocols  = ipv4
mydestination   =                   # we only send, never receive
local_transport = error:local delivery disabled
relayhost =                         # direct delivery
smtp_tls_security_level = may
mynetworks = 127.0.0.0/8
smtpd_relay_restrictions = permit_mynetworks, reject
# milter lines added after OpenDKIM is running:
milter_default_action = accept
smtpd_milters     = unix:/var/spool/postfix/opendkim/opendkim.sock
non_smtpd_milters = unix:/var/spool/postfix/opendkim/opendkim.sock
```

**OpenDKIM** — key setup steps:

```bash
# Generate 2048-bit key, selector "mail"
mkdir -p /etc/opendkim/keys/cflag.net
opendkim-genkey -b 2048 -d cflag.net -D /etc/opendkim/keys/cflag.net -s mail
chown -R opendkim:opendkim /etc/opendkim/keys
chmod 700 /etc/opendkim/keys/cflag.net
chmod 600 /etc/opendkim/keys/cflag.net/mail.private

# Socket directory (inside Postfix chroot)
mkdir -p /var/spool/postfix/opendkim
chown opendkim:postfix /var/spool/postfix/opendkim
chmod 750 /var/spool/postfix/opendkim
usermod -aG opendkim postfix
```

`/etc/opendkim/KeyTable`:
```
mail._domainkey.cflag.net  cflag.net:mail:/etc/opendkim/keys/cflag.net/mail.private
```

`/etc/opendkim/SigningTable`:
```
*@cflag.net  mail._domainkey.cflag.net
```

**Required DNS records** (add at your registrar):

| Type | Name | Value |
|------|------|-------|
| A    | `mail` | `<server-ip>` |
| MX   | `@`    | `mail.cflag.net` (priority 10) |
| TXT  | `@`    | `v=spf1 ip4:<server-ip> mx ~all` |
| TXT  | `mail._domainkey` | *(value from `/etc/opendkim/keys/cflag.net/mail.txt`)* |
| TXT  | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:admin@cflag.net` |

**PTR / reverse DNS**: set in your VPS control panel — IP → `mail.cflag.net`.
Without this, most mail servers will reject outbound mail.

**VPS port 25**: most providers block outbound port 25 by default.
Open a support ticket to have it removed before testing.

**`.env` settings for local Postfix**:

```ini
EMAIL_DEV_MODE=false
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@cflag.net
MAIL_FROM_NAME=CFLAG DMR
```

### Environment file

Copy `.env.example` to `.env` and fill in all values before starting:

```bash
cp .env.example .env
```

Required keys: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_SECRET`,
`SESSION_SECURE_COOKIE`, `APP_URL`, `EMAIL_DEV_MODE`.

For production email, also set:
`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, and set `EMAIL_DEV_MODE=false`.

**Port guide**: 465 = implicit TLS (SMTPS), 587 = STARTTLS (recommended),
25 = plain / opportunistic TLS. Most hosted SMTP providers use 587.

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
- [ ] `postfix` + `opendkim` + `opendkim-tools` installed
- [ ] Postfix configured for loopback-only with DKIM milter
- [ ] DKIM key pair generated in `/etc/opendkim/keys/cflag.net/`
- [ ] PTR record set in VPS control panel (IP → `mail.cflag.net`)
- [ ] DNS records added: A, MX, SPF TXT, DKIM TXT, DMARC TXT
- [ ] VPS provider port 25 restriction removed
- [ ] `postfix` and `opendkim` services enabled and running
