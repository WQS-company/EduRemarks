<?php
// ajax/send_sms_campaign.php - Institutional Strategic Broadcaster
require_once '../includes/auth_check.php';
require_once '../includes/termii_helper.php';
header('Content-Type: application/json');

$school_id = $_SESSION['school_id'] ?? null;
if(!$school_id || !hasFeature('SMS_ALERTS')) {
    die(json_encode(['success'=>false, 'message'=>'Unauthorized transmission access or institutional feature disabled.']));
}

$subject      = trim($_POST['subject'] ?? '');
$message      = trim($_POST['message'] ?? '');
$target_group = $_POST['target_group'] ?? 'all';
$selected_nodes = $_POST['selected_nodes'] ?? [];

if(!$subject || !$message) {
    die(json_encode(['success'=>false, 'message'=>'Subject and message payload are required.']));
}

// 1. Gather Unique Recipients Nodes
$recipients = [];
try {
    if ($target_group === 'custom') {
        $recipients = array_unique($selected_nodes);
    } else {
        if ($target_group === 'staff' || $target_group === 'all') {
            $stmt = $pdo->prepare("SELECT DISTINCT u.phone FROM staff_details sd JOIN users u ON sd.user_id = u.id WHERE sd.school_id = ? AND sd.status = 'active' AND u.phone IS NOT NULL AND u.phone != ''");
            $stmt->execute([$school_id]);
            while($row = $stmt->fetchColumn()) $recipients[] = $row;
        }
        if ($target_group === 'parents' || $target_group === 'all') {
            $stmt = $pdo->prepare("SELECT DISTINCT guardian_phone FROM students WHERE school_id = ? AND guardian_phone IS NOT NULL AND guardian_phone != '' AND status = 'active'");
            $stmt->execute([$school_id]);
            while($row = $stmt->fetchColumn()) $recipients[] = $row;
        }
    }
    $recipients = array_unique($recipients);
    $recipient_count = count($recipients);

    if($recipient_count === 0) {
        die(json_encode(['success'=>false, 'message'=>'No valid recipient nodes discovered for this transmission.']));
    }

    // 2. Financial Orchestration via Global Pricing
    $cost_per_sms = getCreditRate('credit_per_sms', $pdo);
    if (!$cost_per_sms) $cost_per_sms = 10; // Fallback to institutional default
    
    $total_cost = $recipient_count * $cost_per_sms;

    if(!deductCredits($pdo, $school_id, $total_cost, "SMS Campaign Broadcast ($subject)")) {
        die(json_encode(['success'=>false, 'message'=>'Insufficient institutional credits for this transmission. Required: '.$total_cost]));
    }

    // 3. Digital Asset Dispatch via Termii Protocol
    $success_count = 0;
    $failed_count = 0;

    foreach($recipients as $number) {
        $res = send_termii_sms($number, $message);
        if($res['success']) $success_count++;
        else $failed_count++;
    }

    // 4. Log Institutional Campaign
    $stmt = $pdo->prepare("INSERT INTO sms_campaigns (school_id, sender_id, subject, message, recipients_count, total_credits, target_group, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $status = ($success_count > 0) ? 'sent' : 'failed';
    $stmt->execute([$school_id, $user_id, $subject, $message, $recipient_count, $total_cost, $target_group, $status]);

    echo json_encode([
        'success' => true, 
        'message' => "Institutional broadcast complete. Successfully reached $success_count nodes, $failed_count nodes failed.",
        'details' => ['success'=>$success_count, 'failed'=>$failed_count, 'credits'=>$total_cost]
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>'Orchestration Critical Failure: ' . $e->getMessage()]);
}
