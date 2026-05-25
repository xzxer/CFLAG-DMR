<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database/connection.php';
require_once dirname(__DIR__) . '/auth/session.php';

function attempt_login(string $username, string $password): bool
{
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, display_name, is_active
         FROM admin_users
         WHERE username = ?'
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row === false) {
        // Consume constant time to prevent username enumeration
        password_verify($password, '$2y$10$invalidhashpadding00000000000000000000000000000000000000');
        return false;
    }

    if (!(bool) $row['is_active']) {
        return false;
    }

    if (!password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['admin_id']           = (int) $row['id'];
    $_SESSION['admin_username']     = $row['username'];
    $_SESSION['admin_display_name'] = $row['display_name'];
    $_SESSION['authenticated_at']   = time();

    $db->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
       ->execute([$row['id']]);

    return true;
}
