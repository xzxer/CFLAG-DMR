<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../database/connection.php';

const ROLE_HIERARCHY = [
    'user'         => 1,
    'moderator'    => 2,
    'admin'        => 3,
    'system_admin' => 4,
];

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, username, display_name, moderation_state, dmr_id
         FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_user_roles(int $user_id): array
{
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT r.name
         FROM roles r
         JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?
         ORDER BY r.sort_order DESC'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function user_has_role(int $user_id, string $min_role): bool
{
    $min_level = ROLE_HIERARCHY[$min_role] ?? 0;
    foreach (get_user_roles($user_id) as $role) {
        if ((ROLE_HIERARCHY[$role] ?? 0) >= $min_level) {
            return true;
        }
    }
    return false;
}

function require_role(string $min_role): void
{
    require_login();
    $user = current_user();
    if ($user === null || !user_has_role((int) $user['id'], $min_role)) {
        header('HTTP/1.1 403 Forbidden');
        redirect('/admin/');
    }
}

function actor_max_level(int $actor_id): int
{
    $level = 0;
    foreach (get_user_roles($actor_id) as $role) {
        $level = max($level, ROLE_HIERARCHY[$role] ?? 0);
    }
    return $level;
}

function assign_role(int $actor_id, int $target_id, string $role_name): void
{
    $db          = get_db();
    $target_level = ROLE_HIERARCHY[$role_name] ?? 0;

    if ($target_level === 0) {
        throw new \InvalidArgumentException("Unknown role: {$role_name}");
    }
    if (actor_max_level($actor_id) < $target_level) {
        throw new \RuntimeException("Insufficient privileges to assign role: {$role_name}");
    }

    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute([$role_name]);
    $role = $stmt->fetch();
    if ($role === false) {
        throw new \RuntimeException("Role not found: {$role_name}");
    }

    $db->prepare(
        'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by_user_id)
         VALUES (?, ?, ?)'
    )->execute([$target_id, (int) $role['id'], $actor_id]);

    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$target_id]);
    $target = $stmt->fetch();

    log_audit_action($actor_id, 'role_assigned', 'user', $target_id, [
        'role_name'       => $role_name,
        'target_username' => $target['username'] ?? '',
    ]);
}

function revoke_role(int $actor_id, int $target_id, string $role_name): void
{
    $db           = get_db();
    $target_level = ROLE_HIERARCHY[$role_name] ?? 0;

    if ($target_level === 0) {
        throw new \InvalidArgumentException("Unknown role: {$role_name}");
    }
    if (actor_max_level($actor_id) < $target_level) {
        throw new \RuntimeException("Insufficient privileges to revoke role: {$role_name}");
    }

    if ($role_name === 'system_admin') {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             WHERE r.name = "system_admin"'
        );
        $stmt->execute();
        if ((int) $stmt->fetchColumn() <= 1) {
            throw new \RuntimeException("Cannot remove the last system admin role.");
        }
    }

    $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
    $stmt->execute([$role_name]);
    $role = $stmt->fetch();
    if ($role === false) {
        throw new \RuntimeException("Role not found: {$role_name}");
    }

    $db->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?')
       ->execute([$target_id, (int) $role['id']]);

    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$target_id]);
    $target = $stmt->fetch();

    log_audit_action($actor_id, 'role_revoked', 'user', $target_id, [
        'role_name'       => $role_name,
        'target_username' => $target['username'] ?? '',
    ]);
}

function log_audit_action(
    int $actor_id,
    string $action_type,
    string $target_type,
    ?int $target_id,
    array $detail = []
): void {
    get_db()->prepare(
        'INSERT INTO audit_log (actor_user_id, action_type, target_type, target_id, detail_json)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $actor_id,
        $action_type,
        $target_type,
        $target_id,
        empty($detail) ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
    ]);
}

function log_mod_action(
    int $actor_id,
    int $target_id,
    string $action,
    string $reason,
    ?int $duration_hours = null,
    ?string $expires_at = null
): void {
    get_db()->prepare(
        'INSERT INTO mod_log
             (actor_user_id, target_user_id, action, reason, duration_hours, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$actor_id, $target_id, $action, $reason, $duration_hours, $expires_at]);
}

function queue_network_regen(int $actor_id, string $change_type, array $details = []): void
{
    get_db()->prepare(
        'INSERT INTO config_change_queue (triggered_by_user_id, change_type, details_json)
         VALUES (?, ?, ?)'
    )->execute([
        $actor_id,
        $change_type,
        empty($details) ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
    ]);
}
