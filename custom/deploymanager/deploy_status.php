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

require_once DOL_DOCUMENT_ROOT.'/custom/deploymanager/class/deployengine.class.php';

$engine = new DeployEngine($db);
$data = $engine->getBatchStatus($id);

if (!$data) {
    setEventMessages('Batch not found', null, 'errors');
    header('Location: '.dol_buildpath('/custom/deploymanager/deploy_history.php', 1));
    exit;
}

$batch = $data['batch'];
$module = $data['module'];
$source = $data['source'];
$deployments = $data['deployments'];

llxHeader('', $langs->trans('DM_BatchDetail'));

$statusIcon = 'fa-spinner fa-spin';
$statusColor = '#3498db';
if ($batch->status === 'completed') { $statusIcon = 'fa-check-circle'; $statusColor = '#27ae60'; }
elseif ($batch->status === 'failed') { $statusIcon = 'fa-times-circle'; $statusColor = '#e74c3c'; }
elseif ($batch->status === 'partial_failure') { $statusIcon = 'fa-exclamation-circle'; $statusColor = '#f39c12'; }

print load_fiche_titre('<i class="fa '.$statusIcon.'" style="color:'.$statusColor.';margin-right:8px;"></i> '.$module->display_name, '', '');

print '<div style="margin-bottom:20px;">';
print '<strong>'.$batch->description.'</strong><br>';
if ($source) {
    print '<strong>Origen:</strong> '.dol_escape_htmltag($source->domain).'<br>';
}
print $langs->trans('DM_Date').': '.dol_print_date(strtotime($batch->date_creation), 'dayhour');
if ($batch->date_completion) {
    print ' — '.$langs->trans('DM_Duration').': ';
    $diff = abs(strtotime($batch->date_completion) - strtotime($batch->date_creation));
    print ($diff < 60) ? $diff.'s' : floor($diff / 60).'m '.($diff % 60).'s';
}
print '</div>';

// Progress bar
$pct = $batch->total_count > 0 ? round(($batch->completed_count + $batch->failed_count) / $batch->total_count * 100) : 0;
print '<div style="background:#eee;border-radius:4px;height:24px;margin-bottom:20px;overflow:hidden;">';
if ($batch->completed_count > 0) {
    $greenPct = round($batch->completed_count / $batch->total_count * 100);
    print '<div style="background:#27ae60;height:100%;width:'.$greenPct.'%;float:left;"></div>';
}
if ($batch->failed_count > 0) {
    $redPct = round($batch->failed_count / $batch->total_count * 100);
    print '<div style="background:#e74c3c;height:100%;width:'.$redPct.'%;float:left;"></div>';
}
print '</div>';
print '<div style="text-align:center;margin-bottom:20px;">'.$batch->completed_count.' completados / '.$batch->failed_count.' fallidos / '.$batch->total_count.' total</div>';

// Deployments table
print '<table class="tagtable liste">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('DM_InstanceName').'</th>';
print '<th>'.$langs->trans('DM_Result').'</th>';
print '<th>'.$langs->trans('DM_Duration').'</th>';
print '<th>'.$langs->trans('DM_ViewLog').'</th>';
print '</tr></thead>';
print '<tbody>';

foreach ($deployments as $dep) {
    $statusBadge = 'badge-status0';
    $statusLabel = $dep->status;
    if ($dep->status === 'completed') { $statusBadge = 'badge-status4'; $statusLabel = $langs->trans('DM_StatusCompleted'); }
    elseif ($dep->status === 'failed') { $statusBadge = 'badge-status8'; $statusLabel = $langs->trans('DM_StatusFailed'); }
    elseif ($dep->status === 'pending') { $statusBadge = 'badge-status0'; $statusLabel = $langs->trans('DM_StatusPending'); }
    elseif (in_array($dep->status, array('backing_up', 'deploying', 'migrating', 'verifying'))) { $statusBadge = 'badge-status6'; $statusLabel = $langs->trans('DM_Status'.ucfirst(str_replace('_', '', $dep->status))); }

    $duration = '';
    if ($dep->date_start && $dep->date_end) {
        $d = strtotime($dep->date_end) - strtotime($dep->date_start);
        $duration = ($d < 60) ? $d.'s' : floor($d / 60).'m '.($d % 60).'s';
    }

    print '<tr>';
    print '<td><a href="'.dol_buildpath('/custom/deploymanager/instance_card.php?id='.$dep->fk_instance, 1).'">'.dol_escape_htmltag($dep->instance_name).'</a></td>';
    print '<td><span class="badge '.$statusBadge.'">'.$statusLabel.'</span>';
    if ($dep->error_message) {
        print '<br><small style="color:#e74c3c;">'.dol_escape_htmltag($dep->error_message).'</small>';
    }
    print '</td>';
    print '<td>'.$duration.'</td>';
    print '<td>';
    if ($dep->log) {
        print '<details><summary>'.$langs->trans('DM_ViewLog').'</summary><pre style="background:#f8f9fa;padding:10px;border-radius:4px;max-height:300px;overflow:auto;font-size:12px;">'.dol_escape_htmltag($dep->log).'</pre></details>';
    }
    print '</td>';
    print '</tr>';
}

print '</tbody></table>';

if ($batch->status === 'running') {
    print '<script>setTimeout(function(){ location.reload(); }, 3000);</script>';
}

print '<div style="margin-top:16px;"><a class="butAction" href="'.dol_buildpath('/custom/deploymanager/deploy_history.php', 1).'"><i class="fa fa-arrow-left"></i> '.$langs->trans('DM_Back').'</a></div>';

llxFooter();
$db->close();
