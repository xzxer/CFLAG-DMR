# Data Model: F15 — Moderation Tools

## Schema Changes

No new tables. All required tables already exist:
- `users.moderation_state` ENUM('active','suspended','banned','muted_on_network')
- `mod_log` (actor_user_id, target_user_id, action, reason, duration_hours, expires_at, created_at)
- `audit_log` (actor_user_id, action_type, target_type, target_id, ...)

**No migration required.** (Confirm `moderation_state` ENUM includes all needed values.)

## Existing Entities Used

### User (existing)
`moderation_state` transitions driven by this feature:
```
active ──suspend──▶ suspended ──reinstate──▶ active
active ──ban──────▶ banned    ──reinstate──▶ active  
active ──mute─────▶ muted_on_network ──unmute──▶ active
suspended ──ban──▶ banned
```

### ModLog (existing `mod_log`)
| Field | Notes |
|-------|-------|
| actor_user_id | Admin who took the action |
| target_user_id | User being moderated |
| action | 'suspended', 'banned', 'reinstated', 'muted', 'unmuted' |
| reason | Required, non-empty |
| duration_hours | NULL = indefinite |
| expires_at | Computed from duration_hours on write |
| created_at | Auto |

## PHP Layer

### `app/moderation/manager.php` (new file)

```php
function suspend_user(int $target_id, int $actor_id, string $reason, ?int $duration_hours): array
function ban_user(int $target_id, int $actor_id, string $reason): array
function reinstate_user(int $target_id, int $actor_id, string $reason): array
function mute_user(int $target_id, int $actor_id, string $reason): array
function unmute_user(int $target_id, int $actor_id, string $reason): array
function get_mod_log(int $limit = 50, int $offset = 0): array
function get_mod_log_for_user(int $user_id): array
function check_and_auto_reinstate(int $user_id): void   // called on login
```

### Login flow changes (`app/auth/login.php` / `session.php`)
- After verifying credentials, call `check_and_auto_reinstate($user_id)` (handles expired suspensions)
- Then re-read `moderation_state` and reject login with appropriate message if suspended/banned

### Config generation integration
`get_whitelist_eligible_dmr_ids()` already filters `moderation_state = 'active'` — muted users are automatically excluded from the whitelist without any changes needed.

## Admin UI Pages

### `public/admin/moderation/index.php` (new)
- Paginated `mod_log` table: actor, target, action badge, reason, timestamp
- Filter by action type
- Read-only for moderators, no action buttons

### `public/admin/users/view.php` (extend existing)
- Add "Moderation" section to user detail page
- Shows current state with colored badge
- Action buttons: Suspend / Ban / Mute / Reinstate (admin only, hidden for moderators)
- Shows per-user `mod_log` history below
