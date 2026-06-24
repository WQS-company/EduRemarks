<?php
// ajax/update_user_profile.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

$full_name = sanitize($_POST['full_name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');

if (empty($full_name)) {
    die(json_encode(['success' => false, 'message' => 'Full name is required.']));
}

try {
    $profile_picture = null;

    // Handle File Upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed_exts)) {
            die(json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and WEBP allowed.']));
        }

        $new_file_name = "profile_" . $user_id . "_" . time() . "." . $file_ext;
        $upload_dir = "../uploads/users_profile_pictures/";
        
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $dest_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $dest_path)) {
            $profile_picture = "uploads/users_profile_pictures/" . $new_file_name;

            // Delete old picture if exists
            $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_pic = $stmt->fetchColumn();
            if ($old_pic && file_exists("../" . $old_pic)) {
                unlink("../" . $old_pic);
            }
        }
    }

    // Update User Info
    if ($profile_picture) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, profile_picture = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $profile_picture, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$full_name, $phone, $user_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Personal profile updated successfully!', 'profile_picture' => $profile_picture]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
