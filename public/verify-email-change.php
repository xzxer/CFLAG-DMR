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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Email Change — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card" style="width:min(420px,100%);">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Email Change</h1>
            <div class="alert-error"><?= htmlspecialchars($result['error'] ?? 'Invalid link.', ENT_QUOTES, 'UTF-8') ?></div>
            <p style="margin-top:1.5rem;">
                <a href="/" class="nav-link">&#8592; Dashboard</a>
                <a href="/user/profile.php" class="nav-link" style="margin-left:1.5rem;">My Profile</a>
            </p>
        </section>
    </main>
</body>
</html>
