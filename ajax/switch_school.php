<?php
// ajax/switch_school.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}

$user_id = $_SESSION['user_id'];
$school_id = sanitize($_POST['school_id']);

try {
    // Verify user has access to this school
    if ($_SESSION['role'] === 'owner') {
        $stmt = $pdo->prepare("SELECT id FROM schools WHERE id = ? AND owner_id = ?");
        $stmt->execute([$school_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT school_id FROM staff_details WHERE school_id = ? AND user_id = ?");
        $stmt->execute([$school_id, $user_id]);
    }

    if ($stmt->fetch()) {
        $_SESSION['school_id'] = $school_id;
        
        // Persist to database
        $update = $pdo->prepare("UPDATE users SET last_school_id = ? WHERE id = ?");
        $update->execute([$school_id, $user_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'You do not have access to this school.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
