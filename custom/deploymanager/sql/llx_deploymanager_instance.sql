CREATE TABLE IF NOT EXISTS llx_deploymanager_instance (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_server INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    custom_path VARCHAR(500) NOT NULL,
    conf_path VARCHAR(500) NOT NULL,
    environment VARCHAR(20) DEFAULT 'production',
    status TINYINT(1) DEFAULT 1,
    note_public TEXT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_server (fk_server)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
