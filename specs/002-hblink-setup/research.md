# Research: HBLink + HBMonv2 Setup and Analysis — 002-hblink-setup

## Apache Coexistence Strategy

### Decision
Separate the two applications by port. CFLAG DMR keeps port 80. HBMonv2 is moved to port 8080 by reconfiguring the HBMonv2 Apache virtual host after the installer runs.

### Rationale
The installer creates its own Apache virtual host configuration for HBMonv2. On a dev server accessed by raw IP (no DNS), virtual-host-by-hostname is not practical. Path-based aliasing under the same port is possible but requires understanding exactly how HBMonv2 serves its assets and WebSocket endpoint — which we learn during analysis. Port separation is unambiguous, immediately testable, and reversible.

### Implementation approach
1. Before running the installer: capture the full list of Apache virtual host configs in `/etc/apache2/sites-available/` and `/etc/apache2/sites-enabled/`.
2. Run the installer.
3. Identify which new Apache config the installer created for HBMonv2.
4. Edit that config to listen on port 8080 instead of port 80 (add `Listen 8080` in `ports.conf` if needed).
5. Ensure the CFLAG DMR virtual host is re-enabled and serving on port 80.
6. Reload Apache.
7. Verify both apps respond on their respective ports.

### Alternatives considered
- **Path-based alias under port 80**: Cleaner long-term, but requires understanding HBMonv2's internal URL structure before we've analysed it. Risk of broken asset paths or WebSocket URL mismatches. Deferred to after analysis.
- **Virtual host by hostname**: Requires DNS or hosts file entries on every client device. Not practical for a raw-IP dev server.
- **Run HBMonv2 on a completely separate Apache instance**: Adds unnecessary complexity. One Apache process can handle both via virtual hosts.

---

## Pre-Install Requirements and Risk Mitigation

### Decision
Run a pre-install checklist and backup before executing the installer. Treat the installer as destructive even on a partially configured server.

### Rationale
The installer README explicitly says "destructive installer" and "not designed to be used on an existing machine that has other software on it." Our dev server does have existing software (CFLAG DMR on Apache). Mitigating this requires a backup-first discipline and a post-install diff to catch any Apache config overwrites.

### Pre-install checklist
- [ ] Verify the OS is Debian 11/12/13 or Ubuntu 22.04/24.04 LTS (`cat /etc/os-release`)
- [ ] Verify the system is up to date (`apt update && apt list --upgradable`)
- [ ] Verify Git is installed (`git --version`)
- [ ] Back up Apache config: `cp -r /etc/apache2 /root/apache2-backup-$(date +%Y%m%d)`
- [ ] Back up CFLAG DMR: `cp -r /opt/cflag-dmr /root/cflag-dmr-backup-$(date +%Y%m%d)`
- [ ] Note current Apache virtual host state: `apache2ctl -S`
- [ ] Note current listening ports: `ss -tlnp | grep apache`
- [ ] Run the installer as root from a directory outside `/opt/cflag-dmr`

### Post-install diff
After the installer completes, compare Apache config before and after:
```bash
diff -r /root/apache2-backup-YYYYMMDD /etc/apache2/
```
This reveals exactly what the installer changed. Restore or merge as needed.

### Alternatives considered
- **Snapshot the VM before installing**: Best practice in production; acceptable on this dev server if the hosting provider supports it. Recommended as belt-and-suspenders alongside the file backup.
- **Test on a clean VM first**: Ideal but requires a second server. Acceptable to skip if the pre-install backup is solid.

---

## Analysis Methodology

### Decision
Systematic file-by-file inspection with live verification where possible. Each analysis topic gets its own section in `specs/002-hblink-setup/analysis.md`. Findings are written as observed facts, not paraphrased documentation.

### Rationale
The analysis must reflect what is actually installed on this specific server, not what upstream docs describe. Config format quirks, local customisations by the installer, and platform-specific paths can all differ from documentation. Reading the actual files is authoritative.

### Topic coverage and inspection method

| Topic | How to inspect |
|-------|---------------|
| `hblink.cfg` structure | `cat /etc/hblink3/hblink.cfg` — document every `[SECTION]` header and each `KEY: VALUE` line with its data type |
| `rules.py` structure | `cat /etc/hblink3/rules.py` — document Python dict/list structure, field names, example rule |
| Log format | `cat /var/log/hblink/hblink.log` and `docker container logs hblink` — capture startup sequence and any transmission event entries |
| HBMonv2 lastheard DB | `find /opt/HBMonv2 -name "*.db" -o -name "*.sqlite" -o -name "*.json"` — identify format; if SQLite, run `.schema` on it |
| Reload mechanism | Read `docker-compose.yml` and HBLink source in container (`docker exec hblink cat /app/hblink.py` or equivalent) — determine whether SIGHUP or a full restart is needed |
| Directory structure | `find /etc/hblink3 /opt/HBMonv2 -not -path '*/venv/*' -not -path '*/__pycache__/*'` — annotate every file and directory |
| Integration points | Review HBLink report socket (port 4321), log file, config files, HBMonv2 DB — document what CFLAG DMR can read/write |

### Unknowns at plan time (to be resolved during analysis)

| Unknown | Why it matters |
|---------|---------------|
| HBMonv2 lastheard DB format (SQLite vs. flat file vs. MySQL) | Determines how F5 (last-heard display) reads the data |
| Whether HBLink supports SIGHUP reload vs. full restart | Determines F7 (controlled reload) implementation complexity |
| HBLink report socket protocol (port 4321) | May expose structured data CFLAG DMR can query instead of parsing log files |
| HBMonv2 internal URL structure | Needed to evaluate path-based Apache coexistence if port separation is later revised |
