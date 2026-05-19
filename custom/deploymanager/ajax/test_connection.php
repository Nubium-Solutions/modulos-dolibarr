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

$serverId = GETPOSTINT('id');
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE rowid = ".(int) $serverId;
$resq = $db->query($sql);
$server = $db->fetch_object($resq);

if (!$server) {
    echo json_encode(array('ok' => false, 'error' => 'Server not found'));
    exit;
}

$ssh = new SSHExecutor($server);
$ok = $ssh->testConnection();

echo json_encode(array('ok' => $ok, 'message' => $ok ? 'Conexión SSH correcta' : 'Error de conexión SSH'));
