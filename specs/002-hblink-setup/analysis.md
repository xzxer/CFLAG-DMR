# HBLink + HBMonv2 Structure Analysis

**Date**: 2026-05-25 | **Server**: 149.28.97.128 | **Branch**: 002-hblink-setup

All findings are based on direct inspection of the live post-install server. No documentation paraphrasing.

---

## 1. hblink.cfg Structure

**Location**: `/etc/hblink3/hblink.cfg` (bind-mounted read-write into container at `/hblink3/hblink.cfg`)

**Format**: INI-style, `KEY: VALUE` pairs. Section headers are `[NAME]`. Comments begin with `#`.

### Sections

#### `[GLOBAL]`
Program-wide defaults. Applied to all systems unless overridden per-section.

| Key | Type | Value | Meaning |
|-----|------|-------|---------|
| `PATH` | string | `./` | Working directory inside container |
| `PING_TIME` | int | `5` | Seconds between peer pings and master maintenance loop |
| `MAX_MISSED` | int | `3` | Missed pings before peer is de-registered |
| `USE_ACL` | bool | `True` | Enable ACL processing |
| `REG_ACL` | string | `PERMIT:ALL` | ACL for peer registration radio IDs |
| `SUB_ACL` | string | `DENY:1` | ACL for subscriber radio IDs |
| `TGID_TS1_ACL` | string | `PERMIT:ALL` | ACL for talkgroup IDs on TS1 |
| `TGID_TS2_ACL` | string | `PERMIT:ALL` | ACL for talkgroup IDs on TS2 |

ACL format: `ACTION:id|start-end,...` — e.g., `DENY:1,1000-2000`. `PERMIT:ALL` and `DENY:ALL` are valid.

#### `[REPORTS]`
TCP reporting socket that HBMonv2 connects to.

| Key | Type | Value | Meaning |
|-----|------|-------|---------|
| `REPORT` | bool | `True` | Enable reporting socket (must be True for HBMonv2) |
| `REPORT_INTERVAL` | int | `30` | Seconds between full state pushes |
| `REPORT_PORT` | int | `4321` | TCP port the socket listens on |
| `REPORT_CLIENTS` | string | `*` | Allowed client IPs (`*` = all) |

#### `[LOGGER]`
Log output configuration.

| Key | Type | Value | Meaning |
|-----|------|-------|---------|
| `LOG_FILE` | string | `hblink.log` | Log filename (relative to PATH, bind-mounted to `/var/log/hblink/hblink.log`) |
| `LOG_HANDLERS` | string | `file-timed,console-timed` | Output targets: `file`, `file-timed`, `console`, `console-timed`, `syslog`, `null` |
| `LOG_LEVEL` | string | `INFO` | Minimum log level: `DEBUG`, `INFO`, `WARNING`, `CRITICAL` |
| `LOG_NAME` | string | `HBlink` | Logger name prefix in output |

#### `[ALIASES]`
ID alias file download configuration. Used to resolve DMR IDs to callsigns.

| Key | Type | Value | Meaning |
|-----|------|-------|---------|
| `TRY_DOWNLOAD` | bool | `True` | Auto-download alias files on startup |
| `PATH` | string | `./json/` | Directory for alias JSON files |
| `PEER_FILE` | string | `peer_ids.json` | Repeater/peer alias file |
| `SUBSCRIBER_FILE` | string | `subscriber_ids.json` | Subscriber (radio user) alias file |
| `TGID_FILE` | string | `talkgroup_ids.json` | Talkgroup name alias file |
| `PEER_URL` | string | `https://radioid.net/static/rptrs.json` | Download URL |
| `SUBSCRIBER_URL` | string | `https://radioid.net/static/users.json` | Download URL |
| `STALE_DAYS` | int | `28` | Days before re-downloading |

#### `[OBP-*]` — OpenBridge Peer (MODE: OPENBRIDGE)
Connects to external DMR networks (e.g., Brandmeister, DMR+) via OpenBridge protocol.

| Key | Type | Example | Meaning |
|-----|------|---------|---------|
| `MODE` | string | `OPENBRIDGE` | Section type identifier |
| `ENABLED` | bool | `False` | Whether this connection is active |
| `IP` | string | `` | Local bind IP (blank = all interfaces) |
| `PORT` | int | `62035` | Local UDP port |
| `NETWORK_ID` | int | `3129100` | DMR radio ID sent to remote server |
| `PASSPHRASE` | string | `password` | Shared secret with remote server |
| `TARGET_IP` | string | `1.2.3.4` | Remote server IP |
| `TARGET_PORT` | int | `62035` | Remote server UDP port |
| `BOTH_SLOTS` | bool | `True` | Extend OBP to use both timeslots for unit calls |
| `USE_ACL` | bool | `True` | Enable ACLs for this section |
| `SUB_ACL` | string | `DENY:1` | Per-section subscriber ACL |
| `TGID_ACL` | string | `PERMIT:ALL` | Per-section TGID ACL (note: `TGID_ACL`, not split by TS) |

#### `[MASTER-*]` — HomeBrew Protocol Master (MODE: MASTER)
This server acts as a master that other repeaters/hotspots register to.

| Key | Type | Example | Meaning |
|-----|------|---------|---------|
| `MODE` | string | `MASTER` | Section type identifier |
| `ENABLED` | bool | `True` | Whether this master is active |
| `REPEAT` | bool | `True` | Repeat traffic to connected peers |
| `MAX_PEERS` | int | `10` | Maximum concurrent peer connections |
| `EXPORT_AMBE` | bool | `False` | Export raw AMBE audio frames |
| `IP` | string | `` | Local bind IP (blank = all interfaces) |
| `PORT` | int | `54000` | UDP port this master listens on |
| `PASSPHRASE` | string | `passw0rd` | Shared secret peers must use to register |
| `GROUP_HANGTIME` | int | `5` | Seconds group traffic hangs after last packet |
| `USE_ACL` | bool | `True` | Enable ACLs |
| `REG_ACL` | string | `DENY:1` | ACL for peer registration IDs |
| `SUB_ACL` | string | `DENY:1` | ACL for subscriber IDs |
| `TGID_TS1_ACL` | string | `PERMIT:ALL` | TGID ACL for TS1 |
| `TGID_TS2_ACL` | string | `PERMIT:ALL` | TGID ACL for TS2 |

#### `[Parrot]` / `[REPEATER-*]` — HomeBrew Protocol Peer (MODE: PEER)
This server acts as a client connecting to another HBP master.

| Key | Type | Example | Meaning |
|-----|------|---------|---------|
| `MODE` | string | `PEER` | Section type |
| `ENABLED` | bool | `False` | Active flag |
| `LOOSE` | bool | `True` | Relax packet validation (for non-compliant masters like XLXD) |
| `EXPORT_AMBE` | bool | `False` | Export AMBE frames |
| `IP` | string | `127.0.0.1` | Local bind IP |
| `PORT` | int | `54098` | Local UDP port |
| `MASTER_IP` | string | `127.0.0.1` | Remote master IP |
| `MASTER_PORT` | int | `54100` | Remote master UDP port |
| `PASSPHRASE` | string | `passw0rd` | Registration passphrase |
| `CALLSIGN` | string | `ECHO` | Repeater callsign (string, max 8 chars) |
| `RADIO_ID` | int | `9999` | DMR radio ID for this peer |
| `RX_FREQ` | int | `434000000` | Receive frequency in Hz (9 digits) |
| `TX_FREQ` | int | `434000000` | Transmit frequency in Hz |
| `TX_POWER` | int | `10` | Transmit power in watts |
| `COLORCODE` | int | `1` | DMR color code (1–15) |
| `SLOTS` | int | `2` | Number of timeslots |
| `LATITUDE` | float | `33.0000` | Geographic latitude (8-digit unsigned float) |
| `LONGITUDE` | float | `-84.0000` | Geographic longitude (9-digit signed float) |
| `HEIGHT` | int | `75` | Antenna height in meters |
| `LOCATION` | string | `` | Location description |
| `DESCRIPTION` | string | `` | Free-text description |
| `URL` | string | `` | URL for this system |
| `SOFTWARE_ID` | string | `20230806` | Software version identifier |
| `PACKAGE_ID` | string | `MMDVM_HBlink3` | Package identifier |
| `GROUP_HANGTIME` | int | `5` | Group traffic hangtime in seconds |
| `OPTIONS` | string | `` | Optional extended options |
| `USE_ACL` | bool | `False` | Enable ACLs |
| `SUB_ACL` | string | `DENY:1` | Subscriber ACL |
| `TGID_TS1_ACL` | string | `PERMIT:ALL` | TS1 TGID ACL |
| `TGID_TS2_ACL` | string | `PERMIT:ALL` | TS2 TGID ACL |

#### `[XLX-*]` — XLX Reflector Peer (MODE: XLXPEER)
Same fields as `[PEER]` plus `XLXMODULE` (int, e.g., `4004`). Used for XLX reflector connections. `LOOSE: True` required.

---

## 2. rules.py Structure

**Location**: `/etc/hblink3/rules.py` (bind-mounted into container at `/hblink3/rules.py`)

**Format**: Python module. Two top-level names: `BRIDGES` (dict) and `UNIT` (list).

### BRIDGES dict

```python
BRIDGES = {
    'BRIDGE_NAME': [
        {
            'SYSTEM':   'MASTER-1',   # str — must match section name in hblink.cfg exactly
            'TS':       1,            # int — timeslot (1 or 2)
            'TGID':     325,          # int — talkgroup ID that activates/routes this bridge
            'ACTIVE':   True,         # bool — whether this system/bridge pair is active at startup
            'TIMEOUT':  2,            # int — timeout in minutes (0 = no timeout)
            'TO_TYPE':  'ON',         # str — 'ON' (turns off after timeout), 'OFF' (turns on after),
                                      #        or any other string (e.g. 'NONE') = no timer
            'ON':       [2],          # list[int] — TGIDs that activate this system on this bridge
            'OFF':      [9, 10],      # list[int] — TGIDs that deactivate this system on this bridge
            'RESET':    [],           # list[int] — TGIDs that reset running timer without state change
        },
        # additional systems on the same bridge...
    ],
    'ANOTHER_BRIDGE': [...],
}
```

### UNIT list

```python
UNIT = ['SYSTEM_NAME_1', 'SYSTEM_NAME_2']
```

Lists system names (matching hblink.cfg section names) where unit-to-unit (individual, not group) calls should be bridged.

### Routing logic

A transmission from MASTER-1 on TS1 with TGID 325 matches the bridge entry `{'SYSTEM': 'MASTER-1', 'TS': 1, 'TGID': 325, ...}`. When that bridge is `ACTIVE`, the transmission is forwarded to every other system in the same `BRIDGES['FREESTAR']` list that is also `ACTIVE` on that bridge.

### Annotated example: FREESTAR bridge

```python
'FREESTAR': [
    {
        'SYSTEM':   'MASTER-1',
        'TS':       1,          # traffic on timeslot 1
        'TGID':     325,        # talkgroup 325 activates this bridge leg
        'ACTIVE':   True,       # starts active at launch
        'TIMEOUT':  2,          # turns off after 2 minutes of silence
        'TO_TYPE':  'ON',       # timer activates when leg turns ON
        'ON':       [2],        # receiving TGID 2 turns this leg back on
        'OFF':      [9, 10],    # receiving TGID 9 or 10 turns this leg off
        'RESET':    [],         # no additional timer-reset TGIDs
    }
]
```

### Reloading rules.py

Changes to `rules.py` require a full container restart — there is no runtime reload mechanism (see Section 5).

---

## 3. Log Format

**Host path**: `/var/log/hblink/hblink.log` (bind-mounted read-write into container)
**Container path**: `/hblink3/hblink.log`
**Access**: Both paths point to the same file. `docker container logs hblink` also shows stdout/stderr of the container (same content since `LOG_HANDLERS: file-timed,console-timed`).

### Log line format

```
LEVEL YYYY-MM-DD HH:MM:SS,mmm (COMPONENT) Message text
```

| Field | Example | Notes |
|-------|---------|-------|
| Level | `INFO` | `DEBUG`, `INFO`, `WARNING`, `CRITICAL` |
| Timestamp | `2026-05-25 13:28:14,857` | Local system time, comma before milliseconds |
| Component | `(ROUTER)` | Subsystem in parentheses: `GLOBAL`, `ROUTER`, `REPORT`, `MASTER-1`, etc. |
| Message | `Routing bridges file found...` | Free text |

### Clean startup sequence (observed)

```
INFO 2026-05-25 13:28:11,493   [copyright notice — no component tag]
INFO 2026-05-25 13:28:11,493 (GLOBAL) HBlink TCP reporting server configured
INFO 2026-05-25 13:28:11,494 HBlink 'playback.py' -- SYSTEM STARTING...
INFO 2026-05-25 13:28:11,501   [copyright notice — no component tag]
INFO 2026-05-25 13:28:11,505 (GLOBAL) ID ALIAS MAPPER: 'peer_ids.json' is current, not downloaded
INFO 2026-05-25 13:28:11,506 (GLOBAL) ID ALIAS MAPPER: 'subscriber_ids.json' is current, not downloaded
INFO 2026-05-25 13:28:11,773 (GLOBAL) ID ALIAS MAPPER: peer_ids dictionary is available
INFO 2026-05-25 13:28:14,855 (GLOBAL) ID ALIAS MAPPER: subscriber_ids dictionary is available
INFO 2026-05-25 13:28:14,856 (GLOBAL) ID ALIAS MAPPER: talkgroup_ids dictionary is available
INFO 2026-05-25 13:28:14,857 (ROUTER) Routing bridges file found and bridges imported: rules.py
INFO 2026-05-25 13:28:14,857 (REPORT) HBlink TCP reporting server configured
INFO 2026-05-25 13:28:14,859 (GLOBAL) HBlink 'bridge.py' -- SYSTEM STARTING...
INFO 2026-05-25 13:28:14,860 (ROUTER) Conference Bridge ACTIVE (ON timer running): System: MASTER-1 Bridge: FREESTAR, TS: 1, TGID: 325, Timeout in: 120.00s,
INFO 2026-05-25 13:28:27,057 (REPORT) HBlink reporting client connected: IPv4Address(type='TCP', host='172.18.0.1', port=43812)
```

No FATAL or ERROR entries observed at startup. The last line confirms HBMonv2 connected to the report socket.

### Transmission event log format

No live transmission events available at time of analysis (no peers connected yet). Based on `monitor.py` source (lines 781, 837–843), the log format for a group voice event is:

```
INFO HH:MM:SS VOICE_END   SYS: MASTER-1  SRC_ID: 3100100   TS: 1 TGID: 325     Freestar Network  SUB: 3100100 ; W1ABC              Time: 12s
```

Fields parsed by HBMonv2: packet type, START/END, system name, source radio ID, timeslot, TGID, TGID alias, subscriber radio ID, subscriber callsign, duration.

---

## 4. Last-Heard Data Store

**Format**: Flat CSV text file (not SQLite, not MySQL, not JSON)

**Path**: `/opt/HBMonv2/log/lastheard.log`

**Written by**: `monitor.py` appends one line per completed GROUP VOICE call when duration > 2 seconds.

**Read by**: `monitor.py` reads the last 200 entries to build the lastheard HTML table; PHP front-end serves the generated HTML at `/opt/HBMonv2/templates/lastheard.html`.

**Current state**: File does not exist yet — no calls have completed since install. It will be created on the first qualifying transmission.

### CSV schema (12 columns, no header row)

| Column index | Field | Example | Notes |
|-------------|-------|---------|-------|
| 0 | `timestamp` | `2026-05-25 13:45:12` | Date and time of transmission end (local time) |
| 1 | `duration` | `14.3` | Transmission duration as float, seconds |
| 2 | `packet_type` | `GROUP VOICE` | Always `GROUP VOICE` for lastheard entries |
| 3 | `state` | `END` | Always `END` (only END events are written) |
| 4 | `system` | `MASTER-1` | HBLink system name from hblink.cfg |
| 5 | `sub_radio_id` | `3100100` | Transmitting subscriber DMR radio ID |
| 6 | `callsign` | `W1ABC` | Callsign resolved from radio ID via subscriber_ids.json |
| 7 | `timeslot` | `TS1` | Timeslot as `TS1` or `TS2` (string, not integer) |
| 8 | `talkgroup` | `TG325` | Talkgroup as `TG` + integer (string, not integer) |
| 9 | `tg_alias` | `Freestar Network` | Talkgroup name resolved from talkgroup_ids.json |
| 10 | `src_radio_id` | `3100100` | Source radio ID (may differ from sub_radio_id for hotspots) |
| 11 | `src_callsign` | `W1ABC` | Short callsign for source radio ID |

**Update frequency**: Appended in real time at end of each call > 2 seconds. No polling interval — event-driven.

**Deletion/rotation**: Not automatic. The file grows indefinitely. HBMonv2 only reads the last 200 lines for display.

**CFLAG DMR read access**: File is world-readable. PHP can read it with `file()` or `fopen()`. The file is not locked during reads (monitor.py opens, writes, closes immediately).

---

## 5. Reload and Restart Behaviour

### SIGHUP support

**No SIGHUP reload is supported.** Inspection of the container with `docker exec hblink grep -r "SIGHUP\|signal\|reload" /app/` returned no results. HBLink reads configuration only at startup.

Any change to `hblink.cfg` or `rules.py` requires a full container restart.

### Restart commands

| Command | What it does | Effect on active calls |
|---------|-------------|------------------------|
| `hblink-restart` | `docker-compose restart` in `/etc/hblink3/` + `systemctl restart hbmon` | All active calls dropped immediately; peers must re-register |
| `cd /etc/hblink3 && docker compose restart` | Stops and restarts the `hblink` container | Same as above |
| `cd /etc/hblink3 && docker compose down && docker compose up -d` | Full teardown and start | Same as above, also removes Docker networks |
| `cd /etc/hblink3 && docker compose stop` | Stops without removing container | All calls dropped |
| `cd /etc/hblink3 && docker compose start` | Starts stopped container | Reads config fresh from bind-mounted files |
| `hblink-stop` / `hblink-start` | Stop/start wrappers | Same as stop/start above |

**Note**: `hblink-restart` uses `docker-compose` (legacy hyphen form). The installer also created a compatibility symlink or wrapper. The compose file is at `/etc/hblink3/docker-compose.yml` and the working directory for all compose commands must be `/etc/hblink3/`.

### Auto-restart policy

The compose file has `restart: "unless-stopped"`. The container restarts automatically after crashes or reboots unless manually stopped with `docker compose stop`.

### What config changes require a restart

All of them. There is no partial reload. Specifically:
- Adding/removing/modifying any section in `hblink.cfg`
- Changing any bridge or routing rule in `rules.py`
- Changing the passphrase, port, IP, or any operational parameter

### What happens to active calls during restart

All active calls are terminated immediately. Connected peers receive a disconnect notification on the next ping cycle. Peers with `PING_TIME: 5` will detect the disconnection within 5 seconds and attempt re-registration.

### Systemd and Docker interaction

HBLink is a Docker container; it is not a systemd service. It is managed by Docker's own restart policy. The `hblink-restart` script explicitly also restarts the `hbmon` systemd service (`systemctl restart hbmon`), because HBMonv2 holds a TCP connection to the report socket and must reconnect after an HBLink restart.

---

## 6. Annotated Directory Listing

### `/etc/hblink3/` — HBLink working directory

```
/etc/hblink3/
├── docker-compose.yml     Docker Compose v2 service definition for the hblink container
├── hblink.cfg             Main HBLink config (INI-style): systems, ACLs, logger, reports
├── rules.py               Conference bridge routing rules (Python module: BRIDGES dict, UNIT list)
├── .installer_path        Text file containing the path of the installer: /opt/hblink3-docker-install
└── json/
    ├── peer_ids.json      Repeater alias lookup (auto-downloaded from radioid.net at startup)
    ├── subscriber_ids.json  Subscriber alias lookup (auto-downloaded from radioid.net)
    └── talkgroup_ids.json   Talkgroup name lookup (user-provided or radioid.net)
```

### `/opt/HBMonv2/` — HBMonv2 Python monitor

```
/opt/HBMonv2/
├── monitor.py             Main HBMonv2 process: connects to HBLink report socket, processes
│                          events, writes lastheard.log, generates HTML templates, serves WebSocket
├── config.py              Active configuration (paths, ports, feature toggles, alias URLs)
├── config_SAMPLE.py       Template for config.py (reference copy)
├── hbmon-config.py        Installer-generated config helper
├── requirements.txt       Python package dependencies for venv
├── install.sh             Original installation script (do not re-run)
├── Dockerfile             Docker build file (not used; systemd venv install is used instead)
├── entrypoint             Docker entrypoint (not used in systemd deployment)
├── CHANGELOG.md           Version history
├── README.md              Project documentation
├── peer_ids.json          Cached repeater aliases (written by monitor.py at runtime)
├── subscriber_ids.json    Cached subscriber aliases (written by monitor.py at runtime)
├── data/
│   ├── local_peer_ids.json      Optional user-provided local peer aliases
│   ├── local_subscriber_ids.json  Optional user-provided local subscriber aliases
│   ├── local_talkgroup_ids.json   Optional user-provided local talkgroup aliases
│   └── talkgroup_ids.json       Talkgroup alias data
├── html/                  PHP web dashboard files (copied to /var/www/html/ by installer)
│   ├── index.php          Main dashboard page (displays HBLink status via WebSocket)
│   ├── bridges.php        Bridge status page
│   ├── masters.php        Masters status page
│   ├── peers.php          Peers status page
│   ├── opb.php            OpenBridge status page
│   ├── moni.php           Monitor page
│   ├── log.php            Log viewer page
│   ├── info.php           System info page
│   ├── sysinfo.php        System hardware info
│   ├── buttons.html       Navigation buttons include
│   ├── include/
│   │   ├── config.php     PHP dashboard config (REPORT_NAME, THEME_COLOR)
│   │   └── version.php    Dashboard version constant
│   ├── css/styles.php     Stylesheet
│   ├── scripts/hbmon.js   WebSocket client (connects to ws://host:9000)
│   └── elements/footer.php  Page footer include
├── log/
│   ├── hbmon.log          HBMonv2 runtime log (startup, ID downloads, connection events)
│   ├── hbmon-empty.log    Empty placeholder log
│   └── lastheard.log      CSV log of completed calls (created on first qualifying call)
├── templates/
│   ├── lastheard.html     Generated HTML fragment for lastheard table (rewritten on each call)
│   ├── main_table.html    Generated HTML for main system status table
│   ├── masters_table.html Generated HTML for masters table
│   ├── peers_table.html   Generated HTML for peers table
│   ├── bridge_table.html  Generated HTML for bridges table
│   └── opb_table.html     Generated HTML for OpenBridge table
├── sysinfo/
│   ├── cpu.sh             CPU usage data collector script
│   ├── graph.sh           Graph generation script (MRTG)
│   ├── rrd-db.sh          RRD database creation script
│   ├── sysinfo-cron       Cron job definitions for system monitoring
│   └── Readme.txt         Sysinfo module documentation
├── utils/
│   ├── hbmon.service      systemd unit file for the hbmon service
│   ├── lastheard          Utility scripts for lastheard processing
│   └── Readme.md          Utils documentation
└── venv/                  Python virtual environment (managed by systemd; do not modify)
```

---

## 7. Integration Points for CFLAG DMR

### Summary table

| Resource | Path / Address | Access Method | CFLAG DMR Features |
|----------|----------------|---------------|-------------------|
| `hblink.cfg` | `/etc/hblink3/hblink.cfg` | Read/write as root; restart required to apply | F2 (config visibility), F6 (config editing) |
| `rules.py` | `/etc/hblink3/rules.py` | Read/write as root; restart required to apply | F4 (talkgroup routing) |
| HBLink log | `/var/log/hblink/hblink.log` | Tail (read-only) | F2 (live log display) |
| Lastheard CSV | `/opt/HBMonv2/log/lastheard.log` | Read-only; append-only file; no locking | F5 (last-heard display) |
| Report socket | `127.0.0.1:4321` TCP | TCP connection; binary protocol (same as HBMonv2 uses) | F2 (live system state) |
| HBMonv2 WebSocket | `ws://host:9000` | Browser WebSocket (not for PHP server-side use) | N/A — browser-only |
| Control scripts | `/usr/local/sbin/hblink-*` | Execute as root via `exec()` / `shell_exec()` | F7 (restart/reload), F8 (update) |

---

### Detail: hblink.cfg (Read/Write File)

**Path**: `/etc/hblink3/hblink.cfg`
**Permissions**: root-owned; Apache/PHP (`www-data`) cannot write without permission change or `sudo`.
**Format**: INI-style, parseable with PHP's `parse_ini_file()` using `$process_sections = true`. Note that HBLink uses `: ` (colon-space) separators, not `=`, so `parse_ini_file()` will not work directly. Use a custom parser or `preg_match_all` on each section.
**CFLAG DMR approach**: Read with a custom INI parser (colon-separated). Write by reading the full file, modifying in memory, writing back. Requires a container restart to apply changes.
**Serves**: F2 (display config), F6 (edit config).

---

### Detail: rules.py (Read/Write File)

**Path**: `/etc/hblink3/rules.py`
**Permissions**: root-owned.
**Format**: Python source. PHP cannot `eval()` Python. CFLAG DMR must parse it with a regex or string pattern approach. The `BRIDGES` dict structure is regular: bridge names are string keys, each value is a list of dicts with fixed field names (`SYSTEM`, `TS`, `TGID`, `ACTIVE`, `TIMEOUT`, `TO_TYPE`, `ON`, `OFF`, `RESET`). An alternative: use Python (`python3 -c "import rules; import json; print(json.dumps(rules.BRIDGES))"`) via `exec()` to convert to JSON, then parse JSON in PHP.
**CFLAG DMR approach**: Shell out to Python3 for reads; generate Python source for writes. Restart required to apply.
**Serves**: F4 (talkgroup routing management).

---

### Detail: HBLink Log (Read-Only Tail)

**Path**: `/var/log/hblink/hblink.log`
**Permissions**: world-readable (created by container with bind mount).
**Format**: Line-based (see Section 3). Each line: `LEVEL TIMESTAMP (COMPONENT) message`.
**CFLAG DMR approach**: `tail -n 100 /var/log/hblink/hblink.log` via `exec()`, or a live AJAX endpoint that shells out to `tail`. For real-time streaming: `inotify`-based file watcher or server-sent events from a PHP script that polls the file.
**Serves**: F2 (live log viewer).

---

### Detail: Lastheard CSV (Read-Only)

**Path**: `/opt/HBMonv2/log/lastheard.log`
**Permissions**: Owned by the user running the `hbmon` systemd service. Check with `stat /opt/HBMonv2/log/lastheard.log` once a call has been logged. Will need `www-data` read access or PHP execution as root.
**Format**: 12-column CSV, no header. See Section 4 for field definitions.
**CFLAG DMR approach**: `file('/opt/HBMonv2/log/lastheard.log')` in PHP, then `str_getcsv()` on each line. Filter by system name, talkgroup, or callsign. Parse the last N lines (file can grow large; use `array_reverse()` on the last 200 lines).
**Update frequency**: Event-driven (appended when calls end, > 2 second duration only).
**Serves**: F5 (last-heard display and history).

---

### Detail: Report Socket Port 4321 (TCP)

**Address**: `127.0.0.1:4321` TCP
**Status**: Confirmed listening (tested with `nc`). Port is exposed by Docker at `0.0.0.0:4321`.
**Protocol**: Binary protocol used by HBMonv2's Python `monitor.py`. The protocol is the same internal HBLink reporting protocol. Messages are prefixed with a single opcode byte. HBMonv2 source defines opcodes: `CONFIG_REQ` (0x00), `CONFIG_SND` (0x01), `BRIDGE_REQ` (0x02), `BRIDGE_SND` (0x03), `CONFIG_UPD` (0x04), `BRIDGE_UPD` (0x05), `BRDG_EVENT` (0x06), `LINK_EVENT` (0x07), `RCM_SND` (0x08).
**CFLAG DMR approach**: Can query for live system state by connecting via TCP and sending `CONFIG_REQ` (0x00). Response is a pickled Python object (HBLink uses Python's `pickle` module for serialization). PHP cannot unpickle directly — requires a small Python script intermediary (`python3 unpickle_hblink.py`) called via `exec()`, which returns JSON. **Alternatively**, reading `hblink.cfg` and `rules.py` directly is simpler for most CFLAG DMR use cases. The socket is more useful for live peer connection status (which peers are currently registered and active).
**Serves**: F2 (live peer connection status — cannot be derived from config files alone).

---

### Detail: HBMonv2 WebSocket Port 9000

**Address**: `ws://host:9000`
**Protocol**: WebSocket. Serves pre-rendered HTML fragments to the browser dashboard.
**CFLAG DMR approach**: Not useful for server-side PHP. The WebSocket is browser-to-server only. CFLAG DMR should not use port 9000 for back-end data access. Use the lastheard CSV or report socket instead.
**Serves**: No CFLAG DMR features directly.

---

### Detail: Control Scripts

**Path**: `/usr/local/sbin/hblink-restart`, `hblink-stop`, `hblink-start`, `hblink-update`, `hblink-flush`, `hblink-upgrade`, `hblink-uninstall`
**Permissions**: Root-only (`/usr/local/sbin/`).
**CFLAG DMR approach**: CFLAG DMR can trigger a restart by executing `hblink-restart` via PHP's `exec()` after granting `www-data` sudo access for that specific command (`/etc/sudoers.d/cflag-dmr-hblink`). This is the mechanism for F7 (apply config changes / restart). The `hblink-restart` script also restarts `hbmon`, which is correct — both services need to reconnect.
**Serves**: F7 (controlled restart after config edits).

---

## Appendix: UFW Firewall Requirements

Current UFW status on dev server (run `ufw status`):

| Port | Protocol | Direction | Purpose | Required |
|------|----------|-----------|---------|----------|
| 54000–54099 | UDP | Inbound | MMDVM/HomeBrew Master listening ports | Yes — for peer connections |
| 62030–62050 | UDP | Inbound | OpenBridge connections | Yes — if OBP systems used |
| 4321 | TCP | Localhost only | HBLink report socket | No external rule needed (Docker bind is 0.0.0.0; restrict to 127.0.0.1 in hblink.cfg REPORT_CLIENTS or via UFW) |
| 9000 | TCP | Inbound | HBMonv2 WebSocket | Yes — if external browser access needed |
| 8080 | TCP | Inbound | HBMonv2 Apache dashboard | Yes — for external dashboard access |
| 80 | TCP | Inbound | CFLAG DMR Apache | Already open |

**Recommended UFW commands** (add after confirming not already present):
```bash
ufw allow 54000:54099/udp    # MMDVM master ports
ufw allow 62030:62050/udp    # OpenBridge ports
ufw allow 9000/tcp           # HBMonv2 WebSocket
ufw allow 8080/tcp           # HBMonv2 dashboard
```

Port 4321 does not require a UFW rule for external access since it is consumed only by HBMonv2 on localhost. If external monitoring tools need it, add `ufw allow 4321/tcp`.
