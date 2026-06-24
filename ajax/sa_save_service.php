<?php
// ajax/sa_save_service.php - Super Admin Feature Node Controller
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$id      = $_POST['id'] ?? null;
$icon    = $_POST['icon'] ?? 'fas fa-cube';
$title   = $_POST['title'] ?? '';
$desc    = $_POST['description'] ?? '';
$delete  = isset($_POST['delete']);

try {
    if ($delete) {
        $stmt = $pdo->prepare("DELETE FROM platform_services WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Feature node decommissioned.']);
    } else if ($id) {
        $stmt = $pdo->prepare("UPDATE platform_services SET icon=?, title=?, description=? WHERE id=?");
        $stmt->execute([$icon, $title, $desc, $id]);
        echo json_encode(['success' => true, 'message' => 'Feature node configuration updated.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO platform_services (icon, title, description) VALUES (?, ?, ?)");
        $stmt->execute([$icon, $title, $desc]);
        echo json_encode(['success' => true, 'message' => 'New feature node instantiated on the platform.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Execution Error: ' . $e->getMessage()]);
}
