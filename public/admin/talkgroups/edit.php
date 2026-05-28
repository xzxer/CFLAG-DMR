<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_role('system_admin');

$admin_id = (int) $_SESSION['user_id'];
$tg_id    = (int) ($_GET['id'] ?? 0);
$tg       = $tg_id > 0 ? get_talkgroup($tg_id) : null;

if (!$tg) {
    header('Location: /admin/talkgroups/');
    exit;
}

$flash_error = null;

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

$page_title = 'Edit Talkgroup';
$active_nav = 'admin-talkgroups';
require_once $root . '/app/views/header.php';
?>

<div class="layout-single">
    <div class="panel" style="align-self:start;max-width:540px;">
        <div class="panel-header">
            <span class="panel-title">Edit Talkgroup — TG <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?></span>
            <div class="panel-actions">
                <a href="/admin/talkgroups/" class="btn btn-ghost btn-xs">← Talkgroups</a>
            </div>
        </div>
        <div class="panel-body">
            <?php if ($flash_error !== null): ?>
            <div class="alert alert-error" style="margin-bottom:0.75rem;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="field-list" style="margin-bottom:0.875rem;">
                    <div class="field-row">
                        <span class="field-key">TGID</span>
                        <span class="field-val col-mono" style="color:var(--accent-text);">
                            <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" maxlength="128" required
                           value="<?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" maxlength="2000"><?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tg_type">Type</label>
                    <select id="tg_type" name="tg_type" class="form-select">
                        <?php foreach (['open', 'private', 'club'] as $t): ?>
                        <option value="<?= $t ?>" <?= $tg['tg_type'] === $t ? 'selected' : '' ?>>
                            <?= ucfirst($t) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="/admin/talkgroups/" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
