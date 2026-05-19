<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->admin)) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');
$id = GETPOSTINT('id');

// Actions
if ($action === 'confirm_delete' && $id > 0) {
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE rowid = ".(int) $id);
    setEventMessages($langs->trans('DM_DeleteServer').' OK', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('token') == $_SESSION['newtoken']) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_server (name, host, ssh_user, ssh_port, ssh_key_path, is_local) VALUES (";
    $sql .= "'".$db->escape(GETPOST('name', 'alphanohtml'))."',";
    $sql .= "'".$db->escape(GETPOST('host', 'alphanohtml'))."',";
    $sql .= "'".$db->escape(GETPOST('ssh_user', 'alphanohtml') ?: 'deployer')."',";
    $sql .= (int) (GETPOST('ssh_port', 'int') ?: 22).",";
    $sql .= "'".$db->escape(GETPOST('ssh_key_path', 'alphanohtml'))."',";
    $sql .= (int) GETPOST('is_local', 'int').")";
    $db->query($sql);
    setEventMessages($langs->trans('DM_AddServer').' OK', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

llxHeader('', $langs->trans('DM_ServerList'));

print '<script>var DOL_URL_ROOT="'.DOL_URL_ROOT.'";</script>';

print load_fiche_titre($langs->trans('DM_ServerList'), '', 'fa-server');

// Add form
if ($action === 'create') {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';
    print '<table class="border centpercent">';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_ServerName').'</th><td><input type="text" name="name" class="minwidth300" required></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_ServerHost').'</th><td><input type="text" name="host" class="minwidth300" required placeholder="192.168.1.10"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHUser').'</th><td><input type="text" name="ssh_user" value="deployer" class="minwidth200"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHPort').'</th><td><input type="number" name="ssh_port" value="22" class="minwidth100"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHKeyPath').'</th><td><input type="text" name="ssh_key_path" class="minwidth400" placeholder="/root/.ssh/deploy_key"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_IsLocal').'</th><td><input type="checkbox" name="is_local" value="1"></td></tr>';
    print '</table>';
    print '<div class="center" style="margin-top:12px;">';
    print '<input type="submit" class="button" value="'.$langs->trans('DM_Save').'">';
    print ' <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('DM_Cancel').'</a>';
    print '</div>';
    print '</form>';
} else {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=create"><i class="fa fa-plus"></i> '.$langs->trans('DM_AddServer').'</a>';
    print '</div>';
}

// List
$sql = "SELECT s.*, (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."deploymanager_instance i WHERE i.fk_server = s.rowid) as nb_instances";
$sql .= " FROM ".MAIN_DB_PREFIX."deploymanager_server s ORDER BY s.name";
$res = $db->query($sql);

print '<table class="tagtable liste">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('DM_ServerName').'</th>';
print '<th>'.$langs->trans('DM_ServerHost').'</th>';
print '<th>'.$langs->trans('DM_SSHUser').'</th>';
print '<th>'.$langs->trans('DM_SSHPort').'</th>';
print '<th>'.$langs->trans('DM_IsLocal').'</th>';
print '<th>'.$langs->trans('DM_Instances').'</th>';
print '<th>'.$langs->trans('DM_TestConnection').'</th>';
print '<th></th>';
print '</tr></thead>';
print '<tbody>';

if ($res && $db->num_rows($res) > 0) {
    while ($obj = $db->fetch_object($res)) {
        print '<tr>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/server_card.php?id='.$obj->rowid, 1).'">'.dol_escape_htmltag($obj->name).'</a></td>';
        print '<td>'.dol_escape_htmltag($obj->host).'</td>';
        print '<td>'.dol_escape_htmltag($obj->ssh_user).'</td>';
        print '<td>'.$obj->ssh_port.'</td>';
        print '<td>'.($obj->is_local ? '<span class="badge badge-status4">'.$langs->trans('DM_Yes').'</span>' : '').'</td>';
        print '<td>'.$obj->nb_instances.'</td>';
        print '<td><button class="button dm-test-ssh" data-id="'.$obj->rowid.'" type="button"><i class="fa fa-plug"></i> Test</button> <span class="dm-test-result" data-id="'.$obj->rowid.'"></span></td>';
        print '<td><a href="'.$_SERVER['PHP_SELF'].'?action=confirm_delete&id='.$obj->rowid.'&token='.newToken().'" onclick="return confirm(\''.$langs->trans('DM_Confirm').'?\')"><i class="fa fa-trash" style="color:#e74c3c;"></i></a></td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="8" class="center opacitymedium" style="padding:20px;">'.$langs->trans('DM_NoData').'</td></tr>';
}

print '</tbody></table>';

llxFooter();
$db->close();
