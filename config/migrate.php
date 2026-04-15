<?php
/**
 * Migration: Create login_attempts table for brute force protection.
 * This file runs once automatically and then marks itself as done.
 * Include this from db.php or run manually via browser: /irms/config/migrate.php
 */
require_once __DIR__ . '/db.php';

$migrations = [
    'create_login_attempts' => "
        CREATE TABLE IF NOT EXISTS login_attempts (
            id           INT             AUTO_INCREMENT PRIMARY KEY,
            identifier   VARCHAR(255)    NOT NULL,
            failed_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME        NULL,
            last_attempt TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_identifier  (identifier),
            KEY        idx_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    'create_rate_limits' => "
        CREATE TABLE IF NOT EXISTS rate_limits (
            id           INT             AUTO_INCREMENT PRIMARY KEY,
            identifier   VARCHAR(255)    NOT NULL,
            action       VARCHAR(100)    NOT NULL,
            hit_count    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            window_start DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ident_action (identifier, action),
            KEY        idx_window_start (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
];

// Track which migrations have run
$pdo->exec("
    CREATE TABLE IF NOT EXISTS _migrations (
        name       VARCHAR(100) PRIMARY KEY,
        ran_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$ran = 0;
foreach ($migrations as $name => $sql) {
    $exists = $pdo->prepare("SELECT 1 FROM _migrations WHERE name = ?");
    $exists->execute([$name]);
    if ($exists->fetchColumn()) continue;

    try {
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO _migrations (name) VALUES (?)")->execute([$name]);
        $ran++;
        echo "[OK] Migration ran: {$name}\n";
    } catch (PDOException $e) {
        echo "[ERROR] {$name}: " . $e->getMessage() . "\n";
    }
}

if ($ran === 0) {
    echo "All migrations already applied. Nothing to do.\n";
}
