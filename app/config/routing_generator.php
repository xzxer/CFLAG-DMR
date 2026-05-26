<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../talkgroups/manager.php';
require_once __DIR__ . '/../config/settings.php';

define('BRIDGE_ROUTES_JSON_PATH', '/etc/hblink3/json/bridge_routes.json');

function generate_bridge_routes_json(): array
{
    $db = get_db();

    // Fetch current routing version
    $stmt = $db->prepare('SELECT version FROM routing_config_state WHERE id = 1');
    $stmt->execute();
    $version = (int) ($stmt->fetchColumn() ?: 0);

    // Determine the MASTER system name — must match [MASTER] section name in hblink.cfg
    $master_system = 'MASTER';

    // Build BRIDGES from active talkgroups that have at least one approved subscription
    // Each active talkgroup becomes a conference bridge entry on the MASTER system
    $stmt = $db->prepare(
        "SELECT DISTINCT tg.id, tg.tgid, tg.name, dts.timeslot
         FROM talkgroups tg
         JOIN device_talkgroup_subscriptions dts ON dts.talkgroup_id = tg.id
         JOIN devices d ON d.id = dts.device_id
         JOIN users u ON u.id = d.user_id
         WHERE tg.active = 1
           AND d.status = 'approved'
           AND u.moderation_state = 'active'
         ORDER BY tg.tgid, dts.timeslot"
    );
    $stmt->execute();
    $subscribed_tgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also include OpenBridge-linked talkgroups (these create cross-system bridge rules)
    // Table may not exist yet — graceful fallback wraps both prepare and execute
    try {
        $ob_stmt = $db->prepare(
            "SELECT tg.id, tg.tgid, tg.name, ob.name AS ob_name, ob.id AS ob_id
             FROM openbridge_talkgroup_links otl
             JOIN talkgroups tg ON tg.id = otl.talkgroup_id
             JOIN openbridge_connections ob ON ob.id = otl.connection_id
             WHERE ob.enabled = 1 AND tg.active = 1"
        );
        $ob_stmt->execute();
        $ob_links = $ob_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException) {
        $ob_links = [];
    }

    $bridges = [];

    // One bridge entry per talkgroup per timeslot on MASTER
    foreach ($subscribed_tgs as $tg) {
        $bridge_name = $tg['name'] . ' (TG' . $tg['tgid'] . ')';
        $ts          = (int) $tg['timeslot'];

        if (!isset($bridges[$bridge_name])) {
            $bridges[$bridge_name] = [];
        }

        // Avoid duplicate MASTER entries for same TGID+TS
        $exists = false;
        foreach ($bridges[$bridge_name] as $entry) {
            if ($entry['SYSTEM'] === $master_system && $entry['TS'] === $ts) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $bridges[$bridge_name][] = [
                'SYSTEM'   => $master_system,
                'TS'       => $ts,
                'TGID'     => (int) $tg['tgid'],
                'ACTIVE'   => true,
                'TIMEOUT'  => 0,
                'TO_TYPE'  => 'NONE',
                'ON'       => [(int) $tg['tgid']],
                'OFF'      => [],
                'RESET'    => [],
            ];
        }

        // Add OBP system entries for this talkgroup
        foreach ($ob_links as $ob) {
            if ((int) $ob['id'] === (int) $tg['id']) {
                $ob_system = 'OPENBRIDGE-' . strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '_', $ob['ob_name']));
                $bridges[$bridge_name][] = [
                    'SYSTEM'   => $ob_system,
                    'TS'       => $ts,
                    'TGID'     => (int) $tg['tgid'],
                    'ACTIVE'   => true,
                    'TIMEOUT'  => 0,
                    'TO_TYPE'  => 'NONE',
                    'ON'       => [(int) $tg['tgid']],
                    'OFF'      => [],
                    'RESET'    => [],
                ];
            }
        }
    }

    // Always include the parrot bridge (present in original rules.py)
    if (!isset($bridges['Parrot'])) {
        $bridges['Parrot'] = [[
            'SYSTEM'   => $master_system,
            'TS'       => 2,
            'TGID'     => 9999,
            'ACTIVE'   => true,
            'TIMEOUT'  => 0,
            'TO_TYPE'  => 'NONE',
            'ON'       => [9999],
            'OFF'      => [],
            'RESET'    => [],
        ]];
    }

    $payload = json_encode([
        'version'      => $version,
        'generated_at' => gmdate('c'),
        'master_system'=> $master_system,
        'bridges'      => $bridges,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $tmp = BRIDGE_ROUTES_JSON_PATH . '.tmp';
    if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Failed to write bridge_routes.json.tmp — check permissions on ' . dirname(BRIDGE_ROUTES_JSON_PATH)];
    }

    if (!rename($tmp, BRIDGE_ROUTES_JSON_PATH)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Failed to install bridge_routes.json (rename failed).'];
    }

    return ['ok' => true, 'bridge_count' => count($bridges)];
}
