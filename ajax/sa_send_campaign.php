<?php
// ajax/sa_send_campaign.php - Super Admin Global Broadcast Dispatcher
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Broadcast Attempt']));
}

$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
$targets = $_POST['target_schools'] ?? []; // Array of School IDs

if (!$subject || !$message) {
    die(json_encode(['success' => false, 'message' => 'Campaign payload is incomplete.']));
}

// Convert target schools to comma-separated string (NULL if all)
$target_str = !empty($targets) ? implode(',', array_map('intval', $targets)) : null;

try {
    $stmt = $pdo->prepare("INSERT INTO platform_campaigns (subject, message, target_school_ids) VALUES (?, ?, ?)");
    $stmt->execute([$subject, $message, $target_str]);

    echo json_encode(['success' => true, 'message' => 'Campaign successfully broadcasted to the platform.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Broadcast Error: ' . $e->getMessage()]);
}
