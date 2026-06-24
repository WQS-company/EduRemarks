<?php
// ajax/get_class_students.php — used by staff portal
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$school_id = $_SESSION['school_id'] ?? null;
$class_id  = intval($_GET['class_id'] ?? 0);

if (!$class_id || !$school_id) die(json_encode(['success'=>false,'message'=>'Invalid request']));

try {
    if ($role === 'owner') {
        // Owner can view any class in their school
        $chk = $pdo->prepare("SELECT id FROM classes WHERE id=? AND school_id=?");
        $chk->execute([$class_id,$school_id]);
        if (!$chk->fetch()) die(json_encode(['success'=>false,'message'=>'Class not found']));
    } else {
        // Staff: must be assigned to this class
        $sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
        $sd->execute([$user_id,$school_id]);
        $sd_row = $sd->fetch();
        if (!$sd_row) die(json_encode(['success'=>false,'message'=>'Not an active staff member']));

        $assigned = $pdo->prepare("SELECT id FROM staff_class_subjects WHERE staff_detail_id=? AND class_id=? AND school_id=? LIMIT 1");
        $assigned->execute([$sd_row['id'],$class_id,$school_id]);
        if (!$assigned->fetch()) die(json_encode(['success'=>false,'message'=>'You are not assigned to this class']));
    }

    $stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.admission_no, s.gender, s.dob, s.guardian_name, s.guardian_phone
        FROM students s
        JOIN student_classes sc ON sc.student_id = s.id
        WHERE sc.class_id = ? AND sc.school_id = ?
        ORDER BY s.full_name
    ");
    $stmt->execute([$class_id,$school_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'students'=>$students]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
