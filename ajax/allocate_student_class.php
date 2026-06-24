<?php
// ajax/allocate_student_class.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_students'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}
$school_id = $_SESSION['school_id'] ?? null;

$class_id    = intval($_POST['class_id'] ?? 0);
$student_ids = $_POST['student_ids'] ?? [];

if (!$class_id || empty($student_ids) || !$school_id) {
    die(json_encode(['success'=>false,'message'=>'Select students and a class']));
}

// 1. Verify class and get name
$stmt = $pdo->prepare("SELECT name FROM classes WHERE id=? AND school_id=?");
$stmt->execute([$class_id,$school_id]);
$class_data = $stmt->fetch();
if (!$class_data) die(json_encode(['success'=>false,'message'=>'Class not found']));
$class_name = $class_data['name'];

// 2. Extra verification for Staff role
if ($role === 'staff') {
    $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
    $sd_stmt->execute([$user_id, $school_id]);
    $sd_row = $sd_stmt->fetch();
    if (!$sd_row) die(json_encode(['success' => false, 'message' => 'Staff profile not found.']));

    $assigned = $pdo->prepare("SELECT id FROM staff_class_subjects WHERE staff_detail_id=? AND class_id=? AND school_id=? LIMIT 1");
    $assigned->execute([$sd_row['id'], $class_id, $school_id]);
    if (!$assigned->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized: This class is not assigned to you.']));
    }
}

try {
    $pdo->beginTransaction();
    
    // Mapping table update
    $insMapping = $pdo->prepare("INSERT INTO student_classes (student_id,class_id,school_id) VALUES (?,?,?) 
                                 ON DUPLICATE KEY UPDATE class_id=VALUES(class_id)");
    
    // Students table varchar update
    $updVarchar = $pdo->prepare("UPDATE students SET student_class=? WHERE id=? AND school_id=?");

    $count = 0;
    foreach ($student_ids as $sid) {
        $sid = intval($sid);
        if ($sid < 1) continue;
        
        // Verify student belongs to this school
        $chk = $pdo->prepare("SELECT id FROM students WHERE id=? AND school_id=?");
        $chk->execute([$sid,$school_id]);
        if ($chk->fetch()) { 
            // Update BOTH
            $insMapping->execute([$sid,$class_id,$school_id]); 
            $updVarchar->execute([$class_name,$sid,$school_id]);
            $count++; 
        }
    }
    
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>"$count student(s) allocated to $class_name"]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
