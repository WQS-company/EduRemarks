<?php
// ajax/submit_billing_request.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Only institutional owners can initiate billing transitions.']));
}

$school_id = $active_school['id'] ?? null;
if (!$school_id) {
    die(json_encode(['success' => false, 'message' => 'No active institutional node selected.']));
}

$plan = $_POST['requested_plan'] ?? '';
$duration = $_POST['duration'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$plan || !$duration) {
    die(json_encode(['success' => false, 'message' => 'Plan and duration details are mandatory.']));
}

try {
    // Check for existing pending request
    $check = $pdo->prepare("SELECT id FROM billing_requests WHERE school_id = ? AND status = 'pending'");
    $check->execute([$school_id]);
    if ($check->fetch()) {
        die(json_encode(['success' => false, 'message' => 'You already have a pending billing transition request. Please await administrative review.']));
    }

    $stmt = $pdo->prepare("INSERT INTO billing_requests (school_id, requested_plan, duration, notes) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$school_id, $plan, $duration, $notes])) {
        // Dispatch Notification to Super Admin
        try {
            $notif_msg = "New Institutional Billing Request from " . ($active_school['school_name'] ?? 'Institution') . " for " . $plan;
            $notif_stmt = $pdo->prepare("INSERT INTO platform_notifications (school_id, message, type) VALUES (0, ?, 'billing_request')");
            $notif_stmt->execute([$notif_msg]);
        } catch (Exception $e) {
            // Silently fail if notifications table has issues
        }

        echo json_encode(['success' => true, 'message' => 'Your institutional billing request has been dispatched to the Super Admin. You will be notified upon review.']);
    } else {

        echo json_encode(['success' => false, 'message' => 'Failed to dispatch request. Please contact support.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Critical Protocol Error: ' . $e->getMessage()]);
}
?>
