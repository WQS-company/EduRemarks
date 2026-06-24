<?php
// ajax/sa_send_sms.php - Global Platform Strategic Dispatcher
require_once '../super_admin/auth_check.php';
require_once '../includes/termii_helper.php';
header('Content-Type: application/json');

$subject        = trim($_POST['subject'] ?? '');
$message        = trim($_POST['message'] ?? '');
$target_group   = $_POST['target_group'] ?? '';
$selected_schools = $_POST['selected_schools'] ?? [];

if (!$subject || !$message) die(json_encode(['success' => false, 'message' => 'Broadcast payload incomplete.']));

try {
    $recipients = [];

    if ($target_group === 'all_owners') {
        $stmt = $pdo->query("SELECT DISTINCT u.phone FROM schools s JOIN users u ON s.owner_id = u.id WHERE u.phone != ''");
        while($row = $stmt->fetchColumn()) $recipients[] = $row;
    } elseif ($target_group === 'all_admins') {
        $stmt = $pdo->query("SELECT DISTINCT u.phone FROM (
            SELECT owner_id as uid FROM schools UNION SELECT user_id FROM staff_details WHERE status = 'active'
        ) as nodes JOIN users u ON nodes.uid = u.id WHERE u.phone != ''");
        while($row = $stmt->fetchColumn()) $recipients[] = $row;
    } elseif ($target_group === 'low_credits') {
        $stmt = $pdo->query("SELECT DISTINCT u.phone FROM schools s JOIN users u ON s.owner_id = u.id WHERE s.credits < 100 AND u.phone != ''");
        while($row = $stmt->fetchColumn()) $recipients[] = $row;
    } elseif ($target_group === 'custom') {
        if (empty($selected_schools)) die(json_encode(['success' => false, 'message' => 'No target institutional nodes selected.']));
        $placeholders = str_repeat('?,', count($selected_schools) - 1) . '?';
        $stmt = $pdo->prepare("SELECT DISTINCT u.phone FROM schools s JOIN users u ON s.owner_id = u.id WHERE s.id IN ($placeholders) AND u.phone != ''");
        $stmt->execute($selected_schools);
        while($row = $stmt->fetchColumn()) $recipients[] = $row;
    }

    $recipients = array_unique(array_filter($recipients));
    $recipient_count = count($recipients);

    if ($recipient_count === 0) die(json_encode(['success' => false, 'message' => 'Zero active communication nodes discovered for this target tier.']));

    // Economics via Global Pricing
    $cost_per_recipient = getCreditRate('credit_per_sms', $pdo);
    if (!$cost_per_recipient) $cost_per_recipient = 10;
    
    $total_credits = $recipient_count * $cost_per_recipient;

    // Dispatch
    $success = 0;
    foreach($recipients as $number) {
        $res = send_termii_sms($number, $message);
        if($res['success']) $success++;
    }

    // Log Global Campaign
    $stmt = $pdo->prepare("INSERT INTO sms_campaigns (school_id, sender_id, subject, message, recipients_count, total_credits, target_group, status) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)");
    $status = ($success > 0) ? 'sent' : 'failed';
    $stmt->execute([$user_id, $subject, $message, $recipient_count, $total_credits, $target_group, $status]);

    echo json_encode([
        'success' => true, 
        'message' => "Global dispatch complete. Successfully synchronized $success nodes nationwide.",
        'reach' => $recipient_count,
        'cost' => $total_credits
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Global Orchestration Error: ' . $e->getMessage()]);
}
