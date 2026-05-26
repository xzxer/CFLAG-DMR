<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/auth/roles.php';
require_once $root . '/app/devices/manager.php';
require_once $root . '/app/config/settings.php';

start_session();
require_login();

$user_id   = (int) $_SESSION['user_id'];
$device_id = (int) ($_GET['id'] ?? 0);
$format    = $_GET['format'] ?? 'wpsd';

if ($device_id < 1) {
    http_response_code(400);
    exit('Invalid device ID.');
}

$device = get_device($device_id, $user_id);
if (!$device) {
    http_response_code(404);
    exit('Device not found.');
}

if ($device['status'] !== 'approved') {
    http_response_code(403);
    exit('Config is only available for approved devices.');
}

if ($device['device_type'] !== 'hotspot') {
    http_response_code(400);
    exit('Config download is only available for hotspots.');
}

$settings = get_master_settings();
if (!$settings) {
    http_response_code(503);
    exit('Server configuration not yet available. Contact an admin.');
}

$server_host  = $settings['bind_address'] !== '0.0.0.0' ? $settings['bind_address'] : gethostname();
$server_port  = (int) $settings['port'];
$peer_id      = (int) $device['peer_id'];
$passphrase   = $device['device_passphrase'] ?? '';
$callsign     = strtoupper(trim($device['callsign']));

if (!in_array($format, ['wpsd', 'pistar'], true)) {
    $format = 'wpsd';
}

if ($format === 'wpsd') {
    // WPSD / MMDVMHost mmdvmhost configuration snippet
    $filename = 'mmdvmhost_' . strtolower($callsign) . '.cfg';
    $content  = <<<CFG
    [General]
    Callsign={$callsign}
    Id={$peer_id}

    [DMR Network]
    Enable=1
    Address={$server_host}
    Port={$server_port}
    Local=0
    Password={$passphrase}
    Options=
    Debug=0
    CFG;

} else {
    // Pi-Star / MMDVM_Bridge expert configuration snippet
    $filename = 'pistar_dmr_' . strtolower($callsign) . '.cfg';
    $content  = <<<CFG
    # Pi-Star DMR Gateway Configuration
    # Import into Expert Editor > /etc/mmdvmhost

    [DMR Network]
    Enable=1
    Address={$server_host}
    Port={$server_port}
    Local=0
    Password={$passphrase}

    [General]
    Callsign={$callsign}
    Id={$peer_id}
    CFG;
}

// Dedent the heredoc indentation
$lines = explode("\n", $content);
$dedented = [];
foreach ($lines as $line) {
    $dedented[] = ltrim($line, "\t ");
}
$content = implode("\n", $dedented);

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $content;
