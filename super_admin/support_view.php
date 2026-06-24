<?php
// super_admin/support_view.php - Institutional Lifecycle Orchestrator
require_once 'auth_check.php';

$ticket_id = $_GET['id'] ?? null;
if (!$ticket_id) { header('Location: requests.php'); exit(); }

// Fetch Ticket + School Info
$stmt = $pdo->prepare("SELECT r.*, s.school_name, s.unique_id, u.full_name as owner_name, u.email as owner_email, u.profile_picture as owner_pic
                      FROM school_requests r 
                      JOIN schools s ON r.school_id = s.id 
                      JOIN users u ON s.owner_id = u.id
                      WHERE r.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $ticket_id ? $stmt->fetch() : null;

if (!$ticket) { header('Location: requests.php'); exit(); }

$msg_stmt = $pdo->prepare("SELECT m.*, u.full_name, u.profile_picture 
                            FROM support_messages m 
                            JOIN users u ON m.sender_id = u.id 
                            WHERE m.ticket_id = ? 
                            ORDER BY m.created_at ASC");
$msg_stmt->execute([$ticket_id]);
$messages = $msg_stmt->fetchAll();

// Mark messages from the institution as read by the Admin
$pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND sender_role != 'super_admin'")->execute([$ticket_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#TKT-<?php echo $ticket_id; ?> | Admin Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .text-blue { color: #1e3a8a; }
        .tiny-text { font-size: 0.75rem; }
        .fw-800 { font-weight: 800; }

        .chat-container { height: 500px; overflow-y: auto; padding: 20px; background: rgba(0,0,0,0.02); border-radius: 20px; }
        .bubble { max-width: 80%; padding: 12px 18px; border-radius: 18px; margin-bottom: 12px; }
        .bubble-in { background: #f1f5f9; border-bottom-left-radius: 2px; align-self: flex-start; }
        .bubble-out { background: #1e40af; color: white; border-bottom-right-radius: 2px; align-self: flex-end; margin-left: auto; }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="requests.php" class="btn btn-sm btn-light rounded-circle"><i class="fas fa-arrow-left"></i></a>
                <h4 class="fw-800 mb-0">Institutional Transmission Node</h4>
            </div>
            <p class="text-muted small">ID: <code class="fw-bold">#TKT-<?php echo $ticket_id; ?></code> &bull; Priority: <span class="text-uppercase fw-bold text-<?php echo ($ticket['priority'] == 'high') ? 'danger' : 'warning'; ?>"><?php echo $ticket['priority']; ?></span></p>
        </div>
        <div class="d-flex gap-2">
            <?php if($ticket['status'] !== 'in_progress'): ?>
            <button class="btn btn-sm btn-outline-info rounded-pill px-3" onclick="updateStatus('in_progress')">Mark In-Progress</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="updateStatus('closed')">Close Node</button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Chat Area -->
        <div class="col-lg-8">
            <div class="glass-card p-4 h-100 border-0 shadow-sm">
                <h6 class="fw-800 mb-4 border-bottom pb-3">Operational Thread</h6>
                
                <div class="chat-container d-flex flex-column" id="saChatThread">
                    <!-- Initial Support Request -->
                    <div class="bubble bubble-in shadow-sm border mb-4 w-100">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold small text-blue"><?php echo htmlspecialchars($ticket['owner_name']); ?></span>
                            <span class="tiny-text opacity-50"><?php echo date('M d, h:i A', strtotime($ticket['created_at'])); ?></span>
                        </div>
                        <div class="fw-800 mb-1"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                        <div class="small opacity-75"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></div>
                    </div>

                    <?php foreach($messages as $msg): 
                        $is_me = ($msg['sender_role'] == 'super_admin');
                    ?>
                    <div class="bubble <?php echo $is_me ? 'bubble-out shadow' : 'bubble-in border'; ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="tiny-text fw-bold <?php echo $is_me ? 'text-white-50' : 'text-muted'; ?>">
                                <?php echo $is_me ? 'AGENT RESPONSE' : htmlspecialchars($msg['full_name']); ?>
                            </span>
                        </div>
                        <div class="small"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                        <div class="text-end tiny-text mt-1 <?php echo $is_me ? 'text-white-50' : 'opacity-50'; ?>">
                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Logic -->
                <form id="saReplyForm" class="mt-4">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">
                    <div class="input-group">
                        <textarea name="message" class="form-control rounded-4 border-2" rows="2" placeholder="Dispatch response to institution..." required></textarea>
                        <button class="btn btn-primary rounded-4 px-4 ms-2" type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Meta Sidebar -->
        <div class="col-lg-4">
            <!-- Institutional Node Details -->
            <div class="glass-card p-4 mb-4 border-0 shadow-sm">
                <h6 class="fw-800 mb-3">Institutional Metadata</h6>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <img src="<?php echo $ticket['owner_pic'] ?: '../img/default_picture.png'; ?>" class="rounded-circle border" style="width: 55px; height: 55px; object-fit: cover;">
                    <div>
                        <h6 class="fw-800 mb-0"><?php echo htmlspecialchars($ticket['school_name']); ?></h6>
                        <div class="tiny-text text-muted"><?php echo $ticket['unique_id']; ?></div>
                    </div>
                </div>
                <div class="small mb-2">
                    <span class="text-muted fw-bold">ORCHESTRATOR:</span><br>
                    <?php echo htmlspecialchars($ticket['owner_name']); ?>
                </div>
                <div class="small mb-4">
                    <span class="text-muted fw-bold">EMAIL NODE:</span><br>
                    <?php echo htmlspecialchars($ticket['owner_email']); ?>
                </div>
                <div class="d-grid">
                    <a href="school_details.php?id=<?php echo $ticket['school_id']; ?>" class="btn btn-sm btn-light rounded-pill px-3">View Full Node Details</a>
                </div>
            </div>

            <!-- Internal Diagnostics -->
            <div class="glass-card p-4 border-0 shadow-sm bg-premium-blue text-white" style="background: #1e3a8a !important;">
                <h6 class="fw-bold mb-3">Diagnostic Status</h6>
                <ul class="list-unstyled mb-0 tiny-text">
                    <li class="mb-2"><i class="fas fa-circle text-success me-2"></i> Database Synchronization: OK</li>
                    <li class="mb-2"><i class="fas fa-circle text-success me-2"></i> Institutional Credits: ACTIVE</li>
                    <li><i class="fas fa-circle text-warning me-2"></i> Connection Latency: LOW</li>
                </ul>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Scroll to bottom
    const thread = document.getElementById('saChatThread');
    thread.scrollTop = thread.scrollHeight;

    $('#saReplyForm').on('submit', function(e) {
        e.preventDefault();
        $.post('../ajax/save_support_reply.php', $(this).serialize(), function(res) {
            if(res.success) location.reload(); else alert(res.message);
        }, 'json');
    });

    function updateStatus(status) {
        if(confirm('Verify status synchronization to: ' + status.toUpperCase())) {
            $.post('../ajax/sa_update_ticket.php', { id: <?php echo $ticket_id; ?>, status: status }, function(res) {
                if(res.success) location.reload(); else alert(res.message);
            }, 'json');
        }
    }

    $('#saReplyForm textarea').on('input', function() {
        const ticketId = <?php echo $ticket_id; ?>;
        fetch('../ajax/support_chat_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=typing&status=1&ticket_id=${ticketId}`
        });
    });
</script>
</body>
</html>
