<?php
// ajax/toggle_class_position.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($role !== 'staff' && $role !== 'owner' && $role !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = intval($_POST['class_id'] ?? 0);
$show_position = intval($_POST['show_position'] ?? 1);

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid class']);
    exit();
}

try {
    // Verify the class belongs to the school
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
    $stmt->execute([$class_id, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found in this institution.']);
        exit();
    }

    $update = $pdo->prepare("UPDATE classes SET show_position = ? WHERE id = ?");
    $update->execute([$show_position, $class_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
