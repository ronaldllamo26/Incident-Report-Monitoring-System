<?php
require_once __DIR__ . '/../config/db.php';
try {
    $pdo->exec("ALTER TABLE incidents ADD COLUMN ai_summary VARCHAR(500) DEFAULT NULL AFTER tracking_number");
    echo "SUCCESS: ai_summary column added." . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
