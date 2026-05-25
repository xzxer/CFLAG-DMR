<?php
declare(strict_types=1);

// CLI-only: expire timed network mutes and flag network regen.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/app/auth/session.php';
require_once dirname(__DIR__) . '/app/database/connection.php';
require_once dirname(__DIR__) . '/app/auth/roles.php';

$db = get_db();

// Find a system_admin to act as the automation actor.
$actor_stmt = $db->query(
    'SELECT ur.user_id FROM user_roles ur
     JOIN roles r ON r.id = ur.role_id
     WHERE r.name = \'system_admin\'
     ORDER BY ur.user_id
     LIMIT 1'
);
$actor_row = $actor_stmt->fetch();

if (!$actor_row) {
    echo '[expire-mutes] ERROR: no system_admin found; cannot run.' . PHP_EOL;
    exit(1);
}

$system_actor_id = (int) $actor_row['user_id'];

// Find all users with an expired muted_on_network state.
$expired = $db->query(
    'SELECT id, username, dmr_id FROM users
     WHERE moderation_state = \'muted_on_network\'
       AND mute_expires_at IS NOT NULL
       AND mute_expires_at <= NOW()'
)->fetchAll();

if (empty($expired)) {
    exit(0);
}

$update = $db->prepare(
    'UPDATE users SET moderation_state = \'active\', mute_expires_at = NULL WHERE id = ?'
);

foreach ($expired as $user) {
    $user_id = (int) $user['id'];

    $update->execute([$user_id]);

    log_mod_action(
        $system_actor_id,
        $user_id,
        'auto_expired',
        'Timed mute expired automatically'
    );

    if ($user['dmr_id'] !== null) {
        queue_network_regen($system_actor_id, 'mute_expired', ['user_id' => $user_id]);
    }

    echo '[expire-mutes] Expired mute for user ' . $user['username'] . ' (id=' . $user_id . ')' . PHP_EOL;
}
