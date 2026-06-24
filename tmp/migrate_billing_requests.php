<?php
// tmp/migrate_billing_requests.php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS billing_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        requested_plan VARCHAR(50) NOT NULL,
        duration VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approval_date TIMESTAMP NULL DEFAULT NULL,
        notes TEXT,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    )");
    
    echo "Migration Successful: billing_requests table created.";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage();
}
?>
