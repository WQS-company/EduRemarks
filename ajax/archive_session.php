<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$session_id = $data['session_id'] ?? null;
$school_id = $active_school['id'];

if (!$session_id) {
    echo json_encode(['success' => false, 'message' => 'Session ID is required']);
    exit;
}

try {
    // Verify session belongs to school
    $stmt = $pdo->prepare("SELECT id FROM academic_sessions WHERE id = ? AND school_id = ?");
    $stmt->execute([$session_id, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Session not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE academic_sessions SET status = 'archived' WHERE id = ?");
    $stmt->execute([$session_id]);

    echo json_encode(['success' => true, 'message' => 'Academic session archived successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
