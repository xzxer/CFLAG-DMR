<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../auth/roles.php';
require_once __DIR__ . '/../email/mailer.php';
require_once __DIR__ . '/../config/env.php';

function request_email_change(int $user_id, string $new_email): array
{
    $new_email = trim(strtolower($new_email));
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }

    $db = get_db();

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$new_email, $user_id]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'That email address is already in use.'];
    }

    $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'User not found.'];
    }

    if (strtolower($user['email']) === $new_email) {
        return ['ok' => false, 'error' => 'That is already your current email address.'];
    }

    $token      = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + 86400);

    $db->prepare(
        'INSERT INTO email_change_requests (user_id, new_email, token, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE new_email = VALUES(new_email), token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()'
    )->execute([$user_id, $new_email, $token, $expires_at]);

    _send_email_change_verification($new_email, $token);
    log_audit_action($user_id, 'email_change_requested', 'user', $user_id, ['new_email' => $new_email]);
    return ['ok' => true, 'error' => null];
}

function confirm_email_change(string $token): array
{
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, user_id, new_email, expires_at FROM email_change_requests WHERE token = ?'
    );
    $stmt->execute([$token]);
    $req = $stmt->fetch();

    if ($req === false) {
        return ['ok' => false, 'error' => 'Invalid or already-used verification link.'];
    }

    if (strtotime($req['expires_at']) < time()) {
        $db->prepare('DELETE FROM email_change_requests WHERE id = ?')->execute([$req['id']]);
        return ['ok' => false, 'error' => 'This verification link has expired. Please request a new email change.'];
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$req['new_email'], $req['user_id']]);
    if ($stmt->fetch()) {
        $db->prepare('DELETE FROM email_change_requests WHERE id = ?')->execute([$req['id']]);
        return ['ok' => false, 'error' => 'That email address has been taken by another account.'];
    }

    $db->prepare('UPDATE users SET email = ? WHERE id = ?')
       ->execute([$req['new_email'], $req['user_id']]);
    $db->prepare('DELETE FROM email_change_requests WHERE id = ?')->execute([$req['id']]);

    log_audit_action((int) $req['user_id'], 'email_changed', 'user', (int) $req['user_id'], [
        'new_email' => $req['new_email'],
    ]);
    return ['ok' => true, 'error' => null];
}

function get_pending_email_change(int $user_id): array|null
{
    $stmt = get_db()->prepare(
        'SELECT new_email, expires_at FROM email_change_requests WHERE user_id = ?'
    );
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cancel_email_change(int $user_id): void
{
    get_db()->prepare('DELETE FROM email_change_requests WHERE user_id = ?')->execute([$user_id]);
}

function _send_email_change_verification(string $to_email, string $token): void
{
    $app_url = rtrim((string) env('APP_URL', 'http://localhost'), '/');
    $link    = $app_url . '/verify-email-change.php?token=' . urlencode($token);

    if ((bool) env('EMAIL_DEV_MODE', false)) {
        $line = '[' . date('Y-m-d H:i:s') . '] EMAIL_CHANGE TO: ' . $to_email
              . ' | LINK: ' . $link . PHP_EOL;
        file_put_contents('/var/log/cflag-dmr-email-dev.log', $line, FILE_APPEND | LOCK_EX);
        return;
    }

    $subject = 'Confirm your new CFLAG DMR email address';
    $body    = "You requested to change your email address to this address.\r\n\r\n"
             . "Please confirm by visiting the link below:\r\n\r\n"
             . "{$link}\r\n\r\n"
             . "This link expires in 24 hours.\r\n\r\n"
             . "If you did not request this change, you can safely ignore this email.\r\n";

    try {
        require_once __DIR__ . '/../email/smtp.php';
        smtp_send(
            host:         (string) env('MAIL_HOST',         'localhost'),
            port:         (int)    env('MAIL_PORT',         587),
            username:     (string) env('MAIL_USERNAME',     ''),
            password:     (string) env('MAIL_PASSWORD',     ''),
            from_address: (string) env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
            from_name:    (string) env('MAIL_FROM_NAME',    'CFLAG DMR'),
            to_address:   $to_email,
            subject:      $subject,
            body:         $body
        );
    } catch (\RuntimeException $e) {
        error_log('CFLAG DMR email_change mailer: ' . $e->getMessage());
    }
}
