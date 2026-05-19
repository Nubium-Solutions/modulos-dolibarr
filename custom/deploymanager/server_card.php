<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->admin)) {
    accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'alpha');

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('token') == $_SESSION['newtoken']) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_server SET";
    $sql .= " name = '".$db->escape(GETPOST('name', 'alphanohtml'))."',";
    $sql .= " host = '".$db->escape(GETPOST('host', 'alphanohtml'))."',";
    $sql .= " ssh_user = '".$db->escape(GETPOST('ssh_user', 'alphanohtml'))."',";
    $sql .= " ssh_port = ".(int) (GETPOST('ssh_port', 'int') ?: 22).",";
    $sql .= " ssh_key_path = '".$db->escape(GETPOST('ssh_key_path', 'alphanohtml'))."',";
    $sql .= " is_local = ".(int) GETPOST('is_local', 'int');
    $sql .= " WHERE rowid = ".(int) $id;
    $db->query($sql);
    setEventMessages($langs->trans('DM_EditServer').' OK', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
    exit;
}

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE rowid = ".(int) $id;
$resq = $db->query($sql);
$server = $db->fetch_object($resq);

if (!$server) {
    setEventMessages('Server not found', null, 'errors');
    header('Location: '.dol_buildpath('/custom/deploymanager/server_list.php', 1));
    exit;
}

llxHeader('', $langs->trans('DM_ServerCard'));

print '<script>var DOL_URL_ROOT="'.DOL_URL_ROOT.'";</script>';

print load_fiche_titre($langs->trans('DM_ServerCard').': '.dol_escape_htmltag($server->name), '', 'fa-server');

if ($action === 'edit') {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$id.'">';
    print '<table class="border centpercent">';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_ServerName').'</th><td><input type="text" name="name" value="'.dol_escape_htmltag($server->name).'" class="minwidth300" required></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_ServerHost').'</th><td><input type="text" name="host" value="'.dol_escape_htmltag($server->host).'" class="minwidth300" required></td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHUser').'</th><td><input type="text" name="ssh_user" value="'.dol_escape_htmltag($server->ssh_user).'" class="minwidth200"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHPort').'</th><td><input type="number" name="ssh_port" value="'.$server->ssh_port.'" class="minwidth100"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHKeyPath').'</th><td><input type="text" name="ssh_key_path" value="'.dol_escape_htmltag($server->ssh_key_path).'" class="minwidth400"></td></tr>';
    print '<tr><th>'.$langs->trans('DM_IsLocal').'</th><td><input type="checkbox" name="is_local" value="1"'.($server->is_local ? ' checked' : '').'></td></tr>';
    print '</table>';
    print '<div class="center" style="margin-top:12px;">';
    print '<input type="submit" class="button" value="'.$langs->trans('DM_Save').'">';
    print ' <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'.$langs->trans('DM_Cancel').'</a>';
    print '</div>';
    print '</form>';
} else {
    print '<table class="border centpercent">';
    print '<tr><th style="width:25%">'.$langs->trans('DM_ServerName').'</th><td>'.dol_escape_htmltag($server->name).'</td></tr>';
    print '<tr><th>'.$langs->trans('DM_ServerHost').'</th><td>'.dol_escape_htmltag($server->host).'</td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHUser').'</th><td>'.dol_escape_htmltag($server->ssh_user).'</td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHPort').'</th><td>'.$server->ssh_port.'</td></tr>';
    print '<tr><th>'.$langs->trans('DM_SSHKeyPath').'</th><td>'.dol_escape_htmltag($server->ssh_key_path).'</td></tr>';
    print '<tr><th>'.$langs->trans('DM_IsLocal').'</th><td>'.($server->is_local ? $langs->trans('DM_Yes') : $langs->trans('DM_No')).'</td></tr>';
    print '</table>';

    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit"><i class="fa fa-pencil-alt"></i> '.$langs->trans('DM_EditServer').'</a>';
    print '<button class="butAction dm-test-ssh" data-id="'.$server->rowid.'" type="button"><i class="fa fa-plug"></i> '.$langs->trans('DM_TestConnection').'</button>';
    print '<span class="dm-test-result" data-id="'.$server->rowid.'" style="margin-left:10px;"></span>';
    print '</div>';

    // Instances on this server
    $sqlInst = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_instance WHERE fk_server = ".(int) $id." ORDER BY name";
    $resInst = $db->query($sqlInst);

    print '<h3 style="margin-top:20px;">'.$langs->trans('DM_Instances').'</h3>';
    if ($resInst && $db->num_rows($resInst) > 0) {
        print '<table class="tagtable liste">';
        print '<thead><tr class="liste_titre"><th>'.$langs->trans('DM_InstanceName').'</th><th>'.$langs->trans('DM_Domain').'</th><th>'.$langs->trans('DM_Environment').'</th></tr></thead>';
        print '<tbody>';
        while ($inst = $db->fetch_object($resInst)) {
            print '<tr>';
            print '<td><a href="'.dol_buildpath('/custom/deploymanager/instance_card.php?id='.$inst->rowid, 1).'">'.dol_escape_htmltag($inst->name).'</a></td>';
            print '<td>'.dol_escape_htmltag($inst->domain).'</td>';
            print '<td>'.$langs->trans('DM_'.ucfirst($inst->environment)).'</td>';
            print '</tr>';
        }
        print '</tbody></table>';
    } else {
        print '<div class="opacitymedium" style="padding:10px;">'.$langs->trans('DM_NoData').'</div>';
    }
}

llxFooter();
$db->close();
