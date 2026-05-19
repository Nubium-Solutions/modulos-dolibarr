<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modDeployManager extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        $this->numero = 500010;
        $this->rights_class = 'deploymanager';
        $this->family = 'other';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Panel de despliegue de módulos Dolibarr a múltiples instancias";
        $this->descriptionlong = "Gestiona el despliegue de módulos custom a todas las instancias Dolibarr de clientes desde un panel centralizado";
        $this->editor_name = 'Nubium Solutions';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-rocket';

        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'theme' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'css' => array('/custom/deploymanager/css/deploymanager.css'),
            'js' => array('/custom/deploymanager/js/deploymanager.js'),
            'hooks' => array(),
            'moduleforexternal' => 0
        );

        $this->dirs = array();
        $this->config_page_url = array();

        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('deploymanager@deploymanager');
        $this->need_dolibarr_version = array(16, 0);

        $this->const = array();

        if (!isset($conf->deploymanager) || !isset($conf->deploymanager->enabled)) {
            $conf->deploymanager = new stdClass();
            $conf->deploymanager->enabled = 0;
        }

        $this->tabs = array();
        $this->dictionaries = array();

        // Permisos
        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = 500101;
        $this->rights[$r][1] = 'Ver panel de despliegues';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'leer';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 500102;
        $this->rights[$r][1] = 'Ejecutar despliegues';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'deploy';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 500103;
        $this->rights[$r][1] = 'Administrar servidores e instancias';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = '';
        $r++;

        // Menús
        $this->menu = array();
        $r = 0;

        $this->menu[$r] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'DeployManager',
            'mainmenu' => 'deploymanager',
            'leftmenu' => '',
            'url' => '/custom/deploymanager/dashboard.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 100,
            'enabled' => '$user->admin || $user->rights->deploymanager->leer',
            'perms' => '$user->admin || $user->rights->deploymanager->leer',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Submenu: Panel
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=deploymanager',
            'type' => 'left',
            'titre' => 'DM_Dashboard',
            'mainmenu' => 'deploymanager',
            'leftmenu' => 'dm_dashboard',
            'url' => '/custom/deploymanager/dashboard.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 100,
            'enabled' => '$user->admin || $user->rights->deploymanager->leer',
            'perms' => '$user->admin || $user->rights->deploymanager->leer',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Submenu: Servidores
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=deploymanager',
            'type' => 'left',
            'titre' => 'DM_Servers',
            'mainmenu' => 'deploymanager',
            'leftmenu' => 'dm_servers',
            'url' => '/custom/deploymanager/server_list.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 200,
            'enabled' => '$user->admin || $user->rights->deploymanager->admin',
            'perms' => '$user->admin || $user->rights->deploymanager->admin',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Submenu: Instancias
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=deploymanager',
            'type' => 'left',
            'titre' => 'DM_Instances',
            'mainmenu' => 'deploymanager',
            'leftmenu' => 'dm_instances',
            'url' => '/custom/deploymanager/instance_list.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 300,
            'enabled' => '$user->admin || $user->rights->deploymanager->leer',
            'perms' => '$user->admin || $user->rights->deploymanager->leer',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Submenu: Módulos
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=deploymanager',
            'type' => 'left',
            'titre' => 'DM_Modules',
            'mainmenu' => 'deploymanager',
            'leftmenu' => 'dm_modules',
            'url' => '/custom/deploymanager/module_list.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 400,
            'enabled' => '$user->admin || $user->rights->deploymanager->leer',
            'perms' => '$user->admin || $user->rights->deploymanager->leer',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Submenu: Desplegar
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=deploymanager',
            'type' => 'left',
            'titre' => 'DM_Deploy',
            'mainmenu' => 'deploymanager',
            'leftmenu' => 'dm_deploy',
            'url' => '/custom/deploymanager/deploy_wizard.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 600,
            'enabled' => '$user->admin || $user->rights->deploymanager->deploy',
            'perms' => '$user->admin || $user->rights->deploymanager->deploy',
            'target' => '',
            'user' => 2
        );
        $r++;

        // Submenu: Historial
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=deploymanager',
            'type' => 'left',
            'titre' => 'DM_History',
            'mainmenu' => 'deploymanager',
            'leftmenu' => 'dm_history',
            'url' => '/custom/deploymanager/deploy_history.php',
            'langs' => 'deploymanager@deploymanager',
            'position' => 700,
            'enabled' => '$user->admin || $user->rights->deploymanager->leer',
            'perms' => '$user->admin || $user->rights->deploymanager->leer',
            'target' => '',
            'user' => 2
        );
        $r++;
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/deploymanager/sql/');
        if ($result < 0) return -1;

        $sql = array();
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
