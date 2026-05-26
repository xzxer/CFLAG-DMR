# Quickstart: Network Config & Peer Management (F7)

## Prerequisites

- Logged in as a system_admin user
- At least one approved device in the database (from F5)
- `HBLINK_CONFIG_PATH` set in `.env` pointing to the HBLink config file path

## Scenario 1.1 — First-Time Master Settings Setup

1. Navigate to `/admin/config/master.php`
2. Confirm form is pre-populated with defaults (0.0.0.0, port 62031, passphrase "passphrase")
3. Enter a passphrase of exactly 15 characters → save → confirm success
4. Enter a passphrase of 16 characters → confirm validation error shown, no save

## Scenario 1.2 — Generate Config

1. Navigate to `/admin/config/`
2. Click "Generate Config"
3. Confirm success message with timestamp
4. Confirm the generated config file exists at `HBLINK_CONFIG_PATH`
5. Open the file and confirm REG_ACL contains the DMR ID of the approved test device

## Scenario 1.3 — Verify REG_ACL Accuracy

1. Deny a previously approved device
2. Generate config again
3. Open the config file and confirm the denied device's DMR ID is NOT in REG_ACL
4. Confirm the approved device's DMR ID IS still in REG_ACL

## Scenario 2.1 — OpenBridge Connection

1. Navigate to `/admin/config/openbridge.php`
2. Add an OpenBridge entry with valid fields
3. Generate config
4. Open the config file and confirm an `[OPENBRIDGE-name]` stanza appears
5. Disable the OpenBridge entry
6. Generate config again
7. Confirm the `[OPENBRIDGE-name]` stanza is gone

## Scenario 3.1 — Generation History & Diff

1. Generate config (baseline)
2. Approve a new device
3. Generate config again
4. Navigate to `/admin/config/history.php`
5. Confirm two history entries appear, with the second marked "Changed"
6. Click the diff link for the second entry
7. Confirm the new DMR ID appears as an added line in the diff

## Scenario 3.2 — No-Change Generation

1. Generate config twice without any DB changes between them
2. Navigate to history
3. Confirm the second entry is marked "No change"

## Scenario W.1 — Passphrase Boundary

1. Try to save a master passphrase of 16 characters → confirm error
2. Try to save a passphrase of 15 characters → confirm success
3. Try to save an empty passphrase → confirm error

## Scenario W.2 — Non-Admin Access

1. Log in as a regular user (not system_admin)
2. Attempt to navigate to `/admin/config/` directly
3. Confirm redirect to `/` (403 Forbidden, then redirected)
