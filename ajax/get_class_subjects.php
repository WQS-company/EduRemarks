<?php
// ajax/get_class_subjects.php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$class_id = intval($_GET['class_id'] ?? 0);
$term_id = intval($_GET['term_id'] ?? 0);

if (!$class_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit();
}

try {
    $role = $_SESSION['role'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    $school_id = $_SESSION['school_id'] ?? 0;

    if ($role === 'staff') {
        // Find staff_detail_id
        $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id = ? AND school_id = ? AND status = 'active'");
        $sd_stmt->execute([$user_id, $school_id]);
        $staff_detail_id = $sd_stmt->fetchColumn();

        if ($staff_detail_id) {
            $query = "
                SELECT s.id, s.name, s.code, s.credit_units
                FROM subjects s 
                JOIN staff_class_subjects scs ON s.id = scs.subject_id 
                WHERE scs.class_id = ? AND scs.staff_detail_id = ? AND scs.school_id = ?
            ";
            $params = [$class_id, $staff_detail_id, $school_id];

            if ($term_id) {
                $query .= " AND (s.semester_id = ? OR s.semester_id IS NULL OR s.semester_id = 0)";
                $params[] = $term_id;
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        } else {
            $subjects = [];
        }
    } else {
        $query = "
            SELECT s.id, s.name, s.code, s.credit_units
            FROM subjects s 
            JOIN class_subjects cs ON s.id = cs.subject_id 
            WHERE cs.class_id = ?
        ";
        $params = [$class_id];

        if ($term_id) {
            $query .= " AND (s.semester_id = ? OR s.semester_id IS NULL OR s.semester_id = 0)";
            $params[] = $term_id;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }

    if (isset($stmt)) {
        $subjects = $stmt->fetchAll();
    } else {
        $subjects = [];
    }

    echo json_encode(['success' => true, 'subjects' => $subjects]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
