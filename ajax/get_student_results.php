<?php
// ajax/get_student_results.php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$student_id = intval($_GET['student_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
$session_id = intval($_GET['session_id'] ?? 0);
$term_id = intval($_GET['term_id'] ?? 0);

if (!$student_id || !$class_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM student_results WHERE student_id=? AND class_id=? AND session_id=? AND term_id=?");
    $stmt->execute([$student_id, $class_id, $session_id, $term_id]);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $r) {
        $results[$r['subject_id']] = $r;
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
