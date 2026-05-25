<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/env.php';
require_once dirname(__DIR__) . '/app/database/connection.php';
require_once dirname(__DIR__) . '/app/auth/session.php';
require_once dirname(__DIR__) . '/app/email/mailer.php';

start_session();

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        // Silently show success to prevent enumeration even on bad CSRF
        $success = true;
    } else {
        $email = trim($_POST['email'] ?? '');

        $db = get_db();

        // Rate limit: one resend per email per 5 minutes
        $rate = $db->prepare(
            'SELECT MAX(ev.created_at) AS last_sent
               FROM email_verifications ev
               JOIN users u ON u.id = ev.user_id
              WHERE u.email = ?'
        );
        $rate->execute([$email]);
        $last_sent = $rate->fetchColumn();

        $within_limit = $last_sent !== null && strtotime((string) $last_sent) > time() - 300;

        if (!$within_limit) {
            // Look up unverified account
            $user_stmt = $db->prepare(
                'SELECT id, email, display_name FROM users
                  WHERE email = ? AND email_verified_at IS NULL LIMIT 1'
            );
            $user_stmt->execute([$email]);
            $user = $user_stmt->fetch();

            if ($user) {
                // Invalidate all existing unused tokens
                $db->prepare(
                    'UPDATE email_verifications SET used_at = NOW()
                      WHERE user_id = ? AND used_at IS NULL'
                )->execute([$user['id']]);

                // Create new token
                $token = bin2hex(random_bytes(32));
                $db->prepare(
                    'INSERT INTO email_verifications (user_id, token, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
                )->execute([$user['id'], $token]);

                send_verification_email($user['email'], $user['display_name'], $token);
            }
        }

        $success = true;
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Resend Verification — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card" style="width: min(520px, 100%);">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Resend Verification Email</h1>

            <?php if ($success): ?>
                <div class="alert-success">
                    If that email address is registered and unverified, a new verification link has been sent. Check your inbox.
                </div>
                <p style="margin-top: 1rem;">
                    <a href="/login.php" class="nav-link">Back to sign in</a>
                </p>
            <?php else: ?>
                <p class="muted" style="margin-bottom: 1.5rem; font-size: 0.9rem;">
                    Enter the email address you registered with and we'll send a new verification link.
                </p>

                <form method="post" action="/resend-verification.php">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               autocomplete="email" required autofocus>
                    </div>

                    <button type="submit" class="btn">Send Verification Email</button>
                </form>

                <p style="margin-top: 1rem;">
                    <a href="/login.php" class="nav-link">Back to sign in</a>
                </p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
