<?php
// ajax/get_cbt_scores.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($role !== 'staff' && $role !== 'super_admin' && $role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$school_id = $_SESSION['school_id'] ?? null;
$student_id = intval($_GET['student_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);
// session_id and term_id can be passed but usually we can just rely on the latest CBT scores the student has taken for that class.
// For world-class abstraction, we aggregate the maximum score obtained across multiple attempts if any.

if (!$student_id || !$class_id) {
    die(json_encode(['success' => false, 'message' => 'Missing parameters.']));
}

try {
    $stmt = $pdo->prepare("
        SELECT e.subject_id, e.assessment_type, e.test_category, MAX(a.total_score) as max_score
        FROM cbt_exams e
        JOIN cbt_student_attempts a ON a.exam_id = e.id
        WHERE a.student_id = ? AND e.class_id = ? AND e.school_id = ? AND a.status IN ('submitted', 'timed_out', 'graded')
        GROUP BY e.subject_id, e.assessment_type, e.test_category
    ");
    $stmt->execute([$student_id, $class_id, $school_id]);
    $cbt_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Structure the data for easy consumption on frontend
    // map[subject_id] = {ca1: x, ca2: y, exam: z}
    $mapped = [];
    foreach ($cbt_data as $row) {
        $sub = $row['subject_id'];
        if (!isset($mapped[$sub])) {
            $mapped[$sub] = ['ca1' => null, 'ca2' => null, 'exam' => null];
        }
        
        $score = floatval($row['max_score']);
        
        if ($row['assessment_type'] === 'test') {
            if ($row['test_category'] === '1st_ca') {
                $mapped[$sub]['ca1'] = $score;
            } elseif ($row['test_category'] === '2nd_ca') {
                $mapped[$sub]['ca2'] = $score;
            }
        } elseif ($row['assessment_type'] === 'exam') {
            $mapped[$sub]['exam'] = $score;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $mapped,
        'message' => 'CBT Assessments populated successfully!'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database exception occurred.']);
}
