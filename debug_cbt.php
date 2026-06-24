<?php
require_once 'config/db.php';

$token = '6cfc966c485df725d76f55ce99fcaab7';

// 1. Get Exam
$stmt = $pdo->prepare("SELECT * FROM cbt_exams WHERE token = ?");
$stmt->execute([$token]);
$exam = $stmt->fetch();

echo "EXAM DATA:\n";
print_r($exam);

if ($exam) {
    // 2. Get Questions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cbt_questions WHERE exam_id = ?");
    $stmt->execute([$exam['id']]);
    $count = $stmt->fetchColumn();
    echo "\nQUESTION COUNT: $count\n";
    
    $stmt = $pdo->prepare("SELECT * FROM cbt_questions WHERE exam_id = ?");
    $stmt->execute([$exam['id']]);
    $questions = $stmt->fetchAll();
    echo "\nQUESTIONS:\n";
    print_r($questions);
} else {
    echo "\nEXAM NOT FOUND\n";
}
?>
