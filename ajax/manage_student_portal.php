<?php
// ajax/manage_student_portal.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'staff', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$action = $_POST['action'] ?? '';
$school_id = $_SESSION['school_id'] ?? 0;

// Verify school access
if ($_SESSION['role'] !== 'super_admin' && !$school_id) {
    echo json_encode(['success' => false, 'message' => 'No school context active.']);
    exit();
}

// ═══════════════════════════════════════════════════════════
// ACTION: Generate portal password for a single student
// ═══════════════════════════════════════════════════════════
if ($action === 'generate') {
    $student_id = intval($_POST['student_id'] ?? 0);
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, admission_no, full_name, school_id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit();
    }

    if ($_SESSION['role'] !== 'super_admin' && $student['school_id'] != $school_id) {
        echo json_encode(['success' => false, 'message' => 'Security boundary violation.']);
        exit();
    }

    $raw_password = 'EDU-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

    $update = $pdo->prepare("UPDATE students SET student_password = ?, portal_active = 1 WHERE id = ?");
    if ($update->execute([$hashed_password, $student_id])) {
        echo json_encode([
            'success' => true,
            'message' => 'Portal credentials generated.',
            'admission_no' => $student['admission_no'],
            'full_name' => $student['full_name'],
            'password' => $raw_password
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update credentials.']);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: Toggle portal active status for a student
// ═══════════════════════════════════════════════════════════
elseif ($action === 'toggle_status') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $new_status = intval($_POST['status'] ?? 0);

    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, school_id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student || ($_SESSION['role'] !== 'super_admin' && $student['school_id'] != $school_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student or access denied.']);
        exit();
    }

    $update = $pdo->prepare("UPDATE students SET portal_active = ? WHERE id = ?");
    if ($update->execute([$new_status ? 1 : 0, $student_id])) {
        echo json_encode([
            'success' => true,
            'message' => $new_status ? 'Portal access activated.' : 'Portal access deactivated.',
            'status' => $new_status ? 1 : 0
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    }
}

// ═══════════════════════════════════════════════════════════
// ACTION: Bulk generate passwords for multiple students
// ═══════════════════════════════════════════════════════════
elseif ($action === 'bulk_generate') {
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);

    if (empty($student_ids) || !is_array($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'No students selected.']);
        exit();
    }

    $credentials = [];
    $success_count = 0;

    foreach ($student_ids as $sid) {
        $sid = intval($sid);
        $stmt = $pdo->prepare("SELECT id, admission_no, full_name, school_id FROM students WHERE id = ?");
        $stmt->execute([$sid]);
        $student = $stmt->fetch();

        if (!$student || ($_SESSION['role'] !== 'super_admin' && $student['school_id'] != $school_id)) {
            continue;
        }

        $raw_password = 'EDU-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE students SET student_password = ?, portal_active = 1 WHERE id = ?");
        if ($update->execute([$hashed_password, $sid])) {
            $credentials[] = [
                'full_name' => $student['full_name'],
                'admission_no' => $student['admission_no'],
                'password' => $raw_password
            ];
            $success_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "$success_count credential(s) generated successfully.",
        'credentials' => $credentials
    ]);
}

// ═══════════════════════════════════════════════════════════
// ACTION: Bulk generate for an entire class
// ═══════════════════════════════════════════════════════════
elseif ($action === 'bulk_generate_class') {
    $class_id = intval($_POST['class_id'] ?? 0);

    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID is required.']);
        exit();
    }

    // Fetch all students in this class for this school
    $stmt = $pdo->prepare("
        SELECT s.id, s.admission_no, s.full_name 
        FROM students s 
        JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
        WHERE s.school_id = ? AND sc.class_id = ?
        ORDER BY s.full_name
    ");
    $stmt->execute([$school_id, $class_id]);
    $students = $stmt->fetchAll();

    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No students found in this class.']);
        exit();
    }

    $credentials = [];
    $success_count = 0;

    foreach ($students as $student) {
        $raw_password = 'EDU-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE students SET student_password = ?, portal_active = 1 WHERE id = ?");
        if ($update->execute([$hashed_password, $student['id']])) {
            $credentials[] = [
                'full_name' => $student['full_name'],
                'admission_no' => $student['admission_no'],
                'password' => $raw_password
            ];
            $success_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "$success_count credential(s) generated for the class.",
        'credentials' => $credentials
    ]);
}

// ═══════════════════════════════════════════════════════════
// ACTION: Bulk toggle status
// ═══════════════════════════════════════════════════════════
elseif ($action === 'bulk_toggle') {
    $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
    $new_status = intval($_POST['status'] ?? 0);

    if (empty($student_ids) || !is_array($student_ids)) {
        echo json_encode(['success' => false, 'message' => 'No students selected.']);
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $params = array_map('intval', $student_ids);

    // Security: only update students from this school
    $stmt = $pdo->prepare("UPDATE students SET portal_active = ? WHERE id IN ($placeholders) AND school_id = ?");
    $stmt->execute(array_merge([$new_status ? 1 : 0], $params, [$school_id]));

    echo json_encode([
        'success' => true,
        'message' => count($student_ids) . ' student(s) ' . ($new_status ? 'activated' : 'deactivated') . '.'
    ]);
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
}
