<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/lastheard/reader.php';
require_once $root . '/app/hblink/process.php';

start_session();
require_login();

$user_id     = (int) $_SESSION['user_id'];
$name        = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';
$is_admin    = user_has_role($user_id, 'admin');
$is_sysadmin = user_has_role($user_id, 'system_admin');

$lh_result     = load_lastheard(15);
$active_peers  = get_recently_active_dmr_ids(30);
$hblink_status = get_hblink_status();

$page_title = 'Dashboard';
$active_nav = 'dashboard';

if ($is_sysadmin) {
    require_once $root . '/app/config/generator.php';
    $last_gen = get_generation_history(1)[0] ?? null;
}

require_once $root . '/app/views/header.php';
?>

<div class="layout-dashboard">

    <!-- Left: Recent calls -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Recent Calls</span>
            <div class="panel-actions">
                <a href="/last-heard.php" class="btn btn-ghost btn-xs">View all</a>
            </div>
        </div>
        <div class="panel-body pad-none">
            <?php if (!empty($lh_result['rows'])): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Callsign</th>
                        <th>Talkgroup</th>
                        <th>Slot</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lh_result['rows'] as $row): ?>
                    <tr>
                        <td class="col-call"><?= htmlspecialchars($row['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : 'TG ' . $row['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-mono"><?= htmlspecialchars($row['timeslot'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-ts"><?= htmlspecialchars($row['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <strong>No calls yet</strong>
                <span>Activity will appear here once devices connect</span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($lh_result['error'])): ?>
        <div class="panel-footer">
            <span style="font-size:0.72rem;color:var(--red);">
                <?= htmlspecialchars($lh_result['error'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Middle: Active peers -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Active Devices</span>
            <span class="panel-subtitle">Last 30 min</span>
        </div>
        <div class="panel-body pad-none">
            <?php if (!empty($active_peers)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Callsign</th>
                        <th>DMR ID</th>
                        <th>Last Seen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_peers as $peer): ?>
                    <tr>
                        <td class="col-call">
                            <?= htmlspecialchars($peer['callsign'] !== '' ? $peer['callsign'] : '—', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="col-mono" style="color:var(--accent-text);">
                            <?= htmlspecialchars($peer['dmr_id'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="col-ts"><?= htmlspecialchars($peer['last_seen'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <strong>No active devices</strong>
                <span>Devices active in the last 30 minutes will appear here</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Status sidebar -->
    <div class="col-stack">

        <!-- Server status -->
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
            <?php if ($hblink_status['config_drifted'] && $is_sysadmin): ?>
            <div class="drift-warning" style="margin-top:0.5rem;font-size:0.68rem;">
                Config drifted since last start
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick links -->
        <div class="panel-compact">
            <div class="stat-label" style="margin-bottom:0.5rem;">Quick Links</div>
            <div style="display:flex;flex-direction:column;gap:0.25rem;">
                <a href="/user/profile.php" style="font-size:0.78rem;color:var(--text-2);">My Profile</a>
                <a href="/user/devices.php" style="font-size:0.78rem;color:var(--text-2);">My Devices</a>
                <a href="/user/talkgroups.php" style="font-size:0.78rem;color:var(--text-2);">My Talkgroups</a>
                <a href="/network-status.php" style="font-size:0.78rem;color:var(--text-2);">Network Status</a>
                <a href="/last-heard.php" style="font-size:0.78rem;color:var(--text-2);">Last Heard</a>
                <a href="/talkgroups.php" style="font-size:0.78rem;color:var(--text-2);">Talkgroups</a>
            </div>
        </div>

        <?php if ($is_sysadmin): ?>
        <!-- Config status -->
        <div class="panel-compact">
            <div class="stat-label" style="margin-bottom:0.375rem;">Network Config</div>
            <?php if ($last_gen !== null): ?>
            <div style="font-size:0.72rem;color:var(--text-3);">
                Last generated
                <span style="color:var(--text-2);">
                    <?= htmlspecialchars($last_gen['generated_at'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <div style="margin-top:0.25rem;">
                <?php if ($last_gen['changed']): ?>
                <span class="badge badge-green">Changed</span>
                <?php else: ?>
                <span class="badge badge-gray">No change</span>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="font-size:0.72rem;color:var(--text-3);">Not yet generated</div>
            <?php endif; ?>
            <a href="/admin/config/" style="font-size:0.72rem;display:block;margin-top:0.5rem;">
                Config Generator &rarr;
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /.col-stack -->

</div><!-- /.layout-dashboard -->

<script>setTimeout(() => location.reload(), 30000);</script>

<?php require_once $root . '/app/views/footer.php'; ?>
