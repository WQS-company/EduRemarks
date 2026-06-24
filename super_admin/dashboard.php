<?php
// super_admin/dashboard.php - The Central Command Center
require_once 'auth_check.php';

// 1. Fetch Stats with defensive orchestration
try {
    $total_schools = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
    $active_schools = $pdo->query("SELECT COUNT(*) FROM schools WHERE status='active'")->fetchColumn();
    $pending_schools = $pdo->query("SELECT COUNT(*) FROM schools WHERE status='pending'")->fetchColumn();
    $pending_billing_requests = $pdo->query("SELECT COUNT(*) FROM billing_requests WHERE status='pending'")->fetchColumn();
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM platform_payments WHERE status='success'")->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_schools = $active_schools = $pending_schools = $pending_billing_requests = $total_revenue = 0;
}

// 2. Fetch Recent Schools
try {
    $recent_schools = $pdo->query("SELECT s.*, u.full_name as owner_name FROM schools s JOIN users u ON s.owner_id=u.id ORDER BY s.created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    $recent_schools = [];
}

// 3. Fetch Recent Registration Requests
try {
    $recent_requests = $pdo->query("SELECT r.*, s.school_name FROM school_requests r JOIN schools s ON r.school_id = s.id ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    $recent_requests = [];
}

// 4. Fetch Recent Billing Requests
try {
    $billing_requests = $pdo->query("SELECT br.*, s.school_name, s.unique_id FROM billing_requests br JOIN schools s ON br.school_id = s.id WHERE br.status = 'pending' ORDER BY br.request_date DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    $billing_requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Command | <?php echo get_setting('hero_title', 'EduRemarks'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { 
            --sa-blue: #1a4da1; 
            --sa-green: #10b981; 
            --sa-orange: #f59e0b; 
            --sa-red: #ef4444;
            --sa-bg: #f8fafc;
        }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; letter-spacing: -0.2px; }
        .sa-main-content { margin-left: 220px; padding: 30px; transition: 0.3s; }
        
        .text-blue { color: var(--sa-blue); }
        .fw-600 { font-weight: 600; }
        .fw-700 { font-weight: 700; }
        .fw-800 { font-weight: 800; }
        .fw-900 { font-weight: 900; }
        .extra-small { font-size: 0.65rem; }
        .tiny-text { font-size: 0.6rem; }
        .uppercase { text-transform: uppercase; }

        .glass-card { 
            border-radius: 20px; 
            background: #fff; 
            box-shadow: 0 10px 25px rgba(31, 60, 136, 0.05); 
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        .hover-scale:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(31, 60, 136, 0.1); }
        .hover-bg-light:hover { background: #f8fafc; }
        
        .rank-badge { box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .tracking-tight { letter-spacing: -0.025em; }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; padding-top: 10px; }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">

    <div class="row g-3 mb-4">
        <!-- Institutional Statistics Card (Blue) -->
        <div class="col-6 col-md-4">
            <div class="glass-card p-3 h-100" style="background: #1e40af; color: white; border: none;">
                <h6 class="fw-800 extra-small mb-4 text-white"><i class="fas fa-university me-2 text-warning"></i>INSTITUTIONAL SUMMARY</h6>
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-600">Active Nodes</span>
                        <span class="fw-800"><?php echo number_format($active_schools); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-600">Pending Sync</span>
                        <span class="fw-800"><?php echo number_format($pending_schools); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-600">Billing Requests</span>
                        <span class="fw-800 text-warning"><?php echo number_format($pending_billing_requests); ?></span>
                    </div>
                </div>
                <button class="btn btn-light w-100 rounded-pill py-2 fw-800 text-blue shadow-sm hover-scale" style="font-size: 0.75rem;" onclick="location.href='schools.php'">
                    MANAGE SCHOOL NODES
                </button>
            </div>
        </div>

        <!-- Platform Ecosystem (White/Graph) -->
        <?php
        // Fetch real ecosystem counts - Wrapped in defensive logic
        try {
            $total_staff = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
            $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
            $total_classes = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
            $total_subjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
            $total_exams = $pdo->query("SELECT COUNT(*) FROM cbt_exams")->fetchColumn();
        } catch (Exception $e) {
            $total_staff = $total_students = $total_classes = $total_subjects = $total_exams = 0;
        }
        ?>
        <div class="col-6 col-md-4">
            <div class="glass-card p-2 h-100 bg-white border-0 shadow-sm overflow-hidden">
                <div class="px-2 py-1 mb-2 d-flex align-items-center justify-content-between">
                    <h6 class="fw-800 extra-small text-muted mb-0 uppercase tracking-1"><i class="fas fa-chart-line text-success me-1"></i>Platform Ecosystem</h6>
                </div>
                <div class="mb-3 px-2 text-center">
                    <img src="https://img.freepik.com/free-vector/modern-business-bar-chart-financial-concept-infographic-illustration_1017-38645.jpg" class="img-fluid rounded-3" style="height: 85px; width: 100%; object-fit: contain; background: #f8fafc;">
                </div>
                <div class="row g-1 text-center small pb-2">
                    <div class="col-4 border-end"><div class="fw-800 text-blue"><?php echo number_format($total_schools); ?></div><div class="extra-small opacity-50 fw-bold">SCH</div></div>
                    <div class="col-4 border-end"><div class="fw-800 text-blue"><?php echo number_format($total_staff); ?></div><div class="extra-small opacity-50 fw-bold">STF</div></div>
                    <div class="col-4"><div class="fw-800 text-blue"><?php echo number_format($total_students); ?></div><div class="extra-small opacity-50 fw-bold">STD</div></div>
                </div>
                <div class="row g-1 text-center small pt-1 border-top border-light">
                     <div class="col-4 border-end"><div class="fw-800 text-blue"><?php echo number_format($total_classes); ?></div><div class="extra-small opacity-50 fw-bold">CLS</div></div>
                     <div class="col-4 border-end"><div class="fw-800 text-blue"><?php echo number_format($total_subjects); ?></div><div class="extra-small opacity-50 fw-bold">SUB</div></div>
                     <div class="col-4"><div class="fw-800 text-blue"><?php echo number_format($total_exams); ?></div><div class="extra-small opacity-50 fw-bold">EXM</div></div>
                </div>
            </div>
        </div>

        <!-- Revenue Summary (Green) -->
        <div class="col-12 col-md-4">
             <div class="glass-card h-100 d-flex flex-column border-0 p-3" style="background: #10b981; color: white;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h5 class="fw-800 mb-0">₦ <?php echo number_format($total_revenue); ?></h5>
                    <i class="fas fa-wallet opacity-50"></i>
                </div>
                <div class="small fw-700 opacity-75 mb-3">Aggregated "Revenue"</div>
                <div class="mt-auto pt-3 border-top border-white border-opacity-10 text-end">
                    <?php $expected = $total_revenue * 1.25; ?>
                    <div class="fw-800 mb-0">₦ <?php echo number_format($expected); ?></div>
                    <div class="extra-small opacity-50 fw-bold uppercase">Expected Liquidity</div>
                </div>
             </div>
        </div>
    </div>

    <!-- Dropdown / Tracker Section -->
    <div class="glass-card mb-4 p-3 d-flex align-items-center justify-content-between bg-white border-0 shadow-sm">
        <h6 class="fw-800 mb-0 text-blue" style="font-size: 0.85rem;"><i class="fas fa-th-list me-2"></i>Institutional Activity Tracker</h6>
        <div class="dropdown">
            <button class="btn btn-light btn-sm rounded-3 fw-bold px-3 border border-light" type="button" data-bs-toggle="dropdown">
                ALL SYSTEMS <i class="fas fa-chevron-down ms-2 tiny-text opacity-50"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item fw-600" href="#">Login Logs</a></li>
                <li><a class="dropdown-item fw-600" href="#">Result Logs</a></li>
                <li><a class="dropdown-item fw-600" href="#">Payment Logs</a></li>
            </ul>
        </div>
    </div>

    <!-- Registered Schools Leaderboard -->
    <div class="glass-card p-0 bg-white border-0 shadow-sm overflow-hidden mb-5 pb-3">
        <div class="p-3 border-bottom d-flex align-items-center gap-2">
            <i class="fas fa-ranking-star text-warning"></i>
            <h6 class="fw-800 mb-0 text-blue" style="font-size: 0.85rem;">Registered Educational Institutions</h6>
        </div>
        
        <div class="results-table">
            <?php 
            $rank = 1;
            foreach($recent_schools as $school): 
                $logo = !empty($school['logo_path']) ? '../' . $school['logo_path'] : '../img/logo.png';
            ?>
            <div class="d-flex align-items-center border-bottom p-3 hover-bg-light transition">
                <div class="rank-badge me-3 bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 25px; height: 25px; min-width: 25px; font-size: 0.7rem; font-weight: 800; background: <?php echo $rank <= 3 ? '#F4B400' : '#e2e8f0'; ?>; color: <?php echo $rank <= 3 ? '#fff' : '#64748b'; ?>;">
                    <?php echo $rank++; ?>
                </div>
                
                <img src="<?php echo $logo; ?>" class="rounded-circle shadow-sm me-3" style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #fff;">
                
                <div class="flex-grow-1">
                    <div class="fw-800 text-blue" style="font-size: 0.85rem;"><?php echo htmlspecialchars($school['school_name']); ?></div>
                    <div class="extra-small opacity-50 fw-bold uppercase">ID: <?php echo $school['unique_id']; ?> &bull; Owner: <?php echo htmlspecialchars($school['owner_name']); ?></div>
                </div>

                <div class="text-end">
                    <div class="fw-900 text-blue mb-0"><?php echo date('M d', strtotime($school['created_at'])); ?></div>
                    <div class="fw-800 <?php echo $school['status'] === 'active' ? 'text-success' : 'text-warning'; ?> uppercase" style="font-size: 0.65rem;"><?php echo $school['status']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center py-3">
             <button class="btn btn-light rounded-pill px-4 extra-small fw-800 border-light hover-scale" onclick="location.href='schools.php'">VIEW ALL INSTITUTIONS <i class="fas fa-chevron-right ms-2 tiny-text opacity-50"></i></button>
        </div>
    </div>

    <!-- Institutional Billing Transition Requests -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="glass-card p-0 bg-white border-0 shadow-sm overflow-hidden mb-5">
                <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-primary"></i>
                        <h6 class="fw-800 mb-0 text-blue" style="font-size: 0.85rem;">Pending Billing Transitions</h6>
                    </div>
                    <?php if($pending_billing_requests > 0): ?>
                        <span class="badge bg-danger rounded-pill fw-bold" style="font-size: 0.6rem;"><?php echo $pending_billing_requests; ?> ACTION REQUIRED</span>
                    <?php endif; ?>
                </div>

                <?php if(empty($billing_requests)): ?>
                    <div class="p-5 text-center text-muted small">No pending billing transitions discovered in the ecosystem.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="tiny-text uppercase fw-800 text-muted">
                                    <th class="ps-3">Institution</th>
                                    <th>Requested Plan</th>
                                    <th>Duration</th>
                                    <th>Date</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($billing_requests as $br): ?>
                                <tr class="hover-bg-light transition">
                                    <td class="ps-3">
                                        <div class="fw-800 text-blue small"><?php echo htmlspecialchars($br['school_name']); ?></div>
                                        <div class="tiny-text opacity-50 fw-bold uppercase">ID: <?php echo $br['unique_id']; ?></div>
                                    </td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary rounded-pill"><?php echo htmlspecialchars($br['requested_plan']); ?></span></td>
                                    <td class="small fw-600"><?php echo htmlspecialchars($br['duration']); ?></td>
                                    <td class="small text-muted"><?php echo date('M d, Y', strtotime($br['request_date'])); ?></td>
                                    <td class="text-end pe-3">
                                        <a href="school_details.php?id=<?php echo $br['school_id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-800" style="font-size: 0.65rem;">
                                            <i class="fas fa-check-circle me-1"></i> REVIEW & APPROVE
                                        </a>
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
</main>





<?php include '../includes/spinner.php'; ?>
<?php include '../includes/notifications.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
