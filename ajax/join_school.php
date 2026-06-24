<?php
// ajax/join_school.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}

$user_id = $_SESSION['user_id'];
$school_id = sanitize($_POST['school_id']);
$unique_id = sanitize($_POST['unique_school_id']);

if (empty($school_id) || empty($unique_id)) {
    die(json_encode(['success' => false, 'message' => 'School selection and Unique ID are required.']));
}

try {
    // Verify School ID
    $stmt = $pdo->prepare("SELECT id FROM schools WHERE id = ? AND unique_id = ?");
    $stmt->execute([$school_id, $unique_id]);
    if (!$stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Incorrect School Unique ID.']));
    }

    // Check if already joined
    $stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id = ? AND school_id = ?");
    $stmt->execute([$user_id, $school_id]);
    if ($stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'You have already applied/joined this school.']));
    }

    // Join
    $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, school_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$user_id, $school_id]);

    echo json_encode(['success' => true, 'message' => 'Application sent! Please wait for admin approval.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
