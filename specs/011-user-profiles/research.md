# Research: User Profiles (F4)

## Decision: Email Change Token Storage

**Decision**: New `email_change_requests` table (one active request per user). Token stored as a random hex string (`bin2hex(random_bytes(32))`), expires 24 hours from creation. On confirmation, user's email is updated, request is deleted. On a second request, the existing pending row is replaced.

**Rationale**: Mirrors the existing `email_verification_tokens` pattern from F3. Consistent and auditable. A separate table prevents contamination of the registration verification token table.

**Alternatives considered**: Extend `email_verification_tokens` with a change_type column (rejected — different lifecycle, cleaner as separate table), signed JWT (rejected — Principle I, no new dependency justified)

## Decision: Callsign Cascade on Approval

**Decision**: When a callsign update is approved, wrap the update in a DB transaction: `UPDATE users SET callsign=? WHERE id=?` + `UPDATE devices SET callsign=? WHERE user_id=?`. Log via `log_audit_action()`.

**Rationale**: Device callsigns must stay in sync with user callsigns. A transaction ensures either both update or neither does.

**Alternatives considered**: Async job (rejected — overkill for this volume), devices derive callsign from users at render time (rejected — devices table has its own callsign field used independently by HBLink config generation)

## Decision: Password Change UX

**Decision**: Single form with three fields: current password, new password, confirm new password. All in one POST. No "forgot current password" flow here (that is a separate feature scope).

**Rationale**: Standard pattern. Current password requirement prevents session hijacking from updating password. No additional complexity needed.

## Decision: Username Immutability

**Decision**: Username is NOT changeable. It is the login identifier and appears in URLs and logs. Only display_name and callsign are updatable by users.

**Rationale**: Changing usernames is a complex operation (references throughout audit logs, URLs, device records). Out of scope and explicitly documented in spec assumptions.

## Decision: Admin Profile URL

**Decision**: `/admin/users/profile.php?id=N` — extends the existing `/admin/users/` area. User list at `/admin/users/` links to each user's profile page.

**Rationale**: Keeps admin user management in one place. Consistent with the existing admin area structure.
