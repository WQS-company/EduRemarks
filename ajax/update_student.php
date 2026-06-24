<?php
// ajax/update_student.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_students'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}
$school_id = $_SESSION['school_id'] ?? null;

$id             = intval($_POST['id'] ?? 0);
$full_name      = sanitize($_POST['full_name'] ?? '');
$admission_no   = sanitize($_POST['admission_no'] ?? '');
$gender         = sanitize($_POST['gender'] ?? 'Male');
$dob            = sanitize($_POST['dob'] ?? '');
$guardian_name  = sanitize($_POST['guardian_name'] ?? '');
$guardian_phone = sanitize($_POST['guardian_phone'] ?? '');
$student_class   = intval($_POST['student_class'] ?? 0);
$address         = sanitize($_POST['address'] ?? '');
$department_id   = intval($_POST['department_id'] ?? 0);

if (!$id || !$full_name || !$admission_no || !$school_id || !$student_class) {
    die(json_encode(['success'=>false,'message'=>'Required fields missing (Name, Admission No, and Class)']));
}

try {
    // 1. Verify student and class in this school
    $chk = $pdo->prepare("SELECT id FROM students WHERE id=? AND school_id=?");
    $chk->execute([$id,$school_id]);
    if (!$chk->fetch()) die(json_encode(['success'=>false,'message'=>'Student not found']));

    $cls = $pdo->prepare("SELECT name FROM classes WHERE id=? AND school_id=?");
    $cls->execute([$student_class, $school_id]);
    $class_data = $cls->fetch();
    if (!$class_data) die(json_encode(['success'=>false,'message'=>'Invalid class selected']));
    $class_name = $class_data['name'];

    $pdo->beginTransaction();
    
    // 1.5 Handle Image Update if provided
    $img_update = "";
    $params = [$full_name, $admission_no, $gender, $dob ?: null, $guardian_name, $guardian_phone, $address, $class_name, $department_id ?: null];
    
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {
        $image_path = handleMediaUpload($pdo, $school_id, $_FILES['student_image'], 'students');
        if ($image_path) {
            $img_update = ", image_path=? ";
            $params[] = $image_path;
        }
    }
    
    $params[] = $id;
    $params[] = $school_id;

    // 2. Update students table
    $stmt = $pdo->prepare("
        UPDATE students 
        SET full_name=?, admission_no=?, gender=?, dob=?, guardian_name=?, guardian_phone=?, address=?, student_class=?, department_id=?
        $img_update
        WHERE id=? AND school_id=?
    ");
    $stmt->execute($params);

    // 3. Update mapping table
    $updMapping = $pdo->prepare("INSERT INTO student_classes (student_id, class_id, school_id) VALUES (?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE class_id = VALUES(class_id)");
    $updMapping->execute([$id, $student_class, $school_id]);

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Student record and class allocation updated successfully']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = str_contains($e->getMessage(),'Duplicate') ? 'Admission number already exists' : $e->getMessage();
    echo json_encode(['success'=>false,'message'=>$msg]);
}
