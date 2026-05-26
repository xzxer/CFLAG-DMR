<?php
declare(strict_types=1);
// Auth layout — no sidebar, centered card
// Required vars from including page:
//   $page_title (string)
if (!isset($page_title)) { $page_title = 'CFLAG DMR'; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body">
