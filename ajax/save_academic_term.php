<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$school_id = $active_school['id'];
$session_id = $active_school['current_session_id'];

if (!$session_id) {
    echo json_encode(['success' => false, 'message' => 'No active session found.']);
    exit;
}

try {
    if ($action === 'auto_generate') {
        // Tertiary Check
        $type = strtolower($active_school['school_type'] ?? '');
        $is_higher_ed = (
            strpos($type, 'tertiary') !== false || 
            strpos($type, 'vocational') !== false || 
            strpos($type, 'polytechnic') !== false || 
            strpos($type, 'university') !== false || 
            strpos($type, 'college') !== false
        );

        $terms = $is_higher_ed ? ['1st Semester', '2nd Semester'] : ['1st Term', '2nd Term', '3rd Term'];
        
        // Check for existing terms to prevent duplicates
        $check = $pdo->prepare("SELECT COUNT(*) FROM academic_terms WHERE school_id = ? AND session_id = ?");
        $check->execute([$school_id, $session_id]);
        if ($check->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Terms already exist for this session. Please delete them first or add manually.']);
            exit;
        }

        $pdo->beginTransaction();

        foreach ($terms as $t) {
            $stmt = $pdo->prepare("INSERT INTO academic_terms (school_id, session_id, name, status) VALUES (?, ?, ?, 'inactive')");
            $stmt->execute([$school_id, $session_id, $t]);
        }

        $pdo->commit();
        $msg = $is_higher_ed ? 'Two semesters auto-generated successfully!' : 'Three terms auto-generated successfully!';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
