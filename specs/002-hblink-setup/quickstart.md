# Quickstart: HBLink + HBMonv2 Setup — 002-hblink-setup

Step-by-step guide for executing the install, resolving Apache coexistence, and completing the analysis. Run all commands on the dev server as root unless noted otherwise.

---

## Prerequisites

Verify before starting:

```bash
cat /etc/os-release          # Must be Debian 11/12/13 or Ubuntu 22.04/24.04
git --version                # Must be installed
apt update                   # System must be up to date
```

---

## Step 1: Pre-Install Backup

```bash
# Snapshot Apache config
cp -r /etc/apache2 /root/apache2-backup-$(date +%Y%m%d)

# Snapshot CFLAG DMR project
cp -r /opt/cflag-dmr /root/cflag-dmr-backup-$(date +%Y%m%d)

# Note current Apache state
apache2ctl -S > /root/apache2-state-before.txt
ss -tlnp | grep apache >> /root/apache2-state-before.txt

echo "Backup complete."
```

---

## Step 2: Run the HBLink Installer

```bash
cd /opt
git clone https://github.com/ShaYmez/hblink3-docker-install
cd hblink3-docker-install
./hblink3-docker-install.sh
```

Follow all prompts. Accept kernel updates if prompted. When the post-install menu appears, exit without making further changes — configuration review comes next.

---

## Step 3: Verify HBLink is Running

```bash
cd /etc/hblink3
docker compose ps                          # hblink container should show as running
docker container logs hblink | tail -50    # Check for fatal errors
cat /var/log/hblink/hblink.log | tail -50  # Alternative log view
```

Expected: container listed as `Up`, log shows startup sequence with no FATAL or ERROR lines.

---

## Step 4: Verify HBMonv2 is Running

```bash
systemctl status hbmon
```

Expected: `active (running)`.

---

## Step 5: Diff Apache Config

```bash
diff -r /root/apache2-backup-$(date +%Y%m%d) /etc/apache2/
```

Identify which files the installer added or changed. Note the virtual host config the installer created for HBMonv2 — you will edit it in the next step.

---

## Step 6: Restore Apache Coexistence

HBMonv2 moves to port 8080. CFLAG DMR reclaims port 80.

```bash
# Add port 8080 to Apache listening ports
echo "Listen 8080" >> /etc/apache2/ports.conf

# Edit the HBMonv2 vhost to use port 8080
# (replace hbmonv2.conf with the actual filename from the diff above)
nano /etc/apache2/sites-available/hbmonv2.conf
# Change: <VirtualHost *:80>
# To:     <VirtualHost *:8080>

# Re-enable CFLAG DMR vhost if it was disabled by the installer
# Check what exists first:
ls /etc/apache2/sites-available/
ls /etc/apache2/sites-enabled/

# If CFLAG DMR vhost was removed, restore it:
cp /root/apache2-backup-$(date +%Y%m%d)/sites-available/cflag-dmr.conf \
   /etc/apache2/sites-available/cflag-dmr.conf
a2ensite cflag-dmr.conf

# Reload Apache
apache2ctl configtest   # Must say "Syntax OK"
systemctl reload apache2
```

---

## Step 7: Verify Both Apps Load in a Browser

- CFLAG DMR: `http://149.28.97.128/` — should load the existing CFLAG DMR page
- HBMonv2: `http://149.28.97.128:8080/` — should load the HBMonv2 dashboard

---

## Step 8: Run the Analysis

Work through each topic in the analysis document template. For each section, run the inspection command, capture the output, and write it up.

```bash
# Config file
cat /etc/hblink3/hblink.cfg

# Rules file
cat /etc/hblink3/rules.py

# Log
cat /var/log/hblink/hblink.log

# Directory structure (excluding venv and pycache)
find /etc/hblink3 /opt/HBMonv2 \
  -not -path '*/venv/*' \
  -not -path '*/__pycache__/*' \
  | sort

# HBMonv2 lastheard database
find /opt/HBMonv2 -name "*.db" -o -name "*.sqlite" -o -name "*.json" 2>/dev/null
# If SQLite found:
# sqlite3 /path/to/lastheard.db ".schema"
# sqlite3 /path/to/lastheard.db "SELECT * FROM lastheard LIMIT 5;"

# Docker compose file
cat /etc/hblink3/docker-compose.yml
```

Write findings into `specs/002-hblink-setup/analysis.md` (created during tasks execution).

---

## Step 9: Verify Reboot Persistence

```bash
reboot
# After reboot:
docker compose -f /etc/hblink3/docker-compose.yml ps   # HBLink still running
systemctl status hbmon                                   # HBMonv2 still running
curl -s -o /dev/null -w "%{http_code}" http://localhost/ # CFLAG DMR: expect 200
```

---

## Step 10: Commit the Analysis

```bash
cd /opt/cflag-dmr
git add specs/002-hblink-setup/analysis.md
git commit -m "Add HBLink structure analysis"
```
