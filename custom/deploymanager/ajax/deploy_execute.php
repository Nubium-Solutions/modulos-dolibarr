<?php

define('NOTOKENRENEWAL', '1');
define('NOCSRFCHECK', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (!$res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/deployengine.class.php';

header('Content-Type: application/json');

if (!$user->admin && empty($user->rights->deploymanager->deploy)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$instanceIds = isset($input['instance_ids']) ? array_map('intval', $input['instance_ids']) : array();

// Multi-module support
$modules = isset($input['modules']) ? $input['modules'] : array();
// Backward compat: single module
if (empty($modules) && !empty($input['module_id'])) {
    $modules = array(array('module_id' => (int) $input['module_id'], 'source_instance_id' => (int) $input['source_instance_id']));
}

if (empty($modules) || empty($instanceIds)) {
    echo json_encode(array('ok' => false, 'error' => 'Faltan parámetros'));
    exit;
}

$engine = new DeployEngine($db);
$batchIds = array();

foreach ($modules as $mod) {
    $moduleId = (int) $mod['module_id'];
    $sourceInstanceId = (int) $mod['source_instance_id'];
    if ($moduleId <= 0 || $sourceInstanceId <= 0) continue;

    $batchId = $engine->createBatch($moduleId, $sourceInstanceId, $instanceIds, $user->id);
    if ($batchId) {
        $batchIds[] = $batchId;
    }
}

if (empty($batchIds)) {
    echo json_encode(array('ok' => false, 'error' => 'Error creando batches'));
    exit;
}

echo json_encode(array('ok' => true, 'batch_ids' => $batchIds));

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    header('Content-Length: '.ob_get_length());
    ob_end_flush();
    flush();
}

foreach ($batchIds as $bid) {
    $engine->executeBatch($bid);
}
