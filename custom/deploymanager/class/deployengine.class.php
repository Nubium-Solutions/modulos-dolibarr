<?php

require_once __DIR__.'/sshexecutor.class.php';

class DeployEngine
{
    private $db;
    private $dataPath;

    public function __construct($db)
    {
        $this->db = $db;
        $this->dataPath = getDolGlobalString('DEPLOYMANAGER_DATA_PATH', DOL_DATA_ROOT.'/deploymanager');
    }

    public function findSourceInstance($moduleId)
    {
        $sql = "SELECT im.fk_instance, im.installed_version, i.domain";
        $sql .= " FROM ".MAIN_DB_PREFIX."deploymanager_instance_module im";
        $sql .= " JOIN ".MAIN_DB_PREFIX."deploymanager_instance i ON i.rowid = im.fk_instance";
        $sql .= " WHERE im.fk_module = ".(int) $moduleId." AND im.installed_version IS NOT NULL";
        $sql .= " ORDER BY CAST(SUBSTRING_INDEX(im.installed_version, '.', 1) AS UNSIGNED) DESC,";
        $sql .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(im.installed_version, '.', 2), '.', -1) AS UNSIGNED) DESC,";
        $sql .= " CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(im.installed_version, '.', 3), '.', -1) AS UNSIGNED) DESC";
        $sql .= " LIMIT 1";
        $res = $this->db->query($sql);
        return ($res) ? $this->db->fetch_object($res) : null;
    }

    public function createBatch($moduleId, $sourceInstanceId, $instanceIds, $userId = 0)
    {
        $module = $this->getModule($moduleId);
        if (!$module) return null;

        $source = $this->getInstance($sourceInstanceId);
        $desc = 'Deploy '.$module->slug.' desde '.$source->domain.' a '.count($instanceIds).' instancia(s)';

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_batch (fk_module, fk_source_instance, description, total_count, fk_user_author, date_creation) VALUES (";
        $sql .= (int) $moduleId.",";
        $sql .= (int) $sourceInstanceId.",";
        $sql .= "'".$this->db->escape($desc)."',";
        $sql .= (int) count($instanceIds).",";
        $sql .= (int) $userId.",";
        $sql .= "'".$this->db->escape(date('Y-m-d H:i:s'))."')";
        $this->db->query($sql);

        $batchId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'deploymanager_batch');

        foreach ($instanceIds as $instId) {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_deployment (fk_batch, fk_instance, status) VALUES (";
            $sql .= (int) $batchId.",".(int) $instId.",'pending')";
            $this->db->query($sql);
        }

        return $batchId;
    }

    public function executeBatch($batchId)
    {
        $batch = $this->getBatch($batchId);
        if (!$batch) return false;

        $module = $this->getModule($batch->fk_module);
        $sourceInstance = $this->getInstance($batch->fk_source_instance);
        $sourceServer = $this->getServer($sourceInstance->fk_server);

        $sourcePath = rtrim($sourceInstance->custom_path, '/').'/'.$module->slug.'/';

        $deployments = $this->getDeployments($batchId);

        foreach ($deployments as $dep) {
            $this->executeDeployment($dep, $module, $sourcePath, $sourceServer);
        }

        $this->updateBatchStatus($batchId);
        return true;
    }

    private function executeDeployment($deployment, $module, $sourcePath, $sourceServer)
    {
        $instance = $this->getInstance($deployment->fk_instance);
        $server = $this->getServer($instance->fk_server);
        $ssh = new SSHExecutor($server);

        $log = array();
        $modulePath = rtrim($instance->custom_path, '/').'/'.$module->slug;
        $targetPath = $modulePath.'/';

        // 1. Backup del módulo actual
        $this->updateDeploymentStatus($deployment->rowid, 'backing_up');
        $backupDir = $this->dataPath.'/backups/'.$instance->domain;

        if ($ssh->fileExists($modulePath)) {
            $log[] = '['.date('H:i:s').'] Creando backup de '.$module->slug.'...';
            $this->logDeployment($deployment->rowid, implode("\n", $log));

            $backup = $ssh->backup($modulePath, $module->slug, $backupDir);
            if (!$backup['ok']) {
                $log[] = '['.date('H:i:s').'] ERROR backup: '.$backup['error'];
                $this->failDeployment($deployment->rowid, implode("\n", $log), $backup['error']);
                return;
            }
            $log[] = '['.date('H:i:s').'] Backup: '.$backup['filename'];
            $this->updateDeploymentBackup($deployment->rowid, $backup['path']);
        } else {
            $log[] = '['.date('H:i:s').'] Módulo no existía, sin backup';
        }

        // 2. Copiar archivos desde origen a destino
        $this->updateDeploymentStatus($deployment->rowid, 'deploying');
        $log[] = '['.date('H:i:s').'] Copiando '.$module->slug.' desde origen...';
        $this->logDeployment($deployment->rowid, implode("\n", $log));

        $rsyncOutput = '';
        $ok = $this->rsyncBetweenServers($sourceServer, $sourcePath, $server, $targetPath, $rsyncOutput);

        if (!$ok) {
            $log[] = '['.date('H:i:s').'] ERROR rsync: '.$rsyncOutput;
            $this->failDeployment($deployment->rowid, implode("\n", $log), 'Error rsync');
            return;
        }
        $log[] = '['.date('H:i:s').'] Archivos copiados correctamente';

        // Ajustar permisos de archivos
        $ssh->setOwner($targetPath);

        // 3. Git: sincronizar con remoto, reaplicar archivos, commit + push
        $httpdocsPath = preg_replace('#/htdocs/custom$#', '', rtrim($instance->custom_path, '/'));
        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $module->slug);
        $gitSyncCmd = 'cd '.escapeshellarg($httpdocsPath).' && git fetch origin 2>/dev/null && git reset --hard origin/main 2>/dev/null; echo SYNCED';
        $ssh->exec($gitSyncCmd, $gitSyncOut);

        // Reaplicar rsync tras el git reset
        $rsyncOutput2 = '';
        $this->rsyncBetweenServers($sourceServer, $sourcePath, $server, $targetPath, $rsyncOutput2);

        $gitCmd = 'cd '.escapeshellarg($httpdocsPath).' && git add htdocs/custom/'.escapeshellarg($safeSlug).'/ && git diff --cached --quiet || git commit -m '.escapeshellarg('Update '.$safeSlug).' && git push -u origin main';
        $gitOutput = '';
        $gitOk = $ssh->exec($gitCmd, $gitOutput);

        if ($gitOk) {
            $log[] = '['.date('H:i:s').'] Git commit + push OK';
        } else {
            $log[] = '['.date('H:i:s').'] AVISO git push: '.$gitOutput;
        }

        // 4. Ejecutar SQL del módulo (crear tablas, índices, updates)
        $sqlDir = $modulePath.'/sql/';
        $sqlListOutput = '';
        $ssh->exec('ls '.escapeshellarg($sqlDir).'*.sql 2>/dev/null | sort', $sqlListOutput);
        $sqlFiles = array_filter(explode("\n", trim($sqlListOutput)));

        if (!empty($sqlFiles) && !empty($instance->conf_path)) {
            $log[] = '['.date('H:i:s').'] Ejecutando '.count($sqlFiles).' SQL...';
            $this->logDeployment($deployment->rowid, implode("\n", $log));

            $sqlErrors = 0;
            foreach ($sqlFiles as $sqlFile) {
                $sqlFile = trim($sqlFile);
                if (empty($sqlFile)) continue;
                $result = $ssh->runMigration($instance->conf_path, $sqlFile);
                if (!$result['ok']) {
                    $sqlErrors++;
                    $log[] = '['.date('H:i:s').'] AVISO SQL '.basename($sqlFile).': '.$result['output'];
                }
            }

            if ($sqlErrors === 0) {
                $log[] = '['.date('H:i:s').'] SQL ejecutados correctamente';
            } else {
                $log[] = '['.date('H:i:s').'] '.$sqlErrors.' SQL con avisos (tablas ya existentes es normal)';
            }
        }

        // 5. Verificar versión desplegada
        $this->updateDeploymentStatus($deployment->rowid, 'verifying');
        $log[] = '['.date('H:i:s').'] Verificando versión...';

        $descFile = '';
        $ssh->exec('find '.escapeshellarg($modulePath.'/core/modules/').' -name "mod*.class.php" 2>/dev/null | head -1', $descFile);
        $descFile = trim($descFile);

        $deployedVersion = '';
        if ($descFile) {
            $verOutput = '';
            $ssh->exec('grep -oP "version\s*=\s*.\K[0-9][0-9.]*" '.escapeshellarg($descFile).' 2>/dev/null | head -1', $verOutput);
            $deployedVersion = trim($verOutput);
            if (empty($deployedVersion)) {
                $versionTxt = '';
                $ssh->exec('cat '.escapeshellarg($modulePath.'/version.txt').' 2>/dev/null | tr -d "[:space:]"', $versionTxt);
                $deployedVersion = trim($versionTxt);
            }
            $log[] = '['.date('H:i:s').'] Versión: '.($deployedVersion ?: 'no detectada');
        } else {
            $log[] = '['.date('H:i:s').'] No se pudo leer versión';
        }

        if ($deployedVersion) {
            $this->updateInstanceModule($instance->rowid, $module->rowid, $deployedVersion);
        }

        // 6. Completar despliegue
        $this->completeDeployment($deployment->rowid, implode("\n", $log));
    }

    private function rsyncBetweenServers($srcServer, $srcPath, $dstServer, $dstPath, &$output)
    {
        if (strpos($srcPath, '..') !== false || strpos($dstPath, '..') !== false) {
            $output = 'Path traversal detectado';
            return false;
        }
        if (strpos($srcPath, '/custom/') === false || strpos($dstPath, '/custom/') === false) {
            $output = 'Ruta fuera de custom/';
            return false;
        }

        $sshKey = getDolGlobalString('DEPLOYMANAGER_SSH_KEY', '');

        $sameServer = ($srcServer->host === $dstServer->host);

        if ($sameServer) {
            $ssh = new SSHExecutor($srcServer);
            $cmd = 'rsync -a --delete '.escapeshellarg($srcPath).' '.escapeshellarg($dstPath);
            return $ssh->exec($cmd, $output);
        }

        // Servidores diferentes: descargar del origen al panel y subir al destino
        $tmpDir = '/tmp/dm_rsync_'.uniqid().'/';

        // Descargar archivos del origen
        $srcSshCmd = 'ssh -o StrictHostKeyChecking=no -o BatchMode=yes -i '.escapeshellarg($sshKey);
        if ($srcServer->ssh_port && $srcServer->ssh_port != 22) $srcSshCmd .= ' -p '.(int) $srcServer->ssh_port;
        $srcPathNoSlash = rtrim($srcPath, '/');
        $srcRemote = ($srcServer->ssh_user ?: 'root').'@'.$srcServer->host.':'.$srcPathNoSlash;

        $cmd1 = 'mkdir -p '.$tmpDir.' && rsync -a -e '.escapeshellarg($srcSshCmd).' '.escapeshellarg($srcRemote).' '.$tmpDir.' 2>&1';
        $out1 = array();
        exec($cmd1, $out1, $exit1);
        if ($exit1 !== 0) {
            $output = 'Error descargando de origen: '.implode("\n", $out1);
            exec('rm -rf '.escapeshellarg($tmpDir));
            return false;
        }

        // Subir archivos al destino
        $dstSshCmd = 'ssh -o StrictHostKeyChecking=no -o BatchMode=yes -i '.escapeshellarg($sshKey);
        if ($dstServer->ssh_port && $dstServer->ssh_port != 22) $dstSshCmd .= ' -p '.(int) $dstServer->ssh_port;
        $dstRemote = ($dstServer->ssh_user ?: 'root').'@'.$dstServer->host.':'.$dstPath;

        $localSource = $tmpDir.basename($srcPathNoSlash).'/';
        $cmd2 = 'rsync -a --delete -e '.escapeshellarg($dstSshCmd).' '.$localSource.' '.escapeshellarg($dstRemote).' 2>&1';
        $out2 = array();
        exec($cmd2, $out2, $exit2);
        exec('rm -rf '.escapeshellarg($tmpDir));

        if ($exit2 !== 0) {
            $output = 'Error subiendo a destino: '.implode("\n", $out2);
            return false;
        }

        $output = 'OK';
        return true;
    }

    // --- Helpers BD ---


    private function getModule($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_module WHERE rowid = ".(int) $id;
        $res = $this->db->query($sql);
        return ($res) ? $this->db->fetch_object($res) : null;
    }

    private function getBatch($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_batch WHERE rowid = ".(int) $id;
        $res = $this->db->query($sql);
        return ($res) ? $this->db->fetch_object($res) : null;
    }

    private function getInstance($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_instance WHERE rowid = ".(int) $id;
        $res = $this->db->query($sql);
        return ($res) ? $this->db->fetch_object($res) : null;
    }

    private function getServer($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_server WHERE rowid = ".(int) $id;
        $res = $this->db->query($sql);
        return ($res) ? $this->db->fetch_object($res) : null;
    }

    private function getDeployments($batchId)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."deploymanager_deployment WHERE fk_batch = ".(int) $batchId." ORDER BY rowid";
        $res = $this->db->query($sql);
        $list = array();
        while ($res && ($obj = $this->db->fetch_object($res))) {
            $list[] = $obj;
        }
        return $list;
    }

    private function updateDeploymentStatus($depId, $status)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_deployment SET status = '".$this->db->escape($status)."'";
        if ($status !== 'pending') {
            $sql .= ", date_start = COALESCE(date_start, '".$this->db->escape(date('Y-m-d H:i:s'))."')";
        }
        $sql .= " WHERE rowid = ".(int) $depId;
        $this->db->query($sql);
    }

    private function updateDeploymentBackup($depId, $path)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_deployment SET backup_path = '".$this->db->escape($path)."' WHERE rowid = ".(int) $depId;
        $this->db->query($sql);
    }

    private function logDeployment($depId, $log)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_deployment SET log = '".$this->db->escape($log)."' WHERE rowid = ".(int) $depId;
        $this->db->query($sql);
    }

    private function failDeployment($depId, $log, $error)
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_deployment SET status = 'failed', log = '".$this->db->escape($log)."',";
        $sql .= " error_message = '".$this->db->escape($error)."', date_end = '".$this->db->escape($now)."' WHERE rowid = ".(int) $depId;
        $this->db->query($sql);
    }

    private function completeDeployment($depId, $log)
    {
        $now = date('Y-m-d H:i:s');
        $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_deployment SET status = 'completed', log = '".$this->db->escape($log)."',";
        $sql .= " date_end = '".$this->db->escape($now)."' WHERE rowid = ".(int) $depId;
        $this->db->query($sql);
    }

    private function updateBatchStatus($batchId)
    {
        $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,";
        $sql .= " SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed";
        $sql .= " FROM ".MAIN_DB_PREFIX."deploymanager_deployment WHERE fk_batch = ".(int) $batchId;
        $res = $this->db->query($sql);
        $row = $this->db->fetch_object($res);

        $status = 'completed';
        if ($row->failed > 0 && $row->completed > 0) $status = 'partial_failure';
        elseif ($row->failed > 0 && $row->completed == 0) $status = 'failed';

        $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_batch SET status = '".$this->db->escape($status)."',";
        $sql .= " completed_count = ".(int) $row->completed.", failed_count = ".(int) $row->failed.",";
        $sql .= " date_completion = '".$this->db->escape(date('Y-m-d H:i:s'))."' WHERE rowid = ".(int) $batchId;
        $this->db->query($sql);
    }

    private function updateInstanceModule($instanceId, $moduleId, $version)
    {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."deploymanager_instance_module WHERE fk_instance = ".(int) $instanceId." AND fk_module = ".(int) $moduleId;
        $res = $this->db->query($sql);

        if ($res && ($obj = $this->db->fetch_object($res))) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."deploymanager_instance_module SET installed_version = '".$this->db->escape($version)."', last_scan = '".$this->db->escape($now)."' WHERE rowid = ".(int) $obj->rowid;
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."deploymanager_instance_module (fk_instance, fk_module, installed_version, last_scan) VALUES (";
            $sql .= (int) $instanceId.",".(int) $moduleId.",'".$this->db->escape($version)."','".$this->db->escape($now)."')";
        }
        $this->db->query($sql);
    }

    public function getBatchStatus($batchId)
    {
        $batch = $this->getBatch($batchId);
        if (!$batch) return null;

        $module = $this->getModule($batch->fk_module);

        $sourceInstance = null;
        if ($batch->fk_source_instance) {
            $sourceInstance = $this->getInstance($batch->fk_source_instance);
        }

        $deployments = array();
        $sql = "SELECT d.*, i.name as instance_name, i.domain FROM ".MAIN_DB_PREFIX."deploymanager_deployment d";
        $sql .= " JOIN ".MAIN_DB_PREFIX."deploymanager_instance i ON i.rowid = d.fk_instance";
        $sql .= " WHERE d.fk_batch = ".(int) $batchId." ORDER BY d.rowid";
        $res = $this->db->query($sql);
        while ($res && ($obj = $this->db->fetch_object($res))) {
            $deployments[] = $obj;
        }

        return array(
            'batch' => $batch,
            'module' => $module,
            'source' => $sourceInstance,
            'deployments' => $deployments
        );
    }
}
