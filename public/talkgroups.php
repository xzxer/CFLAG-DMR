<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/session.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();

$talkgroups = get_public_talkgroups();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Talkgroups — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">
        <section class="card">
            <p class="eyebrow">CFLAG DMR</p>
            <h1>Talkgroups</h1>
            <p class="muted">Active open talkgroups available on the CFLAG DMR network.</p>

            <?php if (empty($talkgroups)): ?>
            <p class="muted" style="font-size:0.9rem;margin-top:1rem;">No active talkgroups at this time.</p>
            <?php else: ?>
            <div class="lh-table-wrap" style="margin-top:1rem;">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>TGID</th>
                            <th>Name</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($talkgroups as $tg): ?>
                        <tr>
                            <td style="font-weight:700;color:#60a5fa;"><?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="muted" style="font-size:0.85rem;">
                                <?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <p style="margin-top:1.5rem;display:flex;gap:1.5rem;flex-wrap:wrap;">
                <a href="/last-heard.php" class="nav-link">Last Heard</a>
                <a href="/" class="nav-link">← Home</a>
            </p>
        </section>
    </main>
</body>
</html>
