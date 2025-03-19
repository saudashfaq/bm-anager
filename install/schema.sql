CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    verification_frequency ENUM('daily', 'weekly', 'every_two_weeks', 'monthly') NOT NULL DEFAULT 'weekly',
    last_checked DATETIME DEFAULT NULL,
    status  ENUM('enabled', 'disabled') NOT NULL DEFAULT 'enabled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS backlinks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    backlink_url VARCHAR(255) NOT NULL,
    target_url VARCHAR(255) NULL,
    anchor_text VARCHAR(255) NULL,
    `status` ENUM('alive', 'dead', 'pending') NOT NULL DEFAULT 'pending',
    created_by INT NOT NULL,
    last_checked TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backlink_id INT NOT NULL,
    `status` ENUM('alive', 'dead') NOT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (backlink_id) REFERENCES backlinks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS  backlink_verification_helper (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    last_run DATETIME NOT NULL,
    pending_backlinks INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS  settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_key VARCHAR(255) NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    verification_interval INT NOT NULL DEFAULT 24,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/*more to consider for job*/
/*
CREATE INDEX idx_backlinks_status ON backlinks(`status`, campaign_id, last_checked);
CREATE INDEX idx_campaigns_status ON campaigns(`status`, last_checked);
CREATE INDEX idx_verification_logs ON verification_logs(backlink_id, created_at);
*/