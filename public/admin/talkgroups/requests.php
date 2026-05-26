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
        $action     = $_POST['action'] ?? '';
        $request_id = (int) ($_POST['request_id'] ?? 0);

        if ($action === 'approve' && $request_id > 0) {
            $final_tgid = validate_tgid($_POST['final_tgid'] ?? '');
            if ($final_tgid === false) {
                $flash_error = 'Invalid final TGID.';
            } else {
                $tier   = $_POST['tier'] ?? 'admin';
                $result = approve_talkgroup_request($request_id, $admin_id, $final_tgid, $tier);
                if ($result['ok']) {
                    $flash_ok = 'Request approved and talkgroup created.';
                } else {
                    $flash_error = $result['error'];
                }
            }

        } elseif ($action === 'deny' && $request_id > 0) {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                $flash_error = 'A denial reason is required.';
            } else {
                deny_talkgroup_request($request_id, $admin_id, $reason);
                $flash_ok = 'Request denied.';
            }

        } elseif ($action === 'approve_upgrade' && $request_id > 0) {
            if (approve_ownership_upgrade($request_id, $admin_id)) {
                $flash_ok = 'Ownership upgraded to user_full.';
            } else {
                $flash_error = 'Upgrade request not found.';
            }

        } elseif ($action === 'deny_upgrade' && $request_id > 0) {
            deny_ownership_upgrade($request_id, $admin_id);
            $flash_ok = 'Upgrade request denied.';
        }

        header('Location: /admin/talkgroups/requests.php');
        exit;
    }
}

$pending_requests = get_pending_talkgroup_requests();
$pending_upgrades = get_pending_ownership_upgrades();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Talkgroup Requests — CFLAG DMR</title>
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
            <h1>Talkgroup Requests</h1>

            <?php if (empty($pending_requests)): ?>
            <p class="muted" style="font-size:0.9rem;">No pending talkgroup requests.</p>
            <?php else: ?>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Proposed TGID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $r): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($r['display_name'] ?: $r['username'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="muted" style="font-size:0.8rem;display:block;">
                                    <?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:#60a5fa;"><?= htmlspecialchars((string)$r['proposed_tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['proposed_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($r['proposed_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="muted" style="font-size:0.85rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($r['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars(substr($r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-start;padding-top:0.5rem;">
                                <form method="post" style="display:flex;flex-direction:column;gap:0.35rem;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="text" name="final_tgid" value="<?= htmlspecialchars((string)$r['proposed_tgid'], ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="Final TGID" maxlength="8"
                                           style="font-size:0.8rem;padding:0.25rem 0.4rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:4px;width:90px;" required>
                                    <select name="tier" style="font-size:0.8rem;padding:0.25rem 0.4rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:4px;">
                                        <option value="user_partial">user_partial</option>
                                        <option value="user_full">user_full</option>
                                        <option value="admin">admin</option>
                                    </select>
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;">Approve</button>
                                </form>
                                <form method="post" style="display:flex;flex-direction:column;gap:0.35rem;">
                                    <input type="hidden" name="action" value="deny">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <textarea name="reason" rows="2" placeholder="Denial reason (required)"
                                              style="font-size:0.8rem;padding:0.3rem 0.5rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:4px;width:160px;resize:vertical;"
                                              required></textarea>
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;background:#450a0a;color:#fca5a5;">Deny</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($pending_upgrades)): ?>
            <p class="section-title">Ownership Upgrade Requests</p>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Talkgroup</th>
                            <th>TGID</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_upgrades as $u): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($u['display_name'] ?: $u['username'], ENT_QUOTES, 'UTF-8') ?>
                                <span class="muted" style="font-size:0.8rem;display:block;"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><?= htmlspecialchars($u['talkgroup_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color:#60a5fa;font-weight:700;"><?= htmlspecialchars((string)$u['tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="muted" style="font-size:0.85rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                                <?= htmlspecialchars($u['reason'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars(substr($u['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="display:flex;gap:0.5rem;flex-wrap:wrap;padding-top:0.4rem;">
                                <form method="post">
                                    <input type="hidden" name="action" value="approve_upgrade">
                                    <input type="hidden" name="request_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;">Approve</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="deny_upgrade">
                                    <input type="hidden" name="request_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="nav-link" style="font-size:0.8rem;min-height:44px;background:#450a0a;color:#fca5a5;">Deny</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="muted" style="font-size:0.9rem;margin-top:1rem;">No pending ownership upgrade requests.</p>
            <?php endif; ?>

            <p style="margin-top:1rem;">
                <a href="/admin/talkgroups/" class="nav-link">← Talkgroup Management</a>
            </p>
        </section>

    </main>
</body>
</html>
