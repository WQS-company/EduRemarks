<?php
require_once 'config/db.php';
try {
    $pdo->exec("ALTER TABLE student_results ADD COLUMN points DECIMAL(5,2) DEFAULT NULL AFTER grade");
    echo "Success: 'points' column added to student_results.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
