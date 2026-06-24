<?php
// ajax/assign_staff_class.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'super_admin') {
    die(json_encode(['success'=>false,'message'=>'Unauthorized']));
}

$school_id = $active_school['id'] ?? null;
if (!$school_id) die(json_encode(['success'=>false,'message'=>'No active school']));

$input = json_decode(file_get_contents('php://input'), true);

$staff_detail_id = intval($input['staff_detail_id'] ?? 0);
$allocations     = $input['allocations'] ?? []; // Expected format: { "class_id_1": ["sub1","sub2"], "class_id_2": ["sub3"] }

if (!$staff_detail_id) {
    die(json_encode(['success'=>false,'message'=>'Staff detail ID required']));
}

// Verify staff_detail belongs to this school
$stmt = $pdo->prepare("SELECT id FROM staff_details WHERE id=? AND school_id=?");
$stmt->execute([$staff_detail_id, $school_id]);
if (!$stmt->fetch()) {
    die(json_encode(['success'=>false,'message'=>'Staff not found in this school']));
}

try {
    $pdo->beginTransaction();

    // Remove all old assignments for this staff completely
    $pdo->prepare("DELETE FROM staff_class_subjects WHERE staff_detail_id=? AND school_id=?")
        ->execute([$staff_detail_id, $school_id]);

    $ins = $pdo->prepare("INSERT IGNORE INTO staff_class_subjects 
                          (staff_detail_id, class_id, subject_id, school_id) VALUES (?, ?, ?, ?)");
    
    $countClasses = 0;
    $countSubjects = 0;

    foreach ($allocations as $class_id => $subject_ids) {
        $cid = intval($class_id);
        if ($cid < 1 || empty($subject_ids)) continue;
        
        $countClasses++;
        foreach ($subject_ids as $sid) {
            $sid = intval($sid);
            if ($sid < 1) continue;
            
            $ins->execute([$staff_detail_id, $cid, $sid, $school_id]);
            $countSubjects++;
        }
    }

    $pdo->commit();
    echo json_encode(['success'=>true, 'message'=>"Staff successfully assigned to $countClasses class(es) with $countSubjects subject(s) in total."]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
