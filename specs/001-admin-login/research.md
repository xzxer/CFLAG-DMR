# Research: Admin Login — 001-admin-login

## Stack Decisions

### Password Hashing
- **Decision**: `password_hash($password, PASSWORD_BCRYPT)` / `password_verify()`
- **Rationale**: Built into PHP, widely audited, self-describing hash string (algorithm + cost + salt). No external dependency.
- **Alternatives considered**: Argon2id (stronger but requires libargon2; overkill for an admin panel with a small user count). MD5/SHA1 rejected — not suitable for password storage.

### Session Management
- **Decision**: PHP native sessions (`session_start()`) with `session_regenerate_id(true)` on login.
- **Rationale**: No external session store needed at this scale. Native sessions are well-understood and require no additional packages. Session fixation mitigated by ID regeneration.
- **Alternatives considered**: Redis-backed sessions — out of scope; adds infrastructure dependency for no benefit at current scale.

### Session Cookie Hardening
- **Decision**: `session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => $secureFlag])` called before `session_start()`. `secure` flag read from `SESSION_SECURE_COOKIE` env var.
- **Rationale**: `httponly` blocks JS cookie access. `samesite=Strict` prevents CSRF via cross-site requests. `secure` is env-controlled so dev HTTP and future production HTTPS both work without code changes.
- **Alternatives considered**: `samesite=Lax` — weaker; rejected in favour of Strict for an admin-only interface.

### CSRF Protection
- **Decision**: Synchronizer token pattern. Token generated with `bin2hex(random_bytes(32))`, stored in `$_SESSION['csrf_token']`, rendered in form as hidden field, verified on POST using `hash_equals()`.
- **Rationale**: Standard, stateful CSRF defence. `hash_equals()` prevents timing attacks. No third-party library required.
- **Alternatives considered**: Double-submit cookie — stateless but harder to validate server-side without a signing key; rejected in favour of simpler session-bound token.

### Database Access
- **Decision**: PDO with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`, prepared statements throughout.
- **Rationale**: `EMULATE_PREPARES => false` forces real prepared statements at the MariaDB protocol level, preventing any chance of interpolated SQL reaching the driver.
- **Alternatives considered**: MySQLi — PDO is more portable and already the project default; no reason to mix drivers.

### Error Messaging
- **Decision**: Single generic message: `"Invalid username or password."` on any login failure.
- **Rationale**: Prevents username enumeration. Attacker cannot distinguish between "username not found" and "password wrong".
- **Alternatives considered**: Specific messages — rejected as they leak account existence.

### Rate Limiting
- **Decision**: Out of scope for this slice.
- **Follow-up**: Token-bucket or leaky-bucket per-IP rate limiting using a `login_attempts` table or Redis. Document in follow-up items.

### PHP Version
- **Decision**: PHP 8.x (project standard). `declare(strict_types=1)` in all source files (established by `public/index.php`).
- **Alternatives considered**: N/A — stack is fixed.

### Env Loading
- **Decision**: Custom `app/config/env.php` reads `.env` line by line, strips quotes, ignores blanks and `#` comments. Exposes `env(string $key, mixed $default = null)` helper.
- **Rationale**: No Composer dependency (e.g., `vlucas/phpdotenv`) needed for a small, fixed-variable env file. Rolling our own keeps the dependency count at zero.
- **Alternatives considered**: `vlucas/phpdotenv` — rejected because it introduces Composer; overkill for a fixed set of ~10 variables.
