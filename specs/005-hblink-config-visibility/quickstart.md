# Quickstart / Test Scenarios: F8 — HBLink Config Visibility

**Date**: 2026-05-25 | **Branch**: `005-hblink-config-view`

All scenarios require the dev server (dmrdev.cflag.net) to be running with a `system_admin` account logged in. Use the seeded `admin` account.

---

## Prerequisites

- Migration 006 applied: HBLink system_settings keys seeded
- `/opt/hblink3/hblink.cfg` exists and is readable by `www-data`
  - If not: `touch /opt/hblink3/hblink.cfg && chown root:www-data /opt/hblink3/hblink.cfg && chmod 644 /opt/hblink3/hblink.cfg`
- `/opt/hblink3/rules.py` exists and is readable by `www-data`

---

## US1: Config File Viewer

### Scenario 1.1 — Basic render
**Setup**: hblink.cfg contains at least one section and one key.
**Steps**: Log in as system_admin → navigate to `/admin/hblink/config.php`
**Expected**:
- Page loads without error
- File contents render in a styled, line-numbered code block
- Line 1 is numbered "1"
- Last-modified timestamp shown below the header

### Scenario 1.2 — Passphrase masking
**Setup**: hblink.cfg contains a line like `PASSPHRASE = mysecretpass`
**Steps**: Load `/admin/hblink/config.php`
**Expected**:
- The PASSPHRASE line shows `••••••••` in place of the value
- A "Show" button is visible next to the masked value
- Clicking "Show" reveals `mysecretpass` without a page reload
- Clicking "Hide" (or clicking again) re-masks the value

### Scenario 1.3 — Comment line with passphrase text
**Setup**: hblink.cfg contains a comment like `; PASSPHRASE = example`
**Steps**: Load `/admin/hblink/config.php`
**Expected**: The comment line renders as-is — no masking applied to comment lines

### Scenario 1.4 — Config-drift warning
**Setup**: Modify hblink.cfg (`touch /opt/hblink3/hblink.cfg`) after HBLink started
**Steps**: Load `/admin/hblink/config.php`
**Expected**: A visible warning banner states the config has been changed since HBLink last started

### Scenario 1.5 — File not found
**Setup**: Temporarily set `hblink_cfg_path` in system_settings to a non-existent path
**Steps**: Load `/admin/hblink/config.php`
**Expected**: A user-friendly error message is shown — no PHP warning or stack trace visible

### Scenario 1.6 — Path outside allowlist
**Setup**: Set `hblink_cfg_path` in system_settings to `/etc/passwd`
**Steps**: Load `/admin/hblink/config.php`
**Expected**: Error message shown: path is outside permitted directory — `/etc/passwd` content is NOT displayed

### Scenario 1.7 — Non-admin access
**Steps**: Log in as a user with `admin` role (not `system_admin`) → navigate to `/admin/hblink/config.php`
**Expected**: 403 redirect — page is not accessible

---

## US2: Rules File Viewer

### Scenario 2.1 — Basic render
**Setup**: rules.py contains at least a few lines of Python routing rules
**Steps**: Log in as system_admin → navigate to `/admin/hblink/rules.php`
**Expected**:
- Page loads without error
- File contents render in a line-numbered code block
- Last-modified timestamp shown
- No masking (rules.py has no sensitive values)

### Scenario 2.2 — File not found
**Setup**: Temporarily set `hblink_rules_path` to a non-existent path
**Steps**: Load `/admin/hblink/rules.php`
**Expected**: User-friendly error message — no PHP error shown

---

## US3: Process Status Page

### Scenario 3.1 — HBLink running
**Setup**: HBLink process is running (`pgrep -f hblink.py` returns a PID)
**Steps**: Log in as system_admin → navigate to `/admin/hblink/status.php`
**Expected**:
- "Running" indicator shown (green badge or similar)
- PID shown (matches `pgrep -f hblink.py` output)
- Uptime shown in human-readable form (e.g., "2 hours, 14 minutes")

### Scenario 3.2 — HBLink stopped
**Setup**: Ensure no `hblink.py` process is running
**Steps**: Load `/admin/hblink/status.php`
**Expected**:
- "Stopped" indicator shown
- PID and uptime fields absent (not shown as "0" or blank)

### Scenario 3.3 — Config-drift warning on status page
**Setup**: `touch /opt/hblink3/hblink.cfg` after HBLink started
**Steps**: Load `/admin/hblink/status.php`
**Expected**: "Config modified since start" warning banner shown

---

## US4: Dashboard Status Card

### Scenario 4.1 — Running state on dashboard
**Setup**: HBLink process is running
**Steps**: Log in as system_admin → load `/admin/`
**Expected**:
- HBLink status card visible on dashboard
- Shows "Running" indicator and uptime
- Card contains links to `/admin/hblink/config.php`, `/admin/hblink/rules.php`, `/admin/hblink/status.php`

### Scenario 4.2 — Stopped state on dashboard
**Setup**: HBLink process not running
**Steps**: Load `/admin/`
**Expected**: HBLink card shows "Stopped" indicator

### Scenario 4.3 — Config-drift warning on dashboard
**Setup**: Config modified after HBLink started
**Steps**: Load `/admin/`
**Expected**: Dashboard card shows "Config drift" warning

---

## Mobile Viewport Checks

After any scenario above, resize the browser to a mobile width (375px):
- Code blocks scroll horizontally — no horizontal page overflow
- "Show" buttons remain tappable (≥ 44px touch target)
- Warning banners remain readable and not truncated

---

## Quick curl Access Check

```bash
# Should redirect to login (not show 403 or PHP error)
curl -si https://dmrdev.cflag.net/admin/hblink/config.php | head -5

# Should show 403 when logged in as non-system_admin
# (test via browser with a non-system_admin account)
```
