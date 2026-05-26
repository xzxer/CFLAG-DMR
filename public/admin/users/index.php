<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/profile/callsign.php';

start_session();
require_role('system_admin');

$db = get_db();

$filter_verified = isset($_GET['verified']) ? (string) $_GET['verified'] : null;

$per_page = 25;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = $filter_verified === '0' ? 'WHERE u.email_verified_at IS NULL' : '';

$total = (int) $db->query("SELECT COUNT(*) FROM users u {$where}")->fetchColumn();
$pages = (int) ceil($total / $per_page);

$stmt = $db->prepare(
    "SELECT u.id, u.username, u.display_name, u.moderation_state,
            u.email_verified_at, u.last_login_at, u.created_at,
            GROUP_CONCAT(r.name ORDER BY r.sort_order SEPARATOR ',') AS roles
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r       ON r.id = ur.role_id
     {$where}
     GROUP BY u.id
     ORDER BY u.id
     LIMIT ? OFFSET ?"
);
$stmt->execute([$per_page, $offset]);
$users = $stmt->fetchAll();

$pending_cs_count = count(get_pending_callsign_requests());

$state_badge = [
    'active'           => 'badge-active',
    'muted_on_network' => 'badge-gray',
    'suspended'        => 'badge-amber',
    'banned'           => 'badge-red',
];
$state_label = [
    'active'           => 'Active',
    'muted_on_network' => 'Muted',
    'suspended'        => 'Suspended',
    'banned'           => 'Banned',
];
$role_badge = [
    'system_admin' => 'badge-system-admin',
    'admin'        => 'badge-admin',
    'moderator'    => 'badge-blue',
    'user'         => 'badge-gray',
];

$page_title = 'User Management';
$active_nav = 'admin-users';
require_once $root . '/app/views/header.php';
?>

<div class="layout-table-page">

    <div class="panel-compact" style="flex-shrink:0;">
        <div class="filter-bar">
            <a href="/admin/users/"
               class="btn btn-sm <?= $filter_verified === null ? 'btn-primary' : 'btn-secondary' ?>">
               All Users
            </a>
            <a href="/admin/users/?verified=0"
               class="btn btn-sm <?= $filter_verified === '0' ? 'btn-primary' : 'btn-secondary' ?>">
               Unverified
            </a>
            <a href="/admin/users/callsign-requests.php" class="btn btn-sm btn-secondary">
                Callsign Requests
                <?php if ($pending_cs_count > 0): ?>
                <span class="sidebar-link-badge amber"><?= $pending_cs_count ?></span>
                <?php endif; ?>
            </a>
            <span style="margin-left:auto;font-size:0.72rem;color:var(--text-3);">
                <?= $total ?> user<?= $total !== 1 ? 's' : '' ?>
            </span>
        </div>
    </div>

    <div class="panel" style="flex:1;min-height:0;">
        <div class="panel-body pad-none">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Display Name</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-3);">No users found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <a href="/admin/users/view.php?id=<?= (int) $u['id'] ?>">
                                <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($u['display_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php foreach (array_filter(explode(',', (string) $u['roles'])) as $role): ?>
                            <span class="badge <?= htmlspecialchars($role_badge[$role] ?? 'badge-gray', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php $st = $u['moderation_state']; ?>
                            <span class="badge <?= htmlspecialchars($state_badge[$st] ?? 'badge-gray', ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($state_label[$st] ?? $st, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($u['email_verified_at'] === null): ?>
                            <span class="badge badge-unverified">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-ts">
                            <?= $u['last_login_at']
                                ? htmlspecialchars($u['last_login_at'], ENT_QUOTES, 'UTF-8')
                                : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="panel-footer">
            <div class="pagination">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <?php $pqs = $filter_verified !== null ? '&verified=' . htmlspecialchars($filter_verified, ENT_QUOTES, 'UTF-8') : ''; ?>
                <?php if ($p === $page): ?>
                <span class="current"><?= $p ?></span>
                <?php else: ?>
                <a href="?page=<?= $p . $pqs ?>"><?= $p ?></a>
                <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.layout-table-page -->

<?php require_once $root . '/app/views/footer.php'; ?>
