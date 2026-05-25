<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../database/connection.php';

function validate_registration(array $data): array
{
    $errors = [];

    $callsign     = strtoupper(trim($data['callsign'] ?? ''));
    $dmr_id       = trim($data['dmr_id'] ?? '');
    $email        = trim($data['email'] ?? '');
    $password     = $data['password'] ?? '';
    $display_name = trim($data['display_name'] ?? '');

    if ($callsign === '' || !preg_match('/^[A-Z]{1,2}[0-9][A-Z]{1,3}$/', $callsign)) {
        $errors['callsign'] = 'Enter a valid amateur callsign (e.g. W1AW, VE3XYZ).';
    }

    if ($dmr_id === '' || !ctype_digit($dmr_id) || strlen($dmr_id) !== 7) {
        $errors['dmr_id'] = 'DMR ID must be exactly 7 digits.';
    }

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (strlen($password) < 12) {
        $errors['password'] = 'Password must be at least 12 characters.';
    }

    if (strlen($display_name) < 2 || strlen($display_name) > 64) {
        $errors['display_name'] = 'Display name must be 2–64 characters.';
    }

    // Only check uniqueness if basic format is valid — avoids DB query on obviously bad input
    if (empty($errors)) {
        $db   = get_db();
        $stmt = $db->prepare(
            'SELECT 1 FROM users WHERE UPPER(username) = ? OR email = ? OR dmr_id = ? LIMIT 1'
        );
        $stmt->execute([$callsign, $email, (int) $dmr_id]);
        if ($stmt->fetch()) {
            $errors['callsign'] = 'That callsign, email, or DMR ID is already in use.';
        }
    }

    return $errors;
}

function is_radioid_validation_enabled(): bool
{
    $db  = get_db();
    $val = $db->prepare("SELECT `value` FROM system_settings WHERE `key` = 'radioid_validation_enabled' LIMIT 1");
    $val->execute();
    $row = $val->fetchColumn();
    return $row === '1';
}

function lookup_radioid(int $dmr_id): ?array
{
    $url = 'https://www.radioid.net/api/dmr/user/?id=' . $dmr_id;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'CFLAG-DMR/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err !== '' || $body === false) {
        throw new \RuntimeException('RadioID.net lookup failed: ' . $err);
    }
    if ($code !== 200) {
        throw new \RuntimeException('RadioID.net returned HTTP ' . $code);
    }

    $data    = json_decode($body, true);
    $results = $data['results'] ?? [];

    if (empty($results)) {
        return null;
    }

    return [
        'callsign' => (string) ($results[0]['callsign'] ?? ''),
        'id'       => (int)    ($results[0]['id']       ?? 0),
    ];
}

function register_user(array $data): int
{
    $callsign     = strtoupper(trim($data['callsign']));
    $email        = trim($data['email']);
    $display_name = trim($data['display_name']);
    $dmr_id       = (int) $data['dmr_id'];
    $hash         = password_hash($data['password'], PASSWORD_BCRYPT);

    $db   = get_db();
    $stmt = $db->prepare(
        'INSERT INTO users
             (username, callsign, email, password_hash, display_name, dmr_id,
              moderation_state, tier, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, \'active\', \'free\', NOW(), NOW())'
    );
    $stmt->execute([$callsign, $callsign, $email, $hash, $display_name, $dmr_id]);

    return (int) $db->lastInsertId();
}

function create_verification_token(int $user_id): string
{
    $token = bin2hex(random_bytes(32));
    $db    = get_db();
    $db->prepare(
        'INSERT INTO email_verifications (user_id, token, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
    )->execute([$user_id, $token]);

    return $token;
}
