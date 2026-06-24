<?php
// fix_missing_tables.php
require_once 'config/db.php';

try {
    $pdo->beginTransaction();

    echo "Checking for 'credit_logs' table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        amount INT NOT NULL,
        activity VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (school_id)
    ) ENGINE=InnoDB");
    echo "Done.\n";

    echo "Checking for 'platform_payments' table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        package_id INT NOT NULL,
        reference VARCHAR(100) UNIQUE NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        credits_awarded INT DEFAULT 0,
        payment_method VARCHAR(50),
        status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (school_id),
        INDEX (reference)
    ) ENGINE=InnoDB");
    echo "Done.\n";

    echo "Checking for 'platform_notifications' table... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'general',
        status ENUM('unread', 'read') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (school_id)
    ) ENGINE=InnoDB");
    echo "Done.\n";

    $pdo->commit();
    echo "\nAll tables verified/created successfully.";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\nError fixing tables: " . $e->getMessage();
}
?>
