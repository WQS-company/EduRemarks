<?php
// ajax/update_sa_profile.php - Super Admin Profile Sync
require_once dirname(__DIR__) . '/includes/auth_check.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Institutional access denied.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($full_name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Incomplete data nodes detected.']);
    exit();
}

try {
    // 1. Check for Duplicate Email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Identity conflict: Email already registered.']);
        exit();
    }

    // 2. Handle Profile Picture Upload
    $profile_picture_sql = "";
    $params = [$full_name, $email, $phone];
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid media format. Use JPG, PNG or WEBP.']);
            exit();
        }

        $upload_dir = '../uploads/profiles/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = 'super_admin_' . $user_id . '_' . time() . '.' . $ext;
        $target_path = $upload_dir . $filename;
        $db_path = 'uploads/profiles/' . $filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
            $profile_picture_sql = ", profile_picture = ?";
            $params[] = $db_path;
            $_SESSION['profile_picture'] = '../' . $db_path; // Updated session preview
        }
    }

    // 3. Handle Password Update
    $password_sql = "";
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'Secret Handshake Mismatch: Passwords do not match.']);
            exit();
        }
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Secret Key too short (min 6 chars).']);
            exit();
        }
        $password_sql = ", password = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }

    // 4. Update Protocol
    $params[] = $user_id;
    $sql = "UPDATE users SET full_name = ?, email = ?, phone = ? $profile_picture_sql $password_sql WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute($params)) {
        $_SESSION['user_full_name'] = $full_name; // Sync session name
        echo json_encode(['success' => true, 'message' => 'Institutional identity nodes successfully synchronized.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Node update failure. Transmission error.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
