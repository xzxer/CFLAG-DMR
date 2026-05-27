# Research: F15 — Moderation Tools

## Decision 1: Suspension expiry enforcement
**Decision**: Check expiry on login only (not a background cron job). If `moderation_state='suspended'` and `expires_at IS NOT NULL` and `expires_at < NOW()`, auto-reinstate the user at login time.  
**Rationale**: Simplest correct approach. Cron adds operational complexity for little benefit — the common case is the user trying to log in. Auto-reinstate at login is predictable.  
**Alternatives considered**: Cron job to flip state at expiry — adds infrastructure dependency, fails silently if cron stops. Database event — not supported on all MariaDB configurations.

## Decision 2: `muted_on_network` vs `suspended` distinction
**Decision**: `muted_on_network` means the user can still log into the web portal but their devices are excluded from the next config generation (dropped from REG_ACL whitelist). `suspended` means no web login. These are separate states.  
**Rationale**: Operationally useful distinction — a muted user can see the portal and their account but cannot transmit on the DMR network. Config generation already checks `moderation_state='active'` for whitelist inclusion (`get_whitelist_eligible_dmr_ids()`).  
**Alternatives considered**: Single 'suspended' state covering both login and network access — loses the ability to mute without locking out the portal. Two separate boolean columns — less clean than the enum.

## Decision 3: Moderator read-only access
**Decision**: Moderators see the moderation log but all write actions (suspend, ban, reinstate) require the `system_admin` or `admin` role.  
**Rationale**: Matches the access tier spec in the project overview. Moderator role is for traffic moderation (mute requests, flag reports), not account lifecycle management.  
**Alternatives considered**: Allow moderators to suspend — creates accountability issues without a review layer.

## Decision 4: Banned email enforcement at registration
**Decision**: At registration, check if the submitted email matches any `users.email` where `moderation_state='banned'`. If so, reject with a generic "registration is unavailable for this email" message.  
**Rationale**: Don't leak whether the account exists — use a vague error. Simple query, no extra table.  
**Alternatives considered**: Separate `banned_emails` table — overkill; banning by DMR ID or IP is deferred.

## Decision 5: Existing login flow vs adding checks
**Decision**: Review `app/auth/session.php` and `app/auth/login.php` to confirm whether `moderation_state` is already checked on login. If not, add the check there.  
**Rationale**: The check belongs at the session creation layer, not scattered across protected pages. A single check in the login flow is the only reliable enforcement point.

## No new tables required
`mod_log` and `audit_log` already exist. `moderation_state` is already on `users`. The schema is complete for this feature's MVP scope.
