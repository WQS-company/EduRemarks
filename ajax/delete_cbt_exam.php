<?php
// ajax/delete_cbt_exam.php
require_once '../includes/auth_check.php';

if ($role !== 'staff') die(json_encode(['success'=>false, 'message'=>'Unauthorized']));

$school_id = $_SESSION['school_id'] ?? null;
$id        = $_POST['id'] ?? null;

if (!$id || !$school_id) die(json_encode(['success'=>false, 'message'=>'Invalid request']));

try {
    $stmt = $pdo->prepare("DELETE FROM cbt_exams WHERE id=? AND school_id=?");
    $stmt->execute([$id, $school_id]);
    
    if ($stmt->rowCount()) echo json_encode(['success'=>true, 'message'=>'Exam deleted']);
    else echo json_encode(['success'=>false, 'message'=>'Deletion failed']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
