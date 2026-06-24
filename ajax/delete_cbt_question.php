<?php
// ajax/delete_cbt_question.php
require_once '../includes/auth_check.php';

if ($role !== 'staff') die(json_encode(['success'=>false, 'message'=>'Unauthorized']));

$school_id = $_SESSION['school_id'] ?? null;
$id        = $_POST['id'] ?? null;

if (!$id || !$school_id) die(json_encode(['success'=>false, 'message'=>'Invalid request']));

try {
    // Verify question belongs to an exam owned by this school
    $stmt = $pdo->prepare("
        SELECT q.id 
        FROM cbt_questions q
        JOIN cbt_exams e ON e.id = q.exam_id
        WHERE q.id=? AND e.school_id=?
    ");
    $stmt->execute([$id, $school_id]);
    if (!$stmt->fetch()) die(json_encode(['success'=>false, 'message'=>'Question not found or access denied']));

    $stmt = $pdo->prepare("DELETE FROM cbt_questions WHERE id=?");
    $stmt->execute([$id]);
    
    echo json_encode(['success'=>true, 'message'=>'Question deleted']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
