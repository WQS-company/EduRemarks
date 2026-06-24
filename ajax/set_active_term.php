<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$term_id = $data['term_id'] ?? null;
$school_id = $active_school['id'];

if (!$term_id) {
    echo json_encode(['success' => false, 'message' => 'Term ID is required']);
    exit;
}

try {
    // Verify term belongs to school
    $stmt = $pdo->prepare("SELECT id FROM academic_terms WHERE id = ? AND school_id = ?");
    $stmt->execute([$term_id, $school_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Term not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE schools SET current_term_id = ? WHERE id = ?");
    $stmt->execute([$term_id, $school_id]);

    echo json_encode(['success' => true, 'message' => 'Academic term activated successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
