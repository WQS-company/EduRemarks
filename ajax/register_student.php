<?php
// ajax/register_student.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}
if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_students'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}

$full_name     = sanitize($_POST['full_name'] ?? '');
$student_class = sanitize($_POST['student_class'] ?? '');
$gender        = sanitize($_POST['gender'] ?? 'Male');
$dob           = sanitize($_POST['dob'] ?? '');
$guardian_name = sanitize($_POST['guardian_name'] ?? '');
$guardian_phone= sanitize($_POST['guardian_phone'] ?? '');
$address       = sanitize($_POST['address'] ?? '');
$department_id = intval($_POST['department_id'] ?? 0);
$school_id     = $_SESSION['school_id'];

if (!$school_id) {
    die(json_encode(['success' => false, 'message' => 'No active school selected.']));
}

if (empty($full_name) || empty($student_class)) {
    die(json_encode(['success' => false, 'message' => 'Name and Class are required.']));
}

try {
    // 0. Fetch School Admission Protocol
    $stmt = $pdo->prepare("SELECT adm_no_type, adm_no_pattern, adm_no_counter, unique_id FROM schools WHERE id = ?");
    $stmt->execute([$school_id]);
    $sch_set = $stmt->fetch();

    $admission_no = "";
    if ($sch_set['adm_no_type'] === 'system') {
        $admission_no = "ADM" . mt_rand(100000, 999999);
    } elseif ($sch_set['adm_no_type'] === 'pattern') {
        $pattern = $sch_set['adm_no_pattern'];
        $counter = (int)$sch_set['adm_no_counter'];
        
        $admission_no = str_replace('{YEAR}', date('Y'), $pattern);
        $admission_no = str_replace('{SCH}', $sch_set['unique_id'], $admission_no);
        $admission_no = str_replace('{ID}', str_pad($counter, 3, '0', STR_PAD_LEFT), $admission_no);
        
        // Atomic Counter Increment
        $pdo->prepare("UPDATE schools SET adm_no_counter = adm_no_counter + 1 WHERE id = ?")->execute([$school_id]);
    } else {
        $admission_no = sanitize($_POST['admission_no'] ?? '');
        if (empty($admission_no)) {
            die(json_encode(['success' => false, 'message' => 'Admission number is required for manual protocols.']));
        }
    }

    // 1. Verify and get class name
    $stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND school_id = ?");
    $stmt->execute([$student_class, $school_id]);
    $class_data = $stmt->fetch();
    if (!$class_data) {
        die(json_encode(['success' => false, 'message' => 'Selected class is invalid.']));
    }
    $class_name = $class_data['name'];

    // 1.5 Extra verification for Staff role
    if ($role === 'staff') {
        $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
        $sd_stmt->execute([$user_id, $school_id]);
        $sd_row = $sd_stmt->fetch();
        if (!$sd_row) die(json_encode(['success' => false, 'message' => 'Staff profile not found.']));

        $assigned = $pdo->prepare("SELECT id FROM staff_class_subjects WHERE staff_detail_id=? AND class_id=? AND school_id=? LIMIT 1");
        $assigned->execute([$sd_row['id'], $student_class, $school_id]);
        if (!$assigned->fetch()) {
            die(json_encode(['success' => false, 'message' => 'Unauthorized: You can only add students to classes assigned to you.']));
        }
    }

    // 2. Check if admission number already exists in this school
    $stmt = $pdo->prepare("SELECT id FROM students WHERE school_id = ? AND admission_no = ?");
    $stmt->execute([$school_id, $admission_no]);
    if ($stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Admission number already exists in this school.']));
    }

    $pdo->beginTransaction();

    // 2.5 Handle Profile Image Upload
    $image_path = null;
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {
        $image_path = handleMediaUpload($pdo, $school_id, $_FILES['student_image'], 'students');
    }

    // 3. Insert into students table (with student_class name)
    $stmt = $pdo->prepare("
        INSERT INTO students (school_id, department_id, full_name, admission_no, student_class, gender, dob, guardian_name, guardian_phone, address, image_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$school_id, $department_id ?: null, $full_name, $admission_no, $class_name, $gender, $dob ?: null, $guardian_name, $guardian_phone, $address, $image_path]);
    $student_id = $pdo->lastInsertId();

    // 4. Update mapping table student_classes
    $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id, school_id) VALUES (?, ?, ?)");
    $stmt->execute([$student_id, $student_class, $school_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Student registered and allocated to ' . $class_name]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $role_debug = $_SESSION['role'] ?? 'none';
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage() . " (Role: $role_debug, School: $school_id)"]);
}
?>
