<?php

define('NOTOKENRENEWAL', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (!$res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/sshexecutor.class.php';

header('Content-Type: application/json');

if (!$user->admin && empty($user->rights->deploymanager->admin)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'));
    exit;
}

$serverId = GETPOSTINT('server_id');
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE rowid = ".(int) $serverId;
$resq = $db->query($sql);
$server = $db->fetch_object($resq);

if (!$server) {
    echo json_encode(array('ok' => false, 'error' => 'Server not found'));
    exit;
}

// Ejecutar SSH directamente sin pasar por SSHExecutor para evitar escapeshellarg
$sshKey = $server->ssh_key_path ?: '';
$sshUser = $server->ssh_user ?: 'root';
$sshHost = $server->host;
$sshPort = $server->ssh_port ?: 22;

$sshOpts = '-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes';
if ($sshKey) $sshOpts .= ' -i '.escapeshellarg($sshKey);
if ($sshPort != 22) $sshOpts .= ' -p '.(int) $sshPort;

$remoteScript = 'find /var/www/vhosts/ -path "*/httpdocs/htdocs/conf/conf.php" 2>/dev/null | while read d; do vhost=$(echo "$d" | sed "s|/var/www/vhosts/||;s|/httpdocs/.*||"); custom=$(echo "$d" | sed "s|/conf/conf.php|/custom|"); echo "$vhost|$custom|$d"; done';

$tmpScript = tempnam(sys_get_temp_dir(), 'dm_discover_');
file_put_contents($tmpScript, $remoteScript);

$cmd = 'ssh '.$sshOpts.' '.escapeshellarg($sshUser.'@'.$sshHost).' bash < '.escapeshellarg($tmpScript).' 2>&1';
$fullOutput = array();
$exitCode = 0;
exec($cmd, $fullOutput, $exitCode);
$output = implode("\n", $fullOutput);
$ok = ($exitCode === 0);
@unlink($tmpScript);
if (!$ok) {
    echo json_encode(array('ok' => false, 'error' => 'Error SSH: '.$output));
    exit;
}

$existingDomains = array();
$resExist = $db->query("SELECT domain FROM ".MAIN_DB_PREFIX."deploymanager_instance WHERE fk_server = ".(int) $serverId);
while ($resExist && ($row = $db->fetch_object($resExist))) {
    $existingDomains[$row->domain] = true;
}

$added = 0;
$skipped = 0;
$instances = array();
$lines = array_filter(explode("\n", trim($output)));

foreach ($lines as $line) {
    $parts = explode('|', $line, 3);
    if (count($parts) !== 3) continue;

    $domain = trim($parts[0]);
    $customPath = trim($parts[1]);
    $confPath = trim($parts[2]);

    if (empty($domain)) continue;

    if (!empty($existingDomains[$domain])) {
        $skipped++;
        continue;
    }

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_instance (fk_server, name, domain, custom_path, conf_path, environment) VALUES (";
    $sql .= (int) $serverId.",";
    $sql .= "'".$db->escape($domain)."',";
    $sql .= "'".$db->escape($domain)."',";
    $sql .= "'".$db->escape($customPath)."',";
    $sql .= "'".$db->escape($confPath)."',";
    $sql .= "'production')";
    $db->query($sql);

    $added++;
    $instances[] = $domain;
}

echo json_encode(array(
    'ok' => true,
    'added' => $added,
    'skipped' => $skipped,
    'instances' => $instances
));
