<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../auth/roles.php';

function validate_callsign_format(string $callsign): bool
{
    return (bool) preg_match('/^[A-Z0-9]{3,10}$/', strtoupper($callsign));
}

function submit_callsign_request(int $user_id, string $new_callsign, string $explanation): array
{
    $new_callsign = strtoupper(trim($new_callsign));
    if (!validate_callsign_format($new_callsign)) {
        return ['ok' => false, 'error' => 'Invalid callsign format. Use uppercase letters and digits, 3–10 characters.'];
    }

    $db = get_db();

    $stmt = $db->prepare(
        "SELECT id FROM callsign_update_requests WHERE user_id = ? AND status = 'pending'"
    );
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'You already have a pending callsign update request.'];
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE callsign = ? AND id != ?');
    $stmt->execute([$new_callsign, $user_id]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'That callsign is already in use by another account.'];
    }

    $stmt = $db->prepare('SELECT callsign FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $old_callsign = $user ? $user['callsign'] : null;

    $db->prepare(
        'INSERT INTO callsign_update_requests (user_id, old_callsign, requested_callsign, explanation)
         VALUES (?, ?, ?, ?)'
    )->execute([$user_id, $old_callsign, $new_callsign, trim($explanation)]);

    log_audit_action($user_id, 'callsign_request_submitted', 'user', $user_id, [
        'requested_callsign' => $new_callsign,
    ]);
    return ['ok' => true, 'error' => null];
}

function get_user_callsign_requests(int $user_id): array
{
    $stmt = get_db()->prepare(
        'SELECT id, old_callsign, requested_callsign, status, review_notes, reviewed_at, created_at
         FROM callsign_update_requests
         WHERE user_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_pending_callsign_requests(): array
{
    $stmt = get_db()->prepare(
        "SELECT r.id, r.user_id, r.old_callsign, r.requested_callsign, r.explanation, r.created_at,
                u.username, u.email
         FROM callsign_update_requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.status = 'pending'
         ORDER BY r.created_at ASC"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_open_callsign_request(int $user_id): array|null
{
    $stmt = get_db()->prepare(
        "SELECT id, requested_callsign, created_at FROM callsign_update_requests
         WHERE user_id = ? AND status = 'pending'
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function approve_callsign_request(int $request_id, int $admin_id, string $notes = ''): array
{
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT id, user_id, requested_callsign, status FROM callsign_update_requests WHERE id = ?"
    );
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();

    if ($req === false) {
        return ['ok' => false, 'error' => 'Request not found.'];
    }
    if ($req['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'Request is no longer pending.'];
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE callsign = ? AND id != ?');
    $stmt->execute([$req['requested_callsign'], $req['user_id']]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'That callsign was claimed by another account before approval.'];
    }

    try {
        $db->beginTransaction();

        $db->prepare(
            "UPDATE callsign_update_requests
             SET status = 'approved', reviewed_by_user_id = ?, review_notes = ?, reviewed_at = NOW()
             WHERE id = ?"
        )->execute([$admin_id, $notes, $request_id]);

        $db->prepare('UPDATE users SET callsign = ? WHERE id = ?')
           ->execute([$req['requested_callsign'], $req['user_id']]);

        $db->prepare("UPDATE devices SET callsign = ? WHERE user_id = ? AND status = 'approved'")
           ->execute([$req['requested_callsign'], $req['user_id']]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'Database error during approval.'];
    }

    log_audit_action($admin_id, 'callsign_request_approved', 'user', (int) $req['user_id'], [
        'request_id'         => $request_id,
        'new_callsign'       => $req['requested_callsign'],
    ]);
    return ['ok' => true, 'error' => null];
}

function deny_callsign_request(int $request_id, int $admin_id, string $notes): array
{
    $db = get_db();
    $stmt = $db->prepare(
        "SELECT id, user_id, status FROM callsign_update_requests WHERE id = ?"
    );
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();

    if ($req === false) {
        return ['ok' => false, 'error' => 'Request not found.'];
    }
    if ($req['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'Request is no longer pending.'];
    }

    $db->prepare(
        "UPDATE callsign_update_requests
         SET status = 'denied', reviewed_by_user_id = ?, review_notes = ?, reviewed_at = NOW()
         WHERE id = ?"
    )->execute([$admin_id, $notes, $request_id]);

    log_audit_action($admin_id, 'callsign_request_denied', 'user', (int) $req['user_id'], [
        'request_id' => $request_id,
        'notes'      => $notes,
    ]);
    return ['ok' => true, 'error' => null];
}
