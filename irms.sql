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
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table: incidents
-- --------------------------------------------------------
CREATE TABLE incidents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id   INT NOT NULL,
    category_id   INT NOT NULL,
    assigned_to   INT DEFAULT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT NOT NULL,
    location      VARCHAR(255) NOT NULL,
    latitude      DECIMAL(10,8) DEFAULT NULL,
    longitude     DECIMAL(11,8) DEFAULT NULL,
    severity      ENUM('low','medium','high','critical') DEFAULT 'medium',
    status        ENUM('pending','in_progress','resolved','closed') DEFAULT 'pending',
    reported_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
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
-- Table: status_logs  (audit trail)
-- --------------------------------------------------------
CREATE TABLE status_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    changed_by  INT NOT NULL,
    old_status  ENUM('pending','in_progress','resolved','closed'),
    new_status  ENUM('pending','in_progress','resolved','closed') NOT NULL,
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
-- Default data: categories
-- --------------------------------------------------------
INSERT INTO categories (name, description, icon) VALUES
('Fire Incident',       'Sunog at fire-related emergencies',        'fire'),
('Flood',               'Baha at flash flood incidents',            'water'),
('Road Accident',       'Aksidente sa kalsada',                     'car-crash'),
('Crime / Theft',       'Krimen, robbery, holdap',                  'shield'),
('Medical Emergency',   'Medikal na emergency sa lugar',            'ambulance'),
('Power Outage',        'Blackout at power interruption',           'bolt'),
('Missing Person',      'Nawawalang tao',                           'person'),
('Infrastructure',      'Damaged roads, bridges, public property',  'road'),
('Other',               'Iba pang insidente',                       'exclamation');

-- --------------------------------------------------------
-- Default admin account (password: Admin@1234)
-- --------------------------------------------------------
INSERT INTO users (name, email, password, role) VALUES
('System Admin', 'admin@irms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHxQ9GMQY', 'admin');