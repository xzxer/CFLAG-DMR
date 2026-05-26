<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/config/generator.php';

start_session();
require_role('system_admin');

$detail_id = (int) ($_GET['id'] ?? 0);

if ($detail_id > 0) {
    $detail = get_generation_detail($detail_id);
    if ($detail === null) {
        header('Location: /admin/config/history.php');
        exit;
    }
} else {
    $detail  = null;
    $history = get_generation_history(50);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Generation History — CFLAG DMR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .config-block {
            background: #111827;
            border: 1px solid #374151;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.82rem;
            line-height: 1.5;
            white-space: pre-wrap;
            overflow-x: auto;
            color: #d1d5db;
            max-height: 500px;
            overflow-y: auto;
        }
        .diff-line-removed { color: #f87171; }
        .diff-line-added   { color: #86efac; }
    </style>
</head>
<body>
    <main class="page">

        <?php if ($detail !== null): ?>

        <section class="card">
            <p class="eyebrow">Network Config</p>
            <h1>Generation Detail</h1>
            <div class="field-row">
                <span class="field-label">Generated</span>
                <span class="field-value muted"><?= htmlspecialchars($detail['generated_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">By</span>
                <span class="field-value"><?= htmlspecialchars($detail['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="field-row">
                <span class="field-label">Result</span>
                <span class="field-value">
                    <?php if ($detail['changed']): ?>
                    <span class="badge badge-pending">Changed</span>
                    <?php else: ?>
                    <span class="badge" style="background:#374151;color:#d1d5db;">No change</span>
                    <?php endif; ?>
                </span>
            </div>
            <p style="margin-top:1.25rem; display:flex; gap:1.25rem; flex-wrap:wrap;">
                <a href="/admin/config/history.php" class="nav-link">&#8592; History</a>
                <a href="/admin/config/" class="nav-link">Config</a>
            </p>
        </section>

        <?php if ($detail['diff_text'] !== null && $detail['diff_text'] !== ''): ?>
        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Diff</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Changes From Previous</h1>
            <div class="config-block"><?php
                foreach (explode("\n", $detail['diff_text']) as $line) {
                    $escaped = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
                    if (str_starts_with($line, '- ')) {
                        echo '<span class="diff-line-removed">' . $escaped . '</span>' . "\n";
                    } elseif (str_starts_with($line, '+ ')) {
                        echo '<span class="diff-line-added">' . $escaped . '</span>' . "\n";
                    } else {
                        echo $escaped . "\n";
                    }
                }
            ?></div>
        </section>
        <?php endif; ?>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Config</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.75rem;">Full Config Text</h1>
            <div class="config-block"><?= htmlspecialchars($detail['config_text'], ENT_QUOTES, 'UTF-8') ?></div>
        </section>

        <?php else: ?>

        <section class="card">
            <p class="eyebrow">Network Config</p>
            <h1>Generation History</h1>
            <p class="muted">Each time the HBLink config was generated from the database.</p>
            <p style="margin-top:1.25rem;">
                <a href="/admin/config/" class="nav-link">&#8592; Config</a>
            </p>
        </section>

        <?php if (!empty($history)): ?>
        <section class="card" style="margin-top:1.5rem;">
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Generated</th>
                            <th>By</th>
                            <th>Changed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                        <tr>
                            <td class="muted" style="font-size:0.85rem;"><?= (int) $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['generated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['actor_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($row['changed']): ?>
                                <span class="badge badge-pending">Changed</span>
                                <?php else: ?>
                                <span class="badge" style="background:#374151;color:#d1d5db;">No change</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/admin/config/history.php?id=<?= (int) $row['id'] ?>" class="nav-link" style="font-size:0.85rem;">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php else: ?>
        <section class="card" style="margin-top:1.5rem;">
            <p class="muted" style="font-size:0.9rem;">No generations recorded yet.</p>
        </section>
        <?php endif; ?>

        <?php endif; ?>

    </main>
</body>
</html>
