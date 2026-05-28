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
        $success = true;
    } else {
        $email = trim($_POST['email'] ?? '');
        $db    = get_db();

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
            $user_stmt = $db->prepare(
                'SELECT id, email, display_name FROM users
                  WHERE email = ? AND email_verified_at IS NULL LIMIT 1'
            );
            $user_stmt->execute([$email]);
            $user = $user_stmt->fetch();

            if ($user) {
                $db->prepare(
                    'UPDATE email_verifications SET used_at = NOW()
                      WHERE user_id = ? AND used_at IS NULL'
                )->execute([$user['id']]);

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

$csrf       = csrf_token();
$page_title = 'Resend Verification';
require_once dirname(__DIR__) . '/app/views/auth_header.php';
?>
<div class="auth-card">
    <div class="auth-brand">CFLAG DMR</div>
    <div class="auth-title">Resend Verification</div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            If that email address is registered and unverified, a new verification link has been sent.
        </div>
        <a href="/login.php" class="btn btn-secondary" style="width:100%;display:block;text-align:center;margin-top:0.75rem;">Back to sign in</a>
    <?php else: ?>
        <p style="font-size:0.78rem;color:var(--text-2);margin-bottom:1.25rem;">
            Enter your registered email and we'll send a new verification link.
        </p>

        <form method="post" action="/resend-verification.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       autocomplete="email" required autofocus>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width:100%;">Send Verification Email</button>
            </div>
        </form>

        <div class="auth-foot">
            <a href="/login.php">Back to sign in</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/app/views/auth_footer.php'; ?>
