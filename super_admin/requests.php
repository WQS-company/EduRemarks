<?php
// super_admin/requests.php - Institutional Support Command
// Fixed include path and standardized layout
require_once 'auth_check.php';

// Fetch all requests with defensive check
try {
    $stmt = $pdo->query("SELECT r.*, s.school_name, u.full_name as owner_name,
                          (SELECT COUNT(*) FROM support_messages WHERE ticket_id = r.id AND is_read = 0 AND sender_role != 'super_admin') as unread_count
                          FROM school_requests r 
                          JOIN schools s ON r.school_id = s.id 
                          JOIN users u ON s.owner_id = u.id
                          ORDER BY CASE WHEN r.status='open' THEN 1 WHEN r.status='in_progress' THEN 2 ELSE 3 END, r.created_at DESC");
    $requests = $stmt->fetchAll();
} catch (Exception $e) {
    $requests = [];
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .ticket-card { border-radius: 15px; border: 1px solid rgba(0,0,0,0.05); transition: 0.3s; background: #fff; margin-bottom: 20px; }
        .ticket-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .ticket-card.open { border-left: 5px solid #ef4444; }
        .ticket-card.in_progress { border-left: 5px solid #3b82f6; }
        .ticket-card.resolved { border-left: 5px solid #10b981; opacity: 0.8; }
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
        .tiny-text { font-size: 0.75rem; }
        .fw-800 { font-weight: 800; }
        .text-blue { color: #1e3a8a; }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h4 class="fw-800 mb-0">Institutional Support Hub</h4>
            <p class="text-muted small">Monitor and resolve critical school environment issues</p>
        </div>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> Support node communication error: <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if (empty($requests)): ?>
            <div class="col-12 text-center py-5">
                <div class="opacity-25 mb-3"><i class="fas fa-check-circle fa-4x text-success"></i></div>
                <h5 class="text-muted">All clear! No pending requests.</h5>
                <p class="text-muted small">All institutional support nodes are operating within parameters.</p>
            </div>
        <?php else: foreach($requests as $r): ?>
        <div class="col-12">
            <div class="ticket-card glass-card p-4 <?php echo $r['status']; ?> border-0 shadow-sm">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <?php 
                                $b_class = 'info';
                                $icon = 'fa-info-circle';
                                if($r['status'] == 'open') { $b_class = 'danger'; $icon = 'fa-exclamation-circle'; }
                                if($r['status'] == 'resolved') { $b_class = 'success'; $icon = 'fa-check-circle'; }
                                if($r['status'] == 'closed') { $b_class = 'secondary'; $icon = 'fa-times-circle'; }
                                if($r['status'] == 'in_progress') { $b_class = 'primary'; $icon = 'fa-spinner fa-spin'; }
                            ?>
                            <span class="badge rounded-pill px-3 py-2 bg-<?php echo $b_class; ?> bg-opacity-10 text-<?php echo $b_class; ?> border border-<?php echo $b_class; ?> border-opacity-25">
                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                <?php echo strtoupper(str_replace('_', ' ', $r['status'])); ?>
                            </span>
                            <?php if($r['unread_count'] > 0): ?>
                                <span class="badge rounded-pill bg-danger shadow-sm"><i class="fas fa-comment-dots me-1"></i> <?php echo $r['unread_count']; ?> NEW</span>
                            <?php endif; ?>
                            <span class="tiny-text text-muted"><i class="far fa-clock me-1"></i> <?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?></span>
                        </div>
                        <h6 class="fw-800 mb-1"><?php echo htmlspecialchars($r['subject']); ?></h6>
                        <div class="small mb-3">
                            <span class="text-muted">From:</span> 
                            <strong class="text-blue"><?php echo htmlspecialchars($r['school_name']); ?></strong> 
                            <span class="text-muted ms-2">Owner:</span> 
                            <strong><?php echo htmlspecialchars($r['owner_name']); ?></strong>
                        </div>
                        <div class="p-3 bg-light rounded-4 small border-start border-3 border-muted">
                            <?php echo nl2br(htmlspecialchars($r['message'] ?? '')); ?>
                        </div>
                    </div>
                    <div class="d-flex flex-row flex-md-column gap-2">
                        <a href="support_view.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-dark rounded-pill px-4 fw-bold">
                            <i class="fas fa-eye me-1"></i> VIEW
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-4 fw-bold dropdown-toggle w-100" data-bs-toggle="dropdown">
                                ACTION
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3 p-2">
                                <li><a class="dropdown-item small rounded-2" href="#" onclick="updateTicket(<?php echo $r['id']; ?>, 'in_progress')">
                                    <i class="fas fa-spinner me-2 text-primary"></i> Mark In-Progress</a></li>
                                <li><a class="dropdown-item small rounded-2" href="#" onclick="updateTicket(<?php echo $r['id']; ?>, 'resolved')">
                                    <i class="fas fa-check me-2 text-success"></i> Mark Resolved</a></li>
                                <li><a class="dropdown-item small rounded-2" href="#" onclick="updateTicket(<?php echo $r['id']; ?>, 'closed')">
                                    <i class="fas fa-times me-2 text-secondary"></i> Close Ticket</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item small text-danger rounded-2" href="#" onclick="deleteTicket(<?php echo $r['id']; ?>)">
                                    <i class="fas fa-trash-alt me-2"></i> Purge Record</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</main>

<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<!-- Core Dependencies -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const EDUREMARKS_CSRF_TOKEN = '<?php echo Security::csrf_token(); ?>';
    
    $(document).ready(function() {
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': EDUREMARKS_CSRF_TOKEN },
            data: { csrf_token: EDUREMARKS_CSRF_TOKEN }
        });
    });

    function updateTicket(id, status) {
        Spinner.show('Updating ticket state...');
        $.post('../ajax/sa_update_ticket.php', { id: id, status: status }, function(res) {
            Spinner.hide();
            if(res.success) {
                showSuccess('Status Updated', 'The support ticket status has been changed successfully.', { reload: true });
            } else {
                alert('Update Error: ' + res.message);
            }
        }, 'json').fail(function() {
            Spinner.hide();
            alert('Communication failure.');
        });
    }

    function deleteTicket(id) {
        if(confirm('Wipe this support node permanently? This action cannot be reversed.')) {
            Spinner.show('Purging...');
            $.post('../ajax/sa_update_ticket.php', { id: id, delete: true }, function(res) {
                Spinner.hide();
                if(res.success) {
                    showSuccess('Record Purged', 'The support ticket has been permanently removed.', { reload: true });
                } else {
                    alert(res.message);
                }
            }, 'json').fail(function() {
                Spinner.hide();
                alert('Communication failure.');
            });
        }
    }
</script>
</body>
</html>
