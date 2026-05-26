<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_role('system_admin');

$admin_id    = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $result = create_talkgroup($_POST, $admin_id);
            if ($result['ok']) {
                $flash_ok = 'Talkgroup created.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'edit') {
            $tg_id  = (int) ($_POST['tg_id'] ?? 0);
            $result = update_talkgroup($tg_id, $_POST, $admin_id);
            if ($result['ok']) {
                $flash_ok = 'Talkgroup updated.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'disable') {
            $tg_id = (int) ($_POST['tg_id'] ?? 0);
            set_talkgroup_active($tg_id, false, $admin_id);
            $flash_ok = 'Talkgroup disabled.';

        } elseif ($action === 'enable') {
            $tg_id = (int) ($_POST['tg_id'] ?? 0);
            set_talkgroup_active($tg_id, true, $admin_id);
            $flash_ok = 'Talkgroup enabled.';

        } elseif ($action === 'delete') {
            $tg_id  = (int) ($_POST['tg_id'] ?? 0);
            $result = delete_talkgroup($tg_id, $admin_id);
            if ($result['ok']) {
                $flash_ok = 'Talkgroup deleted.';
            } else {
                $flash_error = $result['error'];
            }
        }
    }

    header('Location: /admin/talkgroups/');
    exit;
}

$q          = trim($_GET['q'] ?? '');
$talkgroups = $q !== ''
    ? search_talkgroups($q)
    : get_all_talkgroups(true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Talkgroup Management — CFLAG DMR</title>
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
        <div class="card" style="background:#450a0a;color:#fca5a5;margin-bottom:1rem;padding:0.75rem 1rem;">
            <?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <section class="card">
            <p class="eyebrow">Admin</p>
            <h1>Talkgroup Management</h1>

            <form method="get" style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
                <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Search by name or TGID"
                       style="flex:1;min-width:180px;padding:0.5rem 0.75rem;background:#0f172a;border:1px solid #334155;color:#f8fafc;border-radius:6px;font-size:0.9rem;">
                <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                <?php if ($q !== ''): ?>
                <a href="/admin/talkgroups/" class="btn btn-secondary btn-sm" style="line-height:1.8;text-decoration:none;">Clear</a>
                <?php endif; ?>
            </form>

            <?php if (empty($talkgroups)): ?>
            <p class="muted" style="font-size:0.9rem;">
                <?= $q !== '' ? 'No talkgroups match your search.' : 'No talkgroups defined yet.' ?>
            </p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>TGID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Owner</th>
                            <th>Tier</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($talkgroups as $tg): ?>
                        <tr>
                            <td style="font-weight:700;color:#60a5fa;"><?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($tg['tg_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="muted" style="font-size:0.85rem;">
                                <?= htmlspecialchars($tg['owner_display_name'] ?: $tg['owner_username'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <span class="badge <?= $tg['ownership_tier'] === 'admin' ? 'badge-system-admin' : ($tg['ownership_tier'] === 'user_full' ? 'badge-active' : 'badge-muted') ?>">
                                    <?= htmlspecialchars($tg['ownership_tier'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($tg['active']): ?>
                                <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                <span class="badge badge-suspended">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td style="display:flex;gap:0.4rem;flex-wrap:wrap;padding-top:0.4rem;">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="<?= $tg['active'] ? 'disable' : 'enable' ?>">
                                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;">
                                        <?= $tg['active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <a href="/admin/talkgroups/edit.php?id=<?= (int)$tg['id'] ?>" class="nav-link" style="font-size:0.8rem;min-height:44px;display:inline-flex;align-items:center;">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete talkgroup <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(addslashes($tg['name']), ENT_QUOTES, 'UTF-8') ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;background:#450a0a;color:#fca5a5;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div style="margin-top:1.5rem;">
                <p class="section-title">New Talkgroup</p>
                <form method="post" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="flex:0 0 90px;margin-bottom:0;">
                        <label for="new_tgid">TGID</label>
                        <input type="text" id="new_tgid" name="tgid" maxlength="8" placeholder="e.g. 91" required>
                    </div>
                    <div class="form-group" style="flex:1 1 180px;margin-bottom:0;">
                        <label for="new_name">Name</label>
                        <input type="text" id="new_name" name="name" maxlength="128" placeholder="e.g. Worldwide" required>
                    </div>
                    <div class="form-group" style="flex:0 0 130px;margin-bottom:0;">
                        <label for="new_type">Type</label>
                        <select id="new_type" name="tg_type">
                            <option value="open">Open</option>
                            <option value="private">Private</option>
                            <option value="club">Club</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm" style="margin-bottom:0;">Create</button>
                </form>
            </div>

            <p style="margin-top:1.25rem;display:flex;gap:1.25rem;flex-wrap:wrap;font-size:0.9rem;">
                <a href="/admin/talkgroups/requests.php" class="nav-link">Talkgroup Requests &#8594;</a>
                <a href="/admin/" class="nav-link">← Dashboard</a>
            </p>
        </section>

    </main>
</body>
</html>
