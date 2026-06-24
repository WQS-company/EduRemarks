<?php
// ajax/sa_update_school.php - Super Admin School Orchestrator
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

// Security Guard
if ($role !== 'super_admin') {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? '';

if (!$id) {
    die(json_encode(['success' => false, 'message' => 'No target institutional ID provided.']));
}

try {
    if ($action === 'status') {
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE schools SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true, 'message' => 'Institutional status updated successfully.']);
        exit;
    } 
    
    if ($action === 'reward') {
        $amount = intval($_POST['amount']);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE schools SET credits = credits + ? WHERE id = ?");
        $stmt->execute([$amount, $id]);
        
        // Log the manual reward
        $stmt = $pdo->prepare("INSERT INTO credit_logs (school_id, amount, activity) VALUES (?, ?, ?)");
        $stmt->execute([$id, $amount, 'Institutional Management Reward (Super Admin)']);

        // Dispatch notification
        $gift_message = "Congratulations! EduRemarks just dashed your institution " . number_format($amount) . " credits as a token of excellence.";
        try {
            $stmt = $pdo->prepare("INSERT INTO platform_notifications (school_id, message, type) VALUES (?, ?, 'gift')");
            $stmt->execute([$id, $gift_message]);
        } catch (Exception $notif_err) {
            // Silently fail notification if table missing
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Credits successfully dispatched to institution.']);
        exit;
    } 
    
    if ($action === 'features') {
        $features_raw = $_POST['features'] ?? [];
        $features = is_array($features_raw) ? implode(',', $features_raw) : '';
        $stmt = $pdo->prepare("UPDATE schools SET feature_access = ? WHERE id = ?");
        if ($stmt->execute([$features, $id])) {
            echo json_encode(['success' => true, 'message' => 'Institutional feature nodes synchronized.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to synchronize feature nodes.']);
        }
        exit;
    } 
    
    if ($action === 'delete') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM schools WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Institution successfully decommissioned from system.']);
        exit;
    } 

    if ($action === 'subscription') {
        $mode = $_POST['billing_mode']; 
        $type = $_POST['subscription_type'] ?? null;
        $start = $_POST['subscription_start'] ?? null;
        $end = $_POST['subscription_end'] ?? null;
        $price = floatval($_POST['subscription_price'] ?? 0);
        $active = intval($_POST['subscription_active'] ?? 0);

        $stmt = $pdo->prepare("UPDATE schools SET 
            billing_mode = ?, 
            subscription_type = ?, 
            subscription_start = ?, 
            subscription_end = ?, 
            subscription_price = ?, 
            subscription_active = ? 
            WHERE id = ?");
        
        if ($stmt->execute([$mode, $type, $start, $end, $price, $active, $id])) {
            $msg = ($mode === 'subscription' && $active) ? "Subscription billing activated for institution." : "Institution billing mode updated.";
            
            // Log if activated
            if ($mode === 'subscription' && $active) {
                $stmt = $pdo->prepare("INSERT INTO credit_logs (school_id, amount, activity) VALUES (?, ?, ?)");
                $stmt->execute([$id, 0, "Subscription Activated: $type (Until $end)"]);
                
                // Mark billing request as approved if exists
                $upd_req = $pdo->prepare("UPDATE billing_requests SET status = 'approved', approval_date = CURRENT_TIMESTAMP WHERE school_id = ? AND status = 'pending'");
                $upd_req->execute([$id]);

                // Notify school
                try {
                    $notif = "Your institutional billing request has been APPROVED. Your institution is now on a PERIOD-BASED mode ($type) until $end. You can now print your Official Billing Agreement.";
                    $stmt = $pdo->prepare("INSERT INTO platform_notifications (school_id, message, type) VALUES (?, ?, 'billing')");
                    $stmt->execute([$id, $notif]);
                } catch (Exception $e) {}
            }

            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update billing configuration.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid orchestration command.']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Critical Node Error: ' . $e->getMessage()]);
}
exit;
