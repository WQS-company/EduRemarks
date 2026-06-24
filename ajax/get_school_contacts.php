<?php
// ajax/get_school_contacts.php - Institutional Node Discovery
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$school_id = $_SESSION['school_id'] ?? null;
if(!$school_id) die(json_encode(['success'=>false, 'message'=>'Unauthorized school context.']));

try {
    // 1. Fetch Staff Contacts
    $staff_stmt = $pdo->prepare("
        SELECT u.full_name as name, u.phone, 'STAFF' as role 
        FROM staff_details sd 
        JOIN users u ON sd.user_id = u.id 
        WHERE sd.school_id = ? AND sd.status = 'active' AND u.phone IS NOT NULL AND u.phone != ''
    ");
    $staff_stmt->execute([$school_id]);
    $staff = $staff_stmt->fetchAll();

    // 2. Fetch Parent/Guardian Contacts grouped by Class
    $parent_stmt = $pdo->prepare("
        SELECT guardian_name as name, guardian_phone as phone, student_class as class_name, 'PARENT' as role 
        FROM students 
        WHERE school_id = ? AND guardian_phone IS NOT NULL AND guardian_phone != '' AND status = 'active'
        ORDER BY student_class ASC, guardian_name ASC
    ");
    $parent_stmt->execute([$school_id]);
    $parents_raw = $parent_stmt->fetchAll();

    $classes = [];
    foreach($parents_raw as $p) {
        $c_name = $p['class_name'] ?: 'Unassigned';
        if(!isset($classes[$c_name])) {
            $classes[$c_name] = [];
        }
        $classes[$c_name][] = [
            'name' => $p['name'],
            'phone' => $p['phone'],
            'role' => $p['role']
        ];
    }

    echo json_encode([
        'success' => true, 
        'staff' => $staff,
        'classes' => $classes
    ]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
