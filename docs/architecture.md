# CFLAG DMR Architecture

## Current Project Direction

CFLAG DMR is currently being developed as a web-based management and dashboard platform for an HBLink-compatible DMR server.

The immediate goal is to build a useful working system that improves the day-to-day operation of an HBLink deployment through a cleaner admin interface, safer configuration workflows, better visibility, and better documentation.

This project may eventually evolve toward a CFLAG-native DMR backend, but that is not part of the initial development scope.

The current priority is to build something practical, working, and useful for managing an HBLink-compatible server.

## Product Goal

CFLAG DMR should help an administrator manage and monitor an HBLink-compatible DMR server.

The first version should focus on:

- Admin login and protected management areas
- Server dashboard
- HBLink configuration visibility
- HBLink rules and routing visibility
- Peer and hotspot management
- Talkgroup and timeslot management
- Last-heard and log ingestion
- Basic health and status monitoring
- Safer configuration editing
- Backup and rollback-friendly configuration changes
- Controlled restart or reload workflows where supported
- Documentation for installing on an HBLink-compatible server

## Long-Term Possibilities

The long-term possibilities may include:

- A more advanced backend adapter model
- CFLAG-native DMR server/backend
- API-driven engine control
- Dynamic routing
- Advanced access control
- Real-time event streaming
- Multi-master network architecture
- Regional or distributed master servers
- Hosted or commercial deployment models

These future possibilities should not block the current HBLink-compatible management layer.

## Control Plane vs Engine

CFLAG DMR is separated into two conceptual layers:

1. Control Plane
2. Engine Layer

### Control Plane

The control plane is the web application, database, admin UI, configuration model, audit trail, dashboard, and management workflow.

The control plane owns:

- Admin users
- Protected management pages
- Devices and hotspots
- Talkgroups
- Timeslot and routing models
- Access approvals
- Configuration models
- Generated configuration files
- Dashboard views
- Last-heard and event storage
- Audit logs
- Operational workflows

The current PHP/MariaDB web application is the beginning of this control plane.

### Engine Layer

The engine layer handles low-level DMR server behavior.

For the current phase, the primary engine target is an HBLink-compatible server.

The engine layer is responsible for:

- Peer connections
- Device authentication
- DMR stream handling
- Talkgroup routing
- Timeslot handling
- Bridge behavior
- Parrot/echo behavior
- Network status events
- Logs and last-heard source data

## HBLink-Compatible First Approach

CFLAG DMR will initially target HBLink-compatible deployments.

This means CFLAG DMR may:

- Read HBLink-style configuration files
- Generate HBLink-compatible configuration files
- Generate or manage HBLink-compatible rules files
- Parse logs or exported activity data
- Display peer, talkgroup, and routing information
- Provide safer editing workflows
- Back up existing configuration before changes
- Restart or reload the HBLink service where supported
- Help document and simplify HBLink server administration

HBLink may be downloaded, run, inspected, tested, and analyzed to understand DMR server behavior, configuration needs, routing flow, and operational pain points.

AI tools may be used to help explain HBLink behavior, summarize file responsibilities, map configuration flows, document system concepts, and extract requirements for CFLAG-owned features.

## HBLink Source and Licensing Boundary

CFLAG DMR may learn from HBLink's operating model, configuration concepts, routing behaviors, and server workflows.

However, HBLink source code should remain clearly separated from the CFLAG core application unless an intentional licensing decision is made.

Any HBLink-based engine component should preserve its original license and notices.

The CFLAG control plane should be written as CFLAG-owned application code.

## Inspiration, Analysis, and Reengineering Boundaries

Allowed:

- Download and run HBLink in a separate research or engine directory.
- Use AI to explain HBLink files, flows, configuration, and behavior.
- Use AI to generate summaries, diagrams, requirements, and design notes from HBLink analysis.
- Identify business rules, protocol expectations, operational pain points, and system responsibilities.
- Build CFLAG-owned data models, workflows, services, dashboards, and configuration tools.
- Generate HBLink-compatible configuration from CFLAG-owned models.
- Use HBLink as the first supported backend engine.
- Preserve HBLink’s original license and notices if HBLink is distributed as a separate component.

Not allowed without an intentional licensing decision:

- Ship modified HBLink-derived engine code as closed-source proprietary CFLAG code.
- Remove or obscure HBLink copyright/license notices from HBLink-derived files.
- Mix GPL engine internals directly into the CFLAG control-plane codebase in a way that creates avoidable licensing ambiguity.
- Treat heavy modification of HBLink source as automatically making the code proprietary.

Decision rule:

If we are studying HBLink to understand the problem, that is acceptable.

If we are modifying or distributing HBLink-derived engine code, we must treat that engine component as license-governed by its original license unless legal review determines otherwise.

If we later want a proprietary CFLAG-native engine, we should build it from a CFLAG-authored engine specification and our own implementation.

## Future CFLAG-Native Backend

A future CFLAG-native backend may be developed after the HBLink-compatible management layer is useful and stable.

That future backend should not be rushed into the initial scope.

If pursued, the CFLAG-native backend should be designed around an internal engine specification that defines:

- Peer authentication
- DMR ID authorization
- Talkgroup authorization
- Timeslot behavior
- Stream/session handling
- Routing behavior
- Bridge behavior
- Event output
- Health checks
- Configuration reload behavior
- Multi-master or distributed network behavior

The CFLAG-native backend should eventually aim to be:

- Observable
- Testable
- API-driven
- Configurable from the CFLAG control plane
- Safer to reload or restart
- Easier to debug
- Suitable for packaged deployment
- Capable of larger network architectures if that direction is chosen later

## Backend Capability Model

Even though HBLink is the first target, CFLAG DMR should avoid hardcoding assumptions that would make future backend support impossible.

Future backend integrations should expose capabilities such as:

- config_generation
- rules_generation
- process_restart
- log_ingestion
- last_heard_basic
- static_talkgroup_rules
- live_peer_management
- dynamic_talkgroup_routing
- real_time_events
- api_control
- multi_master
- distributed_policy_sync
- advanced_acl
- live_reload
- audit_backed_changes

The admin UI should eventually use backend capabilities to show, hide, or disable features based on what the selected backend supports.

For the initial version, HBLink-compatible capabilities are the priority.

## Development Rule

Build the HBLink-compatible management layer first.

Do not overbuild for a custom backend before the HBLink management workflow is useful.

When adding engine-specific features, keep the CFLAG control-plane data model and adapter boundary in mind so future backend options remain possible.
