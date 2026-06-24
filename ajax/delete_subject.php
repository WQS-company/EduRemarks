<?php
// ajax/delete_subject.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($role !== 'owner' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$school_id = $_SESSION['school_id'] ?? null;
$id = intval($_POST['id'] ?? 0);

if (!$id || !$school_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

try {
    $pdo->beginTransaction();

    // 1. Security check
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Subject not found or unauthorized access.");
    }

    // 2. Cleanup mappings
    $pdo->prepare("DELETE FROM class_subjects WHERE subject_id = ?")->execute([$id]);

    // 3. Delete subject
    $pdo->prepare("DELETE FROM subjects WHERE id = ? AND school_id = ?")->execute([$id, $school_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Subject and associated records deleted permanently']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
