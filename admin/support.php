<?php
// admin/support.php - Help & Support Hub
require_once '../includes/auth_check.php';

// Filter logic
$view = $_GET['view'] ?? 'active';
$filter_sql = ($view === 'archived') ? "AND r.archived_by_school = 1" : "AND r.archived_by_school = 0";

// Fetch user's tickets (excluding soft-deleted)
$stmt = $pdo->prepare("SELECT r.* FROM school_requests r WHERE (r.user_id = ? OR r.school_id = ?) $filter_sql AND r.deleted_by_school = 0 ORDER BY r.created_at DESC");
$stmt->execute([$user_id, $_SESSION['school_id'] ?? 0]);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Hub | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h3 class="fw-800 mb-0 d-flex align-items-center gap-2" style="font-size: 1.4rem; letter-spacing: -0.5px;">Help & Support Hub</h3>
                    <p class="text-muted small mb-0 mt-1">Communicate directly with the EduRemarks orchestration team</p>
                </div>
                <button class="btn btn-primary btn-sm rounded-pill px-3 py-2 fw-bold shadow-sm d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#newTicketModal" style="font-size: 0.8rem;">
                    <i class="fas fa-plus me-1"></i> New Support Ticket
                </button>
            </div>

            <div class="row g-4">
                <div class="col-lg-12">
                    <div class="glass-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?view=active" class="btn btn-sm <?php echo ($view == 'active') ? 'btn-premium-blue' : 'btn-light border'; ?> rounded-pill px-3 fw-bold text-nowrap" style="font-size: 0.75rem;">Active Streams</a>
                                <a href="?view=archived" class="btn btn-sm <?php echo ($view == 'archived') ? 'btn-premium-blue' : 'btn-light border'; ?> rounded-pill px-3 fw-bold text-nowrap" style="font-size: 0.75rem;">Institutional Archives</a>
                            </div>
                            <div class="d-flex gap-2 text-nowrap">
                                <span class="badge bg-light text-muted rounded-pill px-3 py-2 border fw-normal" style="font-size: 0.75rem;"><?php echo count($tickets); ?> Total Entries</span>
                            </div>
                        </div>
                        <?php if(empty($tickets)): ?>
                        <div class="text-center py-5">
                            <div class="icon-box bg-light text-muted mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-envelope-open-text fa-2x"></i>
                            </div>
                            <h5>No active support transmissions</h5>
                            <p class="text-muted small">Need assistance with the platform? Open a ticket to connect with our specialists.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle custom-table">
                                <thead>
                                    <tr class="text-muted small uppercase tracking-1">
                                        <th>Ticket ID</th>
                                        <th>Subject & Summary</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($tickets as $t): 
                                        $p_color = ($t['priority'] == 'high') ? 'danger' : (($t['priority'] == 'medium') ? 'warning' : 'info');
                                    ?>
                                    <tr class="reveal-fade-up">
                                        <td><code class="fw-800 text-blue">#TKT-<?php echo $t['id']; ?></code></td>
                                        <td>
                                            <div class="fw-700"><?php echo htmlspecialchars($t['subject']); ?></div>
                                            <div class="tiny-text opacity-50 text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($t['message']); ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark fw-bold text-uppercase border"><?php echo $t['category']; ?></span></td>
                                        <td><span class="badge bg-<?php echo $p_color; ?> bg-opacity-10 text-<?php echo $p_color; ?> px-2 rounded-pill"><?php echo $t['priority']; ?></span></td>
                                        <td>
                                            <?php if($t['status'] == 'open'): ?>
                                                <span class="badge bg-warning text-white px-3 rounded-pill">Active</span>
                                            <?php elseif($t['status'] == 'in_progress'): ?>
                                                <span class="badge bg-info text-white px-3 rounded-pill">In Progress</span>
                                            <?php else: ?>
                                                <span class="badge bg-success text-white px-3 rounded-pill">Resolved</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small opacity-75"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="support_view.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-premium-blue rounded-pill px-3">View Node</a>
                                                <button class="btn btn-sm btn-light border rounded-pill px-3" onclick="orchestrateAction(<?php echo $t['id']; ?>, 'archive')" title="Archive Node">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                                <button class="btn btn-sm btn-light border rounded-pill px-3 text-danger" onclick="orchestrateAction(<?php echo $t['id']; ?>, 'delete')" title="Permanent Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <script>
                function orchestrateAction(id, action) {
                    const label = action === 'archive' ? 'Archive' : 'Permanently Delete';
                    if(confirm(`Confirm Institutional Decision: ${label} support node #${id}?`)) {
                        Spinner.show('Synchronizing with data cluster...');
                        $.post('../ajax/client_update_ticket.php', { id, action }, function(res) {
                            Spinner.hide();
                            if(res.success) location.reload(); else alert(res.message);
                        }, 'json');
                    }
                }
            </script>
            
            <div class="row g-4 mt-1 mb-5">
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 text-center">
                        <i class="fas fa-headset text-blue fa-2x mb-3"></i>
                        <h6>Human Assistance</h6>
                        <p class="tiny-text text-muted">Direct hotline for mission-critical emergencies only.</p>
                        <div class="fw-800 text-blue">+234 810 000 0000</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 text-center">
                        <i class="fas fa-book-open text-warning fa-2x mb-3"></i>
                        <h6>Documentation</h6>
                        <p class="tiny-text text-muted">Self-service knowledge base for platform orchestration.</p>
                        <a href="#" class="btn btn-link btn-sm text-premium-gold fw-bold">Explore Guides</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 text-center">
                        <i class="fas fa-shield-halved text-success fa-2x mb-3"></i>
                        <h6>Uptime Status</h6>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <span class="status-indicator"></span>
                            <span class="fw-bold small text-success">99.9% Systems Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="newTicketModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content border-0 shadow-lg rounded-4" id="ticketForm">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Initialize Support Stream</h5>
                    <p class="text-muted small mb-0">Briefly define your technical or administrative challenge.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="small fw-bold mb-2">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="e.g. Credit synchronization error" required>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold mb-2">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="technical">Technical</option>
                                <option value="billing">Billing</option>
                                <option value="academic">Academic</option>
                                <option value="general" selected>General</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small fw-bold mb-2">Priority</label>
                            <select name="priority" class="form-select" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">Transmission Details</label>
                        <textarea name="message" class="form-control" rows="8" required placeholder="Describe the issue in detail, including steps to reproduce for technical nodes..."></textarea>
                    </div>
                    <div class="alert alert-info py-2 rounded-3 border-0 small">
                        <i class="fas fa-info-circle me-1"></i> Our specialists typically respond within 1–4 operational hours.
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">INITIALIZE DISPATCH</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $('#ticketForm').on('submit', function(e) {
            e.preventDefault();
            Spinner.show('Dispatching support stream...');
            $.post('../ajax/save_support_ticket.php', $(this).serialize(), function(res) {
                Spinner.hide();
                if(res.success) location.reload(); else alert(res.message);
            }, 'json');
        });
    </script>
</body>
</html>
