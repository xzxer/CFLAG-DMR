# Research: F3 — User Registration & Accounts

## Decision 1: HTTP Client for RadioID.net API

**Decision**: Use PHP's built-in cURL extension with a 5-second timeout.

**Rationale**: `file_get_contents()` with HTTP wrappers cannot reliably set connection timeouts, making it unsuitable for third-party API calls where the remote may be slow or unreachable. cURL is bundled with PHP and requires no Composer dependency. A 5-second timeout prevents registration from hanging; any cURL error (timeout, DNS failure, non-200 response) triggers fallback to format-only validation.

**Alternatives considered**: Guzzle — rejected (Composer dependency, Principle I violation without documented need). `file_get_contents` with `stream_context_create` — rejected (timeout behaviour unreliable across PHP versions).

---

## Decision 2: Verification Token Generation

**Decision**: `bin2hex(random_bytes(32))` — produces a 64-character lowercase hex string using the CSPRNG.

**Rationale**: `random_bytes()` is the PHP-recommended CSPRNG function (available since PHP 7.0). 32 bytes = 256 bits of entropy, far exceeding practical brute-force feasibility. No external dependency required.

**Alternatives considered**: `uniqid()` — rejected (not cryptographically secure). UUID v4 — rejected (only 122 bits of entropy and requires a library or complex manual generation for no practical benefit).

---

## Decision 3: Rate Limiting Without Redis

**Decision**: Track rate limits in the `email_verifications` table via `created_at` of the most recent token per email.

**Rationale**: No caching infrastructure (Redis, Memcached) is available on this server. The `email_verifications` table already records every token with `created_at`. A simple query `SELECT MAX(created_at) FROM email_verifications JOIN users ON ... WHERE email = ?` gives the last-issue timestamp for any given address. If `MAX(created_at) > NOW() - INTERVAL 5 MINUTE`, reject the resend request. This adds no tables and no dependencies.

**Alternatives considered**: Redis-based rate limiting — rejected (no Redis available; adds infrastructure dependency). Separate `rate_limits` table — rejected (unnecessary; the existing table already holds the needed data).

---

## Decision 4: Email Sending Abstraction

**Decision**: Single function `send_email(string $to, string $subject, string $body, string $from): void` in `app/email/mailer.php`. Behaviour controlled by `EMAIL_DEV_MODE` env variable: `true` → write to `/var/log/cflag-dmr-email-dev.log`; otherwise → PHP `mail()`.

**Rationale**: Wrapping the actual send in one function keeps all future SMTP changes in a single file. The dev-mode flag in `.env` means no code change is needed when switching environments. `mail()` is the correct placeholder — it's always available in PHP and will work on a properly configured mail server when production deployment happens.

**Alternatives considered**: PHPMailer — rejected (Composer dependency; mail() is sufficient as a placeholder per Principle I). Hard-coding log-only mode — rejected (makes environment switching require a code change).

**New env variable**: `EMAIL_DEV_MODE=true` added to `.env` and `.env.example`.

---

## Decision 5: Callsign Column Nullability

**Decision**: `callsign` column is `VARCHAR(10) NULL` with a UNIQUE KEY. The application layer enforces `NOT NULL` at registration; existing migrated accounts receive `callsign = NULL`.

**Rationale**: MariaDB's UNIQUE KEY on a nullable column allows multiple NULL values (NULLs are not considered equal). This lets us add the column without backfilling a valid callsign for the existing admin account (which has no real callsign on record). New registrations will always supply a callsign via the validated form. The collation `utf8mb4_unicode_ci` means the unique constraint is case-insensitive automatically.

**Alternatives considered**: `NOT NULL` with a placeholder value for the admin — rejected (pollutes the callsign namespace and is semantically wrong). Separate `callsigns` table — rejected (over-engineering; one callsign per user is the invariant).

---

## Decision 6: `attempt_login()` Return Type Change

**Decision**: Change `attempt_login()` return type from `bool` to `string`, returning one of three constants: `LOGIN_OK`, `LOGIN_INVALID`, `LOGIN_UNVERIFIED`. Define constants at the top of `app/auth/login.php`. Update `public/login.php` to handle all three codes.

**Rationale**: The unverified-account path requires a distinct response that cannot be encoded as `true`/`false`. A string return with named constants is the simplest way to add this without a new function or a by-reference parameter. `LOGIN_INVALID` replaces `false`; `LOGIN_OK` replaces `true`; `LOGIN_UNVERIFIED` is new. The change is contained to two files.

**Alternatives considered**: Return `null` for unverified — rejected (ambiguous; hard to distinguish from invalid). Separate `check_login_state()` function — rejected (adds a second DB query for every login attempt).

---

## Decision 7: `system_settings` Table

**Decision**: Create a key-value `system_settings` table: `(key VARCHAR(64) PRIMARY KEY, value TEXT NOT NULL, description TEXT, updated_at DATETIME)`. Seed with `radioid_validation_enabled = '1'`.

**Rationale**: The project overview spec (F3 and later features) references `system_settings` as the mechanism for admin-toggleable flags. This feature is the first one that actually needs it. A simple key-value table is sufficient for the current use cases and can be extended with additional rows for future settings without schema changes (Principle I).

**Alternatives considered**: Hard-coded config constant — rejected (not admin-toggleable). JSON config file — rejected (violates Constitution Principle IV; config changes must be database-driven and auditable).

---

## Decision 8: `email_verified_at` Backfill

**Decision**: Migration 005 sets `email_verified_at = created_at` for all existing rows in `users` where `email_verified_at IS NULL`. This marks migrated admin accounts as verified without requiring them to go through the email flow.

**Rationale**: Existing admin accounts were created before this feature existed. Setting `email_verified_at = created_at` is the semantically correct value ("they were 'verified' when created"). The alternative — `email_verified_at = NOW()` — is also acceptable but less accurate.

**Alternatives considered**: Leave `email_verified_at = NULL` for admin — rejected (would lock out the admin on next login). Separate `is_legacy_account` flag — rejected (unnecessary complexity for a one-time migration concern).
