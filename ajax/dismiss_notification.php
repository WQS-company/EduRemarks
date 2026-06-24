<?php
// ajax/dismiss_notification.php - Institutional Notification Orchestration
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? null;

if (!$id) {
    die(json_encode(['success' => false, 'message' => 'No target node provided.']));
}

try {
    // Verify ownership or role
    if ($role === 'owner') {
        $stmt = $pdo->prepare("UPDATE platform_notifications SET is_read = 1 WHERE id = ? AND school_id = ?");
        $stmt->execute([$id, $active_school_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE platform_notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Notification node decommissioned.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Orchestration error: ' . $e->getMessage()]);
}
