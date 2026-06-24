<?php
// ajax/save_support_ticket.php - Institutional Support Dispatcher
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$school_id = $_SESSION['school_id'] ?? null;
$subject   = trim($_POST['subject'] ?? '');
$message   = trim($_POST['message'] ?? '');
$category  = $_POST['category'] ?? 'general';
$priority  = $_POST['priority'] ?? 'medium';

if(!$subject || !$message) {
    die(json_encode(['success'=>false, 'message'=>'Subject and details are required for transmission.']));
}

try {
    $stmt = $pdo->prepare("INSERT INTO school_requests (school_id, user_id, subject, message, status, priority, category) VALUES (?, ?, ?, ?, 'open', ?, ?)");
    $stmt->execute([$school_id, $user_id, $subject, $message, $priority, $category]);
    
    echo json_encode(['success'=>true, 'message'=>'Support stream initialized. Our specialists will review your transmission locally.']);
} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>'Transmission error: ' . $e->getMessage()]);
}
