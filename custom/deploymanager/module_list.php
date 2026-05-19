<?php

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include '../main.inc.php';
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res) die("Include of main fails");

$langs->load('deploymanager@deploymanager');

if (!$user->admin && empty($user->rights->deploymanager->leer)) {
    accessforbidden();
}

llxHeader('', $langs->trans('DM_ModuleList'));

print load_fiche_titre($langs->trans('DM_ModuleList'), '', 'fa-puzzle-piece');

$sql = "SELECT m.*,";
$sql .= " (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im WHERE im.fk_module = m.rowid AND im.installed_version IS NOT NULL) as nb_instances,";
$sql .= " (SELECT im2.installed_version FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im2 WHERE im2.fk_module = m.rowid AND im2.installed_version IS NOT NULL";
$sql .= " ORDER BY CAST(SUBSTRING_INDEX(im2.installed_version, '.', 1) AS UNSIGNED) DESC,";
$sql .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(im2.installed_version, '.', 2), '.', -1) AS UNSIGNED) DESC,";
$sql .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(im2.installed_version, '.', 3), '.', -1) AS UNSIGNED) DESC LIMIT 1) as latest_version,";
$sql .= " (SELECT COUNT(DISTINCT im3.installed_version) FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im3 WHERE im3.fk_module = m.rowid AND im3.installed_version IS NOT NULL) as nb_versions";
$sql .= " FROM ".MAIN_DB_PREFIX."deploymanager_module m ORDER BY m.slug";
$resq = $db->query($sql);

print '<table class="tagtable liste">';
print '<thead><tr class="liste_titre">';
print '<th>'.$langs->trans('DM_ModuleSlug').'</th>';
print '<th>'.$langs->trans('DM_ModuleName').'</th>';
print '<th>'.$langs->trans('DM_LatestVersion').'</th>';
print '<th>Versiones</th>';
print '<th>'.$langs->trans('DM_InstalledOn').'</th>';
print '</tr></thead>';
print '<tbody>';

if ($resq && $db->num_rows($resq) > 0) {
    while ($obj = $db->fetch_object($resq)) {
        print '<tr>';
        print '<td><a href="'.dol_buildpath('/custom/deploymanager/module_card.php?slug='.$obj->slug, 1).'"><strong>'.dol_escape_htmltag($obj->slug).'</strong></a></td>';
        print '<td>'.dol_escape_htmltag($obj->display_name).'</td>';
        print '<td>'.($obj->latest_version ?: '<span class="opacitymedium">—</span>').'</td>';
        print '<td>'.$obj->nb_versions.'</td>';
        print '<td>'.$obj->nb_instances.' '.$langs->trans('DM_Instances').'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="5" class="center opacitymedium" style="padding:20px;">'.$langs->trans('DM_NoData').' — '.$langs->trans('DM_ScanAll').'</td></tr>';
}

print '</tbody></table>';

llxFooter();
$db->close();
