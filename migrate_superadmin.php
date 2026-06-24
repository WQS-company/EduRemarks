<?php
// migrate_superadmin.php
require_once 'config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Update users table role enum
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'staff', 'super_admin') NOT NULL");

    // 2. Update schools table with status and credits
    $pdo->exec("ALTER TABLE schools 
                ADD COLUMN credits INT DEFAULT 3000, 
                ADD COLUMN status ENUM('pending', 'active', 'suspended') DEFAULT 'active',
                ADD COLUMN trial_expiry DATE NULL");

    // 3. Platform Settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 4. Services (Why Choose Us)
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        icon VARCHAR(100),
        sort_order INT DEFAULT 0
    )");

    // 5. Pricing (Credit Packages)
    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing_packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        credits INT NOT NULL,
        price_naira DECIMAL(10, 2) NOT NULL,
        description TEXT
    )");

    // 6. Blog Management
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_blog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        content TEXT,
        image_path VARCHAR(255),
        author_id INT,
        status ENUM('draft', 'published') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. General Campaigns/Messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject VARCHAR(255) NOT NULL,
        message TEXT,
        target_school_ids TEXT NULL, -- NULL means all, or comma separated IDs
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 8. School Requests (Ticketing)
    $pdo->exec("CREATE TABLE IF NOT EXISTS school_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT,
        status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL
    )");

    // 9. Initial Pricing Seed
    $pdo->exec("INSERT IGNORE INTO pricing_packages (name, credits, price_naira) VALUES 
                ('Bronze Starter', 1000, 50000),
                ('Silver Professional', 5000, 200000),
                ('Gold Enterprise', 15000, 500000)");

    $pdo->commit();
    echo "Migration Successful!";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Migration Failed: " . $e->getMessage();
}
