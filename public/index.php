<?php
declare(strict_types=1);

$appName = 'CFLAG DMR';
$environment = 'development';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">Development Server</p>
            <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>The CFLAG DMR development server is Live and connected.</p>
            <p class="muted">Environment: <code><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></code></p>
        </section>
    </main>
</body>
</html>
