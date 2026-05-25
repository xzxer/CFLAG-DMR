<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/auth/roles.php';

start_session();
require_role('admin');

$name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dashboard — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Welcome, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="muted">You are logged in to the CFLAG DMR admin dashboard.</p>
            <p style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                <?php if (user_has_role((int) $_SESSION['user_id'], 'system_admin')): ?>
                    <a href="/admin/users/" class="nav-link">User Management</a>
                <?php endif; ?>
                <a href="/logout.php" class="nav-link">Log out</a>
            </p>
        </section>
    </main>
</body>
</html>
