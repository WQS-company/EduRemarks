<?php
// ajax/client_update_ticket.php - Institutional Lifecycle Orchestrator
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$id     = $_POST['id'] ?? null;
$action = $_POST['action'] ?? ''; // archive, delete, resolve

if (!$id || !$action) {
    die(json_encode(['success' => false, 'message' => 'Action parameters are incomplete.']));
}

try {
    // Verify Ownership
    $check = $pdo->prepare("SELECT school_id FROM school_requests WHERE id = ?");
    $check->execute([$id]);
    $ticket = $check->fetch();

    if (!$ticket || $ticket['school_id'] != $_SESSION['school_id']) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized orchestration attempt.']));
    }

    if ($action === 'archive') {
        $stmt = $pdo->prepare("UPDATE school_requests SET archived_by_school = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $msg = 'Support node successfully archived.';
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE school_requests SET deleted_by_school = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $msg = 'Support record decommissioned successfully.';
    } elseif ($action === 'resolve') {
        $stmt = $pdo->prepare("UPDATE school_requests SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Inject system message
        $sys_msg = "PLATFORM ORCHESTRATOR: Institutional client has synchronized this node as [RESOLVED]. Thread closing.";
        $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, 'owner', ?)")
            ->execute([$id, $user_id, $sys_msg]);
            
        $msg = 'Support stream successfully synchronized as RESOLVED. Experience captured.';
    } else {
        die(json_encode(['success' => false, 'message' => 'Invalid action node.']));
    }

    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Orchestration Error: ' . $e->getMessage()]);
}
