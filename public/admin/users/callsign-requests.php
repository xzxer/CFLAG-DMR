<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/profile/callsign.php';

start_session();
require_role('system_admin');

$actor_id    = (int) $_SESSION['user_id'];
$flash_ok    = null;
$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Invalid request.';
    } else {
        $action     = $_POST['action'] ?? '';
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');

        if ($action === 'approve' && $request_id > 0) {
            $result = approve_callsign_request($request_id, $actor_id, $notes);
            if ($result['ok']) {
                $flash_ok = 'Callsign request approved.';
            } else {
                $flash_error = $result['error'];
            }

        } elseif ($action === 'deny' && $request_id > 0) {
            if ($notes === '') {
                $flash_error = 'Please provide a reason for denial.';
            } else {
                $result = deny_callsign_request($request_id, $actor_id, $notes);
                if ($result['ok']) {
                    $flash_ok = 'Callsign request denied.';
                } else {
                    $flash_error = $result['error'];
                }
            }
        }
    }
}

$pending = get_pending_callsign_requests();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Callsign Requests — CFLAG DMR</title>
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
            <p class="eyebrow">User Management</p>
            <h1>Callsign Update Requests</h1>
            <p style="margin-top:1rem;">
                <a href="/admin/users/" class="nav-link">&#8592; User Management</a>
            </p>
        </section>

        <section class="card" style="margin-top:1.5rem;">
            <?php if (empty($pending)): ?>
            <p class="muted" style="font-size:0.9rem;">No pending callsign update requests.</p>
            <?php else: ?>
            <?php foreach ($pending as $req): ?>
            <div style="border:1px solid #334155;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:baseline;margin-bottom:0.5rem;">
                    <span style="font-size:0.85rem;color:#94a3b8;">
                        <?= htmlspecialchars($req['username'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= htmlspecialchars($req['email'], ENT_QUOTES, 'UTF-8') ?>)
                    </span>
                    <span class="muted" style="font-size:0.8rem;">submitted <?= htmlspecialchars($req['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div style="margin-bottom:0.75rem;">
                    <?php if ($req['old_callsign'] !== null): ?>
                    <span class="callsign"><?= htmlspecialchars($req['old_callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="muted" style="font-size:0.9rem;margin:0 0.5rem;">→</span>
                    <?php endif; ?>
                    <span class="callsign" style="color:#60a5fa;"><?= htmlspecialchars($req['requested_callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php if (!empty($req['explanation'])): ?>
                <p class="muted" style="font-size:0.85rem;margin-bottom:0.75rem;">
                    <?= htmlspecialchars($req['explanation'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>
                <form method="post" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;">
                    <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group" style="flex:1 1 220px;margin-bottom:0;">
                        <label style="font-size:0.85rem;">Notes (required for denial)</label>
                        <input type="text" name="notes" maxlength="255"
                               placeholder="Review notes"
                               style="font-size:0.85rem;padding:0.3rem 0.5rem;background:#1e293b;border:1px solid #334155;color:#e2e8f0;border-radius:4px;width:100%;">
                    </div>
                    <button type="submit" name="action" value="approve" class="nav-link" style="min-height:44px;padding:0.3rem 0.75rem;font-size:0.85rem;">
                        Approve
                    </button>
                    <button type="submit" name="action" value="deny" class="nav-link" style="min-height:44px;padding:0.3rem 0.75rem;font-size:0.85rem;background:#450a0a;color:#fca5a5;"
                            onclick="return confirm('Deny this callsign request?')">
                        Deny
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
