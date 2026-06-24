<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$school_id = $_SESSION['school_id'];
$staff_id = $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    die(json_encode(['success' => false, 'message' => 'Invalid data.']));
}

try {
    $stmt = $pdo->prepare("INSERT INTO question_paper_drafts 
        (school_id, staff_id, title, subject_id, academic_session, term, assessment_type, instructions, questions_json, numbering_format) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $school_id,
        $staff_id,
        $data['title'] ?? 'Examination Paper',
        $data['subject_id'] ?: null,
        $data['session'] ?? '',
        $data['term'] ?? '',
        $data['exam_type'] ?? '',
        $data['instructions'] ?? '',
        $data['questions'],
        $data['num_format'] ?? '1'
    ]);

    echo json_encode(['success' => true, 'message' => 'Paper draft successfully archived.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
