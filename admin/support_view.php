<?php
// admin/support_view.php - Technical Thread Orchestrator
require_once '../includes/auth_check.php';

$ticket_id = $_GET['id'] ?? null;
if (!$ticket_id) { header('Location: support.php'); exit(); }

// Access verification - ensures owner can only see their school's tickets
$stmt = $pdo->prepare("SELECT r.*, s.school_name FROM school_requests r JOIN schools s ON r.school_id = s.id WHERE r.id = ? AND r.school_id = ?");
$stmt->execute([$ticket_id, $_SESSION['school_id']]);
$ticket = $stmt->fetch();

if (!$ticket) { header('Location: support.php'); exit(); }

// Fetch Conversation Thread
$msg_stmt = $pdo->prepare("SELECT m.*, u.full_name, u.profile_picture 
                            FROM support_messages m 
                            JOIN users u ON m.sender_id = u.id 
                            WHERE m.ticket_id = ? 
                            ORDER BY m.created_at ASC");
$msg_stmt->execute([$ticket_id]);
$messages = $msg_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#TKT-<?php echo $ticket_id; ?> | Support Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .chat-bubble { max-width: 85%; border-radius: 20px; padding: 15px 20px; margin-bottom: 20px; position: relative; }
        .bubble-owner { background: #f1f5f9; margin-right: auto; border-bottom-left-radius: 4px; border: 1px solid #e2e8f0; }
        .bubble-staff { background: #eff6ff; margin-left: auto; border-bottom-right-radius: 4px; border: 1px solid #dbeafe; }
        .bubble-sa { background: #fefce8; margin-left: auto; border-bottom-right-radius: 4px; border: 1px solid #fef08a; }
        
        .avatar-chat { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .status-badge { font-size: 0.65rem; padding: 4px 12px; }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="support.php" class="btn btn-light rounded-pill px-3 py-2 small fw-bold">
                    <i class="fas fa-arrow-left me-2"></i>Back to Hub
                </a>
                <div class="d-flex gap-2">
                    <span class="badge bg-white text-dark border px-3 py-2 rounded-pill small fw-normal">ID: #TKT-<?php echo $ticket_id; ?></span>
                    <span class="badge bg-soft-primary text-primary border px-3 py-2 rounded-pill small fw-bold text-uppercase"><?php echo $ticket['status']; ?></span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-12">
                    <div class="glass-card p-0 overflow-hidden border-0 shadow-sm">
                        <!-- Header -->
                        <div class="p-4 bg-white border-bottom">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="fw-800 mb-1"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                    <div class="d-flex align-items-center gap-3 tiny-text opacity-75">
                                        <span><i class="fas fa-folder me-1"></i> <?php echo strtoupper($ticket['category']); ?></span>
                                        <span><i class="fas fa-flag me-1"></i> <?php echo strtoupper($ticket['priority']); ?> PRIORITY</span>
                                        <span><i class="fas fa-clock me-1"></i> Opened <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <?php if($ticket['status'] !== 'resolved'): ?>
                                    <button class="btn btn-outline-success btn-sm rounded-pill px-4" onclick="resolveTicket()">Mark as Resolved</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Thread -->
                        <div class="p-4 bg-light bg-opacity-50" style="min-height: 400px; max-height: 600px; overflow-y: auto;" id="chatThread">
                            <!-- Initial Message -->
                            <div class="chat-bubble bubble-owner reveal-fade-up">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="fw-800 small">Educational Node (Me)</div>
                                    <div class="tiny-text opacity-50"><?php echo date('h:i A', strtotime($ticket['created_at'])); ?></div>
                                </div>
                                <div class="small lh-base"><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></div>
                            </div>

                            <?php foreach($messages as $msg): 
                                $is_system = (strpos($msg['message'], 'PLATFORM ORCHESTRATOR') !== false);
                                $bubble_class = $is_system ? 'bubble-system bg-white border-dashed text-center w-100 mx-0 opacity-75' : (($msg['sender_role'] == 'super_admin') ? 'bubble-sa' : 'bubble-owner');
                                $sender_label = ($msg['sender_role'] == 'super_admin') ? 'Orchestration Expert' : 'Institutional Admin';
                            ?>
                            <?php if($is_system): ?>
                                <div class="py-3 text-center mb-4 reveal-fade-up">
                                    <span class="badge bg-light text-muted border px-4 py-2 rounded-pill small fw-bold">
                                        <i class="fas fa-robot me-2"></i> <?php echo htmlspecialchars($msg['message']); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                            <div class="chat-bubble <?php echo $bubble_class; ?> reveal-fade-up">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php if($msg['sender_role'] == 'super_admin'): ?>
                                        <img src="../img/logo.png" class="avatar-chat border p-1 bg-white" style="object-fit: contain;">
                                        <div>
                                            <div class="fw-800 tiny-text">EduRemarks</div>
                                            <div class="tiny-text fw-bold text-muted">Official Support &bull; <?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo $msg['profile_picture'] ?: '../img/default_picture.png'; ?>" class="avatar-chat border">
                                        <div>
                                            <div class="fw-800 tiny-text"><?php echo htmlspecialchars($msg['full_name']); ?></div>
                                            <div class="tiny-text fw-bold text-muted"><?php echo $sender_label; ?> &bull; <?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="small lh-base"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                
                                <?php if($msg['file_path']): ?>
                                    <div class="mt-3">
                                        <?php if($msg['attachment_type'] === 'image'): ?>
                                            <div class="position-relative d-inline-block">
                                                <img src="../<?php echo $msg['file_path']; ?>" class="img-fluid rounded-4 shadow-sm border" style="max-width: 100%; max-height: 300px; cursor: pointer;" onclick="window.open('../<?php echo $msg['file_path']; ?>')">
                                                <div class="position-absolute bottom-0 end-0 p-2">
                                                    <span class="badge bg-dark bg-opacity-50 blur-sm rounded-pill tiny-text"><i class="fas fa-expand me-1"></i> Preview</span>
                                                </div>
                                            </div>
                                        <?php elseif($msg['attachment_type'] === 'video'): ?>
                                            <video src="../<?php echo $msg['file_path']; ?>" controls class="rounded-4 shadow-sm border" style="max-width: 100%; max-height: 300px;"></video>
                                        <?php else: ?>
                                            <a href="../<?php echo $msg['file_path']; ?>" target="_blank" class="btn btn-white border shadow-sm rounded-4 p-3 d-flex align-items-center gap-3 text-decoration-none text-dark hover-translate-y">
                                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                                                    <i class="fas fa-file-pdf fa-lg"></i>
                                                </div>
                                                <div class="text-start">
                                                    <div class="fw-800 tiny-text">Shared Document</div>
                                                    <div class="extra-small opacity-50">Click to view/download</div>
                                                </div>
                                                <i class="fas fa-arrow-right ms-auto opacity-25"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Reply Input -->
                        <?php if($ticket['status'] !== 'closed'): ?>
                        <div class="p-4 bg-white border-top">
                            <form id="replyForm">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                <div class="position-relative">
                                    <textarea name="message" class="form-control rounded-4 border-2 p-3 pb-5" rows="3" placeholder="Compose your transmission..." required></textarea>
                                    
                                    <!-- Attachment Preview Slot -->
                                    <div id="filePreview" class="p-2 border-top d-none align-items-center gap-2 bg-light">
                                        <div id="previewIcon" class="bg-primary bg-opacity-10 text-primary rounded px-2 small fw-bold"></div>
                                        <div id="previewName" class="extra-small text-truncate" style="max-width: 200px;"></div>
                                        <button type="button" class="btn btn-sm text-danger p-0 ms-auto" onclick="clearFile()"><i class="fas fa-times-circle"></i></button>
                                    </div>

                                    <div class="position-absolute bottom-0 start-0 p-2 d-flex gap-2">
                                        <label class="btn btn-light btn-sm rounded-pill px-3 cursor-pointer mb-0">
                                            <i class="fas fa-paperclip me-1 text-muted"></i> Attach File
                                            <input type="file" name="attachment" id="ticketFile" hidden onchange="handleFile(this)">
                                        </label>
                                    </div>

                                    <div class="position-absolute bottom-0 end-0 p-2">
                                        <button type="submit" class="btn btn-premium-blue rounded-pill px-4 shadow-sm">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </div>
                                </div>
                                <div class="tiny-text text-muted mt-2 ps-2">
                                    <i class="fas fa-info-circle me-1"></i> Support agents are notified instantly upon your dispatch.
                                </div>
                            </form>
                        </div>
                        <?php else: ?>
                        <div class="p-4 bg-light text-center">
                            <div class="text-muted small fw-bold">This support node has been decommissioned (CLOSED).</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to bottom
        const thread = document.getElementById('chatThread');
        thread.scrollTop = thread.scrollHeight;

        $('#replyForm').on('submit', function(e) {
            e.preventDefault();
            const msg = $(this).find('textarea').val();
            const file = document.getElementById('ticketFile').files[0];
            if(!msg.trim() && !file) return;

            const formData = new FormData(this);
            Spinner.show('Transmitting message...');
            
            $.ajax({
                url: '../ajax/save_support_reply.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    Spinner.hide();
                    if(res.success) location.reload(); else alert(res.message);
                },
                error: function() {
                    Spinner.hide();
                    alert('Communication failure.');
                }
            });
        });

        function handleFile(input) {
            if(input.files && input.files[0]) {
                const file = input.files[0];
                const preview = document.getElementById('filePreview');
                const name = document.getElementById('previewName');
                const icon = document.getElementById('previewIcon');
                
                name.innerText = file.name;
                icon.innerText = file.type.split('/')[0].toUpperCase();
                preview.classList.remove('d-none');
                preview.classList.add('d-flex');
            }
        }

        function clearFile() {
            document.getElementById('ticketFile').value = '';
            document.getElementById('filePreview').classList.add('d-none');
            document.getElementById('filePreview').classList.remove('d-flex');
        }

        function resolveTicket() {
            if(confirm('Confirm Institutional Satisfaction: Synchronize this node as RESOLVED? This requires active feedback on the service rendered.')) {
                EduRemarks.showFeedback('support_quality', 'Support Node #TKT-<?php echo $ticket_id; ?>', function(feedbackRes){
                    Spinner.show('Closing node based on institutional satisfaction...');
                    $.post('../ajax/client_update_ticket.php', { id: <?php echo $ticket_id; ?>, action: 'resolve' }, function(res) {
                        Spinner.hide();
                        if(res.success) {
                            alert('Resolution Synchronized. Thank you for your partnership!');
                            location.reload();
                        } else alert(res.message);
                    }, 'json');
                });
            }
        }
    </script>
</body>
</html>
