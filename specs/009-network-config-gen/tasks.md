# Tasks: Network Config & Peer Management (F7)

**Branch**: `009-network-config-gen`
**Input**: specs/009-network-config-gen/plan.md, spec.md, data-model.md, contracts/config-generation.md

## Phase 1: Setup

**Purpose**: Migrations and app directory structure

- [X] T001 Create migrations/011_create_network_config_tables.sql (master_server_settings + openbridge_connections tables per data-model.md) and apply to cflag_dmr_dev
- [X] T002 Create migrations/012_create_config_generation_history.sql (config_generation_history table per data-model.md) and apply to cflag_dmr_dev
- [X] T003 Create app/config/ directory with empty manager stubs: generator.php, settings.php, openbridge.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core service layer before any pages can be built

**⚠️ CRITICAL**: No page work can begin until this phase is complete

- [X] T004 Implement app/config/settings.php: get_master_settings(), save_master_settings(), validate_passphrase() per contracts/config-generation.md
- [X] T005 Implement app/config/openbridge.php: get_openbridge_connections(), get_openbridge_connection(), save_openbridge_connection(), toggle_openbridge_connection(), delete_openbridge_connection() per contracts/config-generation.md
- [X] T006 Implement HBLink config text renderer in app/config/generator.php: internal render_config_text() function that builds [MASTER], [PEER-DMRID] per subscription, [OPENBRIDGE-name] sections from input arrays
- [X] T007 Implement generate_hblink_config(int $actor_id): array in app/config/generator.php — atomic write, diff computation, history insert, config_change_queue clearing, lock guard (all per contracts/config-generation.md)
- [X] T008 Implement get_generation_history() and get_generation_detail() in app/config/generator.php

**Checkpoint**: Service layer complete — all contract functions callable. Verify with a CLI test script or unit-test against seeded data.

---

## Phase 3: User Story 1 — Generate HBLink Config (P1) 🎯 MVP

**Goal**: Admin can trigger config generation from a web page and see the output file updated

**Independent Test**: seed master_server_settings, approved device, subscription; POST to index.php generate action; verify config file contains expected REG_ACL entry

- [X] T009 [US1] Create public/admin/config/index.php — generation trigger (POST action=generate), last-generation status card (timestamp, changed/no-change), link to history
- [X] T010 [US1] Add /admin/config/ link to public/admin/index.php nav section (system_admin only)

**Checkpoint**: US1 independently testable — generation runs, config file updated, status shown

---

## Phase 4: User Story 2 — Master Server Settings (P2)

**Goal**: Admin can edit master settings via a form and see them reflected in next generated config

**Independent Test**: open master.php, edit passphrase to exactly 15 chars, save, generate config, confirm passphrase in output

- [X] T011 [US2] Create public/admin/config/master.php — master settings form (bind_address, port, passphrase, report_address, report_port, ping_time, max_missed); POST save action with CSRF; passphrase maxlength=15 client + server validation
- [X] T012 [US2] Add "Master Settings" link to public/admin/config/index.php nav

**Checkpoint**: US2 independently testable — settings saved, config generation reflects saved values

---

## Phase 5: User Story 3 — OpenBridge Connections (P3)

**Goal**: Admin can add/edit/enable/disable/delete OpenBridge entries; they appear in generated config

**Independent Test**: add an OpenBridge entry, generate config, verify OPENBRIDGE stanza appears; disable it, regenerate, verify stanza gone

- [X] T013 [US3] Create public/admin/config/openbridge.php — list all connections with enable/disable toggle and delete; add/edit form (name, remote_address, port, passphrase, network_id); POST actions: save, toggle, delete with CSRF
- [X] T014 [US3] Add "OpenBridge" link to public/admin/config/index.php nav

**Checkpoint**: US3 independently testable — connections managed, config reflects enabled entries only

---

## Phase 6: User Story 4 — Config Generation History & Diff (P4)

**Goal**: Admin can view generation history and inspect the diff between consecutive configs

**Independent Test**: generate config twice with a device approval between them; view history; confirm diff shows added DMR ID in REG_ACL

- [X] T015 [US4] Create public/admin/config/history.php — table of last 50 generation events (timestamp, actor, changed flag); link to diff view per entry
- [X] T016 [US4] Add diff view to history.php (or inline panel) — shows added/removed lines in colored rows; handles "no change" and "first generation" edge cases

**Checkpoint**: US4 independently testable — history and diff visible, changes accurately shown

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T017 [P] Add "Config" section to public/admin/index.php dashboard card (system_admin only) with pending config_change_queue count and link to /admin/config/
- [ ] T018 [P] Verify all forms have min-height:44px touch targets and mobile-responsive layout
- [ ] T019 Run quickstart.md scenarios 1.1 through W.2 and verify all pass

---

## Dependencies & Execution Order

- T001, T002, T003 → T004, T005 → T006, T007, T008 (sequential within phases)
- T006 and T007 can be written in parallel (different function scopes within generator.php, but coordinate to avoid conflicts)
- T009, T010 depend on T007 complete
- T011, T012 depend on T004 complete
- T013, T014 depend on T005 complete
- T015, T016 depend on T008 complete
- T017, T018, T019 depend on all phases complete

## Implementation Strategy

**MVP**: Complete T001–T010 (US1 config generation) first. This delivers working config file output.
**US2**: T011–T012 (master settings form) — short and independent
**US3**: T013–T014 (OpenBridge management)
**US4**: T015–T016 (history and diff) — depends on multiple generations existing
