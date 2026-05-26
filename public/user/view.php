<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/profile/manager.php';

start_session();
require_login();

$viewer_id   = (int) $_SESSION['user_id'];
$target_id   = (int) ($_GET['id'] ?? 0);

if ($target_id <= 0 || $target_id === $viewer_id) {
    header($target_id === $viewer_id ? 'Location: /user/profile.php' : 'Location: /users');
    exit;
}

$user = get_profile($target_id);

if (!$user || $user['moderation_state'] === 'banned') {
    header('Location: /users');
    exit;
}

$is_admin = user_has_role('system_admin') || user_has_role('admin');

$show_name = $is_admin || !empty($user['show_name_publicly']);

$stmt = get_db()->prepare(
    'SELECT COUNT(*) FROM devices WHERE user_id = ? AND status = ?'
);
$stmt->execute([$target_id, 'approved']);
$device_count = (int) $stmt->fetchColumn();

$display_callsign = $user['callsign'] ?? $user['username'];

$page_title = htmlspecialchars($display_callsign, ENT_QUOTES, 'UTF-8');
$active_nav = 'users';
require_once $root . '/app/views/header.php';
?>

<div class="layout-single">
    <div class="panel" style="align-self:start;max-width:560px;">
        <div class="panel-header">
            <span class="panel-title col-mono" style="color:var(--accent-text);font-size:1.1rem;">
                <?= htmlspecialchars($display_callsign, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="panel-actions">
                <a href="/users" class="btn btn-ghost btn-xs">← Directory</a>
            </div>
        </div>
        <div class="panel-body">
            <div class="field-list">

                <div class="field-row">
                    <span class="field-key">Display Name</span>
                    <span class="field-val" style="font-weight:600;">
                        <?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <?php if ($show_name && ($user['first_name'] || $user['last_name'])): ?>
                <div class="field-row">
                    <span class="field-key">Name</span>
                    <span class="field-val">
                        <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($user['grid_square']): ?>
                <div class="field-row">
                    <span class="field-key">Grid Square</span>
                    <span class="field-val col-mono"><?= htmlspecialchars($user['grid_square'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>

                <div class="field-row">
                    <span class="field-key">Devices</span>
                    <span class="field-val"><?= $device_count ?> approved</span>
                </div>

                <?php if ($is_admin && $user['phone']): ?>
                <div class="field-row">
                    <span class="field-key">Phone <span style="font-size:0.7rem;color:var(--text-3);">(admin)</span></span>
                    <span class="field-val col-mono"><?= htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>

            </div>

            <?php if ($user['bio']): ?>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-1);">
                <p style="font-size:0.82rem;color:var(--text-2);line-height:1.65;white-space:pre-wrap;"><?= htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
