<?php
// ajax/sms_estimate_count.php - Institutional Count Estimator
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$school_id = $_SESSION['school_id'] ?? null;
if(!$school_id) die(json_encode(['success'=>false, 'count'=>0]));

$target = $_GET['target'] ?? 'all';

try {
    $count = 0;
    if ($target === 'staff' || $target === 'all') {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT u.phone) FROM staff_details sd JOIN users u ON sd.user_id = u.id WHERE sd.school_id = ? AND sd.status = 'active' AND u.phone IS NOT NULL AND u.phone != ''");
        $stmt->execute([$school_id]);
        $count += $stmt->fetchColumn();
    }
    
    if ($target === 'parents' || $target === 'all') {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT guardian_phone) FROM students WHERE school_id = ? AND guardian_phone IS NOT NULL AND guardian_phone != '' AND status = 'active'");
        $stmt->execute([$school_id]);
        $count += $stmt->fetchColumn();
    }

    echo json_encode(['success'=>true, 'count'=>$count]);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'count'=>0]);
}
