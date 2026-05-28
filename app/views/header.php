<?php
declare(strict_types=1);
// Required vars from including page:
//   $page_title  (string) — shown in <title> and content-header
//   $active_nav  (string) — matches one of the sidebar nav keys
// Optional:
//   $header_meta (string) — HTML appended to the right side of content-header

if (!isset($page_title))  { $page_title  = 'CFLAG DMR'; }
if (!isset($active_nav))  { $active_nav  = ''; }
if (!isset($header_meta)) { $header_meta = ''; }

$_nav_user_id    = (int) ($_SESSION['user_id'] ?? 0);
$_nav_name       = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';
$_nav_callsign   = $_SESSION['callsign'] ?? '';
$_nav_is_admin   = $_nav_user_id > 0 && user_has_role($_nav_user_id, 'admin');
$_nav_is_sysadmin = $_nav_user_id > 0 && user_has_role($_nav_user_id, 'system_admin');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">

    <!-- ── Sidebar ──────────────────────────────────────────── -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-eyebrow">Network Portal</div>
            <div class="sidebar-brand-name">CFLAG DMR</div>
            <div class="sidebar-brand-sub">Amateur Radio Network</div>
        </div>

        <div class="sidebar-nav">

            <!-- Main -->
            <div class="sidebar-section">
                <span class="sidebar-section-label">Main</span>
                <a href="/index.php" class="sidebar-link<?= $active_nav === 'dashboard' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M1 6l7-5 7 5v8a1 1 0 0 1-1 1H9v-4H7v4H2a1 1 0 0 1-1-1V6z"/></svg>
                    Dashboard
                </a>
                <a href="/network-status.php" class="sidebar-link<?= $active_nav === 'network-status' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="2"/><path d="M2.5 8a5.5 5.5 0 0 1 5.5-5.5m5.5 5.5a5.5 5.5 0 0 1-5.5 5.5M13.5 5.5A7.5 7.5 0 0 1 8 13.5m0-11A7.5 7.5 0 0 1 13.5 8"/></svg>
                    Network Status
                </a>
                <a href="/last-heard.php" class="sidebar-link<?= $active_nav === 'last-heard' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zM7.25 4v4.5l3.5 2-.5.866L6 9.25V4h1.25z"/></svg>
                    Last Heard
                </a>
                <a href="/talkgroups.php" class="sidebar-link<?= $active_nav === 'talkgroups' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M7 1.5A5.5 5.5 0 0 0 1.5 7c0 1.8.87 3.4 2.2 4.4L2 14l3.2-.8A5.5 5.5 0 1 0 7 1.5z"/></svg>
                    Talkgroups
                </a>
                <a href="/users.php" class="sidebar-link<?= $active_nav === 'users' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-6 6a6 6 0 0 1 12 0H2z"/></svg>
                    User Directory
                </a>
            </div>

            <!-- My Account -->
            <div class="sidebar-section">
                <span class="sidebar-section-label">My Account</span>
                <a href="/user/profile.php" class="sidebar-link<?= $active_nav === 'my-profile' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1c-2.67 0-8 1.34-8 4v1h16v-1c0-2.66-5.33-4-8-4z"/></svg>
                    My Profile
                </a>
                <a href="/user/devices.php" class="sidebar-link<?= $active_nav === 'my-devices' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="4" width="10" height="8" rx="1"/><path d="M11 7h3l1 2v2h-4V7z"/><circle cx="3.5" cy="13.5" r="1.5"/><circle cx="9.5" cy="13.5" r="1.5"/><circle cx="13.5" cy="13.5" r="1.5"/></svg>
                    My Devices
                </a>
                <a href="/user/talkgroups.php" class="sidebar-link<?= $active_nav === 'my-talkgroups' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M7 1.5A5.5 5.5 0 0 0 1.5 7c0 1.8.87 3.4 2.2 4.4L2 14l3.2-.8A5.5 5.5 0 1 0 7 1.5z"/></svg>
                    My Talkgroups
                </a>
            </div>

            <?php if ($_nav_is_admin): ?>
            <div class="sidebar-divider"></div>

            <!-- Admin -->
            <div class="sidebar-section">
                <span class="sidebar-section-label">Admin</span>
                <a href="/admin/" class="sidebar-link<?= $active_nav === 'admin-home' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1L2 4v4c0 3.31 2.56 6.41 6 7 3.44-.59 6-3.69 6-7V4L8 1z"/></svg>
                    Admin Dashboard
                </a>
                <a href="/admin/users/" class="sidebar-link<?= $active_nav === 'admin-users' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-6 6a6 6 0 0 1 12 0H2z"/></svg>
                    Users
                </a>
                <a href="/admin/devices/" class="sidebar-link<?= $active_nav === 'admin-devices' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="4" width="10" height="8" rx="1"/><path d="M11 7h3l1 2v2h-4V7z"/><circle cx="3.5" cy="13.5" r="1.5"/><circle cx="9.5" cy="13.5" r="1.5"/><circle cx="13.5" cy="13.5" r="1.5"/></svg>
                    Devices
                </a>
                <a href="/admin/talkgroups/" class="sidebar-link<?= $active_nav === 'admin-talkgroups' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M7 1.5A5.5 5.5 0 0 0 1.5 7c0 1.8.87 3.4 2.2 4.4L2 14l3.2-.8A5.5 5.5 0 1 0 7 1.5z"/></svg>
                    Talkgroups
                </a>
                <?php if ($_nav_is_sysadmin): ?>
                <a href="/admin/subscribers/" class="sidebar-link<?= $active_nav === 'admin-subscribers' ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zm7-6a1 1 0 0 1 0-2 4 4 0 0 1 4 4 1 1 0 0 1-2 0 2 2 0 0 0-2-2z"/></svg>
                    Subscribers
                </a>
                <a href="/admin/config/" class="sidebar-link<?= in_array($active_nav, ['admin-config','admin-config-master','admin-config-openbridge','admin-config-history'], true) ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 5a3 3 0 1 0 0 6A3 3 0 0 0 8 5zm0 1.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3z"/><path d="M6.5 1l-.5 1.5-1.5.5L3 2 1 4l1 1.5-.5 1.5H0l.5 2H2l.5 1.5L1 12l2 2 1.5-1 1.5.5V15h2v-1.5L10 13l1.5 1 2-2-1-1.5.5-1.5H15l-.5-2H13l-.5-1.5L13 4l-2-2-1.5 1L8 2.5 7.5 1H6.5z"/></svg>
                    Network Config
                </a>
                <a href="/admin/hblink/status.php" class="sidebar-link<?= in_array($active_nav, ['admin-hblink','admin-hblink-config','admin-hblink-rules','admin-hblink-status'], true) ? ' active' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 4h2v8H2V4zm3 2h2v6H5V6zm3-3h2v9H8V3zm3 1h2v8h-2V4z"/></svg>
                    HBLink
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.sidebar-nav -->

        <div class="sidebar-user">
            <div class="sidebar-user-name"><?= htmlspecialchars($_nav_callsign ?: $_nav_name, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sidebar-user-sub"><?= $_nav_is_sysadmin ? 'System Admin' : ($_nav_is_admin ? 'Admin' : 'Member') ?></div>
            <div class="sidebar-user-actions">
                <a href="/user/profile.php">Profile</a>
                <a href="/logout.php">Log out</a>
            </div>
        </div>
    </nav><!-- /.sidebar -->

    <!-- ── Content Area ──────────────────────────────────────── -->
    <div class="content-area">
        <div class="content-header">
            <span class="content-title"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($header_meta): ?>
            <div class="content-header-meta"><?= $header_meta ?></div>
            <?php endif; ?>
        </div>
        <div class="content-body">
