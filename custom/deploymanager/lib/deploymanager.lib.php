<?php

function deploymanagerAdminPrepareHead()
{
    global $langs;
    $langs->load('deploymanager@deploymanager');

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/custom/deploymanager/dashboard.php', 1);
    $head[$h][1] = $langs->trans('DM_Dashboard');
    $head[$h][2] = 'dashboard';
    $h++;

    return $head;
}
