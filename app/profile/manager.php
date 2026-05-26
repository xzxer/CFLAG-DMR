<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../auth/roles.php';

function get_profile(int $user_id): array|null
{
    $stmt = get_db()->prepare(
        'SELECT id, username, callsign, email, display_name, dmr_id,
                moderation_state, email_verified_at, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function update_display_name(int $user_id, string $display_name): array
{
    $display_name = trim($display_name);
    if ($display_name === '') {
        return ['ok' => false, 'error' => 'Display name cannot be empty.'];
    }
    if (mb_strlen($display_name) > 128) {
        return ['ok' => false, 'error' => 'Display name cannot exceed 128 characters.'];
    }

    get_db()->prepare('UPDATE users SET display_name = ? WHERE id = ?')
            ->execute([$display_name, $user_id]);

    if (isset($_SESSION['display_name'])) {
        $_SESSION['display_name'] = $display_name;
    }

    log_audit_action($user_id, 'display_name_updated', 'user', $user_id, []);
    return ['ok' => true, 'error' => null];
}

function change_password(int $user_id, string $current_password, string $new_password, string $confirm_password): array
{
    if ($new_password !== $confirm_password) {
        return ['ok' => false, 'error' => 'New password and confirmation do not match.'];
    }
    if (strlen($new_password) < 8) {
        return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
    }

    $stmt = get_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    if (!password_verify($current_password, $row['password_hash'])) {
        return ['ok' => false, 'error' => 'Current password is incorrect.'];
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    get_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$new_hash, $user_id]);

    log_audit_action($user_id, 'password_changed', 'user', $user_id, []);
    return ['ok' => true, 'error' => null];
}
