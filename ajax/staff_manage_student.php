<?php
// ajax/staff_manage_student.php — Permission-gated student management for staff
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized. Staff access only.']));
}

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die(json_encode(['success' => false, 'message' => 'No active school selected.']));
}

// ─── Permission Gate ───
$stmt = $pdo->prepare("SELECT id, can_manage_students FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$stmt->execute([$user_id, $school_id]);
$staff_detail = $stmt->fetch();

if (!$staff_detail) {
    die(json_encode(['success' => false, 'message' => 'Your staff record is not active at this school.']));
}

if (!$staff_detail['can_manage_students']) {
    die(json_encode(['success' => false, 'message' => 'Permission denied. Your school administrator has not granted you student management access. Contact your admin to enable this feature.']));
}

$action = sanitize($_POST['action'] ?? '');

// ─── Validate Action ───
if (!in_array($action, ['add', 'update', 'change_class'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid action.']));
}

try {
    // ═══════════════════════════════════════════
    // ACTION: Add New Student
    // ═══════════════════════════════════════════
    if ($action === 'add') {
        $full_name     = sanitize($_POST['full_name'] ?? '');
        $admission_no  = sanitize($_POST['admission_no'] ?? '');
        $class_id      = intval($_POST['class_id'] ?? 0);
        $gender        = sanitize($_POST['gender'] ?? '');
        $dob           = sanitize($_POST['dob'] ?? '');
        $guardian_name  = sanitize($_POST['guardian_name'] ?? '');
        $guardian_phone = sanitize($_POST['guardian_phone'] ?? '');
        $address       = sanitize($_POST['address'] ?? '');

        if (!$full_name || !$admission_no || !$class_id) {
            die(json_encode(['success' => false, 'message' => 'Full name, admission number, and class are required.']));
        }

        if (strlen($full_name) < 3) {
            die(json_encode(['success' => false, 'message' => 'Full name must be at least 3 characters.']));
        }

        if (strlen($admission_no) < 2) {
            die(json_encode(['success' => false, 'message' => 'Admission number must be at least 2 characters.']));
        }

        // Verify class belongs to this school
        $stmt = $pdo->prepare("SELECT name FROM classes WHERE id=? AND school_id=?");
        $stmt->execute([$class_id, $school_id]);
        $class = $stmt->fetch();
        if (!$class) die(json_encode(['success' => false, 'message' => 'Selected class does not belong to this school.']));

        // Check duplicate admission number
        $stmt = $pdo->prepare("SELECT id FROM students WHERE school_id=? AND admission_no=?");
        $stmt->execute([$school_id, $admission_no]);
        if ($stmt->fetch()) {
            die(json_encode(['success' => false, 'message' => 'A student with this admission number already exists in this school.']));
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO students (school_id, full_name, admission_no, student_class, gender, dob, guardian_name, guardian_phone, address) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$school_id, $full_name, $admission_no, $class['name'], $gender ?: null, $dob ?: null, $guardian_name, $guardian_phone, $address]);
        $student_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id, school_id) VALUES (?,?,?)");
        $stmt->execute([$student_id, $class_id, $school_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Student '{$full_name}' registered in {$class['name']} successfully.", 'student_id' => $student_id]);
    }

    // ═══════════════════════════════════════════
    // ACTION: Update Student Info
    // ═══════════════════════════════════════════
    elseif ($action === 'update') {
        $student_id    = intval($_POST['student_id'] ?? 0);
        $full_name     = sanitize($_POST['full_name'] ?? '');
        $gender        = sanitize($_POST['gender'] ?? '');
        $dob           = sanitize($_POST['dob'] ?? '');
        $guardian_name  = sanitize($_POST['guardian_name'] ?? '');
        $guardian_phone = sanitize($_POST['guardian_phone'] ?? '');
        $address       = sanitize($_POST['address'] ?? '');

        if (!$student_id || !$full_name) {
            die(json_encode(['success' => false, 'message' => 'Student ID and full name are required.']));
        }

        // Verify student belongs to this school
        $stmt = $pdo->prepare("SELECT id FROM students WHERE id=? AND school_id=?");
        $stmt->execute([$student_id, $school_id]);
        if (!$stmt->fetch()) {
            die(json_encode(['success' => false, 'message' => 'Student not found in this school.']));
        }

        $stmt = $pdo->prepare("UPDATE students SET full_name=?, gender=?, dob=?, guardian_name=?, guardian_phone=?, address=? WHERE id=? AND school_id=?");
        $stmt->execute([$full_name, $gender ?: null, $dob ?: null, $guardian_name, $guardian_phone, $address, $student_id, $school_id]);

        echo json_encode(['success' => true, 'message' => "Student '{$full_name}' updated successfully."]);
    }

    // ═══════════════════════════════════════════
    // ACTION: Change Student Class
    // ═══════════════════════════════════════════
    elseif ($action === 'change_class') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $new_class_id = intval($_POST['new_class_id'] ?? 0);

        if (!$student_id || !$new_class_id) {
            die(json_encode(['success' => false, 'message' => 'Student ID and new class are required.']));
        }

        // Verify student belongs to this school
        $stmt = $pdo->prepare("SELECT id FROM students WHERE id=? AND school_id=?");
        $stmt->execute([$student_id, $school_id]);
        if (!$stmt->fetch()) {
            die(json_encode(['success' => false, 'message' => 'Student not found in this school.']));
        }

        // Verify new class belongs to this school
        $stmt = $pdo->prepare("SELECT name FROM classes WHERE id=? AND school_id=?");
        $stmt->execute([$new_class_id, $school_id]);
        $new_class = $stmt->fetch();
        if (!$new_class) {
            die(json_encode(['success' => false, 'message' => 'Target class does not belong to this school.']));
        }

        $pdo->beginTransaction();

        // Update the student's class in the students table
        $stmt = $pdo->prepare("UPDATE students SET student_class=? WHERE id=? AND school_id=?");
        $stmt->execute([$new_class['name'], $student_id, $school_id]);

        // Update or insert in student_classes mapping
        $stmt = $pdo->prepare("SELECT id FROM student_classes WHERE student_id=? AND school_id=?");
        $stmt->execute([$student_id, $school_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE student_classes SET class_id=? WHERE student_id=? AND school_id=?");
            $stmt->execute([$new_class_id, $student_id, $school_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id, school_id) VALUES (?,?,?)");
            $stmt->execute([$student_id, $new_class_id, $school_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Student moved to {$new_class['name']} successfully."]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
