<?php
// ajax/sa_estimate_sms.php - Global Reach Estimator
require_once '../super_admin/auth_check.php';
header('Content-Type: application/json');

$target = $_GET['target'] ?? 'all_owners';

try {
    $count = 0;
    if ($target === 'all_owners') {
        $count = $pdo->query("SELECT COUNT(DISTINCT owner_id) FROM schools")->fetchColumn();
    } elseif ($target === 'all_admins') {
        // Owners + Admins/Staff assigned to schools
        $count = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM (SELECT owner_id as user_id FROM schools UNION SELECT user_id FROM staff_details WHERE status = 'active') as all_nodes")->fetchColumn();
    } elseif ($target === 'low_credits') {
        $count = $pdo->query("SELECT COUNT(*) FROM schools WHERE credits < 100")->fetchColumn();
    }

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0]);
}
