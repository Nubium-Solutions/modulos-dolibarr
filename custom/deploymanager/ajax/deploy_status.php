<?php

define('NOTOKENRENEWAL', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (!$res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/deployengine.class.php';

header('Content-Type: application/json');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'));
    exit;
}

$batchId = GETPOSTINT('id');
$engine = new DeployEngine($db);
$data = $engine->getBatchStatus($batchId);

if (!$data) {
    echo json_encode(array('ok' => false, 'error' => 'Batch not found'));
    exit;
}

echo json_encode(array('ok' => true, 'data' => $data));
