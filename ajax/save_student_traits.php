<?php
// ajax/save_student_traits.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

$student_id = intval($_POST['student_id'] ?? 0);
$class_id = intval($_POST['class_id'] ?? 0);
$session_id = intval($_POST['session_id'] ?? 0);
$term_id = intval($_POST['term_id'] ?? 0);
$traits = $_POST['traits'] ?? [];
$school_id = $_SESSION['school_id'];

if (!$student_id || !$class_id || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Institutional verification failed.']);
    exit();
}

try {
    // SECURITY: Orchestration Check
    if ($_SESSION['role'] === 'staff') {
        $orch_stmt = $pdo->prepare("SELECT global_status FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
        $orch_stmt->execute([$school_id, $session_id, $term_id]);
        $global_status = $orch_stmt->fetchColumn();

        if ($global_status === 'closed') {
            echo json_encode(['success' => false, 'message' => 'Academic Audit window is CLOSED globally.']);
            exit();
        }

        $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id = ? AND school_id = ?");
        $sd_stmt->execute([$_SESSION['user_id'], $school_id]);
        $staff_id = $sd_stmt->fetchColumn();

        if ($staff_id) {
            $win_stmt = $pdo->prepare("SELECT window_status FROM staff_entry_windows WHERE staff_id = ? AND session_id = ? AND term_id = ?");
            $win_stmt->execute([$staff_id, $session_id, $term_id]);
            if ($win_stmt->fetchColumn() === 'closed') {
                echo json_encode(['success' => false, 'message' => 'Your individual entry window is LOCKED.']);
                exit();
            }
        }
    }

    $pdo->beginTransaction();

    foreach ($traits as $t) {
        $name = sanitize($t['name']);
        $type = sanitize($t['type']);
        $rating = intval($t['rating']);

        $stmt = $pdo->prepare("
            INSERT INTO student_traits (school_id, student_id, class_id, session_id, term_id, trait_type, trait_name, rating)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating)
        ");
        $stmt->execute([$school_id, $student_id, $class_id, $session_id, $term_id, $type, $name, $rating]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Developmental node synchronized successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Synchronization failed: ' . $e->getMessage()]);
}
?>
