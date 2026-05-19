<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'alpha');

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && GETPOST('token') == $_SESSION['newtoken']) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_instance SET";
    $sql .= " fk_server = ".(int) GETPOST('fk_server', 'int').",";
    $sql .= " name = '".$db->escape(GETPOST('name', 'alphanohtml'))."',";
    $sql .= " domain = '".$db->escape(GETPOST('domain', 'alphanohtml'))."',";
    $sql .= " custom_path = '".$db->escape(GETPOST('custom_path', 'alphanohtml'))."',";
    $sql .= " conf_path = '".$db->escape(GETPOST('conf_path', 'alphanohtml'))."',";
    $sql .= " environment = '".$db->escape(GETPOST('environment', 'alpha'))."'";
    $sql .= " WHERE rowid = ".(int) $id;
    $db->query($sql);
    setEventMessages($langs->trans('DM_EditInstance').' OK', null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
    exit;
}

$sql = "SELECT i.*, s.name as server_name FROM ".MAIN_DB_PREFIX."deploymanager_instance i";
$sql .= " JOIN ".MAIN_DB_PREFIX."deploymanager_server s ON s.rowid = i.fk_server";
$sql .= " WHERE i.rowid = ".(int) $id;
$resq = $db->query($sql);
$instance = $db->fetch_object($resq);

if (!$instance) {
    setEventMessages('Instance not found', null, 'errors');
    header('Location: '.dol_buildpath('/custom/deploymanager/instance_list.php', 1));
    exit;
}

llxHeader('', $langs->trans('DM_InstanceCard'));

print '<script>var DOL_URL_ROOT="'.DOL_URL_ROOT.'";</script>';

print load_fiche_titre($langs->trans('DM_InstanceCard').': '.dol_escape_htmltag($instance->name), '', 'fa-globe');

if ($action === 'edit') {
    $servers = array();
    $resS = $db->query("SELECT rowid, name FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE status = 1 ORDER BY name");
    while ($resS && ($s = $db->fetch_object($resS))) {
        $servers[$s->rowid] = $s->name;
    }

    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="'.$id.'">';
    print '<table class="border centpercent">';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_Server').'</th><td><select name="fk_server" required>';
    foreach ($servers as $sid => $sname) {
        print '<option value="'.$sid.'"'.($sid == $instance->fk_server ? ' selected' : '').'>'.dol_escape_htmltag($sname).'</option>';
    }
    print '</select></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_InstanceName').'</th><td><input type="text" name="name" value="'.dol_escape_htmltag($instance->name).'" class="minwidth300" required></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_Domain').'</th><td><input type="text" name="domain" value="'.dol_escape_htmltag($instance->domain).'" class="minwidth300" required></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_CustomPath').'</th><td><input type="text" name="custom_path" value="'.dol_escape_htmltag($instance->custom_path).'" class="minwidth400" required></td></tr>';
    print '<tr><th class="fieldrequired">'.$langs->trans('DM_ConfPath').'</th><td><input type="text" name="conf_path" value="'.dol_escape_htmltag($instance->conf_path).'" class="minwidth400" required></td></tr>';
    print '<tr><th>'.$langs->trans('DM_Environment').'</th><td><select name="environment">';
    foreach (array('production', 'staging', 'development') as $env) {
        print '<option value="'.$env.'"'.($instance->environment === $env ? ' selected' : '').'>'.$langs->trans('DM_'.ucfirst($env)).'</option>';
    }
    print '</select></td></tr>';
    print '</table>';
    print '<div class="center" style="margin-top:12px;">';
    print '<input type="submit" class="button" value="'.$langs->trans('DM_Save').'">';
    print ' <a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'.$langs->trans('DM_Cancel').'</a>';
    print '</div>';
    print '</form>';
} else {
    print '<table class="border centpercent">';
    print '<tr><th style="width:25%">'.$langs->trans('DM_Server').'</th><td><a href="'.dol_buildpath('/custom/deploymanager/server_card.php?id='.$instance->fk_server, 1).'">'.dol_escape_htmltag($instance->server_name).'</a></td></tr>';
    print '<tr><th>'.$langs->trans('DM_Domain').'</th><td>'.dol_escape_htmltag($instance->domain).'</td></tr>';
    print '<tr><th>'.$langs->trans('DM_CustomPath').'</th><td><code>'.dol_escape_htmltag($instance->custom_path).'</code></td></tr>';
    print '<tr><th>'.$langs->trans('DM_ConfPath').'</th><td><code>'.dol_escape_htmltag($instance->conf_path).'</code></td></tr>';
    print '<tr><th>'.$langs->trans('DM_Environment').'</th><td>'.$langs->trans('DM_'.ucfirst($instance->environment)).'</td></tr>';
    print '</table>';

    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&action=edit"><i class="fa fa-pencil-alt"></i> '.$langs->trans('DM_EditInstance').'</a>';
    print '<button class="butAction dm-scan-one" data-id="'.$id.'" type="button"><i class="fa fa-search"></i> '.$langs->trans('DM_ScanModules').'</button>';
    print '</div>';

    // Installed modules
    $sqlMod = "SELECT im.*, m.slug, m.display_name FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im";
    $sqlMod .= " JOIN ".MAIN_DB_PREFIX."deploymanager_module m ON m.rowid = im.fk_module";
    $sqlMod .= " WHERE im.fk_instance = ".(int) $id." ORDER BY m.slug";
    $resMod = $db->query($sqlMod);

    print '<h3 style="margin-top:20px;">'.$langs->trans('DM_InstalledModules').'</h3>';

    if ($resMod && $db->num_rows($resMod) > 0) {
        // Get latest versions
        $latestVersions = array();
        $resLV = $db->query("SELECT fk_module, version FROM ".MAIN_DB_PREFIX."deploymanager_release WHERE rowid IN (SELECT MAX(rowid) FROM ".MAIN_DB_PREFIX."deploymanager_release GROUP BY fk_module)");
        while ($resLV && ($lv = $db->fetch_object($resLV))) {
            $latestVersions[$lv->fk_module] = $lv->version;
        }

        print '<table class="tagtable liste">';
        print '<thead><tr class="liste_titre"><th>'.$langs->trans('DM_Module').'</th><th>'.$langs->trans('DM_CurrentVersion').'</th><th>'.$langs->trans('DM_LatestVersion').'</th><th>'.$langs->trans('DM_Result').'</th><th>'.$langs->trans('DM_Date').'</th></tr></thead>';
        print '<tbody>';
        while ($mod = $db->fetch_object($resMod)) {
            $latest = isset($latestVersions[$mod->fk_module]) ? $latestVersions[$mod->fk_module] : '';
            $upToDate = ($latest && $mod->installed_version === $latest);

            print '<tr>';
            print '<td><a href="'.dol_buildpath('/custom/deploymanager/module_card.php?slug='.$mod->slug, 1).'">'.dol_escape_htmltag($mod->display_name).'</a></td>';
            print '<td>'.$mod->installed_version.'</td>';
            print '<td>'.($latest ?: '—').'</td>';
            print '<td>';
            if (!$latest) {
                print '<span class="badge badge-status0">'.$langs->trans('DM_Unknown').'</span>';
            } elseif ($upToDate) {
                print '<span class="badge badge-status4">'.$langs->trans('DM_UpToDate').'</span>';
            } else {
                print '<span class="badge badge-status1">'.$langs->trans('DM_Outdated').'</span>';
            }
            print '</td>';
            print '<td>'.($mod->last_scan ? dol_print_date(strtotime($mod->last_scan), 'dayhour') : '—').'</td>';
            print '</tr>';
        }
        print '</tbody></table>';
    } else {
        print '<div class="opacitymedium" style="padding:10px;">'.$langs->trans('DM_NoData').' — <button class="button dm-scan-one" data-id="'.$id.'" type="button">'.$langs->trans('DM_ScanModules').'</button></div>';
    }
}

llxFooter();
$db->close();
