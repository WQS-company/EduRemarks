<?php
// ajax/delete_academic_session.php — Institutional History Purge Engine
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$id = $_POST['id'] ?? null;
$school_id = $active_school['id'];

if (!$id) {
    die(json_encode(['success' => false, 'message' => 'Session ID is required']));
}

try {
    $pdo->beginTransaction();

    // 1. State Management: Check if it's the current session of the school
    if ($active_school['current_session_id'] == $id) {
        $stmt = $pdo->prepare("UPDATE schools SET current_session_id = NULL WHERE id = ?");
        $stmt->execute([$school_id]);
    }

    // 2. Cascade Deletion: Purge all terms associated with this session context
    $stmt = $pdo->prepare("DELETE FROM academic_terms WHERE session_id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);

    // 3. Final Deletion: Remove the session record from institutional history
    $stmt = $pdo->prepare("DELETE FROM academic_sessions WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Academic session and its terms have been professionally purged.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Critical Error: ' . $e->getMessage()]);
}
