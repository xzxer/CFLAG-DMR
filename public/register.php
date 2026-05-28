<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/env.php';
require_once dirname(__DIR__) . '/app/auth/session.php';
require_once dirname(__DIR__) . '/app/auth/roles.php';
require_once dirname(__DIR__) . '/app/registration/register.php';
require_once dirname(__DIR__) . '/app/email/mailer.php';

start_session();

if (is_logged_in()) {
    redirect('/admin/');
}

$errors  = [];
$old     = [];
$success = isset($_GET['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors['form'] = 'Invalid request. Please try again.';
    } else {
        $errors = validate_registration($_POST);
        $old    = $_POST;

        if (empty($errors) && is_radioid_validation_enabled()) {
            $dmr_id = (int) ($_POST['dmr_id'] ?? 0);
            try {
                $rid = lookup_radioid($dmr_id);
                if ($rid === null) {
                    $errors['dmr_id'] = 'DMR ID not found in RadioID.net database.';
                } elseif (strtoupper($rid['callsign']) !== strtoupper(trim($_POST['callsign'] ?? ''))) {
                    $errors['callsign'] = 'The callsign and DMR ID do not match RadioID.net records.';
                }
            } catch (\RuntimeException $e) {
                error_log('[register] RadioID lookup failed: ' . $e->getMessage());
            }
        }

        if (empty($errors)) {
            $user_id = register_user($_POST);
            $token   = create_verification_token($user_id);
            send_verification_email(trim($_POST['email']), trim($_POST['display_name'] ?? ''), $token);
            redirect('/register.php?success=1');
        }
    }
}

$csrf = csrf_token();

function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="form-hint" style="color:var(--red)">' . htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') . '</p>';
}

function old_val(array $old, string $field): string
{
    return htmlspecialchars($old[$field] ?? '', ENT_QUOTES, 'UTF-8');
}

$page_title = $success ? 'Check Your Email' : 'Create Account';
require_once dirname(__DIR__) . '/app/views/auth_header.php';
?>
<div class="auth-card" style="max-width:460px;">
    <div class="auth-brand">CFLAG DMR</div>

    <?php if ($success): ?>
        <div class="auth-title">Check Your Email</div>
        <p style="font-size:0.835rem;color:var(--text-2);margin-bottom:1.25rem;">
            Your account has been created. A verification link has been sent to your email address.
            Click the link to activate your account, then sign in.
        </p>
        <a href="/login.php" class="btn btn-primary" style="width:100%;display:block;text-align:center;">Go to sign in</a>

    <?php else: ?>
        <div class="auth-title">Create Account</div>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="/register.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="callsign">Callsign</label>
                    <input type="text" id="callsign" name="callsign"
                           value="<?= old_val($old, 'callsign') ?>"
                           autocomplete="off" required placeholder="e.g. W1AW">
                    <?= field_error($errors, 'callsign') ?>
                </div>
                <div class="form-group">
                    <label for="dmr_id">DMR ID</label>
                    <input type="text" id="dmr_id" name="dmr_id"
                           value="<?= old_val($old, 'dmr_id') ?>"
                           autocomplete="off" required placeholder="7-digit ID">
                    <?= field_error($errors, 'dmr_id') ?>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= old_val($old, 'email') ?>"
                       autocomplete="email" required>
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name"
                       value="<?= old_val($old, 'display_name') ?>"
                       autocomplete="name" required minlength="2" maxlength="64">
                <?= field_error($errors, 'display_name') ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" required minlength="12">
                <p class="form-hint">Minimum 12 characters</p>
                <?= field_error($errors, 'password') ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" style="width:100%;">Create Account</button>
            </div>
        </form>

        <div class="auth-foot">
            Already have an account? <a href="/login.php">Sign in</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/app/views/auth_footer.php'; ?>
