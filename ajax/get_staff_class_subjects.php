<?php
// ajax/get_staff_class_subjects.php
require_once '../includes/auth_check.php';

if ($role !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sd_id    = $_GET['staff_detail_id'] ?? null;
try {
    $stmt = $pdo->prepare("SELECT class_id, subject_id FROM staff_class_subjects WHERE staff_detail_id=? AND school_id=?");
    $stmt->execute([$sd_id, $active_school['id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignments = [];
    foreach ($results as $row) {
        $cid = $row['class_id'];
        $sid = $row['subject_id'];
        if (!isset($assignments[$cid])) {
            $assignments[$cid] = [];
        }
        $assignments[$cid][] = $sid;
    }

    echo json_encode(['success' => true, 'assignments' => $assignments]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
