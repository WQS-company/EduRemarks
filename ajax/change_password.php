<?php
// ajax/change_password.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    die(json_encode(['success' => false, 'message' => 'All password fields are required.']));
}

if ($new_password !== $confirm_password) {
    die(json_encode(['success' => false, 'message' => 'New passwords do not match.']));
}

if (strlen($new_password) < 6) {
    die(json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']));
}

try {
    // Get current hashed password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hashed_password = $stmt->fetchColumn();

    if (!password_verify($current_password, $hashed_password)) {
        die(json_encode(['success' => false, 'message' => 'Incorrect current password.']));
    }

    // Hash and update
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$new_hashed_password, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
