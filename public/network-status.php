<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/hblink/process.php';
require_once $root . '/app/lastheard/reader.php';

start_session();
require_login();

$user_id     = (int) $_SESSION['user_id'];
$is_sysadmin = user_has_role($user_id, 'system_admin');

$hblink_status = get_hblink_status();
$active_peers  = get_recently_active_dmr_ids(30);
$lh_result     = load_lastheard(20);

$page_title = 'Network Status';
$active_nav = 'network-status';
require_once $root . '/app/views/header.php';
?>

<div class="layout-dashboard">

    <!-- Server status -->
    <div class="col-stack">
        <div class="panel-compact">
            <div class="stat-label">HBLink Server</div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;">
                <?php if ($hblink_status['running']): ?>
                <span class="dot green"></span>
                <span style="font-size:1rem;font-weight:700;color:var(--green);">Running</span>
                <?php else: ?>
                <span class="dot red"></span>
                <span style="font-size:1rem;font-weight:700;color:var(--red);">Stopped</span>
                <?php endif; ?>
            </div>
            <?php if ($hblink_status['running'] && $hblink_status['uptime_seconds'] !== null): ?>
            <div style="font-size:0.78rem;color:var(--text-3);margin-top:0.25rem;">
                Uptime: <?= htmlspecialchars(format_uptime($hblink_status['uptime_seconds']), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
            <?php if ($hblink_status['config_drifted'] && $is_sysadmin): ?>
            <div class="drift-warning" style="margin-top:0.75rem;">
                &#9888; Config modified since last HBLink start — consider regenerating before reload
            </div>
            <?php endif; ?>
        </div>

        <?php if ($is_sysadmin): ?>
        <div class="panel-compact">
            <div class="stat-label" style="margin-bottom:0.5rem;">Admin Tools</div>
            <div style="display:flex;flex-direction:column;gap:0.375rem;">
                <a href="/admin/hblink/status.php" style="font-size:0.78rem;color:var(--text-2);">Process Details</a>
                <a href="/admin/hblink/config.php" style="font-size:0.78rem;color:var(--text-2);">Config File</a>
                <a href="/admin/hblink/rules.php"  style="font-size:0.78rem;color:var(--text-2);">Rules File</a>
                <a href="/admin/config/"           style="font-size:0.78rem;color:var(--text-2);">Config Generator</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Active devices -->
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

    <!-- Recent calls -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Recent Calls</span>
            <div class="panel-actions">
                <a href="/last-heard.php" class="btn btn-ghost btn-xs">Full log</a>
            </div>
        </div>
        <div class="panel-body pad-none">
            <?php if (!empty($lh_result['rows'])): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Callsign</th>
                        <th>Talkgroup</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lh_result['rows'] as $row): ?>
                    <tr>
                        <td class="col-call"><?= htmlspecialchars($row['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : 'TG ' . $row['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="col-ts"><?= htmlspecialchars($row['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <strong>No recent calls</strong>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.layout-dashboard -->

<script>setTimeout(() => location.reload(), 30000);</script>

<?php require_once $root . '/app/views/footer.php'; ?>
