<!-- SPECKIT START -->
Active planning branch: `016-operational-features`

Planning complete (specs/015–018, not yet implemented):
- F13 Controlled Restart → specs/015-controlled-restart/plan.md
- F14 Subscriber ID Import → specs/016-subscriber-id-import/plan.md
- F15 Moderation Tools → specs/017-moderation-tools/plan.md
- F16 Backup & Rollback → specs/018-backup-rollback/plan.md

Completed: P0 (HBLink + HBMonv2 setup), F1 (Admin login), F2 (Role & Permission System), F3 (User Registration & Accounts), F4 (User Profiles), F5 (Hotspot & Repeater Registration), F6 (Talkgroup Management), F7 (Network Config & Peer Management), F8 (HBLink Config Visibility), F9 (Last-Heard & Activity Log), F10 (Network Status Dashboard), F11 (Extended User Profiles), F12 (Public User Directory).

Key data contracts:
- get_whitelist_eligible_dmr_ids() → app/devices/manager.php (F7 REG_ACL source)
- get_device_subscriptions_for_config() → app/talkgroups/manager.php (F7 peer stanza source)
- generate_hblink_config(int $actor_id) → app/config/generator.php
- apply_hblink_config(int $actor_id) → app/config/generator.php (F13, planned)
<!-- SPECKIT END -->

## Current Architecture Direction

CFLAG DMR is currently focused on building an HBLink-compatible management and dashboard layer.

The current phase should prioritize practical HBLink server administration:
- Admin login
- Protected management pages
- HBLink configuration visibility
- Rules and routing visibility
- Peer/hotspot management
- Talkgroup and timeslot management
- Last-heard/log ingestion
- Safer configuration editing
- Backup and rollback-friendly changes
- Controlled restart/reload workflows

Do not prematurely design around a full custom CFLAG-native backend unless explicitly requested.

A future CFLAG-native backend remains possible, but it is not part of the current implementation scope.

HBLink may be studied, run, inspected, and analyzed to understand DMR server behavior and configuration flow.

The CFLAG control plane should be written as CFLAG-owned application code and should avoid unnecessary tight coupling to one engine implementation.
