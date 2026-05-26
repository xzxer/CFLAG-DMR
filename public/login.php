<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth/session.php';
require_once dirname(__DIR__) . '/app/auth/login.php';

start_session();

if (is_logged_in()) {
    redirect('/');
}

$error       = '';
$show_resend = false;
$verified    = isset($_GET['verified']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid username or password.';
    } elseif (empty($_POST['username']) || empty($_POST['password'])) {
        $error = 'Invalid username or password.';
    } else {
        $result = attempt_login($_POST['username'], $_POST['password']);
        if ($result === LOGIN_OK) {
            redirect('/');
        } elseif ($result === LOGIN_UNVERIFIED) {
            $error       = 'Please verify your email address before logging in.';
            $show_resend = true;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$page_title = 'Sign In';
require_once dirname(__DIR__) . '/app/views/auth_header.php';
?>
<div class="auth-card">
    <div class="auth-brand">CFLAG DMR</div>
    <div class="auth-title">Sign In</div>

    <?php if ($verified): ?>
        <div class="alert alert-success">Email verified! You can now log in.</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($show_resend): ?>
            <p style="margin-bottom:0.75rem;font-size:0.78rem;">
                <a href="/resend-verification.php">Resend verification email</a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username"
                   autocomplete="username" autofocus required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" style="width:100%;">Sign in</button>
        </div>
    </form>

    <div class="auth-foot">
        <a href="/register.php">Create an account</a>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/app/views/auth_footer.php'; ?>
