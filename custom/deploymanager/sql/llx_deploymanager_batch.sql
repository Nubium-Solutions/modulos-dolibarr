CREATE TABLE IF NOT EXISTS llx_deploymanager_batch (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_release INT NOT NULL,
    description VARCHAR(255),
    total_count INT DEFAULT 0,
    completed_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'running',
    fk_user_author INT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_completion DATETIME NULL,
    INDEX idx_release (fk_release)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
