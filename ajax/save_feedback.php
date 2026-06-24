<?php
// ajax/save_feedback.php - Institutional Experience Node
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$rating = intval($_POST['rating'] ?? 5);
$comments = trim($_POST['comments'] ?? '');
$activity_type = $_POST['activity_type'] ?? 'Platform Global';
$school_id = $_SESSION['school_id'] ?? null;

if(!$full_name || !$email) {
    die(json_encode(['success' => false, 'message' => 'Identity nodes required for feedback transmission.']));
}

try {
    $stmt = $pdo->prepare("INSERT INTO platform_feedback (school_id, user_id, full_name, email, activity_type, rating, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$school_id, $user_id, $full_name, $email, $activity_type, $rating, $comments]);
    
    echo json_encode(['success' => true, 'message' => 'Feedback node synchronized. Your insight helps us orchestrate better.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Transmission error: ' . $e->getMessage()]);
}
