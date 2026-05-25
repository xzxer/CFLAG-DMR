<!-- SPECKIT START -->
For additional context about technologies to be used, project structure,
shell commands, and other important information, read the current plan
at specs/002-hblink-setup/plan.md

The current active phase is P0: HBLink + HBMonv2 setup and analysis (specs/002-hblink-setup/).
Admin login (specs/001-admin-login/) is fully planned but not yet built — it follows after P0.
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
