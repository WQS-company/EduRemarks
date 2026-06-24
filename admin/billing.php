<?php
// admin/billing.php - Institutional Financial & Operational Ledger
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    die("Unauthorized Ledger Access. Role detected: " . htmlspecialchars($role)); 
}

// Fetch payment transaction history (Financial Node)
$stmt = $pdo->prepare("SELECT p.*, pkg.name as package_name FROM platform_payments p JOIN pricing_packages pkg ON p.package_id=pkg.id WHERE p.school_id = ? ORDER BY p.created_at DESC");
$stmt->execute([$active_school_id]);
$payments = $stmt->fetchAll();

// Fetch operational credit logs (Activity Ledger)
$stmt = $pdo->prepare("SELECT * FROM credit_logs WHERE school_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$active_school_id]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Ledger | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .ledger-tabs .nav-link { color: #64748b; font-weight: 700; border-radius: 12px; margin-right: 10px; padding: 12px 25px; transition: 0.3s; }
        .ledger-tabs .nav-link.active { background: #1F3C88; color: white; box-shadow: 0 10px 20px rgba(31, 60, 136, 0.15); }
        .invoice-btn { padding: 6px 15px; border-radius: 8px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; }
    </style>
</head>
<body class="bg-light">

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <?php include '../includes/dashboard_top_nav.php'; ?>

        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-5 mt-3">
                <div>
                    <h4 class="fw-800 mb-0">Financial & Resource Ledger</h4>
                    <p class="text-muted small">Track institutional transactions and operational activities</p>
                </div>
                <a href="pricing.php" class="btn btn-primary rounded-pill px-4 fw-900 shadow">
                    <i class="fas fa-plus me-2"></i>REPLENISH CREDITS
                </a>
            </div>

            <!-- SUCCESS NOTIF FROM PAYMENT -->
            <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert bg-success bg-opacity-10 border-success border-opacity-25 rounded-4 p-4 text-center mb-5 d-flex align-items-center justify-content-center animate-fade-in">
                <div class="icon-box bg-success text-white rounded-circle p-3 me-3">
                    <i class="fas fa-check-circle h4 mb-0"></i>
                </div>
                <div class="text-start">
                    <h6 class="fw-800 text-success mb-0">Institutional Synchronization Successful!</h6>
                    <p class="mb-0 small opacity-75">Ref: <strong><?php echo htmlspecialchars($_GET['ref']); ?></strong> has been reconciled on this node.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- NAVIGATION -->
            <ul class="nav nav-pills ledger-tabs mb-4 border-0 bg-white p-2 d-inline-flex rounded-4 shadow-sm" id="ledgerTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabFinancial">Financial History</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOperational">Activity Ledger</button>
                </li>
            </ul>

            <div class="tab-content mt-2">
                <!-- FINANCIAL TABLE -->
                <div class="tab-pane fade show active" id="tabFinancial">
                    <div class="glass-card p-4 border-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr class="text-muted small uppercase fw-bold tracking-1">
                                        <th>Date / Period</th>
                                        <th>Package Tier</th>
                                        <th>Amount (₦)</th>
                                        <th>Ref Node</th>
                                        <th>Status</th>
                                        <th>Operations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-800 text-blue"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                            <div class="tiny-text opacity-75"><?php echo date('h:i A', strtotime($p['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?php echo htmlspecialchars($p['package_name']); ?></span>
                                            <div class="tiny-text opacity-75">+<?php echo number_format($p['credits_awarded']); ?> credits</div>
                                        </td>
                                        <td><strong class="text-dark">₦<?php echo number_format($p['amount']); ?></strong></td>
                                        <td><code class="small text-muted"><?php echo $p['reference']; ?></code></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($p['status']=='success')?'success':'info'; ?> bg-opacity-10 text-<?php echo ($p['status']=='success')?'success':'info'; ?> rounded-pill px-3">
                                                <?php echo strtoupper($p['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="invoice.php?ref=<?php echo $p['reference']; ?>" target="_blank" class="btn btn-outline-primary invoice-btn shadow-sm">
                                                <i class="fas fa-file-invoice me-2"></i> DOWNLOAD INVOICE
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; if(empty($payments)): ?>
                                    <tr><td colspan="6" class="text-center py-5 opacity-50">No financial transactions detected in this lifecycle node.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- OPERATIONAL LOGS -->
                <div class="tab-pane fade" id="tabOperational">
                    <div class="glass-card p-4 border-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr class="text-muted small uppercase fw-bold tracking-1">
                                        <th>Timestamp</th>
                                        <th>Operation / Activity</th>
                                        <th>Impact</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($logs as $l): ?>
                                    <tr>
                                        <td class="small opacity-75"><?php echo date('M d, Y h:i A', strtotime($l['created_at'])); ?></td>
                                        <td>
                                            <div class="fw-800 text-blue"><?php echo htmlspecialchars($l['activity']); ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-<?php echo ($l['amount'] > 50)?'danger':'warning'; ?>">
                                                -<?php echo number_format($l['amount']); ?> credits
                                            </span>
                                        </td>
                                        <td><span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">RECORDED</span></td>
                                    </tr>
                                    <?php endforeach; if(empty($logs)): ?>
                                    <tr><td colspan="4" class="text-center py-5 opacity-50">Operational logs are currently synchronized at zero state.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
