# Research: F16 — Backup & Rollback

## Decision 1: Rollback mechanism
**Decision**: Rollback re-uses `apply_hblink_config()` from F13, passing the historical `config_text` directly instead of calling `generate_hblink_config()`. A thin `rollback_to_generation(int $generation_id, int $actor_id): array` wrapper fetches the config_text from `config_generation_history`, validates the referenced record exists, then calls the apply pipeline.  
**Rationale**: Keeps the apply pipeline as a single code path — no duplicated restart/backup/audit logic. The rollback is just "apply a specific config_text", which is exactly what F13 already does.  
**Alternatives considered**: Re-generating config from DB state at the time of that generation — not feasible since DB state has since changed and cannot be rewound. Separate rollback shell script — more moving parts and bypasses the audit trail.

## Decision 2: `rolled_back_from_id` reference column
**Decision**: Add a nullable `rolled_back_from_id INT UNSIGNED` FK to `config_generation_history` referencing the same table. When a rollback creates a new history record, this field is set to the source generation's ID.  
**Rationale**: Preserves a clean audit trail distinguishing normal applies from rollbacks without adding a separate table. Allows the UI to annotate rollback rows with "↩ Rolled back from #NNN".  
**Alternatives considered**: A separate `rollback_log` table — more joins, no benefit for MVP. A boolean `is_rollback` flag without the reference — loses which generation was restored.

## Decision 3: Config file download
**Decision**: A GET endpoint at `public/admin/config/history.php?action=download&id=NNN` reads `config_text` from the DB row and streams it with `Content-Disposition: attachment; filename=hblink-YYYYMMDD-HHMMSS.cfg`. The date/time in the filename is the `generated_at` of the history record.  
**Rationale**: Simplest correct approach — no temp files, no filesystem dependency. Config text is already in the DB.  
**Alternatives considered**: Serving backup files from `/etc/hblink3/backups/` — those backups are of the *previous* config (made just before each apply), not the record's own config_text. Using the backup path would give the wrong content for the download.

## Decision 4: Diff display
**Decision**: Use the `diff_text` column already written by F7/the config generator when `changed=1`. Display in a `<pre>` block with basic green/red line coloring (CSS only, no JS library). For rollback rows, the diff is against whatever was applied immediately before the rollback.  
**Rationale**: `diff_text` is already stored — no need to recompute diffs at view time. CSS line coloring (`+` lines green, `-` lines red) is lightweight and readable.  
**Alternatives considered**: Recompute diff on the fly — wastes time for large configs and introduces a PHP diff dependency. Full side-by-side diff viewer — out of scope per spec.

## Decision 5: "Currently running" config read
**Decision**: `get_running_hblink_config(): string|null` reads `/etc/hblink3/hblink.cfg` directly (the same constant defined in F13: `HBLINK_CONFIG_PATH`). Returns null if the file cannot be read. Displayed in a `<pre>` block in the admin UI.  
**Rationale**: Simple file read. www-data already needs read access to this file to do atomic writes (F13), so no new permissions required.  
**Alternatives considered**: Reading config from inside the Docker container via `docker exec cat ...` — unnecessarily complex, container may be stopped.

## Decision 6: Pagination
**Decision**: History page paginates at 20 records per page using LIMIT/OFFSET. Page number passed as `?page=N` query param. `config_text` is NOT loaded on the list query — a separate query fetches `config_text` only for download or diff expand actions.  
**Rationale**: `config_text` can be tens of KB per row. Loading it for 20 rows would be costly. The list only needs metadata columns.  
**Alternatives considered**: Cursor-based pagination — unnecessary complexity for a low-volume admin page.
