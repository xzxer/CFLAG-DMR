<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/users/directory.php';

start_session();
require_login();

$_uid     = (int) ($_SESSION['user_id'] ?? 0);
$is_admin = user_has_role($_uid, 'system_admin') || user_has_role($_uid, 'admin');
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 50;

if ($q !== '') {
    $users = search_directory_users($q, $page, $per_page, $is_admin);
    $total = count_search_results($q, $is_admin);
} else {
    $users = get_directory_users($page, $per_page, $is_admin);
    $total = count_directory_users($is_admin);
}

$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
$page        = min($page, $total_pages);

$page_title = 'User Directory';
$active_nav = 'users';
require_once $root . '/app/views/header.php';
?>

<div class="layout-table-page">

    <div class="table-page-header">
        <h1 class="page-title">User Directory</h1>
        <form method="get" class="filter-bar">
            <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Search callsign, username, or display name…"
                   class="filter-input" style="min-width:260px;" autocomplete="off">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($q !== ''): ?>
            <a href="/users.php" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="panel" style="flex:1;min-height:0;display:flex;flex-direction:column;">
        <div class="panel-header">
            <span class="panel-title">
                <?php if ($q !== ''): ?>
                    Results for "<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                <?php else: ?>
                    All Members
                <?php endif; ?>
            </span>
            <span style="font-size:0.75rem;color:var(--text-3);"><?= $total ?> user<?= $total !== 1 ? 's' : '' ?></span>
        </div>

        <div style="flex:1;overflow-y:auto;">
            <?php if (empty($users)): ?>
            <div style="padding:2rem;text-align:center;color:var(--text-3);font-size:0.85rem;">
                <?= $q !== '' ? 'No users match your search.' : 'No users in directory.' ?>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Callsign</th>
                        <th>Display Name</th>
                        <th>Grid Square</th>
                        <th style="text-align:right;">Devices</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <?php
                        $label     = htmlspecialchars($u['callsign'] ?: $u['username'], ENT_QUOTES, 'UTF-8');
                        $disp      = htmlspecialchars($u['display_name'] ?? '', ENT_QUOTES, 'UTF-8');
                        $grid      = htmlspecialchars($u['grid_square'] ?? '', ENT_QUOTES, 'UTF-8');
                        $show_name = !empty($u['show_name_publicly']) || $is_admin;
                        $full_name = $show_name ? trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) : '';
                    ?>
                    <tr>
                        <td>
                            <a href="/user/view.php?id=<?= (int) $u['id'] ?>" class="col-mono" style="color:var(--accent-text);font-weight:700;">
                                <?= $label ?>
                            </a>
                        </td>
                        <td>
                            <a href="/user/view.php?id=<?= (int) $u['id'] ?>" style="color:var(--text-1);">
                                <?= $disp ?>
                            </a>
                            <?php if ($full_name): ?>
                            <span style="font-size:0.75rem;color:var(--text-3);margin-left:0.4rem;"><?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-mono" style="color:var(--text-2);"><?= $grid ?></td>
                        <td style="text-align:right;color:var(--text-3);font-size:0.8rem;"><?= (int) $u['device_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="panel-footer">
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_filter(['q' => $q, 'page' => $page - 1])) ?>" class="btn btn-ghost btn-xs">← Prev</a>
                <?php endif; ?>
                <span style="font-size:0.78rem;color:var(--text-3);">Page <?= $page ?> of <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_filter(['q' => $q, 'page' => $page + 1])) ?>" class="btn btn-ghost btn-xs">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.panel -->

</div><!-- /.layout-table-page -->

<?php require_once $root . '/app/views/footer.php'; ?>
