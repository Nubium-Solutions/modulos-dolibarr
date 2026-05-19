<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

llxHeader('', $langs->trans('DM_ReleaseList'));

print '<script>var DOL_URL_ROOT="'.DOL_URL_ROOT.'";</script>';

print load_fiche_titre($langs->trans('DM_ReleaseList'), '', 'fa-box');

// Upload form
if ($user->admin || !empty($user->rights->deploymanager->deploy)) {
    print '<div class="dm-upload-section" style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;">';
    print '<h3><i class="fa fa-upload"></i> '.$langs->trans('DM_UploadRelease').'</h3>';
    print '<form id="dm-upload-form" enctype="multipart/form-data">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">';
    print '<div><label>'.$langs->trans('DM_SelectZip').'</label><br><input type="file" name="module_zip" accept=".zip" required id="dm-zip-file"></div>';
    print '<div><label>'.$langs->trans('DM_Changelog').' ('.$langs->trans('DM_Opcional', 'Opcional').')</label><br><textarea name="changelog" rows="2" cols="40" placeholder="Cambios en esta versión..."></textarea></div>';
    print '<div><button type="submit" class="button" id="dm-upload-btn"><i class="fa fa-upload"></i> '.$langs->trans('DM_UploadRelease').'</button></div>';
    print '</div>';
    print '<div id="dm-upload-result" style="margin-top:10px;"></div>';
    print '</form>';
    print '</div>';
}

// List releases grouped by module
$sql = "SELECT r.*, m.slug, m.display_name, u.firstname, u.lastname FROM ".MAIN_DB_PREFIX."deploymanager_release r";
$sql .= " JOIN ".MAIN_DB_PREFIX."deploymanager_module m ON m.rowid = r.fk_module";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = r.fk_user_author";
$sql .= " ORDER BY m.slug, r.rowid DESC";
$resq = $db->query($sql);

print '<table class="tagtable liste">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('DM_Module').'</th>';
print '<th>'.$langs->trans('DM_Version').'</th>';
print '<th>'.$langs->trans('DM_Date').'</th>';
print '<th>'.$langs->trans('DM_UploadedBy').'</th>';
print '<th>'.$langs->trans('DM_ZipHash').'</th>';
print '</tr></thead>';
print '<tbody>';

if ($resq && $db->num_rows($resq) > 0) {
    while ($obj = $db->fetch_object($resq)) {
        print '<tr>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/module_card.php?slug='.$obj->slug, 1).'">'.dol_escape_htmltag($obj->display_name).'</a></td>';
        print '<td><strong>'.$obj->version.'</strong></td>';
        print '<td>'.dol_print_date(strtotime($obj->date_creation), 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag(trim($obj->firstname.' '.$obj->lastname)).'</td>';
        print '<td><code>'.substr($obj->zip_hash, 0, 16).'...</code></td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="5" class="center opacitymedium" style="padding:20px;">'.$langs->trans('DM_NoData').'</td></tr>';
}

print '</tbody></table>';

llxFooter();
$db->close();
