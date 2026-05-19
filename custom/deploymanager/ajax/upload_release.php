<?php

define('NOTOKENRENEWAL', '1');
define('NOCSRFCHECK', '1');

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (!$res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/modulepackager.class.php';

header('Content-Type: application/json');

if (!$user->admin && empty($user->rights->deploymanager->deploy)) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Forbidden'));
    exit;
}

if (empty($_FILES['module_zip']) || $_FILES['module_zip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(array('ok' => false, 'error' => 'No file uploaded or upload error'));
    exit;
}

$packager = new ModulePackager($db);
$changelog = GETPOST('changelog', 'restricthtml');

$result = $packager->processUpload($_FILES['module_zip']['tmp_name'], $_FILES['module_zip']['name'], $user->id);

if ($result['ok'] && $changelog) {
    $db->query("UPDATE ".MAIN_DB_PREFIX."deploymanager_release SET changelog = '".$db->escape($changelog)."' WHERE rowid = ".(int) $result['release_id']);
}

echo json_encode($result);
