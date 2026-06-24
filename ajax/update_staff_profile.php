<?php
// ajax/update_staff_profile.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

if ($role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
}

$id = sanitize($_POST['detail_id'] ?? '');
$full_name = sanitize($_POST['full_name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');

if (empty($id) || empty($full_name)) {
    die(json_encode(['success' => false, 'message' => 'ID and Full Name are required.']));
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT sd.user_id 
        FROM staff_details sd 
        JOIN schools s ON sd.school_id = s.id 
        WHERE sd.id = ? AND s.owner_id = ?
    ");
    $stmt->execute([$id, $user_id]);
    $staff = $stmt->fetch();
    
    if (!$staff) {
        die(json_encode(['success' => false, 'message' => 'Staff not found or access denied.']));
    }

    $target_user_id = $staff['user_id'];

    // Update user record
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $target_user_id]);

    echo json_encode(['success' => true, 'message' => 'Staff profile updated successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
