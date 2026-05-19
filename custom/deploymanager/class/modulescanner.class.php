<?php

require_once __DIR__.'/sshexecutor.class.php';

class ModuleScanner
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function scanInstance($instance, $server)
    {
        $ssh = new SSHExecutor($server);

        $customPath = rtrim($instance->custom_path, '/');
        $scanCmd = 'for d in '.$customPath.'/*/; do mod=$(basename "$d"); desc=$(find "$d"core/modules/ -name "mod*.class.php" 2>/dev/null | head -1); if [ -n "$desc" ]; then ver=$(grep -oP "version\s*=\s*.\K[0-9][0-9.]*" "$desc" 2>/dev/null | head -1); if [ -z "$ver" ] && [ -f "$d"version.txt ]; then ver=$(cat "$d"version.txt 2>/dev/null | tr -d "[:space:]"); fi; echo "$mod|$ver"; fi; done';

        $output = '';
        $ok = $ssh->exec($scanCmd, $output);
        if (!$ok) {
            return array('ok' => false, 'error' => 'Error SSH: '.$output);
        }

        $results = array();
        $lines = array_filter(explode("\n", trim($output)));

        foreach ($lines as $line) {
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) continue;

            $slug = trim($parts[0]);
            $version = trim($parts[1]);
            if (empty($slug)) continue;

            $moduleId = $this->ensureModule($slug);
            $this->upsertInstanceModule($instance->rowid, $moduleId, $version);

            $results[] = array('slug' => $slug, 'version' => $version);
        }

        return array('ok' => true, 'modules' => $results);
    }

    private function ensureModule($slug)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."deploymanager_module WHERE slug = '".$this->db->escape($slug)."'";
        $res = $this->db->query($sql);
        if ($res && ($obj = $this->db->fetch_object($res))) {
            return (int) $obj->rowid;
        }

        $displayName = ucfirst($slug);
        $hasMigrations = 0;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_module (slug, display_name, has_migrations) VALUES (";
        $sql .= "'".$this->db->escape($slug)."',";
        $sql .= "'".$this->db->escape($displayName)."',";
        $sql .= (int) $hasMigrations.")";
        $this->db->query($sql);

        return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'deploymanager_module');
    }

    private function upsertInstanceModule($instanceId, $moduleId, $version)
    {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."deploymanager_instance_module";
        $sql .= " WHERE fk_instance = ".(int) $instanceId." AND fk_module = ".(int) $moduleId;
        $res = $this->db->query($sql);

        if ($res && ($obj = $this->db->fetch_object($res))) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_instance_module SET";
            $sql .= " installed_version = '".$this->db->escape($version)."',";
            $sql .= " last_scan = '".$this->db->escape($now)."'";
            $sql .= " WHERE rowid = ".(int) $obj->rowid;
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_instance_module (fk_instance, fk_module, installed_version, last_scan) VALUES (";
            $sql .= (int) $instanceId.",";
            $sql .= (int) $moduleId.",";
            $sql .= "'".$this->db->escape($version)."',";
            $sql .= "'".$this->db->escape($now)."')";
        }

        $this->db->query($sql);
    }
}
