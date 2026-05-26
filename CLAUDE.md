<!-- SPECKIT START -->
Three features are in parallel development:
- F7: Network Config & Peer Management — specs/009-network-config-gen/ (branch: 009-network-config-gen)
- F10: Network Status Dashboard — specs/010-network-status-dashboard/ (branch: 010-network-status-dashboard)
- F4: User Profiles — specs/011-user-profiles/ (branch: 011-user-profiles)

Completed: P0 (HBLink + HBMonv2 setup), F1 (Admin login), F2 (Role & Permission System), F3 (User Registration & Accounts), F5 (Hotspot & Repeater Registration), F6 (Talkgroup Management), F8 (HBLink Config Visibility), F9 (Last-Heard & Activity Log).

Key data contracts available for F7:
- get_whitelist_eligible_dmr_ids() → app/devices/manager.php
- get_device_subscriptions_for_config() → app/talkgroups/manager.php
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
