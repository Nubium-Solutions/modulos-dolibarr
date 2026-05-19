<?php

define('NOTOKENRENEWAL', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (!$res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/modulescanner.class.php';

header('Content-Type: application/json');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'));
    exit;
}

$instanceId = GETPOSTINT('id');

$sqlInst = "SELECT i.rowid as inst_id, i.custom_path, s.host, s.ssh_user, s.ssh_port, s.ssh_key_path, s.is_local";
$sqlInst .= " FROM ".MAIN_DB_PREFIX."deploymanager_instance i";
$sqlInst .= " JOIN ".MAIN_DB_PREFIX."deploymanager_server s ON s.rowid = i.fk_server";
$sqlInst .= " WHERE i.rowid = ".(int) $instanceId;
$resq = $db->query($sqlInst);
$row = $db->fetch_object($resq);

if (!$row) {
    echo json_encode(array('ok' => false, 'error' => 'Instance not found'));
    exit;
}

$instance = new stdClass();
$instance->rowid = $row->inst_id;
$instance->custom_path = $row->custom_path;

$server = new stdClass();
$server->host = $row->host;
$server->ssh_user = $row->ssh_user;
$server->ssh_port = $row->ssh_port;
$server->ssh_key_path = $row->ssh_key_path;
$server->is_local = $row->is_local;

$scanner = new ModuleScanner($db);
$result = $scanner->scanInstance($instance, $server);

echo json_encode($result);
