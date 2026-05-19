<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

llxHeader('', $langs->trans('DM_DashboardTitle'));

print load_fiche_titre($langs->trans('DM_DashboardTitle'), '', 'fa-rocket');

// Stats cards
$sqlServers = "SELECT COUNT(*) as c FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE status = 1";
$sqlInstances = "SELECT COUNT(*) as c FROM ".MAIN_DB_PREFIX."deploymanager_instance WHERE status = 1";
$sqlModules = "SELECT COUNT(*) as c FROM ".MAIN_DB_PREFIX."deploymanager_module";
$sqlOutdated = "SELECT COUNT(*) as c FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im";
$sqlOutdated .= " WHERE im.installed_version IS NOT NULL";
$sqlOutdated .= " AND im.installed_version != (SELECT MAX(im2.installed_version) FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im2 WHERE im2.fk_module = im.fk_module AND im2.installed_version IS NOT NULL)";

$stats = array();
foreach (array('servers' => $sqlServers, 'instances' => $sqlInstances, 'modules' => $sqlModules, 'outdated' => $sqlOutdated) as $key => $sql) {
    $res = $db->query($sql);
    $stats[$key] = ($res && ($obj = $db->fetch_object($res))) ? (int) $obj->c : 0;
}

print '<div class="dm-stats-row">';

$cards = array(
    array('label' => $langs->trans('DM_TotalServers'), 'value' => $stats['servers'], 'icon' => 'fa-server', 'color' => '#3b82f6', 'url' => 'server_list.php'),
    array('label' => $langs->trans('DM_TotalInstances'), 'value' => $stats['instances'], 'icon' => 'fa-globe', 'color' => '#22c55e', 'url' => 'instance_list.php'),
    array('label' => $langs->trans('DM_TotalModules'), 'value' => $stats['modules'], 'icon' => 'fa-puzzle-piece', 'color' => '#a855f7', 'url' => 'module_list.php'),
    array('label' => $langs->trans('DM_PendingUpdates'), 'value' => $stats['outdated'], 'icon' => 'fa-exclamation-triangle', 'color' => $stats['outdated'] > 0 ? '#f59e0b' : '#22c55e', 'url' => 'module_list.php'),
);

foreach ($cards as $card) {
    print '<a href="'.dol_buildpath('/custom/deploymanager/'.$card['url'], 1).'" class="dm-stat-card" style="border-left:4px solid '.$card['color'].'">';
    print '<div class="dm-stat-icon" style="color:'.$card['color'].'"><i class="fa '.$card['icon'].'"></i></div>';
    print '<div class="dm-stat-info"><div class="dm-stat-value">'.$card['value'].'</div><div class="dm-stat-label">'.$card['label'].'</div></div>';
    print '</a>';
}

print '</div>';

// Version matrix
print '<h3 style="margin-top:20px;">'.$langs->trans('DM_ModuleVersionMatrix').'</h3>';
print '<div style="overflow-x:auto;width:calc(100vw - 280px);border:1px solid #e2e5e9;border-radius:10px;">';

$sqlModList = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_module ORDER BY slug";
$resModList = $db->query($sqlModList);
$modules = array();
while ($resModList && ($m = $db->fetch_object($resModList))) {
    $modules[] = $m;
}

$sqlInstList = "SELECT i.*, s.name as server_name FROM ".MAIN_DB_PREFIX."deploymanager_instance i";
$sqlInstList .= " JOIN ".MAIN_DB_PREFIX."deploymanager_server s ON s.rowid = i.fk_server";
$sqlInstList .= " WHERE i.status = 1 ORDER BY s.name, i.name";
$resInstList = $db->query($sqlInstList);
$instances = array();
while ($resInstList && ($inst = $db->fetch_object($resInstList))) {
    $instances[] = $inst;
}

// Latest versions per module (max version found across instances)
$latestVersions = array();
foreach ($modules as $m) {
    $sqlLast = "SELECT installed_version as ver FROM ".MAIN_DB_PREFIX."deploymanager_instance_module WHERE fk_module = ".(int) $m->rowid." AND installed_version IS NOT NULL";
    $sqlLast .= " ORDER BY CAST(SUBSTRING_INDEX(installed_version, '.', 1) AS UNSIGNED) DESC,";
    $sqlLast .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(installed_version, '.', 2), '.', -1) AS UNSIGNED) DESC,";
    $sqlLast .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(installed_version, '.', 3), '.', -1) AS UNSIGNED) DESC LIMIT 1";
    $resLast = $db->query($sqlLast);
    $latestVersions[$m->rowid] = ($resLast && ($lv = $db->fetch_object($resLast))) ? $lv->ver : '';
}

// Installed versions map
$installedMap = array();
$sqlIM = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_instance_module";
$resIM = $db->query($sqlIM);
while ($resIM && ($im = $db->fetch_object($resIM))) {
    $installedMap[$im->fk_instance][$im->fk_module] = $im->installed_version;
}

if (!empty($modules) && !empty($instances)) {
    print '<table class="tagtable liste">';
    print '<thead><tr class="liste_titre">';
    print '<th>'.$langs->trans('DM_InstanceName').'</th>';
    print '<th>'.$langs->trans('DM_Server').'</th>';
    foreach ($modules as $m) {
        print '<th class="center" title="'.dol_escape_htmltag($m->slug).'">'.dol_escape_htmltag(dol_trunc($m->slug, 12)).'</th>';
    }
    print '</tr></thead>';
    print '<tbody>';

    foreach ($instances as $inst) {
        print '<tr>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/instance_card.php?id='.$inst->rowid, 1).'">'.dol_escape_htmltag($inst->name).'</a></td>';
        print '<td>'.dol_escape_htmltag($inst->server_name).'</td>';

        foreach ($modules as $m) {
            $ver = isset($installedMap[$inst->rowid][$m->rowid]) ? $installedMap[$inst->rowid][$m->rowid] : '';
            $latest = $latestVersions[$m->rowid];

            if (empty($ver)) {
                print '<td class="center"><span class="opacitymedium">—</span></td>';
            } elseif ($latest && $ver === $latest) {
                print '<td class="center"><span class="badge badge-status4" title="'.$langs->trans('DM_UpToDate').'">'.$ver.'</span></td>';
            } else {
                print '<td class="center"><span class="badge badge-status1" title="'.$langs->trans('DM_Outdated').': última '.$latest.'">'.$ver.'</span></td>';
            }
        }
        print '</tr>';
    }

    print '</tbody></table>';
} else {
    print '<div class="opacitymedium" style="padding:20px;text-align:center;">';
    if (empty($instances)) {
        print $langs->trans('DM_NoData').' — <a href="'.dol_buildpath('/custom/deploymanager/instance_list.php', 1).'">'.$langs->trans('DM_AddInstance').'</a>';
    } else {
        print $langs->trans('DM_NoData').' — <a href="'.dol_buildpath('/custom/deploymanager/instance_list.php', 1).'">'.$langs->trans('DM_ScanAll').'</a>';
    }
    print '</div>';
}

print '</div>';

// Recent deployments
$sqlRecent = "SELECT b.*, m.slug, m.display_name, src.domain as source_domain FROM ".MAIN_DB_PREFIX."deploymanager_batch b";
$sqlRecent .= " JOIN ".MAIN_DB_PREFIX."deploymanager_module m ON m.rowid = b.fk_module";
$sqlRecent .= " LEFT JOIN ".MAIN_DB_PREFIX."deploymanager_instance src ON src.rowid = b.fk_source_instance";
$sqlRecent .= " ORDER BY b.date_creation DESC LIMIT 10";
$resRecent = $db->query($sqlRecent);

print '<div style="margin-top:20px;">';
print '<h3>'.$langs->trans('DM_RecentDeploys').'</h3>';

if ($resRecent && $db->num_rows($resRecent) > 0) {
    print '<table class="tagtable liste">';
    print '<thead><tr class="liste_titre">';
    print '<th>'.$langs->trans('DM_Date').'</th>';
    print '<th>'.$langs->trans('DM_Module').'</th>';
    print '<th>Origen</th>';
    print '<th>'.$langs->trans('DM_Instances').'</th>';
    print '<th>'.$langs->trans('DM_Result').'</th>';
    print '</tr></thead>';
    print '<tbody>';

    while ($b = $db->fetch_object($resRecent)) {
        $statusClass = 'badge-status0';
        if ($b->status === 'completed') $statusClass = 'badge-status4';
        elseif ($b->status === 'failed') $statusClass = 'badge-status8';
        elseif ($b->status === 'partial_failure') $statusClass = 'badge-status1';
        elseif ($b->status === 'running') $statusClass = 'badge-status6';

        print '<tr>';
        print '<td>'.dol_print_date(strtotime($b->date_creation), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag($b->display_name).'</td>';
        print '<td>'.dol_escape_htmltag($b->source_domain ?: '-').'</td>';
        print '<td>'.$b->completed_count.'/'.$b->total_count.'</td>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/deploy_status.php?id='.$b->rowid, 1).'"><span class="badge '.$statusClass.'">'.$langs->trans('DM_Status'.ucfirst($b->status)).'</span></a></td>';
        print '</tr>';
    }

    print '</tbody></table>';
} else {
    print '<div class="opacitymedium" style="padding:20px;text-align:center;">'.$langs->trans('DM_NoData').'</div>';
}

print '</div>';

llxFooter();
$db->close();
