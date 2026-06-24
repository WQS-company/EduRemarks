<?php
require_once 'config/db.php';
try {
    $pdo->exec("ALTER TABLE staff_details ADD COLUMN can_manage_students TINYINT(1) NOT NULL DEFAULT 0");
    echo "Migration 005 OK: can_manage_students column added.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column already exists, skipping.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
