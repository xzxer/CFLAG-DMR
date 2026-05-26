<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';

start_session();
require_login();

$page_title = 'User Directory';
$active_nav = 'users';
require_once $root . '/app/views/header.php';
?>

<div class="layout-single">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">User Directory</span>
        </div>
        <div class="empty-state">
            <strong>Coming Soon</strong>
            <span>The public user directory is planned for a future release (F12).</span>
        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
