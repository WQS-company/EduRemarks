<?php
require_once 'config/db.php';
try {
    // 1. Create Credit Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 2. Seed Default Values
    $defaults = [
        ['credit_student_result', '1'],     // 1 Credit per Student Result
        ['credit_answer_sheet', '10'],      // 10 Credits per booklet (as requested: 10 students 100 credits = 10 each)
        ['credit_cbt_test', '1'],           // 1 Credit per student for Tests
        ['credit_cbt_exam', '2']            // 2 Credits per student for Exams
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $d) {
        $stmt->execute($d);
    }

    echo "Migration Successful: Resource Pricing Nodes Initialized.\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
