# Feature Specification: Network Status Dashboard

**Feature Branch**: `010-network-status-dashboard`

**Created**: 2026-05-26

**Status**: Draft

**Input**: F10: Network Status Dashboard — Real-time visibility into connected peers, active calls, and server health. Admins and users can view which hotspots and repeaters are currently online, see live or recent call activity, and monitor overall HBLink server state from a polished dashboard view.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Connected Peers & Server Health (Priority: P1)

Any logged-in user visits a Network Status page and sees which hotspots and repeaters are currently connected to the HBLink server, along with the overall server state (running/stopped, uptime). Each connected peer is listed with its callsign, DMR ID, and connection status. Admins see additional detail (IP address, connection timestamps).

**Why this priority**: This is the fundamental real-time visibility this feature exists to provide. Without a live peer list, there is no meaningful "network status." This story alone delivers the core value.

**Independent Test**: Can be fully tested by connecting a test hotspot, navigating to the status page, and verifying the peer appears with correct callsign and DMR ID. Server up/down state is verified by stopping and starting HBLink.

**Acceptance Scenarios**:

1. **Given** HBLink is running with one connected hotspot, **When** a logged-in user visits the status page, **Then** the hotspot's callsign and DMR ID appear in a connected peers list
2. **Given** HBLink is stopped, **When** a user visits the status page, **Then** the server state shows "Stopped" and the peer list shows no active connections
3. **Given** a hotspot disconnects, **When** the status page is refreshed, **Then** that peer no longer appears in the connected list
4. **Given** a non-admin user views the status page, **When** the peer list loads, **Then** IP addresses and raw connection timestamps are NOT shown
5. **Given** an admin views the status page, **When** the peer list loads, **Then** each peer shows connection time, IP address, and link quality indicators if available

---

### User Story 2 - Recent Call Activity on Status Page (Priority: P2)

The status page shows a live feed of the most recent calls (last heard), integrated with the existing Last Heard log. Users see which talkgroup was active, which callsign was transmitting, and when. This gives a real-time sense of network activity without navigating to a separate page.

**Why this priority**: Peer list alone tells you who is connected; recent calls tells you who is active. Together they give a complete picture. This story is second because it builds on the Last Heard system already in place.

**Independent Test**: Can be tested by making a test transmission and verifying the call appears in the status page activity feed within one page refresh cycle.

**Acceptance Scenarios**:

1. **Given** recent calls exist in the Last Heard log, **When** a user visits the status page, **Then** the 10 most recent calls are shown with callsign, talkgroup, and time
2. **Given** the Last Heard log is empty, **When** a user visits the status page, **Then** the activity section shows "No recent activity"
3. **Given** a talkgroup has a custom name, **When** its activity appears in the feed, **Then** the talkgroup name (not just the TGID number) is displayed
4. **Given** the user refreshes the page, **When** new calls have occurred, **Then** the updated activity list reflects those calls

---

### User Story 3 - Admin Server Control Panel on Status Page (Priority: P3)

System admins see a server control section on the status page that shows HBLink process state, uptime, and whether the current running config matches the last generated config (drift detection). Admins can also see a prominent alert if the config has drifted since HBLink was last started.

**Why this priority**: Config drift detection and server state monitoring are admin-specific safety tools. They build on US1 (server health) and require the config generation system from F7 to be meaningful. Placed P3 because it extends admin capabilities beyond basic visibility.

**Independent Test**: Can be tested by generating a new config (changing a DB setting), then viewing the status page and confirming the drift warning appears. Confirming it clears after a config regeneration and reload.

**Acceptance Scenarios**:

1. **Given** the running HBLink config matches the last generated config, **When** an admin views the status page, **Then** no drift warning is shown
2. **Given** the DB state has changed since the last config generation (new device approved), **When** an admin views the status page, **Then** a "Config may be out of date" notice is shown with a link to regenerate
3. **Given** HBLink is running, **When** an admin views the status page, **Then** process uptime is displayed in human-readable format (e.g., "2 days, 4 hours")
4. **Given** the admin navigates to the HBLink process status sub-page, **When** the page loads, **Then** the full process details (PID, memory, uptime) are shown

---

### Edge Cases

- What if the HBLink status API/data source is unreachable? (Status page renders with a "Server status unavailable" notice; other sections still load normally)
- What if a connected peer's DMR ID is not in the local device database? (Peer is shown with its raw DMR ID and callsign from HBLink data; no local device record link is shown)
- What happens when the Last Heard data source returns an error? (Activity section shows "Activity data unavailable" without breaking the rest of the page)
- What if HBLink returns stale data (e.g., cached status file)? (Data is shown with its retrieval timestamp so admins can judge freshness)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display the current HBLink server state (running or stopped) on the network status page
- **FR-002**: System MUST list all currently connected peers with callsign and DMR ID visible to all authenticated users
- **FR-003**: System MUST show connection timestamps and IP addresses for connected peers to system admin users only
- **FR-004**: System MUST display the 10 most recent Last Heard entries (callsign, talkgroup name, time) on the status page
- **FR-005**: System MUST display server uptime in human-readable format when HBLink is running
- **FR-006**: System MUST show a config drift warning to admins when the database state has changed since the last config generation
- **FR-007**: System MUST source connected peer data from the same HBLink status mechanism already used by the admin HBLink status page (no new data source)
- **FR-008**: System MUST source Last Heard data from the existing Last Heard reader (no new data source)
- **FR-009**: The status page MUST be accessible to all logged-in users, with admin-only sections conditionally shown
- **FR-010**: System MUST NOT expose raw server internals (PID, memory stats) to non-admin users

### Key Entities

- **ConnectedPeer**: A live peer record from HBLink status data — DMR ID, callsign, connection time, IP, link state
- **ServerHealth**: Point-in-time snapshot — running state, uptime, config drift indicator
- **RecentActivity**: The last N Last Heard entries used for the activity feed on this page

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A logged-in user can see current server state and connected peers within one page load, with no additional navigation required
- **SC-002**: The status page loads in under 2 seconds under normal conditions (HBLink running, under 50 peers)
- **SC-003**: Config drift detection correctly identifies a changed database state within one page load after the change occurs
- **SC-004**: Non-admin users cannot access IP addresses or connection timestamps of peers — verified by role-based rendering tests
- **SC-005**: The activity feed shows calls from the last 24 hours if available, always showing at least the 10 most recent entries

## Assumptions

- HBLink status data (connected peers, uptime) is read from the same source already implemented in the admin HBLink status page (`get_hblink_status()` in `app/hblink/process.php`)
- Last Heard data is read from the existing `load_lastheard()` function in `app/lastheard/reader.php`
- Config drift detection compares `config_change_queue` table entries (queued-but-not-yet-applied changes) against the last generation timestamp
- The Network Status page lives at `/network-status.php` or `/status.php` under the public docroot, accessible to all logged-in users
- Real-time auto-refresh (WebSocket, SSE) is out of scope for this iteration; page refresh provides updated data
- Peer data from HBLink may not always be in sync with the local device database (e.g., a device connected before being registered); the page handles this gracefully
