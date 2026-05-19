<?php

class SSHExecutor
{
    private $host;
    private $user;
    private $port;
    private $keyPath;
    private $isLocal;

    public function __construct($server)
    {
        $this->host = $server->host;
        $this->user = $server->ssh_user ?: 'deployer';
        $this->port = $server->ssh_port ?: 22;
        $this->keyPath = $server->ssh_key_path ?: '';
        $this->isLocal = !empty($server->is_local);
    }

    public function exec($command, &$output = null, &$exitCode = null)
    {
        if ($this->isLocal) {
            $cmd = $command;
        } else {
            $sshOpts = '-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes';
            if ($this->keyPath) {
                $sshOpts .= ' -i '.escapeshellarg($this->keyPath);
            }
            if ($this->port != 22) {
                $sshOpts .= ' -p '.(int) $this->port;
            }

            $tmpScript = tempnam(sys_get_temp_dir(), 'dm_ssh_');
            file_put_contents($tmpScript, $command);
            $cmd = 'ssh '.$sshOpts.' '.escapeshellarg($this->user.'@'.$this->host).' bash < '.escapeshellarg($tmpScript);
        }

        $fullOutput = array();
        exec($cmd.' 2>&1', $fullOutput, $exitCode);
        $output = implode("\n", $fullOutput);

        if (!empty($tmpScript)) {
            @unlink($tmpScript);
        }

        return ($exitCode === 0);
    }

    public function testConnection()
    {
        $output = '';
        $exitCode = 0;
        $ok = $this->exec('echo "OK"', $output, $exitCode);
        return ($ok && trim($output) === 'OK');
    }

    public function rsync($localPath, $remotePath, &$output = null)
    {
        if (!$this->isLocal) {
            $realRemote = realpath(dirname($remotePath)) ?: $remotePath;
            if (strpos($remotePath, '..') !== false || strpos($remotePath, '/htdocs/custom/') === false) {
                $output = 'Ruta de despliegue inválida';
                return false;
            }
        }

        $localPath = rtrim($localPath, '/').'/';

        if ($this->isLocal) {
            $cmd = 'rsync -avz --delete '.escapeshellarg($localPath).' '.escapeshellarg($remotePath);
        } else {
            $sshCmd = 'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes';
            if ($this->keyPath) {
                $sshCmd .= ' -i '.escapeshellarg($this->keyPath);
            }
            if ($this->port != 22) {
                $sshCmd .= ' -p '.(int) $this->port;
            }
            $cmd = 'rsync -avz --delete -e '.escapeshellarg($sshCmd).' '.escapeshellarg($localPath).' '.escapeshellarg($this->user.'@'.$this->host.':'.$remotePath);
        }

        $fullOutput = array();
        $exitCode = 0;
        exec($cmd.' 2>&1', $fullOutput, $exitCode);
        $output = implode("\n", $fullOutput);

        return ($exitCode === 0);
    }

    public function backup($remotePath, $moduleName, $localBackupDir)
    {
        $timestamp = date('Ymd_His');
        $backupName = $moduleName.'_'.$timestamp.'.tar.gz';
        $remoteTmp = '/tmp/dm_backup_'.$backupName;

        $parentDir = dirname(rtrim($remotePath, '/'));

        $ok = $this->exec('tar -czf '.escapeshellarg($remoteTmp).' -C '.escapeshellarg($parentDir).' '.escapeshellarg($moduleName), $output, $exitCode);
        if (!$ok) {
            return array('ok' => false, 'error' => 'Error creando backup: '.$output);
        }

        if (!is_dir($localBackupDir)) {
            mkdir($localBackupDir, 0750, true);
        }

        $localFile = $localBackupDir.'/'.$backupName;

        $sshOpts = '-o StrictHostKeyChecking=no -o BatchMode=yes';
        if ($this->keyPath) {
            $sshOpts .= ' -i '.escapeshellarg($this->keyPath);
        }
        $portOpt = ($this->port != 22) ? ' -P '.(int) $this->port : '';
        $cmd = 'scp '.$sshOpts.$portOpt.' '.escapeshellarg($this->user.'@'.$this->host.':'.$remoteTmp).' '.escapeshellarg($localFile).' 2>&1';
        exec($cmd, $scpOut, $scpCode);
        $ok = ($scpCode === 0);
        $this->exec('rm -f '.escapeshellarg($remoteTmp));

        if (!$ok) {
            return array('ok' => false, 'error' => 'Error transfiriendo backup: '.implode("\n", $scpOut).' (dir: '.$localBackupDir.', file: '.$localFile.')');
        }

        return array('ok' => true, 'path' => $localFile, 'filename' => $backupName);
    }

    public function getDbCredentials($confPath)
    {
        $phpCode = "include('".addcslashes($confPath, "'")."'); echo json_encode(['host'=>\$dolibarr_main_db_host,'name'=>\$dolibarr_main_db_name,'user'=>\$dolibarr_main_db_user,'pass'=>\$dolibarr_main_db_pass,'prefix'=>\$dolibarr_main_db_prefix]);";
        $ok = $this->exec('php -r '.escapeshellarg($phpCode), $output, $exitCode);
        if (!$ok) {
            return null;
        }
        $data = json_decode(trim($output), true);
        if (!$data || empty($data['name'])) {
            return null;
        }
        return $data;
    }

    public function runMigration($confPath, $sqlFilePath)
    {
        $creds = $this->getDbCredentials($confPath);
        if (!$creds) {
            return array('ok' => false, 'error' => 'No se pudieron obtener credenciales BD');
        }

        $tmpCred = '/tmp/dm_mysql_'.bin2hex(random_bytes(8));
        $credContent = "[client]\nuser=".$creds['user']."\npassword=".$creds['pass']."\n";

        $setupCmd = 'TMPF='.escapeshellarg($tmpCred).' && echo '.escapeshellarg($credContent).' > $TMPF && chmod 600 $TMPF';
        $mysqlCmd = 'mysql --defaults-file='.escapeshellarg($tmpCred);
        if ($creds['host'] !== 'localhost') {
            $mysqlCmd .= ' -h '.escapeshellarg($creds['host']);
        }
        $mysqlCmd .= ' '.escapeshellarg($creds['name']).' < '.escapeshellarg($sqlFilePath);
        $cleanupCmd = 'rm -f '.escapeshellarg($tmpCred);

        $fullCmd = $setupCmd.' && '.$mysqlCmd.'; EXITCODE=$?; '.$cleanupCmd.'; exit $EXITCODE';

        $ok = $this->exec($fullCmd, $output, $exitCode);

        return array('ok' => $ok, 'output' => $output, 'exitCode' => $exitCode);
    }

    public function setOwner($remotePath, $owner = 'www-data:www-data')
    {
        return $this->exec('chown -R '.escapeshellarg($owner).' '.escapeshellarg($remotePath));
    }

    public function fileExists($remotePath)
    {
        return $this->exec('test -e '.escapeshellarg($remotePath));
    }

    public function readFile($remotePath)
    {
        $ok = $this->exec('cat '.escapeshellarg($remotePath), $output);
        return $ok ? $output : null;
    }
}
