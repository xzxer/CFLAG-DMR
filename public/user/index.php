<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';

start_session();
require_login();

$name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Account — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>My Account</h1>
            <p class="muted">Welcome, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                <a href="/user/devices.php" class="nav-link">My Devices</a>
                <a href="/user/talkgroups.php" class="nav-link">Talkgroups</a>
                <a href="/logout.php" class="nav-link">Log out</a>
            </p>
        </section>
    </main>
</body>
</html>
