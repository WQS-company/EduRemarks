<?php
// ajax/delete_student.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_students'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}

$school_id = $_SESSION['school_id'] ?? null;
$id = intval($_POST['id'] ?? 0);

if (!$id || !$school_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

try {
    $pdo->beginTransaction();

    // 1. Security check: verify student belongs to this school
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Student not found or unauthorized access.");
    }

    // 2. Remove from class mapping
    $pdo->prepare("DELETE FROM student_classes WHERE student_id = ? AND school_id = ?")->execute([$id, $school_id]);

    // 3. Delete student record
    $pdo->prepare("DELETE FROM students WHERE id = ? AND school_id = ?")->execute([$id, $school_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Student record and associated mappings removed successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
