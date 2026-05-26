# Research: Network Config & Peer Management (F7)

## Decision: Config File Format

**Decision**: HBLink uses Python ConfigParser `.cfg` format, not YAML. Sections are `[MASTER]`, `[PEER-DMRID]`, `[OPENBRIDGE-name]`, `[GLOBAL]`, `[LOGGER]`. Each section has key=value pairs.

**Rationale**: HBLink source code (`hblink.py`) uses `configparser.ConfigParser()`. The existing `hblink.cfg` in this project confirms the format. No YAML parsing needed.

**Alternatives considered**: YAML (rejected — not what HBLink reads), JSON (rejected — same reason)

## Decision: Atomic File Write Strategy

**Decision**: Write generated config to `$config_path . '.tmp'`, then `rename($tmp, $config_path)`. The rename is atomic on Linux within the same filesystem.

**Rationale**: Prevents HBLink from reading a partial config if generation is interrupted. PHP's `rename()` on Linux is a single syscall (atomic). The temp file is cleaned up on failure.

**Alternatives considered**: Write directly (rejected — partial write risk), file locking with flock (rejected — complex and still risks partial reads by HBLink)

## Decision: Diff Computation

**Decision**: Store the full config text of each generation in the DB. Compute diffs in PHP by exploding both versions into lines and comparing with `array_diff`. Present additions (+) and removals (-) in a simple colored HTML table.

**Rationale**: Config files are small (typically <5KB for 500 peers). Storing full text is practical. No external diff binary needed. PHP line comparison is sufficient for the admin diff view.

**Alternatives considered**: `diff` shell command (rejected — unnecessary shell_exec surface, no portability guarantee), external diff library (rejected — Principle I, no Composer dependency justified)

## Decision: Passphrase Maximum Length

**Decision**: 15 characters maximum (enforced both client-side `maxlength` and server-side `strlen`).

**Rationale**: openSPOT4 Pro devices reject passphrases of exactly 16 characters. Using 15 as the limit provides a one-character safety margin and matches the constraint documented in project memory (AD-2).

**Alternatives considered**: 16 chars (rejected — AD-2 documents this causes openSPOT4 Pro connection failures)

## Decision: Output Path Configuration

**Decision**: Config output path read from `.env` as `HBLINK_CONFIG_PATH`. Validated with `realpath()` against the `HBLINK_ALLOWED_DIRS` hardcoded PHP array before any file write. This array is never DB-configurable.

**Rationale**: Consistent with existing `LASTHEARD_ALLOWED_DIRS` and `HBLINK_ALLOWED_DIRS` security patterns already in the codebase. Prevents path traversal to arbitrary filesystem locations.

**Alternatives considered**: DB-stored path (rejected — security principle, established precedent)

## Decision: Generation Locking

**Decision**: Use a DB flag `config_generation_in_progress` stored as a temporary row in a `config_generation_locks` table (or a simple flag in `master_server_settings`). If a generation is already running when a second is triggered, return an error immediately.

**Rationale**: PHP is stateless; process-level locking is unreliable. A DB row is visible across requests. Lock is inserted at generation start and deleted at generation end (including on failure). A stale lock older than 60 seconds is treated as expired.

**Alternatives considered**: File-based lock (`flock`) (rejected — not cross-process-safe under PHP-FPM), no locking (rejected — concurrent writes could corrupt the config file)

## Decision: Multi-Node Readiness

**Decision**: The `master_server_settings` and `openbridge_connections` tables include an optional `hblink_instance_id` column (nullable INT, default NULL). For the current single-node deployment this is always NULL. A future migration can populate it.

**Rationale**: Principle VIII requires the schema to not preclude multi-node. Adding a nullable FK column costs nothing now and avoids a destructive schema change later.
