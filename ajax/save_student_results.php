<?php
// ajax/save_student_results.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

$student_id = intval($_POST['student_id'] ?? 0);
$class_id = intval($_POST['class_id'] ?? 0);
$session_id = intval($_POST['session_id'] ?? 0);
$term_id = intval($_POST['term_id'] ?? 0);
$results = $_POST['results'] ?? [];
$show_position = intval($_POST['show_position'] ?? 1);
$school_id = $_SESSION['school_id'];

if (!$student_id || !$class_id || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Institutional verification failed.']);
    exit();
}

try {
    // SECURITY: Orchestration Check
    if ($_SESSION['role'] === 'staff') {
        $sd_stmt = $pdo->prepare("SELECT id, can_edit_history FROM staff_details WHERE user_id = ? AND school_id = ?");
        $sd_stmt->execute([$_SESSION['user_id'], $school_id]);
        $staff = $sd_stmt->fetch();
        $staff_id = $staff['id'] ?? 0;
        $can_edit_history = $staff['can_edit_history'] ?? 0;

        if (!$can_edit_history) {
            $orch_stmt = $pdo->prepare("SELECT * FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
            $orch_stmt->execute([$school_id, $session_id, $term_id]);
            $orch = $orch_stmt->fetch();

            if ($orch && $orch['global_status'] === 'closed') {
                echo json_encode(['success' => false, 'message' => 'Academic Audit window is CLOSED globally.']);
                exit();
            }

            if ($staff_id) {
                $win_stmt = $pdo->prepare("SELECT window_status FROM staff_entry_windows WHERE staff_id = ? AND session_id = ? AND term_id = ?");
                $win_stmt->execute([$staff_id, $session_id, $term_id]);
                if ($win_stmt->fetchColumn() === 'closed') {
                    echo json_encode(['success' => false, 'message' => 'Your individual entry window is LOCKED.']);
                    exit();
                }
            }

            $ca1_active = $orch ? (bool)$orch['ca1_status'] : true;
            $ca2_active = $orch ? (bool)$orch['ca2_status'] : true;
            $exam_active = $orch ? (bool)$orch['exam_status'] : true;
        } else {
            // Permission granted for historical edits
            $ca1_active = $ca2_active = $exam_active = true;
        }
    } else {
        // Owners and Super Admins bypass locks
        $ca1_active = $ca2_active = $exam_active = true;
    }

    $pdo->beginTransaction();

    foreach ($results as $res) {
        $subject_id = intval($res['subject_id']);
        $ca1 = floatval($res['ca1']);
        $ca2 = floatval($res['ca2']);
        $exam = floatval($res['exam']);
        
        // Prepare dynamic update parts
        $update_parts = ["total = VALUES(total)", "grade = VALUES(grade)", "remark = VALUES(remark)"];
        if ($ca1_active) $update_parts[] = "ca1 = VALUES(ca1)";
        if ($ca2_active) $update_parts[] = "ca2 = VALUES(ca2)";
        if ($exam_active) $update_parts[] = "exam = VALUES(exam)";
        
        $update_sql = implode(", ", $update_parts);

        // Calculate total manually if some fields are locked to avoid using 0s from disabled fields
        // Actually, the JS sends the values currently in the input. If it's disabled, it's still there.
        // But to be super safe, we should only trust the values of active fields.
        // However, the INSERT part also needs to be careful not to zero out existing data.
        
        // Better approach: Fetch existing record if it exists
        $ext_stmt = $pdo->prepare("SELECT ca1, ca2, exam FROM student_results WHERE school_id=? AND student_id=? AND subject_id=? AND session_id=? AND term_id=?");
        $ext_stmt->execute([$school_id, $student_id, $subject_id, $session_id, $term_id]);
        $existing = $ext_stmt->fetch();
        
        if ($existing) {
            if (!$ca1_active) $ca1 = $existing['ca1'];
            if (!$ca2_active) $ca2 = $existing['ca2'];
            if (!$exam_active) $exam = $existing['exam'];
        }

        $total = floatval($ca1 + $ca2 + $exam);
        $grade = sanitize($res['grade']);
        $remark = sanitize($res['remark']);

        $stmt = $pdo->prepare("
            INSERT INTO student_results (school_id, student_id, class_id, subject_id, session_id, term_id, ca1, ca2, exam, total, grade, remark)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE $update_sql
        ");
        $stmt->execute([$school_id, $student_id, $class_id, $subject_id, $session_id, $term_id, $ca1, $ca2, $exam, $total, $grade, $remark]);
    }

    // Recalculate Positions for the entire class
    if ($show_position) {
        $stmt = $pdo->prepare("
            SELECT student_id, SUM(total) as grand_total 
            FROM student_results 
            WHERE class_id = ? AND session_id = ? AND term_id = ? 
            GROUP BY student_id 
            ORDER BY grand_total DESC
        ");
        $stmt->execute([$class_id, $session_id, $term_id]);
        $rankings = $stmt->fetchAll();

        $rank = 1;
        $prev_total = -1;
        foreach ($rankings as $index => $row) {
            if ($row['grand_total'] != $prev_total) {
                $rank = $index + 1;
            }
            $suffix = getOrdinalSuffix($rank);
            $pos_str = $rank . $suffix;

            $update = $pdo->prepare("UPDATE student_results SET position = ? WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
            $update->execute([$pos_str, $row['student_id'], $class_id, $session_id, $term_id]);
            
            $prev_total = $row['grand_total'];
        }
    } else {
        // Clear positions if not wanted
        $update = $pdo->prepare("UPDATE student_results SET position = NULL WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
        $update->execute([$student_id, $class_id, $session_id, $term_id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Results synchronized successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Synchronization failed: ' . $e->getMessage()]);
}

function getOrdinalSuffix($number) {
    if ($number % 100 >= 11 && $number % 100 <= 13) return 'th';
    switch ($number % 10) {
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
        default: return 'th';
    }
}
?>
