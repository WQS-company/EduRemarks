<?php
// ajax/delete_academic_term.php — High-Precision Term Deletion Engine
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$id = $_POST['id'] ?? null;
$school_id = $active_school['id'];

if (!$id) {
    die(json_encode(['success' => false, 'message' => 'Term ID is required']));
}

try {
    $pdo->beginTransaction();

    // 1. Institutional Validation: Check if it's the current term of the school
    if ($active_school['current_term_id'] == $id) {
        $stmt = $pdo->prepare("UPDATE schools SET current_term_id = NULL WHERE id = ?");
        $stmt->execute([$school_id]);
    }

    // 2. Focused Deletion: Remove the specific term record from institutional history
    $stmt = $pdo->prepare("DELETE FROM academic_terms WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Academic term deleted successfully!']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Critical Error: ' . $e->getMessage()]);
}
