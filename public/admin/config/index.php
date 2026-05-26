<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('system_admin');

$actor_id    = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'generate') {
        $result = generate_hblink_config($actor_id);
        if ($result['ok']) {
            $flash_ok = $result['changed']
                ? 'Config generated and written (changes detected).'
                : 'Config generated — no changes since last generation.';
        } else {
            $flash_error = $result['error'] ?? 'Config generation failed.';
        }
    }
    header('Location: /admin/config/');
    exit;
}

$history = get_generation_history(5);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Network Config — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <main class="page">

        <?php if ($flash_ok !== null): ?>
        <div class="card" style="background:#14532d;color:#bbf7d0;margin-bottom:1rem;padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <?php if ($flash_error !== null): ?>
        <div class="card" style="background:#7f1d1d;color:#fecaca;margin-bottom:1rem;padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section class="card">
            <p class="eyebrow">Network Config</p>
            <h1>HBLink Configuration</h1>
            <p class="muted">Generate the HBLink config file from the current database state — master settings, approved peers, and OpenBridge connections.</p>
            <p style="margin-top:1.5rem; display:flex; gap:1.25rem; flex-wrap:wrap;">
                <a href="/admin/config/master.php" class="nav-link">Master Settings</a>
                <a href="/admin/config/openbridge.php" class="nav-link">OpenBridge</a>
                <a href="/admin/config/history.php" class="nav-link">Generation History</a>
                <a href="/admin/" class="nav-link">&#8592; Admin</a>
            </p>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Generate</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Write Config to Disk</h1>
            <p class="muted" style="font-size:0.9rem;margin-bottom:1.25rem;">
                Reads master settings, all approved device IDs, and enabled OpenBridge connections from the database
                and writes the config file to the server. The previous file is replaced atomically.
            </p>
            <form method="post" action="/admin/config/">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="generate">
                <button type="submit" class="btn">Generate Config Now</button>
            </form>
        </section>

        <?php if (!empty($history)): ?>
        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Recent</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Last Generations</h1>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Generated</th>
                            <th>By</th>
                            <th>Changed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['generated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($row['changed']): ?>
                                <span class="badge badge-pending">Changed</span>
                                <?php else: ?>
                                <span class="badge" style="background:#374151;color:#d1d5db;">No change</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="/admin/config/history.php?id=<?= (int) $row['id'] ?>" class="nav-link" style="font-size:0.85rem;">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:0.75rem;">
                <a href="/admin/config/history.php" class="nav-link">Full History &#8594;</a>
            </p>
        </section>
        <?php else: ?>
        <section class="card" style="margin-top:1.5rem;">
            <p class="muted" style="font-size:0.9rem;">No config has been generated yet. Use the button above to generate the first one.</p>
        </section>
        <?php endif; ?>

    </main>
</body>
</html>
