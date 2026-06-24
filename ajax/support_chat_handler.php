<?php
// ajax/support_chat_handler.php - Enhanced Backend Live Support Synchronization Node
require_once dirname(__DIR__) . '/includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access Node.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$school_id = $_SESSION['last_school_id'] ?? 0;

$action = $_REQUEST['action'] ?? '';

// Update User Activity status
$pdo->prepare("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user_id]);

try {
    switch ($action) {
        case 'send':
            $message = trim($_POST['message'] ?? '');
            $ticket_id = $_POST['ticket_id'] ?? null;
            $file_path = null;
            $attachment_type = null;

            // Handle File Upload
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/support_files/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $orig_name = $_FILES['attachment']['name'];
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                $new_name = 'support_' . $user_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
                    $file_path = 'uploads/support_files/' . $new_name;
                    
                    // Determine Type
                    $img_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    $vid_exts = ['mp4', 'mov', 'webm'];
                    
                    if (in_array($ext, $img_exts)) $attachment_type = 'image';
                    elseif (in_array($ext, $vid_exts)) $attachment_type = 'video';
                    else $attachment_type = 'document';
                }
            }

            if (empty($message) && empty($file_path)) {
                exit(json_encode(['success' => false, 'message' => 'Transmission empty.']));
            }

            // Get/Create Ticket
            if (!$ticket_id) {
                $stmt = $pdo->prepare("SELECT id FROM school_requests WHERE user_id = ? AND status IN ('open', 'in_progress') ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$user_id]);
                $ticket_id = $stmt->fetchColumn();

                if (!$ticket_id) {
                    $insert = $pdo->prepare("INSERT INTO school_requests (school_id, user_id, subject, message, status, priority, category) VALUES (?, ?, ?, ?, 'open', 'medium', 'Live Support')");
                    $insert->execute([$school_id, $user_id, 'Live Support Session', 'System-initiated session via Live Chat.']);
                    $ticket_id = $pdo->lastInsertId();
                }
            }

            // Insert Message Node
            $stmt = $pdo->prepare("INSERT INTO support_messages (ticket_id, sender_id, sender_role, message, file_path, attachment_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ticket_id, $user_id, $role, $message, $file_path, $attachment_type]);
            $msg_id = $pdo->lastInsertId();

            echo json_encode([
                'success' => true, 
                'ticket_id' => $ticket_id, 
                'message_id' => $msg_id,
                'file_path' => $file_path,
                'attachment_type' => $attachment_type
            ]);
            break;

        case 'history':
            $stmt = $pdo->prepare("SELECT id FROM school_requests WHERE user_id = ? AND status IN ('open', 'in_progress') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $ticket_id = $stmt->fetchColumn();

            $messages = [];
            if ($ticket_id) {
                // Mark incoming as read
                $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_role != ?")->execute([$ticket_id, $role]);
                
                $stmt = $pdo->prepare("SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC LIMIT 50");
                $stmt->execute([$ticket_id]);
                $messages = $stmt->fetchAll();
            }

            echo json_encode(['success' => true, 'messages' => $messages, 'ticket_id' => $ticket_id]);
            break;

        case 'poll':
            $last_id = (int)($_GET['last_id'] ?? 0);
            $ticket_id = (int)($_GET['ticket_id'] ?? 0);
            
            if (!$ticket_id) {
                echo json_encode(['success' => true, 'messages' => [], 'is_typing' => false, 'admin_online' => false]);
                break;
            }

            // New Messages from OTHERS
            $stmt = $pdo->prepare("SELECT * FROM support_messages WHERE ticket_id = ? AND id > ? AND sender_id != ? ORDER BY created_at ASC");
            $stmt->execute([$ticket_id, $last_id, $user_id]);
            $new_msgs = $stmt->fetchAll();

            if (!empty($new_msgs)) {
                $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_id != ?")->execute([$ticket_id, $user_id]);
            }

            // Typing Status (Other users in this ticket)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_typing t JOIN users u ON t.user_id = u.id WHERE t.ticket_id = ? AND t.user_id != ? AND t.last_typed > (CURRENT_TIMESTAMP - INTERVAL 5 SECOND)");
            $stmt->execute([$ticket_id, $user_id]);
            $is_typing = $stmt->fetchColumn() > 0;

            // Admin Online Status
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND last_active > (CURRENT_TIMESTAMP - INTERVAL 2 MINUTE)");
            $admin_online = $stmt->fetchColumn() > 0;

            echo json_encode([
                'success' => true, 
                'messages' => $new_msgs, 
                'is_typing' => $is_typing,
                'admin_online' => $admin_online
            ]);
            break;

        case 'typing':
            $status = (int)($_POST['status'] ?? 0);
            $ticket_id = (int)($_POST['ticket_id'] ?? 0);
            if (!$ticket_id) break;

            if ($status) {
                $pdo->prepare("INSERT INTO chat_typing (user_id, ticket_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_typed = CURRENT_TIMESTAMP")->execute([$user_id, $ticket_id]);
            } else {
                $pdo->prepare("DELETE FROM chat_typing WHERE user_id = ? AND ticket_id = ?")->execute([$user_id, $ticket_id]);
            }
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid Command Node.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
