<?php
// ajax/cbt_finish.php
require_once '../config/db.php';

$attempt_id = $_POST['attempt_id'] ?? null;
$timed_out  = $_POST['timed_out'] ?? 0;

if (!$attempt_id) die(json_encode(['success'=>false, 'message'=>'Invalid request']));

try {
    // 1. Mark attempt as submitted/timed out
    $new_status = $timed_out ? 'timed_out' : 'submitted';
    $stmt = $pdo->prepare("UPDATE cbt_student_attempts SET status=?, end_time=NOW() WHERE id=?");
    $stmt->execute([$new_status, $attempt_id]);

    // 2. Fetch all questions and answers for scoring
    $stmt = $pdo->prepare("
        SELECT q.id, q.type, q.correct_answer, q.marks as q_marks, e.marks_per_question as e_marks,
               a.answer_text, a.id as answer_id
        FROM cbt_questions q
        JOIN cbt_exams e ON e.id = q.exam_id
        JOIN cbt_student_attempts att ON att.exam_id = e.id
        LEFT JOIN cbt_student_answers a ON (a.question_id = q.id AND a.attempt_id = att.id)
        WHERE att.id = ?
    ");
    $stmt->execute([$attempt_id]);
    $data = $stmt->fetchAll();

    $total_score = 0;
    foreach ($data as $item) {
        $marks = $item['q_marks'] ?: $item['e_marks'];
        $is_correct = 0;
        $score = 0;

        if ($item['type'] === 'objective' || $item['type'] === 'tf') {
            if ($item['answer_text'] === $item['correct_answer']) {
                $is_correct = 1;
                $score = $marks;
            }
        }
        // Essay needs manual marking later or basic keyword match (skipped for now)

        $total_score += $score;

        if ($item['answer_id']) {
            $upd = $pdo->prepare("UPDATE cbt_student_answers SET is_correct=?, score_obtained=? WHERE id=?");
            $upd->execute([$is_correct, $score, $item['answer_id']]);
        }
    }

    // 3. Final score update
    $pdo->prepare("UPDATE cbt_student_attempts SET total_score=? WHERE id=?")->execute([$total_score, $attempt_id]);

    echo json_encode(['success'=>true, 'score'=>$total_score]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
