CREATE TABLE IF NOT EXISTS llx_deploymanager_instance_module (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_instance INT NOT NULL,
    fk_module INT NOT NULL,
    installed_version VARCHAR(20) NULL,
    last_scan DATETIME NULL,
    UNIQUE KEY uk_inst_mod (fk_instance, fk_module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
