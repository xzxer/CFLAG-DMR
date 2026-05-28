# Research: Network Status Dashboard (F10)

## Decision: Connected Peer List Data Source

**Decision**: Derive "recently active" peers from the lastheard log (DMR IDs seen in the last 30 minutes), joined against the local devices table for callsign lookup. Label the section "Recently Active Devices" rather than "Connected Peers" to accurately represent what the data shows.

**Rationale**: HBMonv2 maintains a live peer table (CTABLE) in Python memory, broadcast via WebSocket — there is no static JSON file that PHP can read. HBLink's TCP reporting port (4321) is another option but requires a PHP socket client and is fragile if HBLink is stopped. The lastheard log is already consumed by `load_lastheard()` and provides the most reliable signal of recent activity. A 30-minute window is a reasonable proxy for "likely still connected" given typical DMR hotspot behavior.

**Alternatives considered**:
- HBLink reporting socket on port 4321 (rejected — adds fragile socket client code; if HBLink is stopped, connection fails; adds new dependency)
- Parse HBMonv2 peers_table.html template output (rejected — scraping internal monitor state; brittle)
- Separate "active peers" DB table written by a background process (rejected — out of scope for F10; adds infrastructure complexity)

**Implementation**: Add `get_recently_active_dmr_ids(int $minutes = 30): array` to `app/lastheard/reader.php`. Parses the lastheard log, extracts unique src_id values seen within the window, joins against `devices` table (where `status='approved'`) for callsign. Returns `[['dmr_id' => int, 'callsign' => string, 'last_seen' => string], ...]`.

## Decision: Page Location

**Decision**: `/network-status.php` in `public/` root. Accessible to all authenticated users.

**Rationale**: Parallel to `/last-heard.php`. A user-facing page with admin-visible extras. Not under `/admin/` because it serves all users.

**Alternatives considered**: `/status.php` (too generic, conflicts with possible health-check endpoints), `/user/network-status.php` (wrong — admin users also use it)

## Decision: Config Drift Signal Source

**Decision**: Use `get_hblink_status()['config_drifted']` which compares `cfg_mtime > started_at`. This is already implemented. No additional query needed.

**Rationale**: The existing implementation already detects whether the config file was modified after HBLink started. For the admin panel section, this is shown as a warning banner. When F7 is deployed and generating configs via DB, the file mtime will update on each generation, providing accurate drift detection.

## Decision: Admin-Only Data

**Decision**: IP addresses, raw connection metadata, and the "Server Control" section (HBLink process details, direct links to config/status admin pages) are conditionally rendered based on `user_has_role($user_id, 'system_admin')`.

**Rationale**: Matches FR-003 and FR-010. Regular users see server state (up/down) and recent activity. Only system_admin users see IP-level detail and control panel links.
