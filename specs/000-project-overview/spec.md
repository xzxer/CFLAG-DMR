# Feature Specification: CFLAG DMR — Project Overview

**Feature Branch**: `dev` (project-level document; not feature-branch-scoped)

**Created**: 2026-05-24 | **Last Updated**: 2026-05-25 (decisions resolved from handoff doc + user clarifications)

**Status**: Active — canonical product scope reference

---

## Purpose of This Document

Top-level product specification for CFLAG DMR. Defines the full scope of what the system needs to do, organized as independently deliverable feature areas. Each feature area listed here will be broken out into its own numbered feature spec (`specs/NNN-*/spec.md`) when it enters the implementation queue.

---

## Product Vision

CFLAG DMR is a web-based platform for managing a live DMR (Digital Mobile Radio) amateur radio network. It is designed around a node-based architecture — each server running HBLink is a node. Nodes can operate independently or link together to route traffic across networks, similar to how AllStar Link works.

The platform has three audiences:

- **System admins**: Full control over server configuration, user management, talkgroup management, moderation, and theming.
- **Network moderators**: Can moderate traffic — mute or ban request problematic nodes/users — within limits defined by admins.
- **Registered users**: Licensed amateur radio operators who register accounts, connect their hotspots/repeaters, manage their own devices and talkgroup subscriptions, and communicate with other users on the platform.

The platform also has **public-facing pages** visible without login: network status, node information, and general information for prospective users.

---

## Architecture Decisions — RESOLVED

All three open architecture decisions have been resolved as of 2026-05-25.

### AD-1: Database-driven config ✅ RESOLVED

**Decision**: Database is the source of truth for all network configuration. HBLink config files (`hblink.cfg`, `rules.py`) are generated artifacts written from the database at apply time, followed by a fast HBLink restart (~5s).

**Rationale**: HBLink has no runtime reload. With a multi-node architecture, one node restarting while others handle traffic gives effectively zero user-visible downtime. The database schema must be engine-agnostic so the DMR engine can be swapped in the future without a schema rewrite.

**Long-term direction**: Eventually build or adopt a CFLAG-native DMR bridge that reads routing rules from the database natively, eliminating the restart requirement entirely. HBLink remains the engine for now.

**Constraint noted from handoff**: The network MMDVM passphrase must be kept short (under 16 characters, simple alphanumeric). The test server's long base64 passphrase was incompatible with openSPOT4 Pro and similar devices.

---

### AD-2: Per-hotspot / per-user authentication ✅ RESOLVED

**Decision**: Authentication is anchored to the user's **DMR ID**. Access control is enforced by a DMR ID whitelist (`REG_ACL` in generated `hblink.cfg`). Only registered, email-verified, admin-approved DMR IDs appear in the whitelist. Devices not in the whitelist are rejected at the HBLink layer even with the correct passphrase.

**Model**:
- User registers with their callsign + DMR ID + email
- Email verified → account activated
- Admin approves device connection request → DMR ID added to whitelist in next generated config
- All hotspots use the same short network passphrase for the MMDVM connection
- Per-device access is controlled by whitelist inclusion, not per-device passphrases

**Future work**: Per-device unique passphrases require a UDP proxy in front of HBLink (HBLink MASTER supports only one shared passphrase). This is a valid future enhancement once the core platform is stable.

**FreeDMR equivalent**: `ALLOW_UNREG_ID: False` + per-ID whitelist. The same model applies if the engine is ever swapped to FreeDMR.

---

### AD-3: Page access tiers ✅ RESOLVED

Four access tiers, each building on the previous:

| Tier | Who | What they can see |
|------|-----|-------------------|
| **Public** | Anyone, no login | Registration page, network info, last-heard\* (\*admin-toggled), node status |
| **User** | Registered + email-verified | Profile, hotspot management, talkgroup subscriptions, messaging, chat |
| **Moderator** | User with moderator role | + Mute tools, ban requests, moderation log |
| **Admin** | System administrator | + Full config, user management, talkgroup approval, theming, server management, node management |

**Public last-heard**: Controlled by a `system_settings` flag. Default: **off**. Admins can enable it. When disabled, the last-heard page requires login (User tier minimum).

**Registration flow**:
1. User submits: callsign, DMR ID, email, password, display name
2. System sends email with a single-use verification token (expires 24h)
3. User clicks link → account activated (User tier)
4. User submits device connection request → admin approves → DMR ID whitelisted
5. After whitelist inclusion + config apply → device can connect to the network

**Email sending**: PHP-native SMTP (no Composer). Config in `.env`: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

---

## Feature Delivery Status

| ID | Feature Area | Status | Notes |
|----|--------------|--------|-------|
| P0 | HBLink + HBMonv2 Setup & Analysis | ✅ Complete | [spec](../002-hblink-setup/spec.md) |
| F1 | Admin Authentication | ✅ Complete | [spec](../001-admin-login/plan.md) |
| F2 | Role & Permission System | Not started | Blocks F3–F17 |
| F3 | User Registration & Accounts | Not started | Depends on F2 |
| F4 | User Profiles | Not started | Depends on F3 |
| F5 | Hotspot & Repeater Registration | Not started | Depends on F3, AD-2 |
| F6 | Talkgroup Management | Not started | Depends on F2, AD-1 |
| F7 | Network Config & Peer Management | Not started | Depends on F2, AD-1 |
| F8 | HBLink Config Visibility | Not started | Read-only, depends on F2 |
| F9 | Last-Heard & Activity Log | Not started | Public + authenticated views |
| F10 | Network Status Dashboard | Not started | Public-facing |
| F11 | Moderation Tools | Not started | Depends on F2, F3 |
| F12 | Node Management | Not started | Depends on F7, AD-1 |
| F13 | Controlled Restart / Reload | Not started | Depends on F7, AD-1 |
| F14 | Backup & Rollback | Not started | Depends on F13 |
| F15 | Theming & Customization | Not started | Depends on F2 |
| F16 | User Messaging | Not started | Depends on F3 |
| F17 | Community Chat Channels | Not started | Depends on F3 |
| F18 | Radio Programming Tools | Not started | Depends on F5 |

---

## Feature Descriptions

---

### P0: HBLink + HBMonv2 Setup and Analysis ✅

Complete. HBLink running in Docker at `/etc/hblink3/`. HBMonv2 running as systemd `hbmon`. Analysis committed at `specs/002-hblink-setup/analysis.md`. Both services accessible at `dmrdev.cflag.net`.

---

### F1: Admin Authentication ✅

Complete. Admins log in at `/login.php`, session auth via MariaDB, protected `/admin/` dashboard, logout destroys session. CSRF protection, bcrypt passwords, cookie hardening.

---

### F2: Role & Permission System

Defines the permission tiers used by every subsequent feature. Must be built before any feature that requires checking what a logged-in user is allowed to do.

**Roles**:
- `system_admin` — full access to everything
- `moderator` — can mute and submit ban requests; cannot change config
- `user` — registered network user; can manage own profile, hotspots, and talkgroup subscriptions

**Requirements**:
- Role stored on the user account
- Role checked on every protected endpoint; wrong role → 403 or redirect
- Admins can assign and change roles
- All current admin_users are `system_admin` role by default

---

### F3: User Registration & Accounts

A public registration form where licensed amateur radio operators can create a user account.

**Requirements**:
- Registration collects: callsign (required, used as username), email, password, display name
- Callsign must be unique in the system
- Email verified before account is activated (or manual activation by admin — TBD)
- Admins can manually create, activate, suspend, and delete accounts
- Users can log in at `/login.php` (same form as admins, role determines what they see)
- Suspended accounts cannot log in; generic error shown

---

### F4: User Profiles

Registered users have a profile page viewable by other logged-in users.

**Requirements**:
- Profile includes: callsign, display name, avatar (uploaded image), bio (free text), optional contact links (QRZ, email)
- User controls visibility of contact details (public to all users / hidden)
- Profile shows: account creation date, last login to website, last heard on network (callsign activity)
- User can edit their own profile
- Admins can view and edit any profile

---

### F5: Hotspot & Repeater Registration

Registered users can add their hotspots and repeaters to the network, manage device settings, and choose talkgroup assignments.

**Requirements**:
- User submits: device callsign, device type (hotspot / repeater), hardware description
- On approval (by admin or auto-approve policy), the device gets a network connection credential
- With AD-2 Option B: approved devices are added to the generated hblink.cfg (as enabled peers); disabled devices are excluded from the next generated config
- User can manage per-device talkgroup subscriptions: add/remove static talkgroups, set dynamic talkgroup timeout (minutes)
- User can view their own device's connection status (connected / not connected) pulled from HBLink report socket

---

### F6: Talkgroup Management

Admins manage the canonical list of talkgroups. Users can request new talkgroups. Admins approve or deny requests.

**Requirements**:
- Talkgroup record: ID (integer), name, description, type (open/private), owner (admin or user), active status
- **Open talkgroups**: any registered user can subscribe their device
- **Private talkgroups**: users must request access; owner/admin approves
- **Whitelist/Blacklist**: admins can explicitly allow or deny specific callsigns on any talkgroup
- New talkgroup requests submitted by users → enter pending queue → admin approves with final TGID assignment
- Admins can create, edit, disable, and delete talkgroups directly

---

### F7: Network Config & Peer Management

Admins manage the full network configuration: master settings, peer settings, OpenBridge connections. All changes stored in the database and applied by generating config files.

**Requirements**:
- Config stored in database (not edited as raw files)
- Changes are staged and applied together via an explicit "Apply & Restart" action
- Generated `hblink.cfg` and `rules.py` are written from database state on apply
- Admins can view current live config (read-only view of what is actually running) and pending staged config
- Diff view shows what will change before applying

---

### F8: HBLink Config Visibility

Read-only display of the currently running HBLink config and routing rules. No editing. Based on the generated files in `/etc/hblink3/`.

**Requirements**:
- Structured display of all `[SECTION]` blocks in `hblink.cfg`
- Structured display of all bridges in `rules.py`
- Shows what is currently running, not the staged/pending DB state
- Requires admin login

---

### F9: Last-Heard & Activity Log

Displays recent radio transmission activity. Public view (limited) and authenticated view (full).

**Requirements**:
- Public view: last 20 entries — callsign, talkgroup name, time, duration
- Authenticated view: full history with filters by callsign, talkgroup, system, time range
- Source: `/opt/HBMonv2/log/lastheard.log` CSV (see analysis.md Section 4)
- Ingested into database for queryability, or read directly from CSV for MVP
- Auto-refreshes on authenticated view (polling or SSE)

---

### F10: Network Status Dashboard

Public-facing dashboard showing live network health without requiring login.

**Requirements**:
- Shows: connected peers count, active talkgroups, recent last-heard (limited), node status
- Pulls peer/system state from HBLink report socket (port 4321) via a PHP intermediary
- Branded with the customizable theme (see F15)
- No sensitive config or user data exposed

---

### F11: Moderation Tools

Moderators and admins can take action against problematic traffic sources.

**Requirements**:
- **Mute 1hr / 3hr / 6hr / 24hr**: Excludes the targeted peer/callsign from the generated config for the duration. Automatically re-enabled when the timer expires (cron job regenerates config + restart)
- **Request Ban**: Moderator submits a ban request with reason and evidence. Goes to admin queue for approval. Approved bans permanently disable the peer account until an admin reverses it
- **Mute/ban log**: All moderation actions recorded with actor, target, reason, timestamp, and outcome
- Moderators can only request bans — they cannot directly ban. Admins approve or deny ban requests.
- All moderation actions are visible to all moderators and admins (not to regular users)

---

### F12: Node Management

Admins manage the multi-node network: register other CFLAG DMR nodes, configure inter-node linking, and monitor node health.

**Requirements**:
- A node is a CFLAG DMR + HBLink server instance
- Nodes can be linked via OpenBridge (OBP) connections between their HBLink instances
- Admin can add, configure, enable/disable, and remove inter-node links
- Node status dashboard shows each node's online/offline status, connected peer count, and last contact time
- Node linking configuration generates the `[OBP-*]` sections in the generated `hblink.cfg`

---

### F13: Controlled Restart / Reload

Admins can apply pending configuration changes, which generates config files and triggers an HBLink restart from the dashboard.

**Requirements**:
- "Apply & Restart" action: generates `hblink.cfg` and `rules.py` from database → writes to `/etc/hblink3/` → runs `hblink-restart` via sudo
- Admin sees live output / status result (success or failure) within 15 seconds
- Action is recorded in audit log with actor, timestamp, and outcome
- Before applying, a diff is shown: what will change vs what is currently running
- Pre-apply backup of current config files is created automatically

---

### F14: Backup & Rollback

Admins can view config backups, inspect them, and restore a previous state.

**Requirements**:
- Backups stored outside web root, not directly accessible via browser
- Each apply action creates a timestamped backup before writing new files
- Backup list shows timestamp, config version, and who triggered the apply
- Admins can restore any backup (writes restored files + triggers restart)
- Backup files are not stored in git

---

### F15: Theming & Customization

System admins can customize the visual appearance of the site.

**Requirements**:
- Built-in theme presets (e.g., dark blue, dark orange, dark green)
- Custom color overrides for key UI elements: primary color, accent color, background, card color, text color
- Theme is applied site-wide (public and authenticated pages)
- Custom CSS variables stored in database, injected into page `<style>` on render
- Live preview in the admin theme editor before saving
- Theme changes take effect immediately without restart

---

### F16: User Messaging

Registered users can send direct messages to other registered users.

**Requirements**:
- User-to-user private messages
- Inbox / sent views
- Unread message count indicator in nav
- Users can block messages from specific users
- No file attachments in direct messages (text only for MVP)
- Admins can view any message thread (moderation capability)

---

### F17: Community Chat Channels

Discord-style chat channels for community communication.

**Requirements**:
- Multiple named channels (e.g., #general, #technical-help, #net-announcements)
- Admins can create, rename, archive channels; set per-channel permission level (public / registered users only / moderators only)
- Messages: text, inline images (uploaded), file attachments (with size limit)
- Messages are persistent (stored in database)
- Real-time delivery via polling or WebSocket
- Users can be muted in chat independently of DMR network mutes
- Message moderation: admins and moderators can delete messages; deleted messages show "[deleted]" placeholder

---

### F18: Radio Programming Tools

Helps users configure their radios and hotspots to connect to the network.

**Requirements**:
- Hotspot config generator: user selects their device type (Pi-Star, MMDVM, etc.) and the tool outputs the exact connection settings (server IP, port, passphrase, color code, slot)
- Radio codeplug guide: per-device instructions for programming a radio to access the network's talkgroups
- Contact list export: downloadable CSV/JSON of active talkgroups and their IDs, suitable for importing into radio programming software
- Settings shown are pulled from live network config (correct talkgroup IDs, server address)

---

## Database Schema Areas (high-level)

The following entities will live in the CFLAG DMR MariaDB database. Detailed schemas are defined per feature spec. Schema patterns are informed by the handoff document from the test server.

| Entity group | Key Tables | Notes |
|---|---|---|
| Users & auth | `users`, `email_verifications`, `user_sessions`, `roles` | Replaces/extends `admin_users`; roles column on users table |
| Subscriber data | `subscriber_ids` | Radio ID → callsign/name lookup; pulled from RadioID.net + local overrides |
| Devices | `devices`, `device_talkgroups` | Hotspots and repeaters; approval status controls REG_ACL whitelist |
| Talkgroups | `talkgroups`, `talkgroup_acl`, `talkgroup_requests` | categories: local, regional, tactical, system, bridge, parrot |
| Network config | `masters`, `peers`, `obp_links`, `bridges`, `bridge_rules` | DB-driven HBLink config; bridge_rules replaces rules.py |
| DMR activity | `dmr_call_sessions`, `dmr_events` | call_sessions for dashboard/analytics; events for raw/debug capture |
| Moderation | `mod_actions`, `ban_requests` | |
| Node management | `nodes`, `node_links` | Multi-node OBP linking |
| Messaging | `messages`, `message_threads` | |
| Chat | `chat_channels`, `chat_messages` | |
| Config backups | `config_backups` | Metadata + file path; files stored outside web root |
| Audit log | `audit_log` | actor_user_id, action, target_type, target_id, before_json, after_json |
| System settings | `system_settings` | Key/value store for admin-toggled flags (e.g. public_lastheard_enabled) |
| Theming | `theme_settings` | Color vars, active preset |

### Key schema notes

**`dmr_call_sessions`** (preferred over a simple `last_heard` table — lessons from handoff):
- `session_key`, `started_at`, `ended_at`, `duration_sec`, `system_name`, `peer_id`, `radio_id`, `callsign`, `tg_number`, `tg_name`, `slot`, `call_type`
- Feed last-heard page, per-user activity, per-talkgroup activity, analytics

**`dmr_events`** (raw event capture for debugging):
- `event_time`, `event_type`, `peer_id`, `radio_id`, `tg_number`, `slot`, `source_ip`, `raw_payload`
- Essential for diagnosing TGRewrite confusion (hotspot rewrites the TG before it reaches the master)

**`bridge_rules`** (replaces `rules.py`):
- `bridge_id`, `system_name`, `timeslot`, `talkgroup_id`, `active`, `timeout`, `to_type`, `on_triggers` (JSON), `off_triggers` (JSON), `reset_triggers` (JSON)

**`system_settings`** (key/value):
- `public_lastheard_enabled` — bool, default false
- `registration_open` — bool, controls whether registration form is accessible
- `require_device_approval` — bool, controls auto-approve vs admin-approve for device requests
- `network_passphrase` — the short MMDVM passphrase (≤16 chars) used in generated hblink.cfg

---

## Routing Diagnostic Vision

A recurring pain point discovered on the test server: a talkgroup can appear correct at one layer and wrong at another, making issues very hard to debug. The full signal chain is:

```
Radio codeplug TG
    → Hotspot local mapping
        → DMRGateway TGRewrite rules
            → Master server received TG       ← what HBLink actually sees
                → HBLink/bridge routing rule
                    → Destination system/TG
```

A future diagnostic view should show the full trace for any call session — what TG the hotspot sent vs. what the master received vs. what route was matched. This makes TGRewrite issues immediately visible without SSH log inspection.

---

## Key Architectural Principles

- **Database is the source of truth.** HBLink config files are generated artifacts, not the canonical store. The database drives everything.
- **Engine-agnostic design.** The CFLAG control plane should not be tightly coupled to HBLink internals. Data models should be expressible in terms of DMR concepts (masters, peers, talkgroups, bridges), not HBLink-specific file formats.
- **Node-based architecture.** Each CFLAG DMR + HBLink server is a node. Nodes are independently manageable. Inter-node linking is via OBP. Restarting one node does not take down the whole network.
- **No public exposure of server internals.** Config details, passphrase values, and internal IP addresses are never shown in public-facing pages.
- **Plain PHP, no framework.** All server-side code is plain PHP 8.x with PDO. No Composer dependencies. No front-end build tools beyond plain CSS.

---

## Feature Delivery Order (Proposed)

| Phase | ID | Feature | Rationale |
|-------|----|---------|-|
| 1 | F2 | Role & Permission System | Foundation for every feature that has access control |
| 2 | F3 | User Registration & Accounts | User accounts unlock hotspot management, profiles, messaging |
| 3 | F4 | User Profiles | Natural extension of user accounts |
| 4 | F5 | Hotspot & Repeater Registration | Core network function; enables self-service onboarding |
| 5 | F6 | Talkgroup Management | Manage what talkgroups exist before configuring routing |
| 6 | F7 | Network Config & Peer Management | DB-driven config; generates HBLink files |
| 7 | F8 | HBLink Config Visibility | Read-only view of running config; lower priority once F7 exists |
| 8 | F9 | Last-Heard & Activity Log | Public + authenticated views |
| 9 | F10 | Network Status Dashboard | Public-facing; depends on F9 for activity data |
| 10 | F11 | Moderation Tools | Depends on F2 (roles) and F3 (user accounts) |
| 11 | F12 | Node Management | Multi-node linking; depends on F7 config patterns |
| 12 | F13 | Controlled Restart / Reload | Apply DB config to HBLink; depends on F7 |
| 13 | F14 | Backup & Rollback | Depends on F13 |
| 14 | F15 | Theming & Customization | Can be done in parallel with any feature |
| 15 | F16 | User Messaging | Depends on F3 |
| 16 | F17 | Community Chat Channels | Depends on F3 |
| 17 | F18 | Radio Programming Tools | Depends on F5 (device data) and F6 (talkgroup data) |

---

## Open Questions

| # | Question | Blocks |
|---|----------|--------|
| OQ-1 | AD-2: Per-hotspot auth — shared passphrase + enable/disable, or proxy layer? | F5, F7 |
| OQ-2 | AD-3: Email verification on registration, or admin-activated accounts? | F3 |
| OQ-3 | Should the public last-heard page show callsigns, or only talkgroup activity? (privacy consideration) | F9, F10 |
| OQ-4 | Is there a public-facing "about this network" page, or is the dashboard the home page? | F10 |
| OQ-5 | Does the chat (F17) need real-time delivery for MVP, or is 30-second polling acceptable? | F17 |
| OQ-6 | What file size limit applies to chat attachments (F17)? | F17 |
