CREATE TABLE IF NOT EXISTS llx_deploymanager_server (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(255) NOT NULL,
    ssh_user VARCHAR(50) DEFAULT 'deployer',
    ssh_port INT DEFAULT 22,
    ssh_key_path VARCHAR(500),
    is_local TINYINT(1) DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
