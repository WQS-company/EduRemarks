<?php
// ajax/sa_save_hero_slide.php - Super Admin Hero Slide Control
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save') {
        $caption = $_POST['caption'] ?? 'New Slide';
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $image_path = $_POST['existing_image'] ?? '';

        // Handle Image Upload
        if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/hero/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_ext = strtolower(pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $new_filename = 'hero_' . time() . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['slide_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $image_path = 'uploads/hero/' . $new_filename;
                }
            }
        }

        if (!$image_path) {
            die(json_encode(['success' => false, 'message' => 'Visual asset is required.']));
        }
        
        $stmt = $pdo->prepare("INSERT INTO platform_hero_slides (caption, image_path, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$caption, $image_path, $sort_order]);
        echo json_encode(['success' => true, 'message' => 'Hero slide commissioned successfully.']);
    } else if ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM platform_hero_slides WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Hero slide decommissioned.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid orchestration command.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Node error: ' . $e->getMessage()]);
}
