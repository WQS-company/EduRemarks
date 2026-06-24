<?php
// ajax/sa_save_settings.php - Super Admin Settings & Asset Orchestrator
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$data = $_POST;
$files = $_FILES;

try {
    $pdo->beginTransaction();

    // Handle File Uploads first
    foreach ($files as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/platform/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'set_' . $key . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                    $asset_path = 'uploads/platform/' . $new_filename;
                    // Save path to settings
                    $data[$key] = $asset_path;
                }
            }
        }
    }

    // Process all settings (including file paths)
    foreach ($data as $key => $value) {
        if ($key === 'csrf_token') continue;
        
        $stmt = $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Institutional settings and assets synchronized.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Synchronization Error: ' . $e->getMessage()]);
}
