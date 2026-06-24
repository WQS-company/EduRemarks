<?php
// ajax/delete_department.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$id = intval($_POST['id'] ?? 0);
$school_id = $_SESSION['school_id'] ?? null;

if (!$id || !$school_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid request parameters.']));
}

try {
    // 1. Unlink courses from this department
    $stmt = $pdo->prepare("UPDATE subjects SET department_id = NULL WHERE department_id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);

    // 2. Delete the department (section)
    $stmt = $pdo->prepare("DELETE FROM school_sections WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);

    $label = get_label('Section');
    echo json_encode(['success' => true, 'message' => "$label deleted successfully."]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
