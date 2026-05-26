<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/talkgroups/manager.php';

start_session();
require_login();

$user_id     = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'request') {
            $result = submit_talkgroup_request($user_id, $_POST);
            if ($result['ok']) {
                $flash_ok = 'Talkgroup request submitted.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'edit_owned') {
            $tg_id  = (int) ($_POST['tg_id'] ?? 0);
            $result = update_talkgroup($tg_id, $_POST, $user_id);
            if ($result['ok']) {
                $flash_ok = 'Talkgroup updated.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'add_access') {
            $tg_id     = (int) ($_POST['tg_id'] ?? 0);
            $dmr_id    = validate_dmr_id_tg($_POST['dmr_id'] ?? '');
            $list_type = $_POST['list_type'] ?? '';
            if ($dmr_id === false || !in_array($list_type, ['allow', 'block'], true)) {
                $flash_error = 'Invalid DMR ID or list type.';
            } else {
                add_access_entry($tg_id, $dmr_id, $list_type, $user_id);
                $flash_ok = 'Access entry added.';
            }

        } elseif ($action === 'remove_access') {
            $tg_id     = (int) ($_POST['tg_id'] ?? 0);
            $dmr_id    = (int) ($_POST['dmr_id'] ?? 0);
            $list_type = $_POST['list_type'] ?? '';
            remove_access_entry($tg_id, $dmr_id, $list_type, $user_id);
            $flash_ok = 'Access entry removed.';

        } elseif ($action === 'request_upgrade') {
            $tg_id  = (int) ($_POST['tg_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $result = submit_ownership_upgrade($tg_id, $user_id, $reason);
            if ($result['ok']) {
                $flash_ok = 'Upgrade request submitted.';
            } else {
                $flash_error = $result['error'];
            }
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

function request_status_badge(string $status): string
{
    return match ($status) {
        'approved' => '<span class="badge badge-active">Approved</span>',
        'pending'  => '<span class="badge badge-pending">Pending</span>',
        'denied'   => '<span class="badge badge-banned">Denied</span>',
        default    => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
    };
}

$my_requests  = get_user_talkgroup_requests($user_id);
$owned_tgs    = get_owned_talkgroups($user_id);
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

        <?php if (!empty($owned_tgs)): ?>
        <section class="card">
            <p class="eyebrow">My Account</p>
            <h1>My Talkgroups</h1>

            <?php foreach ($owned_tgs as $tg): ?>
            <div style="border:1px solid #334155;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <div class="field-row" style="margin-bottom:0.5rem;">
                    <span class="field-label">TGID</span>
                    <span class="field-value" style="color:#60a5fa;font-weight:700;"><?= htmlspecialchars((string)$tg['tgid'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="field-row" style="margin-bottom:0.5rem;">
                    <span class="field-label">Type</span>
                    <span class="field-value"><?= htmlspecialchars(ucfirst($tg['tg_type']), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="field-row" style="margin-bottom:0.75rem;">
                    <span class="field-label">Ownership</span>
                    <span class="badge <?= $tg['ownership_tier'] === 'user_full' ? 'badge-active' : 'badge-muted' ?>">
                        <?= htmlspecialchars($tg['ownership_tier'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <form method="post" style="margin-bottom:0.75rem;">
                    <input type="hidden" name="action" value="edit_owned">
                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <label>Name</label>
                        <input type="text" name="name" maxlength="128" required
                               value="<?= htmlspecialchars($tg['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <label>Description</label>
                        <textarea name="description" maxlength="2000"><?= htmlspecialchars($tg['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                </form>

                <?php if ($tg['ownership_tier'] === 'user_full'): ?>
                <?php $access_list = get_access_list((int)$tg['id']); ?>
                <p class="section-title" style="margin-top:1rem;">Access List</p>
                <?php if (!empty($access_list)): ?>
                <div class="lh-table-wrap">
                    <table class="lh-table">
                        <thead><tr><th>DMR ID</th><th>List</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($access_list as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$entry['dmr_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="badge <?= $entry['list_type'] === 'allow' ? 'badge-active' : 'badge-banned' ?>">
                                        <?= htmlspecialchars($entry['list_type'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="action" value="remove_access">
                                        <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                                        <input type="hidden" name="dmr_id" value="<?= (int)$entry['dmr_id'] ?>">
                                        <input type="hidden" name="list_type" value="<?= htmlspecialchars($entry['list_type'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="nav-link" style="font-size:0.8rem;padding:0.2rem 0.5rem;min-height:44px;background:#450a0a;color:#fca5a5;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="muted" style="font-size:0.85rem;">No access list entries — all DMR IDs may connect.</p>
                <?php endif; ?>

                <form method="post" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;margin-top:0.75rem;">
                    <input type="hidden" name="action" value="add_access">
                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="flex:0 0 140px;margin-bottom:0;">
                        <label>DMR ID</label>
                        <input type="text" name="dmr_id" maxlength="7" placeholder="7-digit ID" required>
                    </div>
                    <div class="form-group" style="flex:0 0 100px;margin-bottom:0;">
                        <label>List</label>
                        <select name="list_type">
                            <option value="allow">Allow</option>
                            <option value="block">Block</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm" style="margin-bottom:0;">Add Entry</button>
                </form>

                <?php elseif ($tg['ownership_tier'] === 'user_partial'): ?>
                <form method="post" style="margin-top:0.75rem;">
                    <input type="hidden" name="action" value="request_upgrade">
                    <input type="hidden" name="tg_id" value="<?= (int)$tg['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <label>Request Full Ownership (reason)</label>
                        <textarea name="reason" maxlength="2000" placeholder="Explain why you need full ownership..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Request Upgrade</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section class="card" style="margin-top:<?= !empty($owned_tgs) ? '1.5rem' : '0' ?>;">
            <p class="eyebrow">My Account</p>
            <h1>Talkgroup Requests</h1>

            <?php if (empty($my_requests)): ?>
            <p class="muted" style="font-size:0.9rem;">No requests submitted yet.</p>
            <?php else: ?>
            <div class="lh-table-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Proposed TGID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_requests as $r): ?>
                        <tr>
                            <td style="color:#60a5fa;font-weight:700;"><?= htmlspecialchars((string)$r['proposed_tgid'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['proposed_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ucfirst($r['proposed_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= request_status_badge($r['status']) ?>
                                <?php if ($r['status'] === 'denied' && $r['denial_reason'] !== null): ?>
                                <span class="muted" style="font-size:0.8rem;display:block;">
                                    <?= htmlspecialchars($r['denial_reason'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(substr($r['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <p class="eyebrow">Request</p>
            <h1 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:1rem;">Request a New Talkgroup</h1>
            <form method="post">
                <input type="hidden" name="action" value="request">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="proposed_tgid">Proposed TGID</label>
                    <input type="text" id="proposed_tgid" name="proposed_tgid" maxlength="8" required placeholder="e.g. 3172">
                </div>
                <div class="form-group">
                    <label for="proposed_name">Talkgroup Name</label>
                    <input type="text" id="proposed_name" name="proposed_name" maxlength="128" required placeholder="e.g. Pacific NW">
                </div>
                <div class="form-group">
                    <label for="proposed_type">Type</label>
                    <select id="proposed_type" name="proposed_type">
                        <option value="open">Open</option>
                        <option value="private">Private</option>
                        <option value="club">Club</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description" maxlength="2000" placeholder="Why this talkgroup? Who is it for?"></textarea>
                </div>
                <button type="submit" class="nav-link" style="min-height:44px;padding:0.5rem 1.25rem;">Submit Request</button>
            </form>
        </section>

        <p style="margin-top:1.5rem;">
            <a href="/" class="nav-link">← Dashboard</a>
        </p>

    </main>
</body>
</html>
