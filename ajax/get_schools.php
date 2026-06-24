<?php
// ajax/get_schools.php
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';

try {
    if (empty($query)) {
        $stmt = $pdo->prepare("SELECT id, school_name, unique_id FROM schools LIMIT 10");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT id, school_name, unique_id FROM schools WHERE school_name LIKE ? OR unique_id LIKE ? LIMIT 10");
        $stmt->execute(["%$query%", "%$query%"]);
    }
    
    $schools = $stmt->fetchAll();
    echo json_encode(['success' => true, 'schools' => $schools]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
