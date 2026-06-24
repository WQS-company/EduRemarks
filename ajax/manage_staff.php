<?php
// ajax/manage_staff.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}

$staff_detail_id = sanitize($_POST['staff_detail_id']);
$action = sanitize($_POST['action']); // 'approve' or 'reject'
$active_school_id = $_SESSION['school_id'];

if (!in_array($action, ['approve', 'reject', 'suspend', 'activate', 'delete'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid action.']));
}

try {
    // Ensure the staff member is actually applying for one of the owner's schools
    $stmt = $pdo->prepare("
        SELECT sd.id 
        FROM staff_details sd 
        JOIN schools s ON sd.school_id = s.id 
        WHERE sd.id = ? AND s.owner_id = ? AND s.id = ?
    ");
    $stmt->execute([$staff_detail_id, $_SESSION['user_id'], $active_school_id]);
    
    if (!$stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Access denied or staff not found.']));
    }

    if ($action === 'approve' || $action === 'activate') {
        $stmt = $pdo->prepare("UPDATE staff_details SET status = 'active' WHERE id = ?");
        $stmt->execute([$staff_detail_id]);
        $message = "Staff member " . ($action === 'approve' ? "approved" : "activated") . " successfully.";
    } elseif ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE staff_details SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$staff_detail_id]);
        $message = "Staff access suspended.";
    } elseif ($action === 'reject' || $action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM staff_details WHERE id = ?");
        $stmt->execute([$staff_detail_id]);
        $message = $action === 'reject' ? "Staff request rejected." : "Staff record removed.";
    }

    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
