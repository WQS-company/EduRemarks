<?php
// ajax/save_academic_orchestration.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access Node']);
    exit();
}

$action = $_POST['action'] ?? '';
$school_id = $active_school_id;
$session_id = intval($_POST['session_id'] ?? 0);
$term_id = intval($_POST['term_id'] ?? 0);

if (!$session_id || !$term_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Session/Term Context']);
    exit();
}

try {
    if ($action === 'update_global') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'];

        // Validate field
        $allowed_fields = ['ca1_status', 'ca2_status', 'exam_status', 'global_status', 'cbt_status', 'entry_deadline'];
        if (!in_array($field, $allowed_fields)) {
            throw new Exception("Illegal orchestration field");
        }

        $stmt = $pdo->prepare("
            INSERT INTO academic_orchestration (school_id, session_id, term_id, $field) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE $field = VALUES($field)
        ");
        $stmt->execute([$school_id, $session_id, $term_id, $value]);

        echo json_encode(['success' => true]);
    } 
    elseif ($action === 'update_staff') {
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $field = $_POST['field'] ?? 'window_status';
        $status = $_POST['status'] ?? 'open';

        // Validate field
        $allowed_fields = ['window_status', 'cbt_status'];
        if (!in_array($field, $allowed_fields)) {
            throw new Exception("Illegal staff orchestration field");
        }

        $stmt = $pdo->prepare("
            INSERT INTO staff_entry_windows (staff_id, school_id, session_id, term_id, $field) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE $field = VALUES($field)
        ");
        $stmt->execute([$staff_id, $school_id, $session_id, $term_id, $status]);

        echo json_encode(['success' => true]);
    }
    elseif ($action === 'bulk_staff') {
        $status = $_POST['status'] ?? 'open';

        // Get all active staff IDs for this school
        $stmt = $pdo->prepare("SELECT id FROM staff_details WHERE school_id = ? AND status = 'active'");
        $stmt->execute([$school_id]);
        $staff_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction();
        $ins = $pdo->prepare("
            INSERT INTO staff_entry_windows (staff_id, school_id, session_id, term_id, window_status) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE window_status = VALUES(window_status)
        ");
        foreach ($staff_ids as $sid) {
            $ins->execute([$sid, $school_id, $session_id, $term_id, $status]);
        }
        $pdo->commit();

        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception("Unknown Orchestration Directive");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
