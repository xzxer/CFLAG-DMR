<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/subscribers/manager.php';

start_session();
require_role('system_admin');

$actor_id  = (int) $_SESSION['user_id'];
$flash_ok  = $_SESSION['_flash_ok']    ?? null; unset($_SESSION['_flash_ok']);
$flash_err = $_SESSION['_flash_error'] ?? null; unset($_SESSION['_flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['_flash_error'] = 'Invalid request. Please try again.';
        header('Location: /admin/subscribers/');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'import_now') {
        $result = import_subscribers_from_radioid(true);
        if ($result['status'] === 'ok') {
            $_SESSION['_flash_ok'] = 'Import complete. ' . number_format($result['imported']) . ' records processed.'
                . ($result['batches_failed'] > 0 ? ' Warning: ' . $result['batches_failed'] . ' batch(es) failed.' : '');
        } else {
            $_SESSION['_flash_error'] = 'Import failed: ' . ($result['error'] ?? 'Unknown error.');
        }
        header('Location: /admin/subscribers/');
        exit;

    } elseif ($action === 'check_updates') {
        $result = import_subscribers_from_radioid(false);
        if ($result['status'] === 'too_soon') {
            $next = $result['next_at'] ?? 'unknown';
            $_SESSION['_flash_ok'] = 'No update yet — next import allowed after ' . $next . '.';
        } elseif ($result['status'] === 'not_modified') {
            $_SESSION['_flash_ok'] = 'RadioID.net data has not changed since last import.';
        } elseif ($result['status'] === 'ok') {
            $_SESSION['_flash_ok'] = 'Update import complete. ' . number_format($result['imported']) . ' records processed.';
        } else {
            $_SESSION['_flash_error'] = 'Check failed: ' . ($result['error'] ?? 'Unknown error.');
        }
        header('Location: /admin/subscribers/');
        exit;

    } elseif ($action === 'add_override') {
        $raw_id       = trim($_POST['radio_id']  ?? '');
        $raw_callsign = trim($_POST['callsign']   ?? '');
        $raw_name     = trim($_POST['name']       ?? '');

        $radio_id = ctype_digit($raw_id) ? (int) $raw_id : 0;
        if ($radio_id <= 0 || $raw_callsign === '' || strlen($raw_callsign) > 16) {
            $_SESSION['_flash_error'] = 'Invalid override: DMR ID must be a positive integer and callsign is required (max 16 chars).';
        } else {
            upsert_subscriber_override($radio_id, $raw_callsign, $raw_name);
            $_SESSION['_flash_ok'] = 'Override saved for DMR ID ' . $radio_id . '.';
        }
        header('Location: /admin/subscribers/');
        exit;

    } elseif ($action === 'delete_override') {
        $radio_id = (int) ($_POST['radio_id'] ?? 0);
        if ($radio_id > 0 && delete_subscriber_override($radio_id)) {
            $_SESSION['_flash_ok'] = 'Override for DMR ID ' . $radio_id . ' deleted.';
        } else {
            $_SESSION['_flash_error'] = 'Override not found or could not be deleted.';
        }
        header('Location: /admin/subscribers/');
        exit;
    }
}

$stats     = get_subscriber_import_stats();
$overrides = get_subscriber_overrides(50, 0);
$url_fopen = (bool) ini_get('allow_url_fopen');

$page_title = 'Subscriber Import';
$active_nav = 'admin-subscribers';
require_once $root . '/app/views/header.php';
?>

<div class="layout-side">

    <div class="layout-side-main">

        <?php if ($flash_ok):  ?><div class="alert alert-success" style="margin-bottom:0.75rem;"><?= htmlspecialchars($flash_ok,  ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($flash_err): ?><div class="alert alert-error"   style="margin-bottom:0.75rem;"><?= htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <?php if (!$url_fopen): ?>
        <div class="alert alert-error" style="margin-bottom:0.75rem;">
            <strong>Warning:</strong> <code>allow_url_fopen</code> is disabled in PHP. Import cannot proceed.
            Enable it in <code>/etc/php/8.3/apache2/php.ini</code> and restart Apache.
        </div>
        <?php endif; ?>

        <!-- Import Status -->
        <div class="panel" style="margin-bottom:1rem;">
            <div class="panel-header">
                <span class="panel-title">Subscriber Database</span>
            </div>
            <div class="panel-body">
                <div class="field-list">
                    <div class="field-row">
                        <span class="field-key">Records</span>
                        <span class="field-val"><?= number_format($stats['count']) ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Last Import</span>
                        <span class="field-val col-ts"><?= htmlspecialchars($stats['last_import_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Last-Modified</span>
                        <span class="field-val" style="font-size:0.82rem;"><?= htmlspecialchars($stats['last_modified'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="field-row">
                        <span class="field-key">Next Allowed</span>
                        <span class="field-val col-ts"><?= htmlspecialchars($stats['next_import_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            <div class="panel-footer" style="gap:0.5rem;display:flex;flex-wrap:wrap;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="import_now">
                    <button type="submit" class="btn btn-primary btn-sm"<?= !$url_fopen ? ' disabled' : '' ?>>Import Now</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="check_updates">
                    <button type="submit" class="btn btn-secondary btn-sm"<?= !$url_fopen ? ' disabled' : '' ?>>Check for Updates</button>
                </form>
            </div>
        </div>

        <!-- Local Overrides -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Local Overrides</span>
                <span style="font-size:0.75rem;color:var(--text-3);"><?= count($overrides) ?> record<?= count($overrides) !== 1 ? 's' : '' ?></span>
            </div>

            <!-- Add override form -->
            <div class="panel-body" style="border-bottom:1px solid var(--border);">
                <form method="post" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:flex-end;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="add_override">
                    <div>
                        <label style="display:block;font-size:0.72rem;font-weight:600;margin-bottom:2px;">DMR ID</label>
                        <input type="text" name="radio_id" class="form-input" style="width:110px;" placeholder="7-digit ID" required pattern="[0-9]+">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.72rem;font-weight:600;margin-bottom:2px;">Callsign</label>
                        <input type="text" name="callsign" class="form-input" style="width:110px;" placeholder="W1ABC" maxlength="16" required>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.72rem;font-weight:600;margin-bottom:2px;">Name (optional)</label>
                        <input type="text" name="name" class="form-input" style="width:180px;" placeholder="Full Name" maxlength="128">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Add / Update Override</button>
                </form>
            </div>

            <div class="panel-body pad-none">
                <?php if (empty($overrides)): ?>
                <div class="empty-state" style="padding:1.25rem;">No local overrides defined.</div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>DMR ID</th>
                            <th>Callsign</th>
                            <th>Name</th>
                            <th class="col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overrides as $ov): ?>
                        <tr>
                            <td class="col-mono"><?= (int) $ov['radio_id'] ?></td>
                            <td><?= htmlspecialchars($ov['callsign'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($ov['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="col-actions">
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete override for <?= htmlspecialchars((string)$ov['radio_id'], ENT_QUOTES, 'UTF-8') ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete_override">
                                    <input type="hidden" name="radio_id" value="<?= (int) $ov['radio_id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-xs" style="color:var(--red);">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Sidebar -->
    <div class="layout-side-aside">
        <div class="panel">
            <div class="panel-header"><span class="panel-title">About</span></div>
            <div class="panel-body" style="font-size:0.82rem;color:var(--text-2);line-height:1.6;">
                <p>Downloads the RadioID.net subscriber CSV (~15.9 MB, ~306k records). Conditional GET skips the download if nothing has changed.</p>
                <p style="margin-top:0.5rem;"><strong>Import Now</strong> bypasses the <?= (int)$stats['min_interval_hours'] ?>-hour minimum interval.</p>
                <p style="margin-top:0.5rem;"><strong>Local overrides</strong> survive re-imports and take precedence in last-heard display.</p>
            </div>
        </div>

        <div class="panel" style="margin-top:1rem;">
            <div class="panel-header"><span class="panel-title">Cron Auto-Update</span></div>
            <div class="panel-body" style="font-size:0.82rem;">
                <p style="color:var(--text-2);margin-bottom:0.75rem;">Add to <code>/etc/crontab</code> to auto-import daily:</p>
                <div class="code-viewer" style="font-size:0.72rem;padding:0.6rem;">0 6 * * * www-data php <?= htmlspecialchars(dirname(__DIR__, 3), ENT_QUOTES, 'UTF-8') ?>/scripts/import_subscribers.php >> /var/log/cflag-subscriber-import.log 2&amp;&amp;1</div>
            </div>
        </div>
    </div>

</div>

<?php require_once $root . '/app/views/footer.php'; ?>
