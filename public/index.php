<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';

start_session();
require_login();

$user_id     = (int) $_SESSION['user_id'];
$name        = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';
$is_admin    = user_has_role($user_id, 'admin');
$is_sysadmin = user_has_role($user_id, 'system_admin');
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
            <p class="muted">You are connected to the CFLAG DMR network portal.</p>
            <p style="margin-top:2rem;display:flex;gap:1.5rem;flex-wrap:wrap;">
                <a href="/user/devices.php" class="nav-link">My Devices</a>
                <a href="/user/talkgroups.php" class="nav-link">Talkgroups</a>
                <a href="/talkgroups.php" class="nav-link">Network Talkgroups</a>
                <a href="/network-status.php" class="nav-link">Network Status</a>
                <a href="/last-heard.php" class="nav-link">Last Heard</a>
                <a href="/logout.php" class="nav-link">Log out</a>
            </p>
        </section>

        <?php if ($is_admin): ?>
        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Administration</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Admin Panel</h1>
            <p class="muted" style="font-size:0.9rem;margin-bottom:1rem;">
                You have admin access. Manage users, devices, talkgroups, and server configuration below.
            </p>
            <p style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                <a href="/admin/" class="nav-link">Admin Dashboard &#8594;</a>
            </p>
        </section>
        <?php endif; ?>
    </main>
</body>
</html>
