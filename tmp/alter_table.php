<?php
require_once 'C:/xampp/htdocs/dashboard/eduremarks/config/db.php';
try {
    $pdo->exec("ALTER TABLE cbt_questions MODIFY COLUMN type ENUM('objective', 'essay', 'tf', 'fill_in_the_blank') DEFAULT 'objective'");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
