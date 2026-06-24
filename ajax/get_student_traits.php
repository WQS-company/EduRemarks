<?php
// ajax/get_student_traits.php
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
    $stmt = $pdo->prepare("SELECT trait_name, rating FROM student_traits WHERE student_id=? AND class_id=? AND session_id=? AND term_id=?");
    $stmt->execute([$student_id, $class_id, $session_id, $term_id]);
    $rows = $stmt->fetchAll();

    $traits = [];
    foreach ($rows as $r) {
        $traits[$r['trait_name']] = $r['rating'];
    }

    echo json_encode(['success' => true, 'traits' => $traits]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
