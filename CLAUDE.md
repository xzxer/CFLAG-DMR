<!-- SPECKIT START -->
Two features are in parallel development:
- F5: Hotspot & Repeater Registration — specs/007-hotspot-registration/ (branch: 007-hotspot-registration)
- F6: Talkgroup Management — specs/008-talkgroup-management/ (branch: 008-talkgroup-management)

Completed: P0 (HBLink + HBMonv2 setup), F1 (Admin login), F2 (Role & Permission System), F3 (User Registration & Accounts), F8 (HBLink Config Visibility), F9 (Last-Heard & Activity Log).
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
