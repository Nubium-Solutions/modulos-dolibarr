<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../master.inc.php')) $res = @include dirname(__FILE__).'/../../master.inc.php';
if (!$res && file_exists(dirname(__FILE__).'/../../../master.inc.php')) $res = @include dirname(__FILE__).'/../../../master.inc.php';
if (!$res) {
    if (!$res && file_exists(dirname(__FILE__).'/../../main.inc.php')) $res = @include dirname(__FILE__).'/../../main.inc.php';
    if (!$res && file_exists(dirname(__FILE__).'/../../../main.inc.php')) $res = @include dirname(__FILE__).'/../../../main.inc.php';
}
if (!$res) die("Include of main/master fails\n");

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/deployengine.class.php';

$batchId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($batchId <= 0) {
    fwrite(STDERR, "Uso: php deploy_worker.php <batch_id>\n");
    exit(1);
}

$engine = new DeployEngine($db);
$result = $engine->executeBatch($batchId);

if ($result) {
    echo "Batch #".$batchId." completado.\n";
    exit(0);
} else {
    fwrite(STDERR, "Error ejecutando batch #".$batchId."\n");
    exit(1);
}
