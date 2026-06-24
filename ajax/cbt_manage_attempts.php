<?php
// ajax/cbt_manage_attempts.php - Institutional Exam Orchestration Node
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid transmission protocol.']));
}

if ($role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized orchestration access.']));
}

$action = $_POST['action'] ?? '';
$exam_id = filter_var($_POST['exam_id'] ?? 0, FILTER_VALIDATE_INT);
$student_ids_raw = $_POST['student_ids'] ?? '';
$school_id = $_SESSION['school_id'] ?? 0;

if (!$exam_id || empty($student_ids_raw) || !in_array($action, ['retake', 'extend_time'])) {
    die(json_encode(['success' => false, 'message' => 'Missing orchestration parameters.']));
}

// Basic structural validation of student_ids
$student_ids = array_filter(array_map('intval', explode(',', $student_ids_raw)));
if (empty($student_ids)) {
    die(json_encode(['success' => false, 'message' => 'No valid candidates selected.']));
}

// Verify staff access to this exam
$stmt = $pdo->prepare("SELECT id FROM cbt_exams WHERE id = ? AND school_id = ?");
$stmt->execute([$exam_id, $school_id]);
if (!$stmt->fetch()) {
    die(json_encode(['success' => false, 'message' => 'Security fault: Assessment access denied.']));
}

try {
    $pdo->beginTransaction();

    $inQuery = implode(',', array_fill(0, count($student_ids), '?'));
    
    if ($action === 'retake') {
        // 1. Permanently delete actual answers via multi-table DELETE syntax supported by MySQL/MariaDB
        $params = array_merge([$exam_id], $student_ids);
        $del_answers = $pdo->prepare("DELETE ans FROM cbt_student_answers ans 
                                      JOIN cbt_student_attempts att ON ans.attempt_id = att.id 
                                      WHERE att.exam_id = ? AND att.student_id IN ($inQuery)");
        $del_answers->execute($params);

        // 2. Delete the attempts themselves to completely refresh it
        $del_attempts = $pdo->prepare("DELETE FROM cbt_student_attempts WHERE exam_id = ? AND student_id IN ($inQuery)");
        $del_attempts->execute($params);
        $msg = "Assessment re-initialized successfully for selected candidates.";
        
    } elseif ($action === 'extend_time') {
        $extra_mins = filter_var($_POST['extra_mins'] ?? 0, FILTER_VALIDATE_INT);
        if ($extra_mins <= 0) {
            throw new Exception("Invalid time extension geometry.");
        }

        $stmt_exam = $pdo->prepare("SELECT duration_mins FROM cbt_exams WHERE id = ?");
        $stmt_exam->execute([$exam_id]);
        $duration_mins = $stmt_exam->fetchColumn();

        foreach ($student_ids as $sid) {
            $stmt_att = $pdo->prepare("SELECT id, start_time, time_extension_mins FROM cbt_student_attempts WHERE exam_id=? AND student_id=?");
            $stmt_att->execute([$exam_id, $sid]);
            $att = $stmt_att->fetch();
            
            if ($att) {
                $started_ts = strtotime($att['start_time']);
                $passed_mins = (time() - $started_ts) / 60;
                $total_allocated_mins = $duration_mins + $att['time_extension_mins'];
                
                if ($passed_mins >= $total_allocated_mins) {
                    // Candidate timed out. Calculate new extension so they have exactly $extra_mins left from NOW.
                    $new_extension = ceil($passed_mins + $extra_mins - $duration_mins);
                    $upd = $pdo->prepare("UPDATE cbt_student_attempts SET time_extension_mins=?, status='started' WHERE id=?");
                    $upd->execute([$new_extension, $att['id']]);
                } else {
                    // Candidate is still active, just append time.
                    $upd = $pdo->prepare("UPDATE cbt_student_attempts SET time_extension_mins=time_extension_mins+?, status='started' WHERE id=?");
                    $upd->execute([$extra_mins, $att['id']]);
                }
            }
        }
        
        $msg = "+$extra_mins minutes dynamically allocated to selected candidates.";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("CBT Orchestration Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Orchestration execution failed.']);
}
