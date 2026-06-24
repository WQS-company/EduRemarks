<?php
// tmp/migrate_billing_system.php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE schools 
                ADD COLUMN billing_mode ENUM('credit', 'subscription') DEFAULT 'credit' AFTER status,
                ADD COLUMN subscription_type VARCHAR(50) DEFAULT NULL AFTER billing_mode,
                ADD COLUMN subscription_start DATE DEFAULT NULL AFTER subscription_type,
                ADD COLUMN subscription_end DATE DEFAULT NULL AFTER subscription_start,
                ADD COLUMN subscription_price DECIMAL(15,2) DEFAULT 0.00 AFTER subscription_end,
                ADD COLUMN subscription_active TINYINT(1) DEFAULT 0 AFTER subscription_price");
    
    echo "Migration Successful: Billing system columns added to schools table.";
} catch (Exception $e) {
    echo "Migration Failed or already applied: " . $e->getMessage();
}
?>
