<?php
// ajax/sa_get_schools.php - Platform Node Discovery
require_once '../super_admin/auth_check.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT s.id, s.school_name, u.phone as owner_phone FROM schools s JOIN users u ON s.owner_id = u.id ORDER BY s.school_name ASC");
    $schools = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $schools]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
