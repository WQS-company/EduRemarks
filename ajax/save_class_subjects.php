<?php
// ajax/save_class_subjects.php — map subjects to a class
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_academics'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}
$school_id = $_SESSION['school_id'] ?? null;

$class_id    = intval($_POST['class_id'] ?? 0);
$subject_ids = $_POST['subject_ids'] ?? [];

if (!$class_id || !$school_id) die(json_encode(['success'=>false,'message'=>'Invalid request']));

// Verify class belongs to this school
$stmt = $pdo->prepare("SELECT id FROM classes WHERE id=? AND school_id=?");
$stmt->execute([$class_id, $school_id]); 
if (!$stmt->fetch()) die(json_encode(['success'=>false,'message'=>'Class not found']));

try {
    // Replace mappings
    $pdo->prepare("DELETE FROM class_subjects WHERE class_id=?")->execute([$class_id]);
    $ins = $pdo->prepare("INSERT IGNORE INTO class_subjects (class_id,subject_id) VALUES (?,?)");
    foreach ($subject_ids as $sid) {
        $sid = intval($sid);
        if ($sid > 0) $ins->execute([$class_id, $sid]);
    }
    echo json_encode(['success'=>true,'message'=>'Class subjects updated']);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
