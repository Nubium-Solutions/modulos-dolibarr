<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->deploy)) {
    accessforbidden();
}

$preselectedModule = GETPOST('module', 'alphanohtml');

llxHeader('', $langs->trans('DM_DeployWizard'));

print '<script>var DOL_URL_ROOT="'.DOL_URL_ROOT.'";</script>';

print load_fiche_titre($langs->trans('DM_DeployWizard'), '', 'fa-rocket');

// Get modules that exist in at least one instance
$modules = array();
$sqlMod = "SELECT m.rowid, m.slug, m.display_name FROM ".MAIN_DB_PREFIX."deploymanager_module m";
$sqlMod .= " WHERE EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im WHERE im.fk_module = m.rowid AND im.installed_version IS NOT NULL)";
$sqlMod .= " ORDER BY m.slug";
$resMod = $db->query($sqlMod);
while ($resMod && ($m = $db->fetch_object($resMod))) {
    $modules[] = $m;
}

// Get instances
$instances = array();
$sqlInst = "SELECT i.rowid, i.name, i.domain, i.environment, s.name as server_name";
$sqlInst .= " FROM ".MAIN_DB_PREFIX."deploymanager_instance i";
$sqlInst .= " JOIN ".MAIN_DB_PREFIX."deploymanager_server s ON s.rowid = i.fk_server";
$sqlInst .= " WHERE i.status = 1 ORDER BY s.name, i.name";
$resInst = $db->query($sqlInst);
while ($resInst && ($inst = $db->fetch_object($resInst))) {
    $instances[] = $inst;
}

// Instance module versions
$instModVersions = array();
$sqlIM = "SELECT fk_instance, fk_module, installed_version FROM ".MAIN_DB_PREFIX."deploymanager_instance_module";
$resIM = $db->query($sqlIM);
while ($resIM && ($im = $db->fetch_object($resIM))) {
    $instModVersions[$im->fk_instance][$im->fk_module] = $im->installed_version;
}

// Source instance per module (highest version)
$sourceByModule = array();
$sqlSrc = "SELECT im.fk_module, im.fk_instance, im.installed_version, i.domain";
$sqlSrc .= " FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im";
$sqlSrc .= " JOIN ".MAIN_DB_PREFIX."deploymanager_instance i ON i.rowid = im.fk_instance";
$sqlSrc .= " WHERE im.installed_version IS NOT NULL";
$sqlSrc .= " ORDER BY im.fk_module,";
$sqlSrc .= " CAST(SUBSTRING_INDEX(im.installed_version, '.', 1) AS UNSIGNED) DESC,";
$sqlSrc .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(im.installed_version, '.', 2), '.', -1) AS UNSIGNED) DESC,";
$sqlSrc .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(im.installed_version, '.', 3), '.', -1) AS UNSIGNED) DESC";
$resSrc = $db->query($sqlSrc);
$seen = array();
while ($resSrc && ($s = $db->fetch_object($resSrc))) {
    if (empty($seen[$s->fk_module])) {
        $sourceByModule[$s->fk_module] = array('instance_id' => (int) $s->fk_instance, 'version' => $s->installed_version, 'domain' => $s->domain);
        $seen[$s->fk_module] = true;
    }
}

$jsData = array(
    'modules' => $modules,
    'instances' => $instances,
    'instModVersions' => $instModVersions,
    'sourceByModule' => $sourceByModule,
    'preselectedModule' => $preselectedModule,
);

print '<div id="dm-wizard">';

// Step 1: Select module
print '<div class="dm-wizard-step" id="dm-step1">';
print '<h3>'.$langs->trans('DM_Step1SelectModule').'</h3>';
print '<div>';
print '<label><strong>'.$langs->trans('DM_SelectModule').'</strong></label><br>';
print '<select id="dm-module-select" class="minwidth300">';
print '<option value="">-- '.$langs->trans('DM_SelectModule').' --</option>';
foreach ($modules as $m) {
    print '<option value="'.$m->rowid.'">'.dol_escape_htmltag($m->display_name).' ('.$m->slug.')</option>';
}
print '</select>';
print '<div id="dm-selected-modules" style="margin-top:8px;"></div>';
print '</div>';
print '<div id="dm-source-info" style="margin-top:12px;display:none;"></div>';
print '<div style="margin-top:16px;"><button class="button" id="dm-step1-next" disabled>'.$langs->trans('DM_Step2SelectInstances').' &rarr;</button></div>';
print '</div>';

// Step 2: Select instances
print '<div class="dm-wizard-step" id="dm-step2" style="display:none;">';
print '<h3>'.$langs->trans('DM_Step2SelectInstances').'</h3>';
print '<div style="margin-bottom:12px;">';
print '<button type="button" class="button" id="dm-select-all">'.$langs->trans('DM_SelectAll').'</button> ';
print '<button type="button" class="button" id="dm-deselect-all">'.$langs->trans('DM_DeselectAll').'</button> ';
print '</div>';
print '<table class="tagtable liste" id="dm-instances-table">';
print '<thead><tr class="liste_titre">';
print '<th style="width:40px;"></th>';
print '<th>'.$langs->trans('DM_InstanceName').'</th>';
print '<th>'.$langs->trans('DM_Server').'</th>';
print '<th>'.$langs->trans('DM_Environment').'</th>';
print '<th>'.$langs->trans('DM_CurrentVersion').'</th>';
print '</tr></thead>';
print '<tbody id="dm-instances-body"></tbody>';
print '</table>';
print '<div style="margin-top:16px;">';
print '<button class="button" id="dm-step2-back">&larr; '.$langs->trans('DM_Back').'</button> ';
print '<button class="button" id="dm-step2-next" disabled>'.$langs->trans('DM_Step3Confirm').' &rarr;</button>';
print '</div>';
print '</div>';

// Step 3: Confirm
print '<div class="dm-wizard-step" id="dm-step3" style="display:none;">';
print '<h3>'.$langs->trans('DM_Step3Confirm').'</h3>';
print '<div id="dm-confirm-summary" style="background:#f8f9fa;padding:16px;border-radius:8px;margin-bottom:16px;"></div>';
print '<div style="margin-top:16px;">';
print '<button class="button" id="dm-step3-back">&larr; '.$langs->trans('DM_Back').'</button> ';
print '<button class="button button-primary" id="dm-deploy-btn" style="background:#e74c3c;color:#fff;border:none;padding:8px 24px;font-weight:bold;"><i class="fa fa-rocket"></i> '.$langs->trans('DM_DeployNow').'</button>';
print '</div>';
print '</div>';

print '</div>';

print '<script>var DM_WIZARD_DATA = '.json_encode($jsData).'; var DM_TOKEN = "'.newToken().'";</script>';

llxFooter();
$db->close();
