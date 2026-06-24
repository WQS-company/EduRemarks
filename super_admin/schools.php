<?php
// super_admin/schools.php - Central Institutional Management
require_once 'auth_check.php';

// Fetch all schools with revenue, population, and resource stats
$stmt = $pdo->query(
    "SELECT s.*, 
            u.full_name AS owner_name,
            u.email AS owner_email,
            (SELECT COALESCE(SUM(amount), 0) FROM platform_payments WHERE school_id = s.id AND status = 'success' LIMIT 1) AS total_revenue,
            (SELECT COUNT(*) FROM students WHERE school_id = s.id LIMIT 1) AS student_count,
            (SELECT COUNT(*) FROM staff_details WHERE school_id = s.id LIMIT 1) AS staff_count,
            (SELECT COALESCE(SUM(amount), 0) FROM credit_logs WHERE school_id = s.id LIMIT 1) AS credits_total,
            s.feature_access
     FROM schools s 
     JOIN users u ON s.owner_id = u.id 
     ORDER BY s.created_at DESC"
);
$schools = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schools | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 220px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .hover-primary:hover { color: var(--sa-blue) !important; }
        
        #processing-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,0.85);
            backdrop-filter: blur(8px); z-index: 99999; display: none;
            align-items: center; justify-content: center; flex-direction: column; color: white;
        }
        .spinner-box {
            width: 60px; height: 60px; border: 4px solid rgba(255,255,255,0.1);
            border-top-color: #F4B400; border-radius: 50%;
            animation: spin-node 1s linear infinite; margin-bottom: 20px;
        }
        @keyframes spin-node { to { transform: rotate(360deg); } }
        .processing-text { font-weight: 800; letter-spacing: 2px; text-transform: uppercase; font-size: 0.75rem; opacity: 0.8; }

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
            <h4 class="fw-800 mb-0">Institutional Network</h4>
            <p class="text-muted small">Manage and authorize school environments</p>
        </div>
    </div>

        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr class="text-muted small uppercase fw-bold">
                            <th>ID / School Name</th>
                            <th>Ownership Identity</th>
                            <th>Population Nodes</th>
                            <th>Internal Resources</th>
                            <th>Status</th>
                            <th>Operations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $s): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3">
                                        <i class="fas fa-school"></i>
                                    </div>
                                    <div>
                                        <a href="school_details.php?id=<?php echo $s['id']; ?>" class="fw-800 text-decoration-none text-dark d-block hover-primary">
                                            <?php echo htmlspecialchars($s['school_name']); ?>
                                        </a>
                                        <div class="tiny-text text-muted">
                                            <?php echo $s['unique_id']; ?> &bull; <?php echo $s['school_type']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-600 small"><?php echo htmlspecialchars($s['owner_name']); ?></div>
                                <div class="tiny-text opacity-75"><?php echo htmlspecialchars($s['owner_email']); ?></div>
                            </td>
                            <td>
                                <div class="small fw-800 text-blue"><i class="fas fa-user-graduate me-1 opacity-50"></i> <?php echo number_format($s['student_count']); ?> Students</div>
                                <div class="tiny-text fw-600 text-muted"><i class="fas fa-users-cog me-1 opacity-50"></i> <?php echo number_format($s['staff_count']); ?> Staff</div>
                            </td>
                            <td>
                                <div class="badge bg-primary bg-opacity-10 text-primary px-3 rounded-pill fw-bold mb-1">
                                    <?php echo number_format($s['credits'] ?? 0); ?> <i class="fas fa-bolt ms-1 tiny-text"></i>
                                </div>
                                <div class="tiny-text fw-bold text-success"><i class="fas fa-coins me-1 opacity-50"></i> ₦<?php echo number_format($s['total_revenue'] ?? 0, 2); ?> Total</div>
                            </td>
                            <td>
                                <select class="form-select form-select-sm status-select rounded-pill px-3" data-id="<?php echo $s['id']; ?>" style="width: 120px;">
                                    <option value="active" <?php echo ($s['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="pending" <?php echo ($s['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo ($s['status'] == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2">
                                        <li><a class="dropdown-item rounded-3 small fw-600 btn-manage-features" href="#" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['school_name'], ENT_QUOTES, 'UTF-8'); ?>" data-features="<?php echo htmlspecialchars($s['feature_access'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-layer-group me-2 text-primary"></i> Feature Access</a></li>
                                        <li><a class="dropdown-item rounded-3 small fw-600 btn-reward-credits" href="#" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['school_name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-gift me-2 text-warning"></i> Reward Credits</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item rounded-3 small fw-600 text-danger btn-delete-school" href="#" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['school_name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-trash-alt me-2"></i> Terminate Account</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Feature Offcanvas -->
    <div class="offcanvas offcanvas-end shadow-lg border-0" tabindex="-1" id="featureOffcanvas" style="width: 400px;">
        <form class="h-100 d-flex flex-column" id="featureForm">
            <input type="hidden" name="id" id="featureSchoolId">
            <input type="hidden" name="action" value="features">
            <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">
            <div class="offcanvas-header border-bottom p-4 bg-light">
                <h5 class="fw-bold mb-0"><i class="fas fa-layer-group text-primary me-2"></i>Institutional Features</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body p-4 flex-grow-1 overflow-auto">
                <p class="text-muted small mb-4">Configure accessible nodes for:<br><strong id="featureSchoolName" class="text-blue h6"></strong></p>
                <div class="list-group list-group-flush rounded-3 border">
                    <?php
                    $available_features = [
                        'CBT_EXAMS' => 'Elite CBT & Assessment Engine',
                        'RESULT_PROCESSING' => 'Institutional Result Processing',
                        'SMS_ALERTS' => 'Automated SMS & Notifications',
                        'ADMISSION_PORTAL' => 'Advanced Admission Ecosystem',
                        'FEE_MANAGEMENT' => 'Fiscal & Wallet Hub',
                        'PRINTING_REPORTS' => 'Premium PDF & Printing Nodes',
                        'STUDENT_PORTAL' => 'Institutional Student/Parent Hub',
                        'COURSE_CURRICULUM' => 'Institutional Course Curriculum'
                    ];
                    foreach ($available_features as $key => $label):
                    ?>
                    <label class="list-group-item d-flex justify-content-between align-items-center p-3 hover-bg-light transition-base" style="cursor: pointer;">
                        <span class="small fw-600"><?php echo $label; ?></span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input feature-item" type="checkbox" name="features[]" value="<?php echo $key; ?>" style="cursor: pointer;">
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="offcanvas-footer p-4 border-top bg-white">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm">SYNCHRONIZE FEATURES</button>
            </div>
        </form>
    </div>

    <!-- Reward Offcanvas -->
    <div class="offcanvas offcanvas-end shadow-lg border-0" tabindex="-1" id="rewardOffcanvas" style="width: 400px;">
        <div class="offcanvas-header border-bottom p-4 bg-light">
            <h5 class="fw-bold mb-0"><i class="fas fa-gift text-warning me-2"></i>Reward Credits</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-4 text-center">
            <div class="icon-box bg-warning bg-opacity-10 text-warning mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                <i class="fas fa-bolt"></i>
            </div>
            <p class="text-muted small mb-4">Dispatch operational credits to:<br><strong id="targetSchoolName" class="text-blue h5 mt-1 d-block"></strong></p>
            <div class="text-start mb-4">
                <label class="small fw-bold mb-2">Amount (Credits)</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-coins"></i></span>
                    <input type="number" id="rewardAmount" class="form-control border-start-0 ps-0 text-dark fw-bold" placeholder="e.g. 500">
                </div>
            </div>
        </div>
        <div class="offcanvas-footer p-4 border-top bg-white">
            <button class="btn btn-dark w-100 rounded-pill py-3 fw-bold shadow-sm btn-confirm-reward">DISPATCH CREDITS <i class="fas fa-paper-plane ms-2"></i></button>
        </div>
    </div>

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

            let activeSchoolId = null;
            const rewardOffcanvas = bootstrap.Offcanvas.getOrCreateInstance('#rewardOffcanvas');
            const featureOffcanvas = bootstrap.Offcanvas.getOrCreateInstance('#featureOffcanvas');

            // Status Change
            $('.status-select').on('change', function() {
                const id = $(this).data('id');
                const status = $(this).val();
                Spinner.show('Updating node status...');
                $.post('../ajax/sa_update_school.php', { id, status, action: 'status' }, function(res) {
                    Spinner.hide();
                    if (res.success) {
                        showSuccess('Status Updated', 'School node status has been synchronized.', { reload: true });
                    } else {
                        alert(res.message);
                    }
                }, 'json').fail(function() {
                    Spinner.hide();
                    alert('Communication failure with institutional node.');
                });
            });

            // Open Reward Offcanvas
            $('.btn-reward-credits').on('click', function(e) {
                e.preventDefault();
                activeSchoolId = $(this).data('id');
                $('#targetSchoolName').text($(this).data('name'));
                $('#rewardAmount').val('');
                rewardOffcanvas.show();
            });

            // Confirm Reward
            $('.btn-confirm-reward').on('click', function() {
                const amount = $('#rewardAmount').val();
                if (!amount || amount <= 0) { alert('Please enter a valid credit amount.'); return; }
                Spinner.show('Dispatching credits...');
                $.post('../ajax/sa_update_school.php', { id: activeSchoolId, amount, action: 'reward' }, function(res) {
                    Spinner.hide();
                    if (res.success) {
                        rewardOffcanvas.hide();
                        showSuccess('Credits Dispatched', 'Reward credits have been successfully allocated.', { reload: true });
                    } else {
                        alert(res.message);
                    }
                }, 'json').fail(function() {
                    Spinner.hide();
                    alert('Transaction failed. Network handshake refused.');
                });
            });

            // Open Feature Offcanvas
            $('.btn-manage-features').on('click', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const name = $(this).data('name');
                const current = String($(this).data('features') || '');
                $('#featureSchoolId').val(id);
                $('#featureSchoolName').text(name);
                $('.feature-item').prop('checked', false);
                if (current) {
                    current.split(',').forEach(f => {
                        if (f.trim()) $(`.feature-item[value="${f.trim()}"]`).prop('checked', true);
                    });
                }
                featureOffcanvas.show();
            });

            // Feature Form Submit
            $('#featureForm').on('submit', function(e) {
                e.preventDefault();
                Spinner.show('Synchronizing institutional nodes...');
                
                $.ajax({
                    url: '../ajax/sa_update_school.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        setTimeout(() => {
                            Spinner.hide();
                            if (res.success) {
                                featureOffcanvas.hide();
                                showSuccess('Features Synchronized', 'Institutional access nodes have been successfully updated.', { 
                                    buttonText: 'CONTINUE OPERATIONS',
                                    reload: true 
                                });
                            } else {
                                alert(res.message);
                            }
                        }, 800); // Artificial delay for perceptual stability
                    },
                    error: function() {
                        Spinner.hide();
                        alert('Synchronization failed. Link to institutional node terminated.');
                    }
                });
            });

            // Delete School
            $('.btn-delete-school').on('click', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const name = $(this).data('name');
                if (confirm(`CRITICAL WARNING: Are you sure you want to PERMANENTLY terminate ${name}? This action wipes all institutional data nodes.`)) {
                    Spinner.show('Decommissioning school...');
                    $.post('../ajax/sa_update_school.php', { id, action: 'delete' }, function(res) {
                        Spinner.hide();
                        if (res.success) {
                            showSuccess('School Terminated', 'The institutional node has been decommissioned.', { reload: true });
                        } else {
                            alert(res.message);
                        }
                    }, 'json').fail(function() {
                        Spinner.hide();
                        alert('Termination failed. Node resistance encountered.');
                    });
                }
            });
        });
    </script>
</body>
</html>
