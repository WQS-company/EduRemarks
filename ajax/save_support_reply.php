<?php
// ajax/save_support_reply.php - Multi-Node Communication Dispatcher
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$ticket_id = $_POST['ticket_id'] ?? null;
$message   = trim($_POST['message'] ?? '');

if (!$ticket_id) {
    die(json_encode(['success' => false, 'message' => 'Target support node ID is required.']));
}

try {
    // Verify Access - Check if user belongs to the school that owns the ticket OR is Super Admin
    $check_stmt = $pdo->prepare("SELECT school_id FROM school_requests WHERE id = ?");
    $check_stmt->execute([$ticket_id]);
    $ticket = $check_stmt->fetch();

    if (!$ticket) {
        die(json_encode(['success' => false, 'message' => 'Target support node does not exist.']));
    }

    $is_authorized = false;
    if ($role === 'super_admin') {
        $is_authorized = true;
    } else {
        // Must be school owner or staff with school_id match
        if (isset($_SESSION['school_id']) && $_SESSION['school_id'] == $ticket['school_id']) {
            $is_authorized = true;
        }
    }

    if (!$is_authorized) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized transmission attempt. Access denied.']));
    }

    // Handle File Upload
    $file_path = null;
    $attachment_type = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/support_files/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $orig_name = $_FILES['attachment']['name'];
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $new_name = 'reply_' . $user_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
            $file_path = 'uploads/support_files/' . $new_name;
            
            $img_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $vid_exts = ['mp4', 'mov', 'webm'];
            
            if (in_array($ext, $img_exts)) $attachment_type = 'image';
            elseif (in_array($ext, $vid_exts)) $attachment_type = 'video';
            else $attachment_type = 'document';
        }
    }

    if (!$message && !$file_path) {
        die(json_encode(['success' => false, 'message' => 'Transmission payload is empty.']));
    }

    // Insert Reply
    $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_role, message, file_path, attachment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ticket_id, $user_id, $role, $message, $file_path, $attachment_type]);

    // Update ticket status based on sender role to ensure visibility
    if($role === 'super_admin') {
        // SA replied: marker it in_progress if it was just open
        $update = $pdo->prepare("UPDATE school_requests SET status = 'in_progress' WHERE id = ? AND status = 'open'");
        $update->execute([$ticket_id]);
    } else {
        // Institution replied: reset to 'open' to alert SA
        $update = $pdo->prepare("UPDATE school_requests SET status = 'open' WHERE id = ?");
        $update->execute([$ticket_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Message successfully dispatched into the orchestration stream.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Transmission Orchestration Error: ' . $e->getMessage()]);
}
