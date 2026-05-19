CREATE TABLE IF NOT EXISTS llx_deploymanager_release (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_module INT NOT NULL,
    version VARCHAR(20) NOT NULL,
    zip_path VARCHAR(500) NOT NULL,
    zip_hash VARCHAR(64) NOT NULL,
    changelog TEXT NULL,
    fk_user_author INT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_module_version (fk_module, version),
    INDEX idx_module (fk_module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
