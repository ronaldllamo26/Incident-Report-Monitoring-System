CREATE DATABASE IF NOT EXISTS irms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE irms;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('citizen','responder','admin') DEFAULT 'citizen',
    email_verified TINYINT(1) DEFAULT 0,
    verify_token VARCHAR(100) DEFAULT NULL,
    verify_token_expires DATETIME DEFAULT NULL,
    phone       VARCHAR(20),
    address     VARCHAR(255),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table: categories
-- --------------------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    icon        VARCHAR(50),
    default_responder_id INT DEFAULT NULL,
    sla_critical INT DEFAULT 60,
    sla_high     INT DEFAULT 120,
    sla_medium   INT DEFAULT 240,
    sla_low      INT DEFAULT 480,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (default_responder_id) REFERENCES users(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: incidents
-- --------------------------------------------------------
CREATE TABLE incidents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id   INT NULL,
    category_id   INT NOT NULL,
    assigned_to   INT DEFAULT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT NOT NULL,
    location      VARCHAR(255) NOT NULL,
    latitude      DECIMAL(10,8) DEFAULT NULL,
    longitude     DECIMAL(11,8) DEFAULT NULL,
    ip_address    VARCHAR(45) DEFAULT NULL,
    severity      ENUM('low','medium','high','critical') DEFAULT 'medium',
    status        ENUM('pending','in_progress','resolved','closed','rejected') DEFAULT 'pending',
    reported_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    tracking_number VARCHAR(50) DEFAULT NULL,
    is_anonymous  TINYINT(1) DEFAULT 0,
    anon_name     VARCHAR(100) DEFAULT NULL,
    anon_email    VARCHAR(100) DEFAULT NULL,
    anon_phone    VARCHAR(20) DEFAULT NULL,
    is_duplicate  TINYINT(1) DEFAULT 0,
    duplicate_of  INT DEFAULT NULL,
    ai_summary    TEXT DEFAULT NULL,
    ai_formal_report TEXT DEFAULT NULL,
    sla_deadline  DATETIME DEFAULT NULL,
    priority      INT DEFAULT 3,
    acknowledged_at DATETIME DEFAULT NULL,
    escalated     TINYINT(1) DEFAULT 0,
    sla_breached  TINYINT(1) DEFAULT 0,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (duplicate_of) REFERENCES incidents(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: attachments
-- --------------------------------------------------------
CREATE TABLE attachments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    file_type   VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: status_logs
-- --------------------------------------------------------
CREATE TABLE status_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    changed_by  INT NULL,
    old_status  ENUM('pending','in_progress','resolved','closed','rejected'),
    new_status  ENUM('pending','in_progress','resolved','closed','rejected') NOT NULL,
    remarks     TEXT,
    changed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- --------------------------------------------------------
-- Table: responses
-- --------------------------------------------------------
CREATE TABLE responses (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    incident_id  INT NOT NULL,
    responder_id INT NOT NULL,
    message      TEXT NOT NULL,
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (responder_id) REFERENCES users(id)
);

-- --------------------------------------------------------
-- Table: banned_ips
-- --------------------------------------------------------
CREATE TABLE banned_ips (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL UNIQUE,
    reason       VARCHAR(255),
    banned_by    INT NULL,
    banned_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    incident_id INT DEFAULT NULL,
    title       VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: feedback
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    citizen_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_feedback (incident_id, citizen_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: login_attempts (Security)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(255) NOT NULL,
    failed_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_identifier (identifier),
    KEY idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: rate_limits (Firewall)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    identifier   VARCHAR(255) NOT NULL,
    action       VARCHAR(100) NOT NULL,
    hit_count    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ident_action (identifier, action),
    KEY idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: audit_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id INT NULL,
    details TEXT,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Default data: categories
-- --------------------------------------------------------
INSERT INTO categories (name, description, icon, sla_critical, sla_high, sla_medium, sla_low) VALUES
('Fire Incident',       'Sunog at fire-related emergencies',        'fire', 30, 60, 120, 240),
('Flood',               'Baha at flash flood incidents',            'water', 60, 120, 240, 480),
('Road Accident',       'Aksidente sa kalsada',                     'car-crash', 45, 90, 180, 360),
('Crime / Theft',       'Krimen, robbery, holdap',                  'shield', 30, 60, 120, 240),
('Medical Emergency',   'Medikal na emergency sa lugar',            'ambulance', 15, 30, 60, 120),
('Power Outage',        'Blackout at power interruption',           'bolt', 120, 240, 480, 960),
('Missing Person',      'Nawawalang tao',                           'person', 60, 120, 240, 480),
('Infrastructure',      'Damaged roads, bridges, public property',  'road', 240, 480, 960, 1920),
('Other',               'Iba pang insidente',                       'exclamation', 60, 120, 240, 480);

-- --------------------------------------------------------
-- Default admin account (password: Admin@1234)
-- --------------------------------------------------------
INSERT INTO users (name, email, password, role) VALUES
('System Admin', 'admin@irms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHxQ9GMQY', 'admin');