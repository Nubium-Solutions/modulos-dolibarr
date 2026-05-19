<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

llxHeader('', $langs->trans('DM_DeployHistory'));

print load_fiche_titre($langs->trans('DM_DeployHistory'), '', 'fa-history');

$sql = "SELECT b.*, m.slug, m.display_name, u.firstname, u.lastname, src.domain as source_domain";
$sql .= " FROM ".MAIN_DB_PREFIX."deploymanager_batch b";
$sql .= " JOIN ".MAIN_DB_PREFIX."deploymanager_module m ON m.rowid = b.fk_module";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = b.fk_user_author";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."deploymanager_instance src ON src.rowid = b.fk_source_instance";
$sql .= " ORDER BY b.date_creation DESC LIMIT 50";
$resq = $db->query($sql);

$batchDestinos = array();
$sqlDest = "SELECT d.fk_batch, i.domain FROM ".MAIN_DB_PREFIX."deploymanager_deployment d";
$sqlDest .= " JOIN ".MAIN_DB_PREFIX."deploymanager_instance i ON i.rowid = d.fk_instance";
$sqlDest .= " ORDER BY d.fk_batch, i.domain";
$resDest = $db->query($sqlDest);
while ($resDest && ($dd = $db->fetch_object($resDest))) {
    $batchDestinos[$dd->fk_batch][] = $dd->domain;
}

print '<table class="tagtable liste">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('DM_Date').'</th>';
print '<th>'.$langs->trans('DM_Module').'</th>';
print '<th>Origen</th>';
print '<th>Destino</th>';
print '<th>'.$langs->trans('DM_Result').'</th>';
print '<th>'.$langs->trans('DM_Duration').'</th>';
print '<th></th>';
print '</tr></thead>';
print '<tbody>';

if ($resq && $db->num_rows($resq) > 0) {
    while ($b = $db->fetch_object($resq)) {
        $statusBadge = 'badge-status0';
        $statusLabel = $b->status;
        if ($b->status === 'completed') { $statusBadge = 'badge-status4'; $statusLabel = $langs->trans('DM_StatusCompleted'); }
        elseif ($b->status === 'failed') { $statusBadge = 'badge-status8'; $statusLabel = $langs->trans('DM_StatusFailed'); }
        elseif ($b->status === 'partial_failure') { $statusBadge = 'badge-status1'; $statusLabel = $langs->trans('DM_StatusPartialFailure'); }
        elseif ($b->status === 'running') { $statusBadge = 'badge-status6'; $statusLabel = $langs->trans('DM_StatusRunning'); }

        $duration = '';
        if ($b->date_creation && $b->date_completion) {
            $d = abs(strtotime($b->date_completion) - strtotime($b->date_creation));
            $duration = ($d < 60) ? $d.'s' : floor($d / 60).'m '.($d % 60).'s';
        }

        print '<tr>';
        print '<td>'.dol_print_date(strtotime($b->date_creation), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag($b->display_name).'</td>';
        print '<td>'.dol_escape_htmltag($b->source_domain ?: '-').'</td>';
        $destinos = isset($batchDestinos[$b->rowid]) ? $batchDestinos[$b->rowid] : array();
        $destTxt = count($destinos) <= 3 ? implode(', ', $destinos) : implode(', ', array_slice($destinos, 0, 3)).' +'.(count($destinos) - 3).' más';
        print '<td>'.dol_escape_htmltag($destTxt).'</td>';
        print '<td><span class="badge '.$statusBadge.'">'.$statusLabel.'</span></td>';
        print '<td>'.$duration.'</td>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/deploy_status.php?id='.$b->rowid, 1).'" style="padding:2px 6px;"><i class="fa fa-eye"></i></a></td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="8" class="center opacitymedium" style="padding:20px;">'.$langs->trans('DM_NoData').'</td></tr>';
}

print '</tbody></table>';

llxFooter();
$db->close();
