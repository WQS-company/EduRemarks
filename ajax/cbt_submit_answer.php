<?php
// ajax/cbt_submit_answer.php
require_once '../config/db.php';

$attempt_id   = $_POST['attempt_id'] ?? null;
$question_id  = $_POST['question_id'] ?? null;
$answer_text  = $_POST['answer'] ?? '';

if (!$attempt_id || !$question_id) die(json_encode(['success'=>false, 'message'=>'Invalid request']));

try {
    // Check if attempt is still started
    $stmt = $pdo->prepare("SELECT status FROM cbt_student_attempts WHERE id=?");
    $stmt->execute([$attempt_id]);
    $attempt = $stmt->fetch();
    
    if (!$attempt || $attempt['status'] !== 'started') {
        die(json_encode(['success'=>false, 'message'=>'Attempt is closed or invalid']));
    }

    // Upsert answer
    $stmt = $pdo->prepare("
        INSERT INTO cbt_student_answers (attempt_id, question_id, answer_text)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text)
    ");
    $stmt->execute([$attempt_id, $question_id, $answer_text]);

    echo json_encode(['success'=>true, 'message'=>'Answer saved']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
