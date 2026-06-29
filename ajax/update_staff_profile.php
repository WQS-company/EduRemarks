<?php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$school_id = $_SESSION['school_id'];
$detail_id = isset($_POST['staff_detail_id']) ? (int)$_POST['staff_detail_id'] : 0;
$full_name = sanitize($_POST['full_name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');

if (!$detail_id || !$full_name) {
    die(json_encode(['success' => false, 'message' => 'Invalid parameters. Please provide name.']));
}

try {
    $stmt = $pdo->prepare("UPDATE users u JOIN staff_details sd ON u.id = sd.user_id SET u.full_name = ?, u.phone = ? WHERE sd.id = ? AND sd.school_id = ?");
    $stmt->execute([$full_name, $phone, $detail_id, $school_id]);
    echo json_encode(['success' => true, 'message' => 'Staff profile updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
