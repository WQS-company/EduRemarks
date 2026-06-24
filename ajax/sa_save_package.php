<?php
// ajax/sa_save_package.php - Super Admin Credit Package Orchestrator
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$id      = $_POST['id'] ?? null;
$name    = $_POST['name'] ?? '';
$credits = intval($_POST['credits'] ?? 0);
$price   = floatval($_POST['price'] ?? 0);
$delete  = isset($_POST['delete']);

try {
    if ($delete) {
        $stmt = $pdo->prepare("DELETE FROM pricing_packages WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Node decommissioned.']);
    } else if ($id) {
        $stmt = $pdo->prepare("UPDATE pricing_packages SET name=?, credits=?, price_naira=? WHERE id=?");
        $stmt->execute([$name, $credits, $price, $id]);
        echo json_encode(['success' => true, 'message' => 'Configuration updated.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pricing_packages (name, credits, price_naira) VALUES (?, ?, ?)");
        $stmt->execute([$name, $credits, $price]);
        echo json_encode(['success' => true, 'message' => 'New monetization node added.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Execution Error: ' . $e->getMessage()]);
}
