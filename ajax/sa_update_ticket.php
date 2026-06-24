<?php
// ajax/sa_update_ticket.php - Super Admin LifeCycle Support Controller
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$id      = $_POST['id'] ?? null;
$status  = $_POST['status'] ?? 'open';
$delete  = isset($_POST['delete']);

if (!$id) {
    die(json_encode(['success' => false, 'message' => 'Support Node ID missing.']));
}

try {
    if ($delete) {
        $stmt = $pdo->prepare("DELETE FROM school_requests WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Support node successfully decommissioned.']);
    } else {
        $stmt = $pdo->prepare("UPDATE school_requests SET status = ?, resolved_at = ? WHERE id = ?");
        $resolved_at = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$status, $resolved_at, $id]);

        // Inject System Notification into Thread
        $notif_msg = "PLATFORM ORCHESTRATOR: Target node state synchronized to [" . strtoupper($status) . "].";
        if($status === 'resolved') $notif_msg .= " Mission accomplished.";
        
        $msg_stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, 'super_admin', ?)");
        $msg_stmt->execute([$id, $user_id, $notif_msg]);

        echo json_encode(['success' => true, 'message' => 'Institutional request state synchronized.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Support Orchestration Error: ' . $e->getMessage()]);
}
