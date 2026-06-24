<?php
// ajax/get_lesson_plan.php
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$id = $_GET['id'] ?? null;
$school_id = $_SESSION['school_id'] ?? null;

if (!$id || !$school_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid identity']));
}

try {
    $stmt = $pdo->prepare("SELECT * FROM lesson_plans WHERE id=? AND school_id=?");
    $stmt->execute([$id, $school_id]);
    $plan = $stmt->fetch();
    
    if ($plan) {
        echo json_encode(['success' => true, 'plan' => $plan]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lesson plan not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
