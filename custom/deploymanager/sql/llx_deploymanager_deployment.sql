CREATE TABLE IF NOT EXISTS llx_deploymanager_deployment (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_batch INT NOT NULL,
    fk_instance INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    backup_path VARCHAR(500) NULL,
    date_start DATETIME NULL,
    date_end DATETIME NULL,
    log TEXT NULL,
    error_message TEXT NULL,
    INDEX idx_batch (fk_batch),
    INDEX idx_instance (fk_instance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
