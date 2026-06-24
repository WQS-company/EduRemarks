<?php
// ajax/save_batch_results.php - Secure Historical Result Synchronization
// Supports two modes:
//   Mode A (per-student):  student_id + class_id + session_id + term_id + results[{subject_id, ca1, ca2, exam, total, grade, points, remark}]
//   Mode B (per-subject):  subject_id + class_id + session_id + term_id + results[{student_id, result_id, ca1, ca2, exam, total, grade, points}]
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

// Permission Guard
if (empty($staff_permissions['can_edit_history'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Insufficient Node Permissions']);
    exit();
}

$school_id = $_SESSION['school_id'];
$data      = json_decode(file_get_contents('php://input'), true);

if (empty($data) || empty($data['results'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Synchronization Parameters']);
    exit();
}

$session_id = intval($data['session_id'] ?? 0);
$term_id    = intval($data['term_id']    ?? 0);
$class_id   = intval($data['class_id']   ?? 0);

// Determine mode
$mode_a = !empty($data['student_id']); // Per-student, multiple subjects
$mode_b = !empty($data['subject_id']); // Per-subject, multiple students

if (!$session_id || !$term_id || (!$mode_a && !$mode_b)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters (session, term, or student/subject id)']);
    exit();
}

try {
    $pdo->beginTransaction();

    // UPSERT via UPDATE then INSERT if no rows affected
    $upd_stmt = $pdo->prepare("
        UPDATE student_results
        SET ca1 = ?, ca2 = ?, exam = ?, total = ?, grade = ?, points = ?
        WHERE student_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? AND school_id = ?
    ");

    $ins_stmt = $pdo->prepare("
        INSERT INTO student_results (student_id, subject_id, session_id, term_id, school_id, ca1, ca2, exam, total, grade, points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE ca1=VALUES(ca1), ca2=VALUES(ca2), exam=VALUES(exam),
                                 total=VALUES(total), grade=VALUES(grade), points=VALUES(points)
    ");

    $success_count = 0;

    foreach ($data['results'] as $res) {
        $ca1    = ($res['ca1']   === '' || $res['ca1']   === null) ? null : floatval($res['ca1']);
        $ca2    = ($res['ca2']   === '' || $res['ca2']   === null) ? null : floatval($res['ca2']);
        $exam   = ($res['exam']  === '' || $res['exam']  === null) ? null : floatval($res['exam']);
        $total  = floatval($res['total']  ?? 0);
        $grade  = (!isset($res['grade'])  || $res['grade']  === '-' || $res['grade']  === '') ? null : $res['grade'];
        $points = floatval($res['points'] ?? 0);

        if ($mode_a) {
            // Per-student mode: subject_id comes from each row
            $student_id_r = intval($data['student_id']);
            $subject_id_r = intval($res['subject_id']);
        } else {
            // Per-subject mode: student_id comes from each row
            $student_id_r = intval($res['student_id']);
            $subject_id_r = intval($data['subject_id']);
        }

        if (!$student_id_r || !$subject_id_r) continue;

        // Try update first
        $upd_stmt->execute([$ca1, $ca2, $exam, $total, $grade, $points, $student_id_r, $subject_id_r, $session_id, $term_id, $school_id]);

        if ($upd_stmt->rowCount() === 0) {
            // No existing row — insert
            $ins_stmt->execute([$student_id_r, $subject_id_r, $session_id, $term_id, $school_id, $ca1, $ca2, $exam, $total, $grade, $points]);
        }

        $success_count++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Successfully synchronized $success_count records."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Synchronization Layer Failure: ' . $e->getMessage()]);
}
?>
