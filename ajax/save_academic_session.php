<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$school_id = $active_school['id'];

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Session name is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert new session
    $stmt = $pdo->prepare("INSERT INTO academic_sessions (school_id, name, status) VALUES (?, ?, 'active')");
    $stmt->execute([$school_id, $name]);
    $session_id = $pdo->lastInsertId();

    // Set as current session for the school
    $stmt = $pdo->prepare("UPDATE schools SET current_session_id = ? WHERE id = ?");
    $stmt->execute([$session_id, $school_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Academic session created and activated successfully!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
