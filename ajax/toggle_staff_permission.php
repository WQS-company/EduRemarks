<?php
// ajax/toggle_staff_permission.php — Admin-only endpoint to toggle staff permissions
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Only school owners can modify permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized. Only school administrators can modify staff permissions.']));
}

$staff_detail_id = intval($_POST['staff_detail_id'] ?? 0);
$permission      = sanitize($_POST['permission'] ?? '');
$enabled         = intval($_POST['enabled'] ?? 0);
$active_school_id = $_SESSION['school_id'] ?? null;

// Whitelist allowed permission columns to prevent SQL injection
$allowed_permissions = ['can_manage_students', 'can_manage_academics', 'can_manage_cbt', 'can_edit_history'];

if (!in_array($permission, $allowed_permissions)) {
    die(json_encode(['success' => false, 'message' => 'Invalid permission type.']));
}

if (!$staff_detail_id || !$active_school_id) {
    die(json_encode(['success' => false, 'message' => 'Missing required parameters.']));
}

try {
    // Verify: The staff detail belongs to a school owned by the current user
    $stmt = $pdo->prepare("
        SELECT sd.id 
        FROM staff_details sd 
        JOIN schools s ON sd.school_id = s.id 
        WHERE sd.id = ? AND s.owner_id = ? AND s.id = ?
    ");
    $stmt->execute([$staff_detail_id, $_SESSION['user_id'], $active_school_id]);
    
    if (!$stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Access denied. Staff member not found in your school.']));
    }

    // Update the permission
    $value = $enabled ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE staff_details SET {$permission} = ? WHERE id = ?");
    $stmt->execute([$value, $staff_detail_id]);

    $label = str_replace('_', ' ', str_replace('can_', '', $permission));
    $action = $value ? 'granted' : 'revoked';
    
    echo json_encode([
        'success' => true, 
        'message' => ucfirst($label) . " permission {$action} successfully."
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
