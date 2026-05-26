<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_login();

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'request') {
            $result = submit_talkgroup_request($user_id, $_POST);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Talkgroup request submitted.' : $result['error'];

        } elseif ($action === 'edit_owned') {
            $tg_id  = (int) ($_POST['tg_id'] ?? 0);
            $result = update_talkgroup($tg_id, $_POST, $user_id);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Talkgroup updated.' : $result['error'];

        } elseif ($action === 'add_access') {
            $tg_id     = (int) ($_POST['tg_id'] ?? 0);
            $dmr_id    = validate_dmr_id_tg($_POST['dmr_id'] ?? '');
            $list_type = $_POST['list_type'] ?? '';
            if ($dmr_id === false || !in_array($list_type, ['allow', 'block'], true)) {
                $_SESSION['_flash_error'] = 'Invalid DMR ID or list type.';
            } else {
                add_access_entry($tg_id, $dmr_id, $list_type, $user_id);
                $_SESSION['_flash_ok'] = 'Access entry added.';
            }

        } elseif ($action === 'remove_access') {
            $tg_id     = (int) ($_POST['tg_id'] ?? 0);
            $dmr_id    = (int) ($_POST['dmr_id'] ?? 0);
            $list_type = $_POST['list_type'] ?? '';
            remove_access_entry($tg_id, $dmr_id, $list_type, $user_id);
            $_SESSION['_flash_ok'] = 'Access entry removed.';

        } elseif ($action === 'request_upgrade') {
            $tg_id  = (int) ($_POST['tg_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $result = submit_ownership_upgrade($tg_id, $user_id, $reason);
            $_SESSION[$result['ok'] ? '_flash_ok' : '_flash_error'] = $result['ok'] ? 'Upgrade request submitted.' : $result['error'];
        }

        header('Location: /user/talkgroups.php');
        exit;
    }
}

function validate_dmr_id_tg(string $raw): int|false
{
    $raw = trim($raw);
    if (!ctype_digit($raw)) return false;
    $id = (int) $raw;
    return ($id >= 1000000 && $id <= 9999999) ? $id : false;
}

$flash_ok    = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_error = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

$my_requests = get_user_talkgroup_requests($user_id);
$owned_tgs   = get_owned_talkgroups($user_id);

$page_title = 'My Talkgroups';
$active_nav = 'my-talkgroups';
require_once $root . '/app/views/header.php';
?>

<?php if ($flash_ok):    ?><div class="alert alert-success" style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_ok,    ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;flex-shrink:0;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="layout-primary-aside" style="flex:1;min-height:0;">

    <!-- Main: owned TGs + requests history -->
    <div class="col-stack">

        <?php if (!empty($owned_tgs)): ?>
        <div class="panel" style="flex:1;min-height:0;">
            <div class="panel-header"><span class="panel-title">My Talkgroups</span></div>
            <div class="panel-body">
                <?php foreach ($owned_tgs as $tg): ?>
                <div style="border:1px solid var(--border-2);border-radius:var(--radius);padding:0.875rem;margin-bottom:0.75rem;">

                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:0.625rem;">
                        <span class="col-mono" style="color:var(--accent-text);font-weight:700;"><?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span style="font-weight:600;color:var(--text);"><?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge badge-gray"><?= htmlspecialchars(ucfirst($tg['tg_type']), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge <?= $tg['ownership_tier'] === 'user_full' ? 'badge-active' : 'badge-gray' ?>">
                            <?= htmlspecialchars($tg['ownership_tier'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <form method="post" style="margin-bottom:0.625rem;">
                        <input type="hidden" name="action" value="edit_owned">
                        <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" maxlength="128" required
                                   value="<?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" maxlength="2000"><?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                        </div>
                    </form>

                    <?php if ($tg['ownership_tier'] === 'user_full'): ?>
                        <?php $access_list = get_access_list((int)$tg['id']); ?>
                        <span class="form-section-label">Access List</span>
                        <?php if (!empty($access_list)): ?>
                        <table class="data-table" style="margin-bottom:0.5rem;">
                            <thead><tr><th>DMR ID</th><th>List</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($access_list as $entry): ?>
                                <tr>
                                    <td class="col-mono"><?= htmlspecialchars((string)$entry['dmr_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge <?= $entry['list_type'] === 'allow' ? 'badge-active' : 'badge-red' ?>">
                                            <?= htmlspecialchars($entry['list_type'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <form method="post">
                                            <input type="hidden" name="action" value="remove_access">
                                            <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                                            <input type="hidden" name="dmr_id" value="<?= (int)$entry['dmr_id'] ?>">
                                            <input type="hidden" name="list_type" value="<?= htmlspecialchars($entry['list_type'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="font-size:0.72rem;color:var(--text-3);margin-bottom:0.5rem;">No entries — all DMR IDs may connect.</p>
                        <?php endif; ?>

                        <form method="post" class="filter-bar">
                            <input type="hidden" name="action" value="add_access">
                            <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <label>DMR ID</label>
                            <input type="text" class="form-input" name="dmr_id" maxlength="7"
                                   placeholder="7-digit ID" required style="width:110px;">
                            <label>List</label>
                            <select class="form-select" name="list_type" style="width:80px;">
                                <option value="allow">Allow</option>
                                <option value="block">Block</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-xs">Add</button>
                        </form>

                    <?php elseif ($tg['ownership_tier'] === 'user_partial'): ?>
                        <form method="post" style="margin-top:0.625rem;">
                            <input type="hidden" name="action" value="request_upgrade">
                            <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-group">
                                <label>Request Full Ownership</label>
                                <textarea name="reason" maxlength="2000"
                                          placeholder="Explain why you need full ownership..." required></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-secondary btn-sm">Request Upgrade</button>
                            </div>
                        </form>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Request history -->
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Request History</span></div>
            <div class="panel-body pad-none">
                <?php if (empty($my_requests)): ?>
                <div class="empty-state">
                    <strong>No requests yet</strong>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>TGID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_requests as $r): ?>
                        <tr>
                            <td class="col-mono" style="color:var(--accent-text);"><?= htmlspecialchars((string)$r['proposed_tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['proposed_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="font-size:0.72rem;"><?= htmlspecialchars(ucfirst($r['proposed_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($r['status'] === 'approved'): ?>
                                <span class="badge badge-active">Approved</span>
                                <?php elseif ($r['status'] === 'denied'): ?>
                                <span class="badge badge-red">Denied</span>
                                <?php else: ?>
                                <span class="badge badge-amber">Pending</span>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'denied' && $r['denial_reason'] !== null): ?>
                                <div style="font-size:0.65rem;color:var(--red);margin-top:0.15rem;">
                                    <?= htmlspecialchars($r['denial_reason'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="col-ts"><?= htmlspecialchars(substr($r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.col-stack -->

    <!-- Aside: request form -->
    <div class="panel" style="align-self:start;">
        <div class="panel-header"><span class="panel-title">Request a Talkgroup</span></div>
        <div class="panel-body">
            <form method="post">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="proposed_tgid">Proposed TGID</label>
                    <input type="text" id="proposed_tgid" name="proposed_tgid"
                           maxlength="8" required placeholder="e.g. 3172">
                </div>
                <div class="form-group">
                    <label for="proposed_name">Talkgroup Name</label>
                    <input type="text" id="proposed_name" name="proposed_name"
                           maxlength="128" required placeholder="e.g. Pacific NW">
                </div>
                <div class="form-group">
                    <label for="proposed_type">Type</label>
                    <select id="proposed_type" name="proposed_type" class="form-select">
                        <option value="open">Open</option>
                        <option value="private">Private</option>
                        <option value="club">Club</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="req_description">Description (optional)</label>
                    <textarea id="req_description" name="description" maxlength="2000"
                              placeholder="Why this talkgroup? Who is it for?"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

</div><!-- /.layout-primary-aside -->

<?php require_once $root . '/app/views/footer.php'; ?>
