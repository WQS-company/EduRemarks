<?php
require_once 'config/db.php';
$sql = "CREATE TABLE IF NOT EXISTS curriculum_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    section_id INT DEFAULT NULL,
    class_id INT DEFAULT NULL,
    subject_id INT DEFAULT NULL,
    term INT DEFAULT 1,
    week INT DEFAULT 1,
    topic VARCHAR(255) NOT NULL,
    objectives TEXT,
    content LONGTEXT,
    resources TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (school_id),
    INDEX (section_id),
    INDEX (class_id),
    INDEX (subject_id)
) ENGINE=InnoDB";

try {
    $pdo->exec($sql);
    echo "Table curriculum_nodes created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
