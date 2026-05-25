<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth/session.php';
require_once dirname(__DIR__) . '/app/auth/login.php';

start_session();

if (is_admin()) {
    redirect('/admin/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid username or password.';
    } elseif (empty($_POST['username']) || empty($_POST['password'])) {
        $error = 'Invalid username or password.';
    } elseif (attempt_login($_POST['username'], $_POST['password'])) {
        redirect('/admin/');
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card" style="width: min(420px, 100%);">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Admin Login</h1>

            <?php if ($error !== ''): ?>
                <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="/login.php">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           autocomplete="username" autofocus required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn">Sign in</button>
            </form>
        </section>
    </main>
</body>
</html>
