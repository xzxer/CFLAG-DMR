---
description: "Implementation tasks for HBLink + HBMonv2 setup and analysis (P0)"
---

# Tasks: HBLink + HBMonv2 Setup and Analysis (P0)

**Input**: Design documents from `specs/002-hblink-setup/`

**Sources**: [plan.md](plan.md) · [spec.md](spec.md) · [data-model.md](data-model.md) · [research.md](research.md) · [quickstart.md](quickstart.md)

**Tests**: No automated test framework. All verification is manual (command output inspection + browser checks).

**Note**: Tasks in this phase run on the dev server as root. "File paths" in task descriptions refer to server filesystem paths or CFLAG DMR repo paths, as appropriate.

---

## Phase 1: Setup (Pre-Install Preparation)

**Purpose**: Verify the server is compatible and capture a clean backup before the destructive installer runs. T002–T004 are independent and can run in parallel after T001.

- [ ] T001 Verify dev server OS compatibility — run `cat /etc/os-release` on the dev server and confirm the OS is Debian 11, 12, or 13 OR Ubuntu 22.04 or 24.04 LTS; if not, stop and resolve the OS requirement before proceeding
- [ ] T002 [P] Back up Apache config — run `cp -r /etc/apache2 /root/apache2-backup-$(date +%Y%m%d)` on the dev server; verify with `ls /root/` that the backup directory exists
- [ ] T003 [P] Back up CFLAG DMR project — run `cp -r /opt/cflag-dmr /root/cflag-dmr-backup-$(date +%Y%m%d)` on the dev server; verify the backup exists
- [ ] T004 [P] Record current Apache state — run `apache2ctl -S > /root/apache2-state-before.txt` then `ss -tlnp | grep apache >> /root/apache2-state-before.txt` on the dev server; this captures the pre-install virtual host layout and listening ports for use in T010

**Checkpoint**: OS confirmed compatible. Backups in place. Apache state recorded. Safe to run the installer.

---

## Phase 2: Foundational (Blocking Prerequisite)

**Purpose**: Install HBLink and HBMonv2 using the ShaYmez installer. This single task blocks all three user stories.

**⚠️ CRITICAL**: Phases 3, 4, and 5 cannot begin until this phase is complete.

- [ ] T005 Run the HBLink + HBMonv2 installer — on the dev server as root, run: `cd /opt && git clone https://github.com/ShaYmez/hblink3-docker-install && cd hblink3-docker-install && ./hblink3-docker-install.sh`; follow all on-screen prompts; accept kernel updates if prompted; when the post-install setup menu appears, exit without making config changes (config review happens in later tasks); note: the installer installs Docker, Apache2, PHP, Python3, and sets up both services

**Checkpoint**: Installer completed without fatal errors. HBLink and HBMonv2 service files are in place. Proceed to US1 verification.

---

## Phase 3: User Story 1 — HBLink and HBMonv2 Installed and Verified (Priority: P1) 🎯 MVP

**Goal**: Confirm both services are running, logs are clean, and config files are accessible.

**Independent Test**: Run `docker compose ps` in `/etc/hblink3/` and `systemctl status hbmon` — both show active/running.

- [ ] T006 [US1] Verify HBLink Docker container is running — run `cd /etc/hblink3 && docker compose ps` on the dev server; confirm the `hblink` container appears in the list with status `Up`; if the container is not running, run `docker compose up -d` and re-check
- [ ] T007 [US1] Verify HBLink log shows a clean startup — run `docker container logs hblink 2>&1 | tail -80` on the dev server; scan for lines containing `FATAL` or `ERROR`; a clean startup shows connection listeners starting without crash loops; also check `tail -50 /var/log/hblink/hblink.log`
- [ ] T008 [US1] Verify HBMonv2 service is active — run `systemctl status hbmon` on the dev server; confirm the output shows `active (running)`; if stopped, run `systemctl start hbmon` and re-check; if it fails to start, run `journalctl -u hbmon -n 50` to view the error before proceeding
- [ ] T009 [US1] Verify HBLink config files are present and readable — run `cat /etc/hblink3/hblink.cfg` and `cat /etc/hblink3/rules.py` on the dev server; both files must produce non-empty output; if either file is missing the install did not complete correctly

**Checkpoint**: HBLink container is Up, log shows clean startup, HBMonv2 is active, both config files are readable. US1 is independently verified.

---

## Phase 4: User Story 2 — Apache Coexistence Resolved (Priority: P2)

**Goal**: Both CFLAG DMR (port 80) and HBMonv2 (port 8080) are accessible via Apache without conflict or interference.

**Independent Test**: Open `http://<server-ip>/` and `http://<server-ip>:8080/` in a browser — both load without errors.

- [ ] T010 [US2] Diff Apache config before and after install — run `diff -r /root/apache2-backup-$(ls /root/ | grep apache2-backup | tail -1 | sed 's/apache2-backup-//') /etc/apache2/` on the dev server; identify which files were added or changed by the installer; note the filename of the HBMonv2 virtual host config that was created (e.g., `/etc/apache2/sites-available/hbmonv2.conf` or similar)
- [ ] T011 [US2] Add port 8080 to Apache listening configuration — on the dev server, check if `Listen 8080` is already in `/etc/apache2/ports.conf`; if not, append it: `echo "Listen 8080" >> /etc/apache2/ports.conf`
- [ ] T012 [US2] Reconfigure HBMonv2 virtual host to port 8080 — on the dev server, edit the HBMonv2 vhost file identified in T010; change `<VirtualHost *:80>` to `<VirtualHost *:8080>`; save the file
- [ ] T013 [US2] Restore CFLAG DMR virtual host if overwritten — on the dev server, check `ls /etc/apache2/sites-enabled/` for the CFLAG DMR vhost; if it was disabled or overwritten by the installer, restore it: `cp /root/cflag-dmr-backup-*/etc/apache2/sites-available/cflag-dmr.conf /etc/apache2/sites-available/` (adjust path to match backup) then `a2ensite cflag-dmr.conf`; if no CFLAG DMR vhost existed before (default vhost was in use), verify that the default site is still enabled and pointing to `/opt/cflag-dmr/public`
- [ ] T014 [US2] Test Apache configuration syntax — run `apache2ctl configtest` on the dev server; output must include `Syntax OK`; if there are errors, fix the identified config file before proceeding
- [ ] T015 [US2] Reload Apache — run `systemctl reload apache2` on the dev server; confirm the command exits without error
- [ ] T016 [P] [US2] Verify CFLAG DMR loads — run `curl -s -o /dev/null -w "%{http_code}" http://localhost/` on the dev server; expect `200`; also load `http://149.28.97.128/` in a browser and confirm the CFLAG DMR page renders correctly with no errors
- [ ] T017 [P] [US2] Verify HBMonv2 dashboard loads — run `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/` on the dev server; expect `200`; also load `http://149.28.97.128:8080/` in a browser and confirm the HBMonv2 dashboard renders
- [ ] T018 [US2] Confirm document root separation — run `apache2ctl -S` on the dev server and review the output; confirm each virtual host has a distinct document root; confirm neither vhost's document root is a parent or child of the other's; confirm no directory under `/opt/cflag-dmr/app/`, `/opt/cflag-dmr/migrations/`, or `/opt/cflag-dmr/specs/` is web-accessible

**Checkpoint**: Both apps load in a browser. Apache config test passes. Document roots confirmed isolated.

---

## Phase 5: User Story 3 — Full HBLink Analysis Committed (Priority: P3)

**Goal**: A complete, committed analysis document at `specs/002-hblink-setup/analysis.md` covering all 7 required topic areas.

**Independent Test**: The file exists, is non-empty, and contains all 7 section headings listed below with no "TBD" entries.

**Investigation tasks (T019–T024 can run in parallel — each reads a different source)**:

- [ ] T019 [P] [US3] Investigate and document `hblink.cfg` structure — on the dev server, run `cat /etc/hblink3/hblink.cfg` and study the output; identify every `[SECTION]` header and list all `KEY: VALUE` lines within each section with the data type of each value; note what each section controls (MASTER, PEER, OBP, PARROT, etc.); save findings as notes for T025
- [ ] T020 [P] [US3] Investigate and document `rules.py` structure — on the dev server, run `cat /etc/hblink3/rules.py` and study the output; document the Python data structure used (dict? list of dicts?); identify field names and their values; annotate a complete example rule showing how a talkgroup is routed to a system and timeslot; save findings as notes for T025
- [ ] T021 [P] [US3] Investigate and document HBLink log format — on the dev server, run `cat /var/log/hblink/hblink.log` and `docker container logs hblink 2>&1`; capture the startup sequence (first 30–50 lines after startup); if any transmission events are logged, capture one; identify the fields in each log line (timestamp format, log level, source component, message); save findings for T025
- [ ] T022 [P] [US3] Locate and document HBMonv2 lastheard data store — on the dev server, run `find /opt/HBMonv2 -not -path '*/venv/*' -name "*.db" -o -name "*.sqlite" -o -name "*.json" -o -name "*.csv" 2>/dev/null`; identify the format of the lastheard store; if SQLite, run `sqlite3 <path> ".schema"` and `sqlite3 <path> "SELECT * FROM lastheard LIMIT 5;" 2>/dev/null`; document the file path, format, schema, fields, and update frequency; save findings for T025
- [ ] T023 [P] [US3] Document reload and restart behaviour — on the dev server, run `cat /etc/hblink3/docker-compose.yml` and inspect for restart policies and signal handling; run `docker exec hblink grep -r "SIGHUP\|signal\|reload" /app/ 2>/dev/null | head -20` to check if HBLink handles SIGHUP for config reload; document: whether a reload-without-restart is supported, what config changes require a full `docker compose restart`, what happens to active calls during a restart, and the exact commands to trigger each option; save findings for T025
- [ ] T024 [P] [US3] Produce annotated directory listing — on the dev server, run `find /etc/hblink3 /opt/HBMonv2 -not -path '*/venv/*' -not -path '*/__pycache__/*' -not -path '*/.git/*' | sort`; for each file and directory in the output, write a one-line description of its purpose; save the annotated listing for T025

**Synthesis tasks (run after T019–T024)**:

- [ ] T025 [US3] Document integration points for CFLAG DMR — using findings from T019–T024, compile a list of every mechanism CFLAG DMR can use to read from or interact with HBLink: readable/writable config files and their paths, the log file and how to tail it, the HBMonv2 lastheard database and how to query it, the report socket on port 4321 (test with `nc -zv localhost 4321` to confirm it's listening), and any other hooks; for each integration point document the access method, required permissions, and which CFLAG DMR features (F2–F8) it serves; save findings for T026
- [ ] T026 [US3] Write `specs/002-hblink-setup/analysis.md` — create the file at `/opt/cflag-dmr/specs/002-hblink-setup/analysis.md` and write up all findings from T019–T025 organised into these 7 sections: (1) hblink.cfg Structure, (2) rules.py Structure, (3) Log Format, (4) Last-Heard Data Store, (5) Reload and Restart Behaviour, (6) Annotated Directory Listing, (7) Integration Points for CFLAG DMR; each section must contain observed facts with no "TBD" entries; if a topic could not be fully investigated, document why and what partial information is available
- [ ] T027 [US3] Commit `analysis.md` to the repository — from `/opt/cflag-dmr`, run `git add specs/002-hblink-setup/analysis.md && git commit -m "Add HBLink structure analysis"`; verify with `git log --oneline -3` that the commit appears

**Checkpoint**: `specs/002-hblink-setup/analysis.md` exists, is committed, and contains all 7 sections with no "TBD" entries. US3 is complete.

---

## Phase 6: Polish and Cross-Cutting Concerns

**Purpose**: Reboot persistence test and firewall documentation. Can begin after US1 and US2 are complete; runs in parallel with or after US3.

- [ ] T028 Reboot persistence test — on the dev server, run `reboot`; after the server comes back up, verify: `docker compose -f /etc/hblink3/docker-compose.yml ps` shows `hblink` as Up; `systemctl status hbmon` shows `active (running)`; `curl -s -o /dev/null -w "%{http_code}" http://localhost/` returns 200; `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/` returns 200
- [ ] T029 Document UFW firewall rules required for HBLink — on the dev server, check current UFW status with `ufw status`; if UFW is active, verify the following ports are allowed: `62030:62031/udp` (MMDVM), `62032:62050/udp` (OpenBridge), `4321/tcp` (HBLink report socket), `9000/udp` (HBMonv2 WebSocket), `8080/tcp` (HBMonv2 web UI); document the required rules and whether they are currently in place; add this as an appendix to `specs/002-hblink-setup/analysis.md` or as a note in the plan

**Checkpoint**: Both services survive a reboot. Firewall requirements documented. P0 is complete.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately. T002, T003, T004 are parallel after T001.
- **Phase 2 (Foundational)**: Depends on Phase 1 completion. Blocks all user stories.
- **Phase 3 (US1)**: Depends on Phase 2. T006–T009 run in sequence (each verifies a precondition for the next).
- **Phase 4 (US2)**: Depends on Phase 2 (installer must be done). T010–T015 run in sequence. T016 and T017 run in parallel after T015.
- **Phase 5 (US3)**: Depends on Phase 2 (server must have HBLink installed). T019–T024 run in parallel. T025 depends on T019–T024. T026 depends on T025. T027 depends on T026.
- **Phase 6 (Polish)**: T028 depends on US1 and US2 complete. T029 is independent and can run any time after Phase 2.

### User Story Dependencies

- **US1 and US2 are independent of each other** at the phase level (both depend only on Phase 2). In practice, US2 must follow US1 because you cannot meaningfully test coexistence until you know both services are up.
- **US3 is independent of US2** — analysis can begin once Phase 2 is done, even while US2 coexistence work is in progress. The analysis reads files; it does not depend on Apache working correctly.

### Parallel Opportunities

```
# Phase 1 — after T001 completes:
T002: Back up Apache config
T003: Back up CFLAG DMR project
T004: Record current Apache state

# Phase 4 — after T015 (Apache reload) completes:
T016: Verify CFLAG DMR loads
T017: Verify HBMonv2 loads

# Phase 5 — after Phase 2 completes (can overlap with Phase 3 and 4):
T019: Investigate hblink.cfg
T020: Investigate rules.py
T021: Investigate log format
T022: Investigate HBMonv2 lastheard DB
T023: Investigate reload behaviour
T024: Produce directory listing
```

---

## Implementation Strategy

### MVP First

1. Complete Phase 1 — Pre-install backup
2. Complete Phase 2 — Run installer
3. Complete Phase 3 (US1) — Verify services are up
4. **STOP and validate**: Both Docker container and HBMonv2 service are confirmed running
5. Complete Phase 4 (US2) — Restore Apache coexistence
6. **STOP and validate**: Both apps load in browser
7. Complete Phase 5 (US3) — Produce and commit analysis
8. Complete Phase 6 — Reboot test and firewall docs

### Incremental Delivery

1. Phase 1 + Phase 2 → HBLink is on the server
2. Phase 3 → HBLink is confirmed working
3. Phase 4 → Both web apps coexist without conflict
4. Phase 5 → Analysis committed; team has everything needed to plan F2–F8
5. Phase 6 → Environment validated as stable and production-ready

---

## Notes

- `[P]` = tasks that can run in parallel (different files/sources, no blocking dependency on each other)
- `[US1]`/`[US2]`/`[US3]` = user story traceability
- All shell commands run on the dev server (149.28.97.128) as root
- The `analysis.md` file written in T026 is the primary output of this entire phase — it feeds directly into planning for F2 through F8
- Do not modify any CFLAG DMR application code during this phase
- The backup directories at `/root/apache2-backup-*` and `/root/cflag-dmr-backup-*` should not be deleted until P0 is fully validated
