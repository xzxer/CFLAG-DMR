# CFLAG DMR Architecture

## Strategic Direction

CFLAG DMR is being developed as a ground-up DMR network management and control platform.

The project may study HBLink, FreeDMR, BrandMeister-style systems, TGIF-style systems, and other DMR network tools to understand common operating models, configuration needs, routing concepts, and known pain points. However, the CFLAG DMR core application will not copy HBLink source code or become a direct fork of HBLink.

## Product Goal

The long-term goal is to build a better, more maintainable, more observable, and more product-ready DMR network platform.

CFLAG DMR should support:

- Admin login and protected management areas
- User and device management
- Access request workflows
- DMR ID and hotspot authorization
- Talkgroup and timeslot management
- Routing and bridge visibility
- Engine configuration generation
- Live dashboard and health monitoring
- Last-heard/event storage
- Audit logs and rollback-friendly changes
- Future support for private, club, amateur, and commercial networks

## Control Plane vs Engine

CFLAG DMR is separated into two conceptual layers:

### Control Plane

The control plane is the web application, database, admin UI, configuration model, audit trail, and management workflow.

The control plane owns:

- Users
- Admins
- Devices
- Talkgroups
- Permissions
- Access approvals
- Configuration models
- Generated configuration files
- Dashboard views
- Audit logs
- Operational workflows

### Engine Layer

The engine layer handles low-level DMR network behavior.

The engine layer may include:

- HBLink-compatible engines
- FreeDMR-style engines
- Future CFLAG-native engine components

The engine is responsible for:

- Peer connections
- DMR stream routing
- Talkgroup routing
- Timeslot handling
- Device authentication
- Bridge behavior
- Parrot/echo behavior
- Network status events

## HBLink Position

HBLink may be used as reference material and as an external engine during early development.

CFLAG DMR may learn from HBLink's operating model, configuration concepts, and routing behaviors, but the CFLAG codebase must not copy HBLink source code into the proprietary/commercial core.

Any HBLink-based engine should remain a separate component with its original license preserved.

## Future CFLAG-Native Engine

A future CFLAG-native DMR engine may be developed from an internal engine specification.

That specification should define required behavior, inputs, outputs, protocol expectations, event formats, health checks, and integration boundaries.

The CFLAG-native engine should be designed to be:

- Observable
- Testable
- API-driven
- Configurable from the CFLAG control plane
- Safer to reload or restart
- Easier to debug
- Suitable for packaged deployment

## Development Rule

Before building engine-specific functionality, define the data model and adapter boundary in the CFLAG control plane.

The control plane should not be tightly coupled to one engine implementation.
