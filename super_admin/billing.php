<?php
// super_admin/billing.php - Global Financial Hub
// Corrected include path and added safety checks for financial data retrieval.
require_once 'auth_check.php';

// Safe query execution to handle missing/empty tables
function safeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (Exception $e) {
        return null;
    }
}

// 1. Fetch Aggregated Financial Data
$total_revenue = safeQuery($pdo, "SELECT SUM(amount) FROM platform_payments WHERE status='success'")->fetchColumn() ?? 0;
$total_tx = safeQuery($pdo, "SELECT COUNT(*) FROM platform_payments")->fetchColumn() ?? 0;
$success_tx = safeQuery($pdo, "SELECT COUNT(*) FROM platform_payments WHERE status='success'")->fetchColumn() ?? 0;
$failed_tx = safeQuery($pdo, "SELECT COUNT(*) FROM platform_payments WHERE status='failed'")->fetchColumn() ?? 0;

// 2. Fetch Weekly Revenue (Last 7 Days) for a mini trend
$weekly_revenue = safeQuery($pdo, "SELECT SUM(amount) FROM platform_payments WHERE status='success' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn() ?? 0;

// 3. Fetch All Transactions with School names
$stmt = safeQuery($pdo, "
    SELECT p.*, s.school_name, s.unique_id as school_uid 
    FROM platform_payments p 
    JOIN schools s ON p.school_id = s.id 
    ORDER BY p.created_at DESC
");
$payments = $stmt ? $stmt->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Hub | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 220px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .stat-card { border-radius: 12px; border: none; padding: 25px; color: white; transition: 0.3s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-label { font-size: 0.7rem; font-weight: 700; opacity: 0.8; text-transform: uppercase; }
        .stat-value { font-size: 1.5rem; font-weight: 800; margin-top: 5px; }

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
            <h4 class="fw-800 mb-0">Financial Orchestration</h4>
            <p class="text-muted small">Monitor global platform revenue and transaction integrity</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm"><i class="fas fa-file-export me-1"></i> EXPORT LEDGER</button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="stat-card glass-card" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                <div class="stat-label">Aggregated Revenue</div>
                <div class="stat-value">₦<?php echo number_format($total_revenue, 2); ?></div>
                <div class="tiny-text opacity-75">+ Rolling Growth</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card glass-card" style="background: linear-gradient(135deg, #059669, #10b981);">
                <div class="stat-label">Validated Flow</div>
                <div class="stat-value"><?php echo number_format($success_tx); ?></div>
                <div class="tiny-text opacity-75">Successful Nodes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card glass-card" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                <div class="stat-label">Refused Handshakes</div>
                <div class="stat-value"><?php echo number_format($failed_tx); ?></div>
                <div class="tiny-text opacity-75">Failed Transactions</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card glass-card" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                <div class="stat-label">Total Integrity</div>
                <div class="stat-value"><?php echo number_format($total_tx); ?></div>
                <div class="tiny-text opacity-75">Global Ledger Count</div>
            </div>
        </div>
    </div>

    <!-- Transaction Ledger -->
    <div class="glass-card p-4 border-0 shadow-lg">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-800 mb-0">Global Transaction Ledger</h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light rounded-pill px-3 fw-bold"><i class="fas fa-file-export me-1"></i> Export CSV</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr class="text-muted small uppercase fw-bold">
                        <th>Date / Reference</th>
                        <th>Institution</th>
                        <th>Resource Node</th>
                        <th>Value</th>
                        <th>Flow Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($payments)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 opacity-50">No platform transactions recorded yet.</td>
                    </tr>
                    <?php else: foreach($payments as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-600 small"><?php echo date('M d, Y - h:i A', strtotime($p['created_at'])); ?></div>
                            <div class="tiny-text text-muted"><?php echo htmlspecialchars($p['reference']); ?> &bull; <?php echo htmlspecialchars($p['payment_method'] ?? 'Online'); ?></div>
                        </td>
                        <td>
                            <div class="fw-800 small text-blue"><?php echo htmlspecialchars($p['school_name']); ?></div>
                            <div class="tiny-text opacity-75"><?php echo htmlspecialchars($p['school_uid']); ?></div>
                        </td>
                        <td>
                            <div class="small fw-bold text-primary">+ <?php echo number_format($p['credits_awarded']); ?> Credits</div>
                            <div class="tiny-text opacity-75">Scale Package Node</div>
                        </td>
                        <td>
                            <div class="fw-900 text-dark">₦<?php echo number_format($p['amount'], 2); ?></div>
                        </td>
                        <td>
                            <?php if($p['status'] == 'success'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 rounded-pill border border-success border-opacity-25">Validated</span>
                            <?php elseif($p['status'] == 'failed'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger px-3 rounded-pill border border-danger border-opacity-25">Failed Attempt</span>
                            <?php else: ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 rounded-pill border border-warning border-opacity-25">Awaiting Flow</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
