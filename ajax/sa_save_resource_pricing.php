<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $settings = [
            'credit_student_result' => $_POST['credit_student_result'] ?? 1,
            'credit_answer_sheet' => $_POST['credit_answer_sheet'] ?? 10,
            'credit_cbt_test' => $_POST['credit_cbt_test'] ?? 1,
            'credit_cbt_exam' => $_POST['credit_cbt_exam'] ?? 2,
            'credit_per_sms' => $_POST['credit_per_sms'] ?? 10,
            'credit_cost_id_card' => $_POST['credit_cost_id_card'] ?? 10,
            'credit_admission_applicant' => $_POST['credit_admission_applicant'] ?? 5
        ];

        $stmt = $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Resource pricing synchronized across all institutional nodes.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Synchronization failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
