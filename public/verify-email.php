<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/env.php';
require_once dirname(__DIR__) . '/app/database/connection.php';
require_once dirname(__DIR__) . '/app/auth/session.php';

start_session();

$token = trim($_GET['token'] ?? '');

function render_verify_page(string $title, string $heading, string $message, string $link_href = '', string $link_text = ''): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — CFLAG DMR</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/app.css">
    </head>
    <body>
        <main class="page">
            <section class="card" style="width: min(520px, 100%);">
                <p class="eyebrow">CFLAG DMR</p>
                <h1><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($link_href !== ''): ?>
                    <p style="margin-top: 1.5rem;">
                        <a href="<?= htmlspecialchars($link_href, ENT_QUOTES, 'UTF-8') ?>" class="nav-link">
                            <?= htmlspecialchars($link_text, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </p>
                <?php endif; ?>
            </section>
        </main>
    </body>
    </html>
    <?php
}

if ($token === '') {
    render_verify_page(
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
    render_verify_page(
        'Invalid Link', 'Invalid Verification Link',
        'This verification link is invalid or has already expired.',
        '/resend-verification.php', 'Request a new verification email'
    );
    exit;
}

if ($row['used_at'] !== null) {
    render_verify_page(
        'Already Verified', 'Email Already Verified',
        'Your email address has already been verified.',
        '/login.php', 'Sign in'
    );
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    render_verify_page(
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
