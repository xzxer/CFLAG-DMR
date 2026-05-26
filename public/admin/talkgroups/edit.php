<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_role('system_admin');

$admin_id    = (int) $_SESSION['user_id'];
$tg_id       = (int) ($_GET['id'] ?? 0);
$tg          = $tg_id > 0 ? get_talkgroup($tg_id) : null;
$flash_ok    = null;
$flash_error = null;

if (!$tg) {
    header('Location: /admin/talkgroups/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $result = update_talkgroup($tg_id, $_POST, $admin_id);
        if ($result['ok']) {
            header('Location: /admin/talkgroups/');
            exit;
        }
        $flash_error = $result['error'];
        $tg = get_talkgroup($tg_id);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit Talkgroup — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">Admin</p>
            <h1>Edit Talkgroup</h1>

            <?php if ($flash_error !== null): ?>
            <div class="alert-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="field-row" style="margin-bottom:0.75rem;">
                    <span class="field-label">TGID</span>
                    <span class="field-value"><?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" maxlength="128" required
                           value="<?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" maxlength="2000"
                              style="min-height:80px;"><?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tg_type">Type</label>
                    <select id="tg_type" name="tg_type">
                        <?php foreach (['open', 'private', 'club'] as $t): ?>
                        <option value="<?= $t ?>" <?= $tg['tg_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn">Save Changes</button>
            </form>

            <p style="margin-top:1rem;">
                <a href="/admin/talkgroups/" class="nav-link">← Back to Talkgroups</a>
            </p>
        </section>
    </main>
</body>
</html>
