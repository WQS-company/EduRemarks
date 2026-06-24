<?php
// user/support.php - Help & Support Hub (Staff)
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
    <title>Support Hub | EduRemarks Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h3 class="fw-800 mb-0 d-flex align-items-center gap-2" style="font-size:1.4rem; letter-spacing:-0.5px;">Help & Support Hub</h3>
                    <p class="text-muted small mb-0 mt-1">Communicate directly with the EduRemarks orchestration team</p>
                </div>
                <button class="btn btn-premium-blue btn-sm rounded-pill px-3 py-2 fw-bold shadow-sm d-flex align-items-center text-nowrap" data-bs-toggle="modal" data-bs-target="#newTicketModal" style="font-size:0.8rem;">
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
                            <i class="fas fa-envelope-open-text fa-3x text-muted opacity-25 mb-3"></i>
                            <p class="text-muted">No active support transmissions found. Use the button above to begin.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr class="text-muted small uppercase tracking-1">
                                        <th>Date</th>
                                        <th>Subject Node</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($tickets as $t): ?>
                                    <tr>
                                        <td class="small opacity-75"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                        <td>
                                            <div class="fw-800 text-blue mb-0"><?php echo htmlspecialchars($t['subject']); ?></div>
                                            <div class="tiny-text opacity-50"><code class="text-muted">#TKT-<?php echo $t['id']; ?></code></div>
                                        </td>
                                        <td>
                                            <?php 
                                            switch($t['status']) {
                                                case 'open': echo '<span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">ACTIVE</span>'; break;
                                                case 'in_progress': echo '<span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3">PROCESSING</span>'; break;
                                                case 'resolved': echo '<span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">RESOLVED</span>'; break;
                                                default: echo '<span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">'.strtoupper($t['status']).'</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="tiny-text fw-bold text-uppercase"><i class="fas fa-layer-group me-1"></i> <?php echo $t['category']; ?></span>
                                                <span class="tiny-text fw-bold text-uppercase <?php echo ($t['priority'] == 'high') ? 'text-danger' : 'text-warning'; ?>"><i class="fas fa-flag me-1"></i> <?php echo $t['priority']; ?></span>
                                            </div>
                                        </td>
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

            <div class="row g-4 mt-2 mb-5">
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 text-center">
                        <div class="icon-box-sm bg-soft-primary text-primary mx-auto mb-3"><i class="fas fa-headset h5 mb-0"></i></div>
                        <h6 class="fw-800">24/7 Hotline</h6>
                        <p class="tiny-text text-muted">+234 810 000 0000</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 text-center border-dashed">
                        <div class="icon-box-sm bg-soft-info text-info mx-auto mb-3"><i class="fas fa-book h5 mb-0"></i></div>
                        <h6 class="fw-800">Support Docs</h6>
                        <p class="tiny-text text-muted">Knowledge Base Node</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 h-100 text-center">
                        <div class="icon-box-sm bg-soft-warning text-warning mx-auto mb-3"><i class="fas fa-shield-alt h5 mb-0"></i></div>
                        <h6 class="fw-800">Technical FAQ</h6>
                        <p class="tiny-text text-muted">Core Troubleshooting</p>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>

    <!-- Modal -->
    <div class="modal fade" id="newTicketModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="ticketForm">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <div>
                        <h5 class="fw-800 mb-0">Initialize Support Stream</h5>
                        <p class="small opacity-75 mb-0">Dispatch a new technical transmission</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-800 mb-2 uppercase tracking-1 opacity-75">Topic Subject</label>
                        <input type="text" name="subject" class="form-control rounded-3 border-2" placeholder="e.g. CBT Portal latency" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-800 mb-2 uppercase tracking-1 opacity-75">Category Node</label>
                            <select name="category" class="form-select rounded-3 border-2">
                                <option value="technical">Technical</option>
                                <option value="billing">Billing/Payment</option>
                                <option value="academic">Academic/Setup</option>
                                <option value="general" selected>General Inquiry</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-800 mb-2 uppercase tracking-1 opacity-75">Priority Level</label>
                            <select name="priority" class="form-select rounded-3 border-2">
                                <option value="low">Low (Standard)</option>
                                <option value="medium" selected>Medium (Normal)</option>
                                <option value="high">High (Urgent)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="small fw-800 mb-2 uppercase tracking-1 opacity-75">Transmission Payload</label>
                        <textarea name="message" class="form-control rounded-3 border-2" rows="5" required placeholder="Describe the service node requirements..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-900 shadow-sm">DISPATCH REQUEST <i class="fas fa-paper-plane ms-2"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        $('#ticketForm').on('submit', function(e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<i class="fas fa-sync fa-spin"></i> Dispatching...');
            
            $.post('../ajax/save_support_ticket.php', $(this).serialize(), function(res) {
                if(res.success) location.reload(); 
                else {
                    alert(res.message);
                    btn.prop('disabled', false).html('DISPATCH REQUEST <i class="fas fa-paper-plane ms-2"></i>');
                }
            }, 'json');
        });
    </script>
</body>
</html>
