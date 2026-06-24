<?php
require_once 'config/db.php';
try {
    // Modify schools table to include admission number orchestration logic
    $pdo->exec("ALTER TABLE schools ADD COLUMN adm_no_type ENUM('system', 'pattern', 'manual') DEFAULT 'system' AFTER credits");
    $pdo->exec("ALTER TABLE schools ADD COLUMN adm_no_pattern VARCHAR(100) DEFAULT '{YEAR}/{ID}' AFTER adm_no_type");
    $pdo->exec("ALTER TABLE schools ADD COLUMN adm_no_counter INT DEFAULT 1 AFTER adm_no_pattern");

    echo "Migration Successful: Admission Orchestration Logic Initialized.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Migration Note: Admission columns already exist.\n";
    } else {
        echo "Migration Failed: " . $e->getMessage() . "\n";
    }
}
