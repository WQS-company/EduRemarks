<?php
// ajax/add_school.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized. Only school owners can add schools.']));
}

$user_id = $_SESSION['user_id'];
$school_name = sanitize($_POST['school_name']);
$school_type = sanitize($_POST['school_type']);
$school_address = sanitize($_POST['school_address']);

if (empty($school_name) || empty($school_type) || empty($school_address)) {
    die(json_encode(['success' => false, 'message' => 'All fields are required.']));
}

try {
    $unique_id = generateSchoolID($pdo);
    
    $stmt = $pdo->prepare("INSERT INTO schools (unique_id, school_name, school_type, school_address, owner_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$unique_id, $school_name, $school_type, $school_address, $user_id]);
    
    $new_school_id = $pdo->lastInsertId();
    $_SESSION['school_id'] = $new_school_id; // Switch to the new school automatically

    echo json_encode(['success' => true, 'message' => 'School added successfully! ID: ' . $unique_id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
