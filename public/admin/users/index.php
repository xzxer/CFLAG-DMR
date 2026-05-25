<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/auth/roles.php';

start_session();
require_role('system_admin');

$db = get_db();

$per_page = 25;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$total = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$pages = (int) ceil($total / $per_page);

$stmt = $db->prepare(
    'SELECT u.id, u.username, u.display_name, u.moderation_state,
            u.last_login_at, u.created_at,
            GROUP_CONCAT(r.name ORDER BY r.sort_order SEPARATOR ",") AS roles
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r       ON r.id = ur.role_id
     GROUP BY u.id
     ORDER BY u.id
     LIMIT ? OFFSET ?'
);
$stmt->execute([$per_page, $offset]);
$users = $stmt->fetchAll();

$state_badge = [
    'active'           => 'badge-active',
    'muted_on_network' => 'badge-muted',
    'suspended'        => 'badge-suspended',
    'banned'           => 'badge-banned',
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
    'moderator'    => 'badge-moderator',
    'user'         => 'badge-user',
];

$name = $_SESSION['display_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>User Management — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page" style="align-items: start; padding: 2rem;">
        <section class="card" style="width: min(960px, 100%);">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>User Management</h1>

            <p style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.9rem; margin-bottom: 1.5rem;">
                <a href="/admin/" class="nav-link">← Dashboard</a>
                <a href="/logout.php" class="nav-link">Log out</a>
            </p>

            <div class="table-wrap">
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
                        <?php if (count($users) === 0): ?>
                            <tr><td colspan="5" style="color:#94a3b8;">No users found.</td></tr>
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
                                            <span class="badge <?= htmlspecialchars($role_badge[$role] ?? 'badge-user', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php $st = $u['moderation_state']; ?>
                                        <span class="badge <?= htmlspecialchars($state_badge[$st] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($state_label[$st] ?? $st, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="muted">
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
                <div class="pagination">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <p class="muted" style="margin-top: 1rem; font-size: 0.85rem;">
                <?= $total ?> user<?= $total !== 1 ? 's' : '' ?> total
            </p>
        </section>
    </main>
</body>
</html>
