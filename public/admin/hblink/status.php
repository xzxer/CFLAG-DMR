<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';

start_session();
require_role('system_admin');

$status = get_hblink_status();

$page_title = 'HBLink Status';
$active_nav = 'admin-hblink';
require_once $root . '/app/views/header.php';
?>

<div class="layout-single">
    <div class="panel" style="align-self:start;max-width:480px;">
        <div class="panel-header">
            <span class="panel-title">Process Status</span>
            <div class="panel-actions">
                <a href="/admin/hblink/config.php" class="btn btn-ghost btn-xs">Config</a>
                <a href="/admin/hblink/rules.php" class="btn btn-ghost btn-xs">Rules</a>
            </div>
        </div>
        <div class="panel-body">

            <?php if ($status['config_drifted']): ?>
            <div class="alert alert-error" style="margin-bottom:1rem;">
                &#9888; Config file has been modified since HBLink was last started.
            </div>
            <?php endif; ?>

            <div class="field-list">
                <div class="field-row">
                    <span class="field-key">Status</span>
                    <span class="field-val">
                        <?= $status['running']
                            ? '<span class="badge badge-active">Running</span>'
                            : '<span class="badge badge-error">Stopped</span>' ?>
                    </span>
                </div>

                <?php if ($status['running']): ?>
                <div class="field-row">
                    <span class="field-key">PID</span>
                    <span class="field-val col-mono"><?= htmlspecialchars((string)$status['pid'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="field-row">
                    <span class="field-key">Uptime</span>
                    <span class="field-val">
                        <?= htmlspecialchars(
                            $status['uptime_seconds'] !== null
                                ? format_uptime($status['uptime_seconds'])
                                : 'Unknown',
                            ENT_QUOTES, 'UTF-8'
                        ) ?>
                    </span>
                </div>
                <div class="field-row">
                    <span class="field-key">Started</span>
                    <span class="field-val col-ts">
                        <?= htmlspecialchars(
                            $status['started_at'] !== null
                                ? date('Y-m-d H:i:s', $status['started_at'])
                                : 'Unknown',
                            ENT_QUOTES, 'UTF-8'
                        ) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once $root . '/app/views/footer.php'; ?>
