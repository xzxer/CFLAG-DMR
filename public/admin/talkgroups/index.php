<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_role('system_admin');

$admin_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $result = create_talkgroup($_POST, $admin_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Talkgroup created.' : $result['error'];
        } elseif ($action === 'disable') {
            set_talkgroup_active((int)($_POST['tg_id'] ?? 0), false, $admin_id);
            $_SESSION['_flash_ok'] = 'Talkgroup disabled.';
        } elseif ($action === 'enable') {
            set_talkgroup_active((int)($_POST['tg_id'] ?? 0), true, $admin_id);
            $_SESSION['_flash_ok'] = 'Talkgroup enabled.';
        } elseif ($action === 'delete') {
            $result = delete_talkgroup((int)($_POST['tg_id'] ?? 0), $admin_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Talkgroup deleted.' : $result['error'];
        }
    }
    header('Location: /admin/talkgroups/');
    exit;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$q          = trim($_GET['q'] ?? '');
$talkgroups = $q !== '' ? search_talkgroups($q) : get_all_talkgroups(true);

$page_title = 'Talkgroup Management';
$active_nav = 'admin-talkgroups';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-table-page">

    <div class="panel-compact" style="flex-shrink:0;">
        <div class="filter-bar" style="flex-wrap:wrap;gap:0.625rem;">
            <form method="get" class="filter-bar" style="flex:1;">
                <input type="text" class="form-input" name="q"
                       value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Search by name or TGID"
                       style="flex:1;min-width:160px;">
                <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                <?php if ($q !== ''): ?>
                <a href="/admin/talkgroups/" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </form>
            <a href="/admin/talkgroups/requests.php" class="btn btn-secondary btn-sm">Requests</a>

            <!-- Quick create -->
            <form method="post" class="filter-bar" style="flex:none;margin-left:auto;">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="text" class="form-input" name="tgid" maxlength="8"
                       placeholder="TGID" required style="width:72px;">
                <input type="text" class="form-input" name="name" maxlength="128"
                       placeholder="Name" required style="width:150px;">
                <select class="form-select" name="tg_type" style="width:90px;">
                    <option value="open">Open</option>
                    <option value="private">Private</option>
                    <option value="club">Club</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
            </form>
        </div>
    </div>

    <div class="panel" style="flex:1;min-height:0;">
        <div class="panel-body pad-none">
            <?php if (empty($talkgroups)): ?>
            <div class="empty-state">
                <strong><?= $q !== '' ? 'No talkgroups match your search' : 'No talkgroups defined yet' ?></strong>
            </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>TGID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Owner</th>
                        <th>Tier</th>
                        <th>Status</th>
                        <th class="col-actions">Actions</th>
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
                        <td><span class="badge badge-gray"><?= htmlspecialchars(ucfirst($tg['tg_type']), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="col-ts">
                            <?= htmlspecialchars($tg['owner_display_name'] ?: $tg['owner_username'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <span class="badge <?= $tg['ownership_tier'] === 'admin' ? 'badge-system-admin' : ($tg['ownership_tier'] === 'user_full' ? 'badge-active' : 'badge-gray') ?>">
                                <?= htmlspecialchars($tg['ownership_tier'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?= $tg['active']
                                ? '<span class="badge badge-active">Active</span>'
                                : '<span class="badge badge-amber">Disabled</span>' ?>
                        </td>
                        <td class="col-actions">
                            <div class="action-row" style="justify-content:flex-end;">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="<?= $tg['active'] ? 'disable' : 'enable' ?>">
                                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-secondary btn-xs">
                                        <?= $tg['active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <a href="/admin/talkgroups/edit.php?id=<?= (int)$tg['id'] ?>"
                                   class="btn btn-ghost btn-xs">Edit</a>
                                <form method="post"
                                      onsubmit="return confirm('Delete TG <?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(addslashes($tg['name']), ENT_QUOTES, 'UTF-8') ?>?')"
                                      style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.layout-table-page -->

<?php require_once $root . '/app/views/footer.php'; ?>
