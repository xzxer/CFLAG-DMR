<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/lastheard/reader.php';

start_session();

$is_authed      = isset($_SESSION['user_id']);
$public_enabled = (bool)(int) get_lastheard_setting('public_lastheard_enabled');

if (!$is_authed && !$public_enabled) {
    header('Location: /login.php');
    exit;
}

// Build filters and pagination for authenticated view
$filters  = [];
$page     = 1;
$per_page = 50;
$offset   = 0;

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

$rows       = $result['rows'];
$total      = $result['total'];
$error      = $result['error'];
$total_pages = ($is_authed && $per_page > 0) ? (int)ceil($total / $per_page) : 1;

// Build a URL preserving current GET params but swapping page
function page_url(int $p): string
{
    $params = $_GET;
    $params['page'] = $p;
    unset($params['page']);
    if ($p > 1) $params['page'] = $p;
    return '/last-heard.php' . (count($params) ? '?' . http_build_query($params) : '');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Last Heard — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!$is_authed): ?>
    <meta http-equiv="refresh" content="30">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page" style="align-items: flex-start; padding: 2rem 1rem;">
        <section class="card" style="width: min(960px, 100%);">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Last Heard</h1>

            <?php if ($error !== null): ?>
            <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

            <?php elseif (empty($rows)): ?>
            <p class="muted">No activity recorded yet.</p>

            <?php else: ?>

            <?php if ($is_authed): ?>
            <form method="get" style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.25rem; align-items:flex-end;">
                <div>
                    <label style="display:block; font-size:0.8rem; color:#94a3b8; margin-bottom:0.25rem;">Callsign</label>
                    <input type="text" name="callsign" value="<?= htmlspecialchars($raw_callsign ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:0.4rem 0.6rem;border-radius:6px;font-size:0.875rem;width:120px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:#94a3b8; margin-bottom:0.25rem;">Talkgroup</label>
                    <input type="text" name="tg" value="<?= htmlspecialchars($raw_tg ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="ID or name"
                           style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:0.4rem 0.6rem;border-radius:6px;font-size:0.875rem;width:130px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:#94a3b8; margin-bottom:0.25rem;">From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($raw_date_from ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:0.4rem 0.6rem;border-radius:6px;font-size:0.875rem;">
                </div>
                <div>
                    <label style="display:block; font-size:0.8rem; color:#94a3b8; margin-bottom:0.25rem;">To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($raw_date_to ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           style="background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:0.4rem 0.6rem;border-radius:6px;font-size:0.875rem;">
                </div>
                <div style="display:flex;gap:0.5rem;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary" style="min-height:44px;">Filter</button>
                    <a href="/last-heard.php" class="btn btn-secondary" style="min-height:44px;display:inline-flex;align-items:center;">Clear</a>
                </div>
            </form>
            <?php if ($is_authed && !empty($filters)): ?>
            <p class="muted" style="font-size:0.82rem; margin-bottom:0.75rem;">
                <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> found
                <?= $total_pages > 1 ? '— page ' . $page . ' of ' . $total_pages : '' ?>
            </p>
            <?php endif; ?>
            <?php endif; ?>

            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Callsign</th>
                            <th>DMR ID</th>
                            <th>TG Name</th>
                            <th>TG ID</th>
                            <th>Slot</th>
                            <th>System</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['datetime'],    ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="callsign"><?= htmlspecialchars($row['callsign'],    ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['src_id'],      ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tg_name'] !== '' ? $row['tg_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['tgid'],        ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['timeslot'],    ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['system_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(round((float)$row['duration']) . 's', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($is_authed && $total_pages > 1): ?>
            <p style="display:flex; gap:1rem; align-items:center; margin-top:0.75rem; flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars(page_url($page - 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="min-height:44px;">&#8592; Prev</a>
                <?php endif; ?>
                <span class="muted" style="font-size:0.875rem;">Page <?= $page ?> of <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="<?= htmlspecialchars(page_url($page + 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="min-height:44px;">Next &#8594;</a>
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <?php endif; ?>

            <p style="margin-top:1.5rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
                <?php if ($is_authed): ?>
                <a href="/admin/" class="nav-link">&#8592; Dashboard</a>
                <?php else: ?>
                <a href="/" class="nav-link">&#8592; Home</a>
                <a href="/login.php" class="nav-link">Log in</a>
                <?php endif; ?>
            </p>
        </section>
    </main>

    <?php if ($is_authed): ?>
    <script>setTimeout(() => location.reload(), 30000);</script>
    <?php endif; ?>
</body>
</html>
