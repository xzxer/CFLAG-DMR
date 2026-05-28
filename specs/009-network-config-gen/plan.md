# Implementation Plan: Network Config & Peer Management (F7)

**Branch**: `009-network-config-gen` | **Date**: 2026-05-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/009-network-config-gen/spec.md`

## Summary

F7 adds DB-driven HBLink config file generation. Admins manage master server settings and OpenBridge connections through a web UI. On demand, a generation job reads approved devices (`get_whitelist_eligible_dmr_ids()`), active subscriptions (`get_device_subscriptions_for_config()`), and master settings, then writes an atomic HBLink-compatible config file. Every generation is recorded with a diff against the previous version for audit purposes.

## Technical Context

**Language/Version**: PHP 8.3, strict types

**Primary Dependencies**: PDO/MariaDB (existing), plain PHP file I/O for config write, `shell_exec` only for HBLink process status (already gated behind `HBLINK_ALLOWED_DIRS` hardcoded allowlist)

**Storage**: MariaDB — new tables for master settings, OpenBridge entries, and generation history; generated config files written to a hardcoded path defined in `.env`

**Testing**: Manual browser testing per quickstart.md scenarios; SQL assertions for data contracts

**Target Platform**: Linux server (Apache/PHP 8.3)

**Project Type**: Web application (admin UI + server-side generation)

**Performance Goals**: Config generation completes in under 5 seconds for up to 500 approved devices (SC-001)

**Constraints**: Passphrase max 15 characters (openSPOT4 Pro hardware constraint AD-2). Output path hardcoded in PHP via `.env`, never DB-configurable (Principle II / security boundary). File write must be atomic (write-to-temp then rename). Config generation is system_admin only.

**Scale/Scope**: Single HBLink instance. Multi-node extension must not be precluded by schema design (Principle VIII).

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Simplicity | ✅ Pass | Plain PHP, no new framework. Config generation is a single service function. |
| II. Security First | ✅ Pass | Output path from `.env` only. No shell injection surface (no user-controlled shell args). Passphrase max enforced server-side. All HTML output escaped. |
| III. Public Directory Isolation | ✅ Pass | All generation logic in `app/config/`. Web pages in `public/admin/`. Generated config files written outside `public/`. |
| IV. Database Integrity | ✅ Pass | New tables delivered as numbered migration files (011, 012). |
| V. Feature Quality | ✅ Pass | Each US has testable acceptance scenarios and an independent test. |
| VI. Branch Strategy | ✅ Pass | Branched from dev as `009-network-config-gen`. |
| VII. Mobile-First | ✅ Pass | Admin forms use existing card/field-row CSS classes. Touch targets 44px min. |
| VIII. Scale/Observability | ✅ Pass | Generation history is first-class DB data. Schema does not assume single node — `hblink_instances` FK column optional for future multi-node. |

## Project Structure

### Documentation (this feature)

```text
specs/009-network-config-gen/
├── plan.md              ← this file
├── spec.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── config-generation.md
└── checklists/
    └── requirements.md
```

### Source Code

```text
migrations/
├── 011_create_network_config_tables.sql   ← master_server_settings, openbridge_connections
└── 012_create_config_generation_history.sql

app/
└── config/
    ├── generator.php        ← generate_hblink_config(), write_config_atomic()
    ├── settings.php         ← get_master_settings(), save_master_settings(), validate_passphrase()
    └── openbridge.php       ← CRUD for openbridge_connections

public/
└── admin/
    ├── config/
    │   ├── index.php        ← generate button, last-generation status, history list
    │   ├── master.php       ← master server settings form
    │   ├── openbridge.php   ← OpenBridge connection list + add/edit/delete
    │   └── history.php      ← generation history table + diff view
    └── hblink/
        └── (existing files untouched)
```

**Structure Decision**: All generation logic lives under `app/config/` (new subdirectory following the existing `app/hblink/`, `app/devices/` pattern). Admin pages under `public/admin/config/`. No new top-level directories.

## Phase 0: Research

All decisions are already resolved from project context:

- **Config file format**: HBLink uses Python ConfigParser `.cfg` format (not YAML as originally speculated in description). Sections like `[MASTER]`, `[PEER-XXXXXXX]`, `[OPENBRIDGE-name]`. PHP's `file_put_contents()` with string building is used — no external parser needed.
- **Atomic write**: Write to `$path . '.tmp'`, then `rename()`. On Linux, `rename()` within the same filesystem is atomic.
- **Diff computation**: PHP's built-in `array_diff_assoc` on exploded lines, or a line-by-line diff using `array_udiff`. No external diff tool required.
- **Config output path**: From `.env` as `HBLINK_CONFIG_PATH`. Validated against `HBLINK_ALLOWED_DIRS` hardcoded allowlist before any write.
- **Passphrase constraint**: 15 chars max (not 16) — hardware compatibility with openSPOT4 Pro devices.

**Output**: See `research.md`

## Phase 1: Design & Contracts

### Data Model

See `data-model.md`

Key tables:
- `master_server_settings` — singleton (id=1), bind_address, port, passphrase, report_address, report_port, updated_at, updated_by_user_id
- `openbridge_connections` — id, name, remote_address, port, passphrase, network_id, enabled, created_at, updated_at
- `config_generation_history` — id, generated_at, generated_by_user_id, config_text (MEDIUMTEXT), changed (TINYINT), diff_text (MEDIUMTEXT NULL)

### Interface Contracts

See `contracts/config-generation.md`

Key function signatures:
- `generate_hblink_config(int $actor_id): array` → `['ok' => bool, 'changed' => bool, 'error' => string|null]`
- `get_master_settings(): array|null`
- `save_master_settings(int $actor_id, array $data): array` → `['ok' => bool, 'error' => string|null]`
- `get_openbridge_connections(bool $enabled_only = false): array`
- `save_openbridge_connection(int $actor_id, array $data): array`
- `get_generation_history(int $limit = 50): array`
- `get_generation_diff(int $history_id): array|null`

### Quickstart / Test Scenarios

See `quickstart.md`
