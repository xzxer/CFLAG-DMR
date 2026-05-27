# Research: F14 — Subscriber ID Import

## Decision 1: RadioID.net data source format
**Decision**: Use the RadioID.net user CSV export at `https://radioid.net/static/user.csv`.  
**Rationale**: This is a well-known, stable, publicly accessible file that the amateur radio community widely uses for DMR ID lookups. No API key required. Format: `RADIO_ID,CALLSIGN,FIRST_NAME,LAST_NAME,CITY,STATE,COUNTRY,REMARKS`.  
**Alternatives considered**: RadioID.net JSON API — requires credentials for bulk access. FCC ULS database — only covers US licensees. Local manual entry only — not scalable.

## Decision 2: Import mechanism
**Decision**: PHP `fopen()` stream from the URL directly into CSV parsing via `fgetcsv()`, with row-by-row upsert in batches of 500 using prepared statements.  
**Rationale**: Memory-efficient (no full file load), no temp file needed, single PHP request for MVP. `set_time_limit(300)` and `ini_set('memory_limit','256M')` called at start of import.  
**Alternatives considered**: Download to temp file then parse (two steps, no advantage). Background job with progress polling (better UX but adds complexity — deferred to later).

## Decision 3: Upsert strategy
**Decision**: `INSERT INTO subscriber_ids (...) ON DUPLICATE KEY UPDATE callsign=VALUES(callsign), name=VALUES(name), ...` but with `AND source != 'local'` guard via a trigger or application-layer check.  
**Rationale**: Application-layer check is simpler: skip upsert for any radio_id where current source='local'. Single SELECT before batch insert is acceptable at this scale.  
**Alternatives considered**: Database trigger to protect local overrides — adds hidden complexity. Separate import table with merge — overkill.

## Decision 4: Subscriber display in last-heard
**Decision**: Join `subscriber_ids` in the last-heard query rather than doing per-row PHP lookups.  
**Rationale**: A SQL LEFT JOIN on `radio_id` is far more efficient than N individual PHP lookups for a page showing 50–100 rows. The `subscriber_ids` table will have an index on `radio_id` (PK).  
**Alternatives considered**: PHP array cache (load all into memory) — memory-inefficient for 300k records. Per-row lookup function — N+1 query problem.

## Decision 5: Name field storage
**Decision**: Store `CONCAT(first_name, ' ', last_name)` as a single `name` column. Don't store first/last separately.  
**Rationale**: The display use case is always "full name". Splitting is unnecessary complexity.  
**Alternatives considered**: Store first_name + last_name separately for filtering — not needed at this stage.

## Constraint: RadioID.net CSV size
The user.csv file is approximately 50–80MB. PHP streaming via `fopen()` URL wrappers requires `allow_url_fopen=On` in php.ini (typically on by default). Verify on this server before implementation.
