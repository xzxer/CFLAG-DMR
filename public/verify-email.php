<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/env.php';
require_once dirname(__DIR__) . '/app/database/connection.php';
require_once dirname(__DIR__) . '/app/auth/session.php';

start_session();

$token = trim($_GET['token'] ?? '');

function _render_verify(string $page_title, string $heading, string $message, string $link_href = '', string $link_text = ''): void
{
    require_once dirname(__DIR__) . '/app/views/auth_header.php';
    ?>
    <div class="auth-card">
        <div class="auth-brand">CFLAG DMR</div>
        <div class="auth-title"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></div>
        <p style="font-size:0.835rem;color:var(--text-2);margin-bottom:1.25rem;">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php if ($link_href !== ''): ?>
            <a href="<?= htmlspecialchars($link_href, ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-primary" style="width:100%;display:block;text-align:center;">
                <?= htmlspecialchars($link_text, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    require_once dirname(__DIR__) . '/app/views/auth_footer.php';
}

if ($token === '') {
    _render_verify(
        'Invalid Link', 'Invalid Verification Link',
        'This verification link is invalid.',
        '/resend-verification.php', 'Request a new verification email'
    );
    exit;
}

$db   = get_db();
$stmt = $db->prepare(
    'SELECT id, user_id, expires_at, used_at FROM email_verifications WHERE token = ? LIMIT 1'
);
$stmt->execute([$token]);
$row = $stmt->fetch();

if ($row === false) {
    _render_verify(
        'Invalid Link', 'Invalid Verification Link',
        'This verification link is invalid or has already expired.',
        '/resend-verification.php', 'Request a new verification email'
    );
    exit;
}

if ($row['used_at'] !== null) {
    _render_verify(
        'Already Verified', 'Email Already Verified',
        'Your email address has already been verified.',
        '/login.php', 'Sign in'
    );
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    _render_verify(
        'Link Expired', 'Verification Link Expired',
        'This verification link has expired. Please request a new one.',
        '/resend-verification.php', 'Resend verification email'
    );
    exit;
}

$db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')
   ->execute([$row['user_id']]);

$db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')
   ->execute([$row['id']]);

redirect('/login.php?verified=1');
