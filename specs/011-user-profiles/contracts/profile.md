# Contract: User Profiles (F4)

## app/profile/manager.php

### get_profile(int $user_id): array|null

Returns full user row for the given user ID. Returns null if not found.

### update_display_name(int $user_id, string $display_name): array

Updates the user's display_name. Max 128 characters, non-empty.

**Returns**: `['ok' => bool, 'error' => string|null]`

### change_password(int $user_id, string $current_password, string $new_password, string $confirm_password): array

Verifies current password against stored hash. If valid and new/confirm match (min 8 chars), updates password_hash.

**Returns**: `['ok' => bool, 'error' => string|null]`

---

## app/profile/email_change.php

### request_email_change(int $user_id, string $new_email): array

Validates new_email (valid format, not already in use by another account), creates/replaces `email_change_requests` row with a new token (expires 24h), and sends verification email to new_email.

**Returns**: `['ok' => bool, 'error' => string|null]`

### confirm_email_change(string $token): array

Looks up token in `email_change_requests`, checks expiry, updates `users.email`, deletes the request row.

**Returns**: `['ok' => bool, 'error' => string|null]`

### get_pending_email_change(int $user_id): array|null

Returns the pending email_change_requests row for the user, or null if none.

---

## app/profile/callsign.php

### submit_callsign_request(int $user_id, string $new_callsign, string $explanation): array

Validates new callsign format (uppercase letters/numbers, typical amateur call pattern). Checks no other pending request exists for this user. Inserts `callsign_update_requests` row.

**Returns**: `['ok' => bool, 'error' => string|null]`

### get_user_callsign_requests(int $user_id): array

Returns all callsign_update_requests for a user (newest first).

### get_pending_callsign_requests(): array

Returns all callsign_update_requests with status='pending' (for admin review).

### approve_callsign_request(int $request_id, int $admin_id, string $notes = ''): array

Within a transaction: updates request status to 'approved', updates `users.callsign`, updates all `devices.callsign` for that user. Logs via `log_audit_action()`.

**Returns**: `['ok' => bool, 'error' => string|null]`

### deny_callsign_request(int $request_id, int $admin_id, string $notes): array

Updates request status to 'denied', sets review_notes and reviewed_by_user_id. Logs via `log_audit_action()`.

**Returns**: `['ok' => bool, 'error' => string|null]`

### get_open_callsign_request(int $user_id): array|null

Returns the currently pending callsign_update_requests row for a user, or null if none open.
