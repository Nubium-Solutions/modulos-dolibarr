<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');
$id = GETPOSTINT('id');

if ($action === 'confirm_delete' && $id > 0) {
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."deploymanager_instance_module WHERE fk_instance = ".(int) $id);
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."deploymanager_instance WHERE rowid = ".(int) $id);
    setEventMessages($langs->trans('DM_DeleteInstance').' OK', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('token') == $_SESSION['newtoken']) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_instance (fk_server, name, domain, custom_path, conf_path, environment) VALUES (";
    $sql .= (int) GETPOST('fk_server', 'int').",";
    $sql .= "'".$db->escape(GETPOST('name', 'alphanohtml'))."',";
    $sql .= "'".$db->escape(GETPOST('domain', 'alphanohtml'))."',";
    $sql .= "'".$db->escape(GETPOST('custom_path', 'alphanohtml'))."',";
    $sql .= "'".$db->escape(GETPOST('conf_path', 'alphanohtml'))."',";
    $sql .= "'".$db->escape(GETPOST('environment', 'alpha'))."')";
    $db->query($sql);
    setEventMessages($langs->trans('DM_AddInstance').' OK', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

llxHeader('', $langs->trans('DM_InstanceList'));

print '<script>var DOL_URL_ROOT="'.DOL_URL_ROOT.'";</script>';

print load_fiche_titre($langs->trans('DM_InstanceList'), '', 'fa-globe');

// Servers for select
$servers = array();
$resS = $db->query("SELECT rowid, name FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE status = 1 ORDER BY name");
while ($resS && ($s = $db->fetch_object($resS))) {
    $servers[$s->rowid] = $s->name;
}

if ($action === 'create') {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    print '<table class="border centpercent">';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_Server').'</th><td><select name="fk_server" class="minwidth200" required>';
    print '<option value="">--</option>';
    foreach ($servers as $sid => $sname) {
        print '<option value="'.$sid.'">'.dol_escape_htmltag($sname).'</option>';
    }
    print '</select></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_InstanceName').'</th><td><input type="text" name="name" class="minwidth300" required placeholder="erp.micliente.com"></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_Domain').'</th><td><input type="text" name="domain" class="minwidth300" required placeholder="erp.micliente.com"></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_CustomPath').'</th><td><input type="text" name="custom_path" class="minwidth400" required placeholder="/var/www/html/dolibarr/htdocs/custom"></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_ConfPath').'</th><td><input type="text" name="conf_path" class="minwidth400" required placeholder="/var/www/html/dolibarr/htdocs/conf/conf.php"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_Environment').'</th><td><select name="environment">';
    print '<option value="production">'.$langs->trans('DM_Production').'</option>';
    print '<option value="staging">'.$langs->trans('DM_Staging').'</option>';
    print '<option value="development">'.$langs->trans('DM_Development').'</option>';
    print '</select></td></tr>';
    print '</table>';
    print '<div class="center" style="margin-top:12px;">';
    print '<input type="submit" class="button" value="'.$langs->trans('DM_Save').'">';
    print ' <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('DM_Cancel').'</a>';
    print '</div>';
    print '</form>';
} else {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=create"><i class="fa fa-plus"></i> '.$langs->trans('DM_AddInstance').'</a>';
    if (!empty($servers)) {
        print '<button class="butAction" id="dm-scan-all" type="button"><i class="fa fa-search"></i> '.$langs->trans('DM_ScanAll').'</button>';
    }
    // Autodiscover per server
    foreach ($servers as $sid => $sname) {
        print '<button class="butAction dm-autodiscover" data-server="'.$sid.'" type="button"><i class="fa fa-magic"></i> Detectar instancias ('.$sname.')</button>';
    }
    print '</div>';
}

// List
$sql = "SELECT i.*, s.name as server_name,";
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im WHERE im.fk_instance = i.rowid AND im.installed_version IS NOT NULL) as nb_modules";
$sql .= " FROM ".MAIN_DB_PREFIX."deploymanager_instance i";
$sql .= " JOIN ".MAIN_DB_PREFIX."deploymanager_server s ON s.rowid = i.fk_server";
$sql .= " ORDER BY s.name, i.name";
$resq = $db->query($sql);

print '<table class="tagtable liste">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('DM_InstanceName').'</th>';
print '<th>'.$langs->trans('DM_Domain').'</th>';
print '<th>'.$langs->trans('DM_Server').'</th>';
print '<th>'.$langs->trans('DM_Environment').'</th>';
print '<th>'.$langs->trans('DM_Modules').'</th>';
print '<th></th>';
print '</tr></thead>';
print '<tbody>';

if ($resq && $db->num_rows($resq) > 0) {
    while ($obj = $db->fetch_object($resq)) {
        $envBadge = 'badge-status4';
        if ($obj->environment === 'staging') $envBadge = 'badge-status1';
        elseif ($obj->environment === 'development') $envBadge = 'badge-status0';

        print '<tr>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/instance_card.php?id='.$obj->rowid, 1).'">'.dol_escape_htmltag($obj->name).'</a></td>';
        print '<td>'.dol_escape_htmltag($obj->domain).'</td>';
        print '<td>'.dol_escape_htmltag($obj->server_name).'</td>';
        print '<td><span class="badge '.$envBadge.'">'.$langs->trans('DM_'.ucfirst($obj->environment)).'</span></td>';
        print '<td>'.$obj->nb_modules.'</td>';
        print '<td>';
        print '<button class="button dm-scan-one" data-id="'.$obj->rowid.'" type="button" title="'.$langs->trans('DM_ScanModules').'"><i class="fa fa-search"></i></button>';
        print ' <a href="'.$_SERVER['PHP_SELF'].'?action=confirm_delete&id='.$obj->rowid.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('DM_Confirm').'?\')"><i class="fa fa-trash" style="color:#e74c3c;"></i></a>';
        print '</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="6" class="center opacitymedium" style="padding:20px;">'.$langs->trans('DM_NoData').'</td></tr>';
}

print '</tbody></table>';

llxFooter();
$db->close();
