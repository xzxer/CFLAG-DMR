<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database/connection.php';
require_once dirname(__DIR__) . '/auth/session.php';

const LOGIN_OK         = 'ok';
const LOGIN_INVALID    = 'invalid';
const LOGIN_UNVERIFIED = 'unverified';

function attempt_login(string $identifier, string $password): string
{
    $db       = get_db();
    $by_email = str_contains($identifier, '@');
    $col      = $by_email ? 'email' : 'username';

    $stmt = $db->prepare(
        "SELECT id, username, password_hash, display_name, moderation_state, email_verified_at
         FROM users
         WHERE {$col} = ?
         LIMIT 1"
    );
    $stmt->execute([$identifier]);
    $row = $stmt->fetch();

    if ($row === false) {
        password_verify($password, '$2y$10$invalidhashpadding00000000000000000000000000000000000000');
        return LOGIN_INVALID;
    }

    if (!password_verify($password, $row['password_hash'])) {
        return LOGIN_INVALID;
    }

    if ($row['moderation_state'] === 'suspended' || $row['moderation_state'] === 'banned') {
        return LOGIN_INVALID;
    }

    if ($row['email_verified_at'] === null) {
        return LOGIN_UNVERIFIED;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']      = (int) $row['id'];
    $_SESSION['username']     = $row['username'];
    $_SESSION['display_name'] = $row['display_name'];

    $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
       ->execute([$row['id']]);

    return LOGIN_OK;
}
