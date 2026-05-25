<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database/connection.php';
require_once dirname(__DIR__) . '/auth/session.php';

function attempt_login(string $username, string $password): bool
{
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, display_name, moderation_state
         FROM users
         WHERE username = ?'
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row === false) {
        password_verify($password, '$2y$10$invalidhashpadding00000000000000000000000000000000000000');
        return false;
    }

    if (!password_verify($password, $row['password_hash'])) {
        return false;
    }

    if ($row['moderation_state'] === 'suspended' || $row['moderation_state'] === 'banned') {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']      = (int) $row['id'];
    $_SESSION['username']     = $row['username'];
    $_SESSION['display_name'] = $row['display_name'];

    $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
       ->execute([$row['id']]);

    return true;
}
