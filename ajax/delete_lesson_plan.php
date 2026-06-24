<?php
// ajax/delete_lesson_plan.php
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$id = $_POST['id'] ?? null;
$school_id = $_SESSION['school_id'] ?? null;

if (!$id || !$school_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid identity']));
}

try {
    // If staff, can only delete their own
    if ($role === 'staff') {
        $stmt = $pdo->prepare("DELETE lp FROM lesson_plans lp JOIN staff_details sd ON sd.id = lp.staff_detail_id WHERE lp.id=? AND lp.school_id=? AND sd.user_id=?");
        $stmt->execute([$id, $school_id, $user_id]);
    } else {
        // Owner can delete any in their school
        $stmt = $pdo->prepare("DELETE FROM lesson_plans WHERE id=? AND school_id=?");
        $stmt->execute([$id, $school_id]);
    }

    if ($stmt->rowCount()) {
        echo json_encode(['success' => true, 'message' => 'Lesson plan deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed: Not found or unauthorized']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
