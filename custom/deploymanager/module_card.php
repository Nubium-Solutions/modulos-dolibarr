<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

$slug = GETPOST('slug', 'alphanohtml');
$id = GETPOSTINT('id');

if ($id > 0) {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_module WHERE rowid = ".(int) $id;
} else {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_module WHERE slug = '".$db->escape($slug)."'";
}
$resq = $db->query($sql);
$module = $db->fetch_object($resq);

if (!$module) {
    setEventMessages('Module not found', null, 'errors');
    header('Location: '.dol_buildpath('/custom/deploymanager/module_list.php', 1));
    exit;
}

llxHeader('', $langs->trans('DM_ModuleCard'));

print load_fiche_titre($langs->trans('DM_ModuleCard').': '.dol_escape_htmltag($module->display_name), '', 'fa-puzzle-piece');

// Module info
print '<table class="border centpercent">';
print '<tr><th style="width:25%">'.$langs->trans('DM_ModuleSlug').'</th><td><strong>'.dol_escape_htmltag($module->slug).'</strong></td></tr>';
print '<tr><th>'.$langs->trans('DM_ModuleName').'</th><td>'.dol_escape_htmltag($module->display_name).'</td></tr>';
print '</table>';

// Where installed
$sqlInst = "SELECT im.*, i.name as instance_name, i.domain, i.environment, s.name as server_name";
$sqlInst .= " FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im";
$sqlInst .= " JOIN ".MAIN_DB_PREFIX."deploymanager_instance i ON i.rowid = im.fk_instance";
$sqlInst .= " JOIN ".MAIN_DB_PREFIX."deploymanager_server s ON s.rowid = i.fk_server";
$sqlInst .= " WHERE im.fk_module = ".(int) $module->rowid." AND im.installed_version IS NOT NULL";
$sqlInst .= " ORDER BY s.name, i.name";
$resInst = $db->query($sqlInst);

// Latest release
$sqlLast = "SELECT version FROM ".MAIN_DB_PREFIX."deploymanager_release WHERE fk_module = ".(int) $module->rowid." ORDER BY rowid DESC LIMIT 1";
$resLast = $db->query($sqlLast);
$latestVersion = ($resLast && ($lv = $db->fetch_object($resLast))) ? $lv->version : '';

print '<h3 style="margin-top:20px;">'.$langs->trans('DM_InstalledOn').'</h3>';

if ($resInst && $db->num_rows($resInst) > 0) {
    print '<table class="tagtable liste">';
    print '<thead><tr class="liste_titre"><th>'.$langs->trans('DM_InstanceName').'</th><th>'.$langs->trans('DM_Server').'</th><th>'.$langs->trans('DM_Environment').'</th><th>'.$langs->trans('DM_CurrentVersion').'</th><th>'.$langs->trans('DM_Result').'</th></tr></thead>';
    print '<tbody>';
    while ($inst = $db->fetch_object($resInst)) {
        $upToDate = ($latestVersion && $inst->installed_version === $latestVersion);
        print '<tr>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/instance_card.php?id='.$inst->fk_instance, 1).'">'.dol_escape_htmltag($inst->instance_name).'</a></td>';
        print '<td>'.dol_escape_htmltag($inst->server_name).'</td>';
        print '<td>'.$langs->trans('DM_'.ucfirst($inst->environment)).'</td>';
        print '<td>'.$inst->installed_version.'</td>';
        print '<td>';
        if ($upToDate) {
            print '<span class="badge badge-status4">'.$langs->trans('DM_UpToDate').'</span>';
        } else {
            print '<span class="badge badge-status1">'.$langs->trans('DM_Outdated').'</span>';
        }
        print '</td>';
        print '</tr>';
    }
    print '</tbody></table>';
} else {
    print '<div class="opacitymedium" style="padding:10px;">'.$langs->trans('DM_NoData').'</div>';
}

// Releases
$sqlRel = "SELECT r.*, u.firstname, u.lastname FROM ".MAIN_DB_PREFIX."deploymanager_release r";
$sqlRel .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = r.fk_user_author";
$sqlRel .= " WHERE r.fk_module = ".(int) $module->rowid." ORDER BY r.rowid DESC";
$resRel = $db->query($sqlRel);

print '<h3 style="margin-top:20px;">'.$langs->trans('DM_Releases').'</h3>';

if ($resRel && $db->num_rows($resRel) > 0) {
    print '<table class="tagtable liste">';
    print '<thead><tr class="liste_titre"><th>'.$langs->trans('DM_Version').'</th><th>'.$langs->trans('DM_Date').'</th><th>'.$langs->trans('DM_UploadedBy').'</th><th>'.$langs->trans('DM_ZipHash').'</th></tr></thead>';
    print '<tbody>';
    while ($rel = $db->fetch_object($resRel)) {
        print '<tr>';
        print '<td><strong>'.$rel->version.'</strong></td>';
        print '<td>'.dol_print_date(strtotime($rel->date_creation), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag(trim($rel->firstname.' '.$rel->lastname)).'</td>';
        print '<td><code>'.substr($rel->zip_hash, 0, 16).'...</code></td>';
        print '</tr>';
    }
    print '</tbody></table>';
} else {
    print '<div class="opacitymedium" style="padding:10px;">'.$langs->trans('DM_NoData').'</div>';
}

// Deploy action
if ($latestVersion && ($user->admin || !empty($user->rights->deploymanager->deploy))) {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.dol_buildpath('/custom/deploymanager/deploy_wizard.php?module='.$module->slug, 1).'"><i class="fa fa-rocket"></i> '.$langs->trans('DM_Deploy').' '.$latestVersion.'</a>';
    print '</div>';
}

llxFooter();
$db->close();
