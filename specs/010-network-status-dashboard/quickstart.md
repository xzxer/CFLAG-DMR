# Quickstart: Network Status Dashboard (F10)

## Prerequisites

- Logged in as any user (US1/US2 viewable by all authenticated users)
- At least one entry in the lastheard log from within the last 30 minutes (for active peers display)
- Logged in as system_admin for US3 admin sections

## Scenario 1.1 — Basic Status Page Load

1. Log in as a regular user
2. Navigate to `/network-status.php`
3. Confirm server state section shows "Running" or "Stopped" with uptime
4. Confirm no IP addresses or raw connection metadata are visible
5. Confirm no admin control links are visible

## Scenario 1.2 — Recently Active Devices

1. Ensure the lastheard log has entries from within the last 30 minutes
2. Navigate to `/network-status.php`
3. Confirm "Recently Active Devices" section shows DMR IDs and callsigns
4. Confirm devices with recent lastheard entries appear; devices silent for >30 minutes do not appear

## Scenario 1.3 — Server Stopped State

1. Stop HBLink (or set PID path to non-existent file)
2. Navigate to `/network-status.php`
3. Confirm server state shows "Stopped"
4. Confirm recently active section still loads (from log history)

## Scenario 2.1 — Recent Activity Feed

1. Navigate to `/network-status.php`
2. Confirm activity feed shows up to 10 most recent lastheard entries
3. Confirm each entry shows callsign, talkgroup name, and time
4. If no lastheard entries exist, confirm "No recent activity" message appears

## Scenario 3.1 — Admin View (system_admin)

1. Log in as system_admin
2. Navigate to `/network-status.php`
3. Confirm "Server Controls" section is visible with links to admin/hblink pages
4. Confirm config drift warning appears if `config_drifted` is true
5. Confirm process uptime and status are shown with full detail

## Scenario 3.2 — Config Drift Warning

1. As system_admin, navigate to `/network-status.php`
2. If HBLink is running and the config file was modified after HBLink started, confirm drift warning is visible
3. If no drift, confirm no warning is shown

## Scenario W.1 — Non-Admin Cannot See Admin Sections

1. Log in as a regular user
2. Navigate to `/network-status.php`
3. Confirm no "Server Controls" section is shown
4. Confirm no drift warning is shown (admin-only)
5. Confirm no IP addresses visible in any section

## Scenario W.2 — Unauthenticated Access

1. Log out
2. Attempt to navigate to `/network-status.php`
3. Confirm redirect to `/login.php`
