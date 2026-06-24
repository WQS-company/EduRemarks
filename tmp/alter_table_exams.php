<?php
require_once 'C:/xampp/htdocs/dashboard/eduremarks/config/db.php';
try {
    $pdo->exec("ALTER TABLE cbt_exams ADD COLUMN numbering_format ENUM('1', 'A', 'i') DEFAULT '1'");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // If it already exists, just ignore it
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
