# Research: F13 — Controlled Restart / Reload

## Decision 1: Docker restart mechanism
**Decision**: Use `docker compose -f /etc/hblink3/docker-compose.yml restart hblink` via `shell_exec()` with a hardcoded, non-user-configurable path.  
**Rationale**: The path is a deployment constant, not user input, so it's safe to hardcode. `shell_exec()` is acceptable here because no user-supplied values enter the command string. The command must run as a user with Docker access.  
**Alternatives considered**: `docker restart hblink` (simpler but not compose-aware — misses env vars and volume mounts). A separate restart daemon (adds complexity, not needed at this scale).

## Decision 2: Permission model for Docker access
**Decision**: Add the `www-data` user to the `docker` group on the server, OR use a targeted `sudoers` rule for the exact restart command.  
**Rationale**: `www-data` in docker group is simpler but gives broad Docker access. A sudoers rule (`www-data ALL=(ALL) NOPASSWD: /usr/bin/docker compose -f /etc/hblink3/docker-compose.yml restart hblink`) is least-privilege. Sudoers rule preferred for production.  
**Alternatives considered**: A separate Unix socket daemon that the web process signals via a file/pipe — more robust but over-engineered for this scale.

## Decision 3: Config file backup strategy
**Decision**: Copy the current live config file to a timestamped backup path before overwriting. Keep the last 10 backups (rotate older ones). Backup stored adjacent to the live file (e.g., `/etc/hblink3/hblink.cfg.bak.20260527-143022`).  
**Rationale**: Simple filesystem operation. Rotation prevents unbounded disk usage. Does not require a separate backup table — `config_generation_history` already records the config text in the DB.  
**Alternatives considered**: Storing backups only in the DB (already done via `config_generation_history.config_text`) — sufficient for rollback but not for emergency manual recovery.

## Decision 4: Atomic file write
**Decision**: Write new config to a temp file (`hblink.cfg.new`) in the same directory, then use `rename()` which is atomic on POSIX filesystems.  
**Rationale**: Prevents a partial write from corrupting the running config if the PHP process is interrupted mid-write.  
**Alternatives considered**: Write directly (risky), write to `/tmp` then copy (not atomic across filesystems).

## Decision 5: Restart status detection
**Decision**: Query container state with `docker inspect --format='{{.State.Status}}' hblink` via shell_exec. Parse for "running".  
**Rationale**: No polling loop needed for the status indicator — just a point-in-time check on page load. For the apply workflow, poll up to 10s after restart command.  
**Alternatives considered**: Parse docker-compose ps output (more fragile). HBLink health check endpoint (HBLink doesn't expose one).

## Decision 6: config_generation_history — extend or keep as-is
**Decision**: Add `success TINYINT(1) NOT NULL DEFAULT 1` and `error_message TEXT NULL` columns to `config_generation_history` via a new migration (020).  
**Rationale**: The table currently only records successful generations. Recording failures lets admins see why an apply failed without consulting server logs.  
**Alternatives considered**: Separate `apply_log` table — more normalized but unnecessary overhead for this use case.

## Constraint: www-data file write permissions
The web process must be able to write to `/etc/hblink3/`. This was already required for F7 config generation. Confirm permissions are set correctly: `chown -R www-data:www-data /etc/hblink3/` or use a dedicated config output directory.
