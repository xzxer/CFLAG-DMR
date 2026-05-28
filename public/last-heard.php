<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/lastheard/reader.php';
require_once $root . '/app/subscribers/manager.php';

start_session();

$is_authed      = isset($_SESSION['user_id']);
$public_enabled = (bool)(int) get_lastheard_setting('public_lastheard_enabled');

if (!$is_authed && !$public_enabled) {
    header('Location: /login.php');
    exit;
}

$filters  = [];
$page     = 1;
$per_page = 50;

if ($is_authed) {
    $raw_callsign  = trim(substr($_GET['callsign']  ?? '', 0, 100));
    $raw_tg        = trim(substr($_GET['tg']        ?? '', 0, 100));
    $raw_date_from = trim(substr($_GET['date_from'] ?? '', 0, 20));
    $raw_date_to   = trim(substr($_GET['date_to']   ?? '', 0, 20));

    if ($raw_callsign  !== '') $filters['callsign']  = $raw_callsign;
    if ($raw_tg        !== '') $filters['tg']        = $raw_tg;
    if ($raw_date_from !== '') $filters['date_from'] = $raw_date_from;
    if ($raw_date_to   !== '') $filters['date_to']   = $raw_date_to;

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $per_page;

    $result = load_lastheard($per_page, $offset, $filters);
} else {
    $result = load_lastheard(20);
}

$rows        = $result['rows'];
$total       = $result['total'];
$error       = $result['error'];
$total_pages = ($is_authed && $per_page > 0) ? (int) ceil($total / $per_page) : 1;

$dmr_ids      = array_filter(array_map('intval', array_column($rows, 'src_id')));
$subscriber_map = !empty($dmr_ids) ? get_subscribers_for_ids($dmr_ids) : [];

function page_url(int $p): string
{
    $params = $_GET;
    unset($params['page']);
    if ($p > 1) {
        $params['page'] = $p;
    }
    return '/last-heard.php' . (count($params) ? '?' . http_build_query($params) : '');
}

// ── Authenticated view: full app shell ─────────────────────────────────────
if ($is_authed) {
    $page_title = 'Last Heard';
    $active_nav = 'last-heard';
    require_once $root . '/app/views/header.php';
    ?>

    <div class="layout-table-page">

        <?php if ($is_authed): ?>
        <div class="panel-compact" style="flex-shrink:0;">
            <form method="get" class="filter-bar">
                <label>Callsign</label>
                <input type="text" class="form-input" name="callsign" style="width:110px;"
                       value="<?= htmlspecialchars($raw_callsign ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label>Talkgroup</label>
                <input type="text" class="form-input" name="tg" style="width:130px;"
                       placeholder="ID or name"
                       value="<?= htmlspecialchars($raw_tg ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label>From</label>
                <input type="date" class="form-input" name="date_from" style="width:140px;"
                       value="<?= htmlspecialchars($raw_date_from ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label>To</label>
                <input type="date" class="form-input" name="date_to" style="width:140px;"
                       value="<?= htmlspecialchars($raw_date_to ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="/last-heard.php" class="btn btn-secondary btn-sm">Clear</a>
                <?php if (!empty($filters)): ?>
                <span style="font-size:0.72rem;color:var(--text-3);">
                    <?= $total ?> result<?= $total !== 1 ? 's' : '' ?>
                    <?= $total_pages > 1 ? '— page ' . $page . ' of ' . $total_pages : '' ?>
                </span>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <div class="panel" style="flex:1;min-height:0;">
            <?php if ($error !== null): ?>
            <div class="panel-body">
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php elseif (empty($rows)): ?>
            <div class="empty-state">
                <strong>No activity recorded yet</strong>
            </div>
            <?php else: ?>
            <div class="panel-body pad-none">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Callsign</th>
                            <th>DMR ID</th>
                            <th>Talkgroup</th>
                            <th>TG ID</th>
                            <th>Slot</th>
                            <th>System</th>
                            <th>Dur.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <?php $sub = $subscriber_map[(int)$row['src_id']] ?? null; ?>
                        <tr>
                            <td class="col-ts"><?= htmlspecialchars($row['datetime'],    ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-call"><?= htmlspecialchars($row['callsign'],  ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-mono" style="color:var(--accent-text);"><?= htmlspecialchars($row['src_id'], ENT_QUOTES, 'UTF-8') ?><?php if ($sub !== null && $sub['name'] !== ''): ?><br><span style="font-size:0.72rem;color:var(--text-3);font-family:inherit;"><?= htmlspecialchars($sub['name'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></td>
                            <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-mono"><?= htmlspecialchars($row['tgid'],      ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-mono"><?= htmlspecialchars($row['timeslot'],  ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['system_name'],                ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-ts"><?= htmlspecialchars(round((float)$row['duration']) . 's', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="panel-footer">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="<?= htmlspecialchars(page_url($page - 1), ENT_QUOTES, 'UTF-8') ?>">&larr;</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars(page_url($i), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="<?= htmlspecialchars(page_url($page + 1), ENT_QUOTES, 'UTF-8') ?>">&rarr;</a>
                    <?php endif; ?>
                </div>
                <span style="font-size:0.72rem;color:var(--text-3);">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

    </div><!-- /.layout-table-page -->

    <script>setTimeout(() => location.reload(), 30000);</script>

    <?php require_once $root . '/app/views/footer.php'; ?>
    <?php exit; ?>
<?php
}

// ── Public (non-authenticated) view ────────────────────────────────────────
$page_title = 'Last Heard';
require_once $root . '/app/views/auth_header.php';
?>
<div style="width:min(760px,100%);padding:1rem;">
    <div style="margin-bottom:1.25rem;display:flex;gap:1.25rem;align-items:center;">
        <div>
            <div style="font-size:0.62rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-3);">CFLAG DMR</div>
            <div style="font-size:1rem;font-weight:700;color:var(--text);">Last Heard</div>
        </div>
        <a href="/login.php" class="btn btn-primary btn-sm" style="margin-left:auto;">Sign in</a>
    </div>

    <?php if ($error !== null): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($rows)): ?>
    <p style="color:var(--text-3);font-size:0.835rem;">No activity recorded yet.</p>
    <?php else: ?>
    <div class="table-wrap" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius);">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Callsign</th>
                    <th>Talkgroup</th>
                    <th>System</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="col-ts"><?= htmlspecialchars($row['datetime'],    ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="col-call"><?= htmlspecialchars($row['callsign'],  ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : 'TG ' . $row['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['system_name'],                ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="font-size:0.68rem;color:var(--text-3);margin-top:0.5rem;">Auto-refreshes every 30 seconds</p>
    <?php endif; ?>
</div>
<meta http-equiv="refresh" content="30">
<?php require_once $root . '/app/views/auth_footer.php'; ?>
