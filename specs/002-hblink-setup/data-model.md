# Data Model: HBLink + HBMonv2 Setup and Analysis — 002-hblink-setup

This phase produces no new CFLAG DMR database tables. The "data model" here describes two things:

1. The filesystem state after a successful install — what exists where and why it matters to CFLAG DMR.
2. The required schema of the analysis document — what sections it must contain and what each section must prove.

---

## Post-Install Filesystem Layout

```text
/etc/hblink3/                        ← HBLink working directory (Docker Compose context)
├── hblink.cfg                       ← Main HBLink config (INI-style, KEY: VALUE)
├── rules.py                         ← Talkgroup routing rules (Python dict/list)
├── docker-compose.yml               ← Container orchestration; controls HBLink container
└── [additional files TBD]           ← Discover during analysis

/opt/HBMonv2/                        ← HBMonv2 installation root
├── venv/                            ← Python virtual environment (do not modify)
├── [app files TBD]                  ← Discover during analysis
└── [lastheard DB TBD]               ← Format and exact path: confirmed during analysis

/var/log/hblink/
└── hblink.log                       ← HBLink runtime log (plain text, line-based)

/etc/apache2/                        ← Apache config (modified by installer)
├── sites-available/
│   ├── 000-default.conf             ← Pre-existing default (may be modified by installer)
│   ├── cflag-dmr.conf               ← CFLAG DMR virtual host (must be preserved/restored)
│   └── hbmonv2.conf                 ← Created by installer for HBMonv2 (to be reconfigured)
├── sites-enabled/                   ← Symlinks to active configs
└── ports.conf                       ← Apache listening ports (may need 8080 added)

/usr/local/sbin/                     ← HBLink control scripts installed by installer
├── hblink-start
├── hblink-stop
├── hblink-restart
├── hblink-menu
├── hblink-update
└── hblink-uninstall
```

**Notes**:
- Paths marked `[TBD]` are confirmed during US3 analysis and recorded in `analysis.md`.
- Apache config filenames (`cflag-dmr.conf`, `hbmonv2.conf`) are illustrative — actual names discovered post-install.
- The venv at `/opt/HBMonv2/venv/` must not be modified; HBMonv2 is managed via systemd.

---

## Analysis Document Schema

`specs/002-hblink-setup/analysis.md` is the primary deliverable of this phase. It must contain the following sections, each verified against the actual post-install filesystem.

### Required Sections

| Section | Content Required | Verified By |
|---------|-----------------|-------------|
| `hblink.cfg` Structure | Every `[SECTION]` type listed; key fields per section with data types and example values; what each section controls | Reading `/etc/hblink3/hblink.cfg` |
| `rules.py` Structure | Python data structure documented; field names; how a talkgroup-to-system mapping is expressed; annotated example rule | Reading `/etc/hblink3/rules.py` |
| Log Format | Startup sequence captured (first N lines); transmission event entry captured (if available); fields identified per log line | Reading `/var/log/hblink/hblink.log` and `docker container logs hblink` |
| Last-Heard Data Store | Exact file path; format (SQLite / MySQL / flat file / other); schema or field list; what each field contains; how frequently it is updated | `find` + schema inspection |
| Reload and Restart Behaviour | Whether SIGHUP reload is supported; what config changes require a full restart; what happens to active calls during restart; exact command(s) to trigger each | `docker-compose.yml` inspection + testing |
| Directory Listing | Annotated `find` output for `/etc/hblink3/` and `/opt/HBMonv2/` (excluding venv and pycache) | `find` on live server |
| Integration Points | Every mechanism CFLAG DMR can use: readable/writable files, sockets, APIs, log streams; access requirements (permissions, ports); recommended approach per CFLAG DMR feature | Cross-referencing all of the above |

### Completeness Rules

- Every section must contain observed facts from the live server, not paraphrased documentation.
- "TBD" is not acceptable in any required section of the committed analysis document.
- If a section cannot be completed (e.g., no log entries exist yet), it must contain a documented explanation and whatever structural information is derivable without live data.

---

## HBLink Config Entities (Pre-Analysis Knowledge)

These entity types are known from the installer documentation. Exact fields will be confirmed and documented during analysis.

| Entity | Source | Known Fields (pre-analysis) | CFLAG DMR Relevance |
|--------|--------|-----------------------------|---------------------|
| MASTER | `hblink.cfg [MASTER_*]` | IP, port, passphrase, enabled | F2 config visibility, F6 editing |
| PEER | `hblink.cfg [*]` | callsign, IP, port, passphrase, enabled, mode | F3 peer management |
| OBP | `hblink.cfg [OBP_*]` | IP, port, network ID | F2 visibility |
| PARROT | `hblink.cfg [Parrot]` | enabled, talkgroup | F2 visibility |
| Routing rule | `rules.py` | system name(s), talkgroup ID(s), timeslot | F4 talkgroup management |
| Last-heard entry | HBMonv2 DB | callsign, talkgroup, timeslot, system, timestamp, duration (TBC) | F5 last-heard display |
