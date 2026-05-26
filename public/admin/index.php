<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';
require_once $root . '/app/lastheard/reader.php';
require_once $root . '/app/devices/manager.php';
require_once $root . '/app/talkgroups/manager.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('admin');

$user_id              = (int) $_SESSION['user_id'];
$is_sysadmin          = user_has_role($user_id, 'system_admin');
$hblink_status        = $is_sysadmin ? get_hblink_status()                    : null;
$lh_result            = $is_sysadmin ? load_lastheard(8)                      : null;
$pending_device_count = $is_sysadmin ? count(get_pending_devices())           : 0;
$pending_tg_count     = $is_sysadmin ? count(get_pending_talkgroup_requests()) : 0;
$last_gen             = $is_sysadmin ? (get_generation_history(1)[0] ?? null)  : null;

$page_title = 'Admin Dashboard';
$active_nav = 'admin-home';
require_once $root . '/app/views/header.php';
?>

<div class="layout-dashboard">

    <!-- Left: recent activity -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Recent Calls</span>
            <div class="panel-actions">
                <a href="/last-heard.php" class="btn btn-ghost btn-xs">Full log</a>
            </div>
        </div>
        <div class="panel-body pad-none">
            <?php if ($lh_result !== null && !empty($lh_result['rows'])): ?>
            <table class="data-table">
                <thead>
                    <tr><th>Callsign</th><th>Talkgroup</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lh_result['rows'] as $row): ?>
                    <tr>
                        <td class="col-call"><?= htmlspecialchars($row['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : 'TG '.$row['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-ts"><?= htmlspecialchars($row['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><strong>No recent calls</strong></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Middle: pending approvals -->
    <div class="col-stack">

        <div class="panel-compact">
            <div class="stat-label" style="margin-bottom:0.5rem;">Pending Devices</div>
            <?php if ($pending_device_count > 0): ?>
            <div style="font-size:1.4rem;font-weight:700;color:var(--amber);"><?= $pending_device_count ?></div>
            <a href="/admin/devices/" style="font-size:0.72rem;display:block;margin-top:0.25rem;">Review &rarr;</a>
            <?php else: ?>
            <div style="font-size:0.78rem;color:var(--text-3);">None pending</div>
            <a href="/admin/devices/" style="font-size:0.72rem;display:block;margin-top:0.25rem;">Device list &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="panel-compact">
            <div class="stat-label" style="margin-bottom:0.5rem;">Pending Talkgroups</div>
            <?php if ($pending_tg_count > 0): ?>
            <div style="font-size:1.4rem;font-weight:700;color:var(--amber);"><?= $pending_tg_count ?></div>
            <a href="/admin/talkgroups/requests.php" style="font-size:0.72rem;display:block;margin-top:0.25rem;">Review &rarr;</a>
            <?php else: ?>
            <div style="font-size:0.78rem;color:var(--text-3);">None pending</div>
            <a href="/admin/talkgroups/" style="font-size:0.72rem;display:block;margin-top:0.25rem;">Talkgroup list &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="panel-compact" style="flex:1;">
            <div class="stat-label" style="margin-bottom:0.5rem;">Admin Links</div>
            <div style="display:flex;flex-direction:column;gap:0.375rem;">
                <a href="/admin/users/" style="font-size:0.78rem;color:var(--text-2);">User Management</a>
                <a href="/admin/devices/" style="font-size:0.78rem;color:var(--text-2);">Device Approvals</a>
                <a href="/admin/talkgroups/" style="font-size:0.78rem;color:var(--text-2);">Talkgroups</a>
                <?php if ($is_sysadmin): ?>
                <a href="/admin/config/" style="font-size:0.78rem;color:var(--text-2);">Network Config</a>
                <a href="/admin/hblink/status.php" style="font-size:0.78rem;color:var(--text-2);">HBLink Status</a>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.col-stack -->

    <!-- Right: server status -->
    <div class="col-stack">

        <?php if ($hblink_status !== null): ?>
        <div class="panel-compact">
            <div class="stat-label">HBLink Server</div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.375rem;">
                <?php if ($hblink_status['running']): ?>
                <span class="dot green"></span>
                <span style="font-size:0.835rem;font-weight:600;color:var(--green);">Running</span>
                <?php else: ?>
                <span class="dot red"></span>
                <span style="font-size:0.835rem;font-weight:600;color:var(--red);">Stopped</span>
                <?php endif; ?>
            </div>
            <?php if ($hblink_status['running'] && $hblink_status['uptime_seconds'] !== null): ?>
            <div style="font-size:0.68rem;color:var(--text-3);margin-top:0.2rem;">
                Up <?= htmlspecialchars(format_uptime($hblink_status['uptime_seconds']), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <?php if ($hblink_status['config_drifted']): ?>
            <div class="drift-warning" style="margin-top:0.5rem;font-size:0.68rem;">Config drifted since last start</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($is_sysadmin): ?>
        <div class="panel-compact">
            <div class="stat-label" style="margin-bottom:0.375rem;">Network Config</div>
            <?php if ($last_gen !== null): ?>
            <div style="font-size:0.72rem;color:var(--text-3);">
                Last generated
                <span style="color:var(--text-2);"><?= htmlspecialchars($last_gen['generated_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div style="margin-top:0.25rem;">
                <?= $last_gen['changed'] ? '<span class="badge badge-green">Changed</span>' : '<span class="badge badge-gray">No change</span>' ?>
            </div>
            <?php else: ?>
            <div style="font-size:0.72rem;color:var(--text-3);">Not yet generated</div>
            <?php endif; ?>
            <a href="/admin/config/" style="font-size:0.72rem;display:block;margin-top:0.375rem;">Config Generator &rarr;</a>
        </div>
        <?php endif; ?>

    </div><!-- /.col-stack -->

</div><!-- /.layout-dashboard -->

<?php require_once $root . '/app/views/footer.php'; ?>
