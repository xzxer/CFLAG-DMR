# Research: F14 — Subscriber ID Import

## Decision 1: Correct RadioID.net bulk download URL
**Decision**: Use `https://radioid.net/static/user.csv` (note: singular "user", not "users"). The commonly referenced `users.csv` URL returns 404.  
**Rationale**: Direct HEAD request confirmed: HTTP 200, `Content-Type: application/octet-stream`, `Content-Length: ~15.9 MB`, `Last-Modified: [daily ~05:00 UTC]`. The file is real and actively maintained.  
**Alternatives considered**: `https://radioid.net/static/users.json` (60.5 MB JSON) — same data but 4× larger; CSV is sufficient for our field set. RadioID.net paginated JSON API (`/api/dmr/user/`) — ~1,533 requests for 306k records; suitable for per-ID lookups, too chatty for bulk import. `/database/dumps` page — JS-gated, cannot be reliably fetched without a browser.

## Decision 2: Conditional GET with If-Modified-Since
**Decision**: On every import attempt, send an `If-Modified-Since` header with the `Last-Modified` value stored from the previous successful download. If the server returns 304 Not Modified, skip the import. If it returns 200, import and store the new `Last-Modified` value.  
**Rationale**: The RadioID.net CSV endpoint returns a `Last-Modified` header (confirmed: daily updates at ~05:00 UTC). Conditional GET is the standard, server-friendly mechanism for polling a resource without downloading it unnecessarily. It avoids a 15.9 MB download on every cron run when data hasn't changed.  
**Alternatives considered**: ETag-based conditional GET — the endpoint does not return an ETag header. Hash comparison (download → hash → compare to stored hash) — downloads the full file even when unchanged, wasting bandwidth. Time-based only (check if last_import_at > 24h) — doesn't adapt to days when RadioID.net publishes multiple updates.  
**Fallback**: If the server stops sending `Last-Modified` (unlikely but possible), fall back to pure time-based interval checking.

## Decision 3: Minimum re-import interval (rate limiting)
**Decision**: Enforce a minimum interval between imports in code (default: 23 hours), stored as `subscriber_min_import_interval_hours` in `system_settings`. The import function checks `subscriber_last_import_at` + the interval before proceeding; if within the window, it returns early with a "too soon" status.  
**Rationale**: RadioID.net's API policy prohibits excessive requests and bulk mirroring. A minimum interval prevents an admin from accidentally hammering the endpoint. 23 hours (not 24) prevents schedule drift: a daily cron at 06:00 UTC won't skip days if the previous run landed at 05:55.  
**Alternatives considered**: No rate limiting (trust the admin) — too fragile if cron is misconfigured or the button is spam-clicked. Per-session lock (prevent concurrent imports) — necessary but not sufficient on its own.

## Decision 4: Cron-compatible CLI script
**Decision**: Provide a standalone PHP script at `scripts/import_subscribers.php` that can be invoked from a system cron job. It calls the same `import_subscribers_from_radioid()` function, logs output, and exits with code 0 (success or no-update) or 1 (error). A sample crontab line is documented in the admin UI.  
**Rationale**: The constitution prohibits daemons and background jobs in the application, but a standalone CLI script invokable by system cron is a standard UNIX pattern that adds no application complexity. The admin installs the cron job once; the script handles all rate-limiting and conditional-GET logic internally.  
**Alternatives considered**: PHP built-in scheduler — doesn't exist. Curl-calling the admin UI — fragile (requires session auth). Background queue — massive overkill for one daily task.  
**Recommended cron schedule**: `0 6 * * * www-data php /opt/cflag-dmr/scripts/import_subscribers.php >> /var/log/cflag-subscriber-import.log 2>&1`

## Decision 5: Name field storage
**Decision**: Store first and last name as a single concatenated `name` VARCHAR(128) column. During import, compute `TRIM(CONCAT(first_name, ' ', last_name))` from the CSV's `FIRST_NAME` and `LAST_NAME` fields.  
**Rationale**: Last-heard display only needs a single name string. Splitting into two columns adds no query benefit for our read pattern. The CSV fields are `FIRST_NAME` and `LAST_NAME`.  
**Alternatives considered**: Separate first/last columns — useful if we ever want to sort by last name — deferred.

## Decision 6: CSV column mapping
The confirmed CSV format at `https://radioid.net/static/user.csv`:  
`RADIO_ID, CALLSIGN, FIRST_NAME, LAST_NAME, CITY, STATE, COUNTRY`

Column mapping:
- `RADIO_ID` → `radio_id` (INT UNSIGNED PK) — cast from string on INSERT
- `CALLSIGN` → `callsign` (VARCHAR 16)
- `TRIM(CONCAT(FIRST_NAME, ' ', LAST_NAME))` → `name` (VARCHAR 128)
- `CITY` → `city` (VARCHAR 128)
- `STATE` → `state` (VARCHAR 64)
- `COUNTRY` → `country` (VARCHAR 64)

## Decision 7: Import atomicity — per-batch, not full transaction
**Decision**: Use per-batch INSERT … ON DUPLICATE KEY UPDATE (500 rows/batch). If a batch fails, log and continue. `last_import_at` is only updated if zero batches failed.  
**Rationale**: A full-transaction import of 306k rows holds locks for minutes, blocking all reads. Per-batch inserts are interruptible and partially safe — partial success is better than a total rollback. Since this is upsert-only (not destructive), partial completion is recoverable.  
**Alternatives considered**: Full transaction — safest but unacceptable lock duration. Staging table → RENAME — clean but adds migration complexity.

## Decision 8: system_settings keys
Four keys stored in `system_settings`:
- `subscriber_last_import_at` — ISO datetime of last successful full import
- `subscriber_last_modified` — Last-Modified header value from last successful download (used for If-Modified-Since)
- `subscriber_import_count` — record count at last import
- `subscriber_min_import_interval_hours` — minimum hours between imports (default value: 23)
