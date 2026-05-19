CREATE TABLE IF NOT EXISTS llx_deploymanager_migration_log (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_instance INT NOT NULL,
    fk_module INT NOT NULL,
    migration_file VARCHAR(255) NOT NULL,
    fk_deployment INT NULL,
    date_applied DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(10) DEFAULT 'applied',
    INDEX idx_inst_mod (fk_instance, fk_module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
