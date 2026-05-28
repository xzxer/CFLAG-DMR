<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();

$is_authed  = isset($_SESSION['user_id']);
$talkgroups = get_public_talkgroups();

if ($is_authed) {
    $page_title = 'Talkgroups';
    $active_nav = 'talkgroups';
    require_once $root . '/app/views/header.php';
    ?>

    <div class="layout-single">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Network Talkgroups</span>
                <span class="panel-subtitle">Open talkgroups available on CFLAG DMR</span>
            </div>
            <div class="panel-body pad-none">
                <?php if (empty($talkgroups)): ?>
                <div class="empty-state">
                    <strong>No active talkgroups</strong>
                </div>
                <?php else: ?>
                <table class="data-table">
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
                            <td class="col-mono" style="color:var(--accent-text);font-weight:700;">
                                <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="font-weight:600;color:var(--text);">
                                <?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="color:var(--text-2);">
                                <?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    require_once $root . '/app/views/footer.php';
    exit;
}

// ── Public (non-authenticated) view ────────────────────────────────────────
$page_title = 'Talkgroups';
require_once $root . '/app/views/auth_header.php';
?>
<div style="width:min(640px,100%);padding:1rem;">
    <div style="margin-bottom:1.25rem;display:flex;gap:1.25rem;align-items:center;">
        <div>
            <div style="font-size:0.62rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-3);">CFLAG DMR</div>
            <div style="font-size:1rem;font-weight:700;color:var(--text);">Talkgroups</div>
        </div>
        <a href="/login.php" class="btn btn-primary btn-sm" style="margin-left:auto;">Sign in</a>
    </div>

    <?php if (empty($talkgroups)): ?>
    <p style="color:var(--text-3);font-size:0.835rem;">No active talkgroups at this time.</p>
    <?php else: ?>
    <div class="table-wrap" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius);">
        <table class="data-table">
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
                    <td class="col-mono" style="color:var(--accent-text);font-weight:700;">
                        <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td style="font-weight:600;color:var(--text);"><?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require_once $root . '/app/views/auth_footer.php'; ?>
