<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/session.php';
require_once $root . '/app/profile/email_change.php';

start_session();

$token  = $_GET['token'] ?? '';
$result = confirm_email_change($token);

if ($result['ok']) {
    $_SESSION['_flash_ok'] = 'Email address updated successfully.';
    if (is_logged_in()) {
        header('Location: /user/profile.php');
    } else {
        header('Location: /login.php?verified=1');
    }
    exit;
}

$page_title = 'Email Change';
require_once $root . '/app/views/auth_header.php';
?>
<div class="auth-card">
    <div class="auth-brand">CFLAG DMR</div>
    <div class="auth-title">Email Change</div>
    <div class="alert alert-error">
        <?= htmlspecialchars($result['error'] ?? 'Invalid link.', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div style="display:flex;gap:1rem;margin-top:1rem;justify-content:center;font-size:0.78rem;">
        <a href="/">Dashboard</a>
        <a href="/user/profile.php">My Profile</a>
    </div>
</div>
<?php require_once $root . '/app/views/auth_footer.php'; ?>
