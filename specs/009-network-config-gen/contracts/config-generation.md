# Contract: Config Generation (F7)

## app/config/generator.php

### generate_hblink_config(int $actor_id): array

Generates and atomically writes the HBLink config file from the current database state.

**Returns**: `['ok' => bool, 'changed' => bool, 'error' => string|null]`

**Preconditions**:
- `master_server_settings` row exists (id=1) with all required fields populated
- `HBLINK_CONFIG_PATH` is set in `.env` and passes `realpath()` allowlist validation
- No other generation is currently in progress (lock check)

**Behavior**:
1. Acquires generation lock (fail fast if locked)
2. Reads master settings, openbridge connections (enabled only), whitelist DMR IDs, device subscriptions
3. Renders config text as a string
4. Writes to `$config_path . '.tmp'`, renames to `$config_path` (atomic)
5. Computes diff against last generation history record
6. Inserts `config_generation_history` row with full config text, `changed` flag, diff text
7. Clears `config_change_queue` rows older than generation timestamp
8. Releases generation lock

**Error cases**: Returns `['ok' => false, 'error' => '...']` for: lock conflict, missing master settings, unwritable path, filesystem error.

---

## app/config/settings.php

### get_master_settings(): array|null

Returns the current master_server_settings row as an associative array, or null if not initialized.

### save_master_settings(int $actor_id, array $data): array

Validates and upserts the master_server_settings row.

**Returns**: `['ok' => bool, 'error' => string|null]`

**Validates**: bind_address (IPv4/IPv6 or 0.0.0.0), port (1–65535), passphrase (1–15 chars), report_address, report_port, ping_time (>0), max_missed (>0)

### validate_passphrase(string $passphrase): bool

Returns true if passphrase is 1–15 characters (non-empty, max 15).

---

## app/config/openbridge.php

### get_openbridge_connections(bool $enabled_only = false): array

Returns all OpenBridge connection rows. If `$enabled_only`, filters to enabled=1.

### get_openbridge_connection(int $id): array|null

Returns a single OpenBridge connection row.

### save_openbridge_connection(int $actor_id, array $data): array

Creates or updates an OpenBridge connection. `$data['id']` present → update, absent → create.

**Returns**: `['ok' => bool, 'id' => int|null, 'error' => string|null]`

**Validates**: name (unique, 1–64 chars), remote_address, port, passphrase (1–15 chars), network_id (uint)

### toggle_openbridge_connection(int $id, bool $enabled): bool

Sets the enabled flag on an OpenBridge connection row.

### delete_openbridge_connection(int $id): bool

Deletes an OpenBridge connection row. Returns false if not found.

---

## app/config/history.php (or part of generator.php)

### get_generation_history(int $limit = 50): array

Returns the most recent N generation history records (newest first), excluding `config_text` and `diff_text` for the list view (large fields).

### get_generation_detail(int $history_id): array|null

Returns a single history record including `config_text` and `diff_text`.

---

## Upstream Contracts (F5/F6, read-only)

### get_whitelist_eligible_dmr_ids(): array (app/devices/manager.php)

Returns array of DMR ID integers for all approved, active devices.

### get_device_subscriptions_for_config(): array (app/talkgroups/manager.php)

Returns array of subscription records:
```php
[
  ['dmr_id' => 3171234, 'tgid' => 31672, 'timeslot' => 1, 'callsign' => 'W7ABC'],
  ...
]
```
