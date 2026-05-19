<?php

class ModulePackager
{
    private $db;
    private $dataPath;

    public function __construct($db)
    {
        $this->db = $db;
        $this->dataPath = getDolGlobalString('DEPLOYMANAGER_DATA_PATH', DOL_DATA_ROOT.'/deploymanager');
    }

    public function processUpload($tmpFile, $originalName, $userId = 0)
    {
        $tmpDir = $this->dataPath.'/tmp/'.uniqid('upload_');
        @mkdir($tmpDir, 0750, true);

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            $this->cleanup($tmpDir);
            return array('ok' => false, 'error' => 'No se pudo abrir el archivo ZIP');
        }

        if (!$this->validateZipSecurity($zip)) {
            $zip->close();
            $this->cleanup($tmpDir);
            return array('ok' => false, 'error' => 'ZIP contiene rutas peligrosas');
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        $moduleDir = $this->findModuleDir($tmpDir);
        if (!$moduleDir) {
            $this->cleanup($tmpDir);
            return array('ok' => false, 'error' => 'ZIP inválido: no contiene core/modules/mod*.class.php');
        }

        $slug = basename($moduleDir);
        $version = $this->extractVersion($moduleDir);
        if (!$version) {
            $this->cleanup($tmpDir);
            return array('ok' => false, 'error' => 'No se pudo extraer la versión del módulo');
        }

        $moduleId = $this->ensureModule($slug);

        $existing = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."deploymanager_release WHERE fk_module = ".(int) $moduleId." AND version = '".$this->db->escape($version)."'");
        if ($existing && $this->db->fetch_object($existing)) {
            $this->cleanup($tmpDir);
            return array('ok' => false, 'error' => 'Ya existe la versión '.$version.' de '.$slug);
        }

        $releasesDir = $this->dataPath.'/releases/'.$slug;
        @mkdir($releasesDir, 0750, true);
        $zipPath = $releasesDir.'/'.$version.'.zip';

        $this->createCleanZip($moduleDir, $zipPath, $slug);
        $zipHash = hash_file('sha256', $zipPath);

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_release (fk_module, version, zip_path, zip_hash, fk_user_author, date_creation) VALUES (";
        $sql .= (int) $moduleId.",";
        $sql .= "'".$this->db->escape($version)."',";
        $sql .= "'".$this->db->escape($zipPath)."',";
        $sql .= "'".$this->db->escape($zipHash)."',";
        $sql .= (int) $userId.",";
        $sql .= "'".$this->db->escape(date('Y-m-d H:i:s'))."')";
        $this->db->query($sql);

        $releaseId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'deploymanager_release');

        $this->cleanup($tmpDir);

        return array(
            'ok' => true,
            'release_id' => $releaseId,
            'module_slug' => $slug,
            'version' => $version,
            'zip_hash' => $zipHash
        );
    }

    private function validateZipSecurity($zip)
    {
        $forbidden = array('.sh', '.py', '.phar', '.so', '.exe', '.bat');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, '..') !== false || substr($name, 0, 1) === '/') {
                return false;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array('.'.$ext, $forbidden)) {
                return false;
            }
        }
        return true;
    }

    private function findModuleDir($tmpDir)
    {
        $found = glob($tmpDir.'/*/core/modules/mod*.class.php');
        if (!empty($found)) {
            return dirname(dirname(dirname($found[0])));
        }
        $found = glob($tmpDir.'/core/modules/mod*.class.php');
        if (!empty($found)) {
            return $tmpDir;
        }
        return null;
    }

    private function extractVersion($moduleDir)
    {
        $files = glob($moduleDir.'/core/modules/mod*.class.php');
        if (empty($files)) return null;

        $content = file_get_contents($files[0]);
        if (preg_match("/version\s*=\s*'([^']+)'/", $content, $m)) {
            return $m[1];
        }
        if (preg_match('/version\s*=\s*"([^"]+)"/', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    private function createCleanZip($sourceDir, $zipPath, $slug)
    {
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = $slug.'/'.substr($file->getPathname(), strlen($sourceDir) + 1);
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
    }

    private function ensureModule($slug)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."deploymanager_module WHERE slug = '".$this->db->escape($slug)."'";
        $res = $this->db->query($sql);
        if ($res && ($obj = $this->db->fetch_object($res))) {
            return (int) $obj->rowid;
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_module (slug, display_name) VALUES (";
        $sql .= "'".$this->db->escape($slug)."','".$this->db->escape(ucfirst($slug))."')";
        $this->db->query($sql);

        return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'deploymanager_module');
    }

    private function cleanup($dir)
    {
        if (!$dir || !is_dir($dir)) return;
        $cmd = 'rm -rf '.escapeshellarg($dir);
        exec($cmd);
    }

    public function getDataPath()
    {
        return $this->dataPath;
    }
}
