<?php
// admin/invoice.php - Professional Institutional Invoice Generation
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    die("Unauthorized Ledger Access. Role detected: " . htmlspecialchars($role)); 
}

$ref = $_GET['ref'] ?? '';
if (empty($ref)) { die("Institutional Reference ID missing."); }

// Fetch Payment Evidence
$stmt = $pdo->prepare("SELECT p.*, pkg.name as package_name, pkg.credits as pkg_credits, s.school_name, s.unique_id as school_id_str 
                      FROM platform_payments p 
                      JOIN pricing_packages pkg ON p.package_id=pkg.id 
                      JOIN schools s ON p.school_id = s.id
                      WHERE p.reference = ? AND p.school_id = ?");
$stmt->execute([$ref, $active_school_id]);
$invoice = $stmt->fetch();

if (!$invoice) { die("Transaction identification failed on this node."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice | <?php echo $ref; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none; } body { background: white; } }
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .invoice-paper { background: white; width: 210mm; min-height: 297mm; margin: 30px auto; padding: 25mm; box-shadow: 0 0 30px rgba(0,0,0,0.1); border-radius: 8px; border: 1px solid #e2e8f0; }
        .invoice-header { border-bottom: 2px solid #1F3C88; padding-bottom: 30px; margin-bottom: 50px; }
        .invoice-title { font-size: 2.225rem; font-weight: 900; color: #1F3C88; letter-spacing: -1px; }
        .invoice-details { margin-bottom: 50px; display: grid; grid-template-columns: 1fr 1fr; }
        .detail-item { font-size: 0.85rem; color: #64748b; margin-bottom: 15px; }
        .detail-item strong { color: #1e293b; font-weight: 800; display: block; font-size: 1rem; }
        
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 50px; }
        .invoice-table th { background: #f8fafc; padding: 15px; border-bottom: 2px solid #e2e8f0; font-size: 0.7rem; text-transform: uppercase; font-weight: 900; color: #1F3C88; }
        .invoice-table td { padding: 20px 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; font-weight: 500; }
        
        .total-section { float: right; width: 300px; text-align: right; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; }
        .total-row.grand { border-bottom: none; margin-top: 15px; border-top: 2px solid #1F3C88; padding-top: 20px; }
        .grand-title { font-size: 1.1rem; font-weight: 900; color: #1F3C88; }
        .grand-value { font-size: 1.5rem; font-weight: 900; color: #1F3C88; }

        .invoice-footer { margin-top: 200px; border-top: 1px solid #f1f5f9; padding-top: 30px; font-size: 0.75rem; color: #94a3b8; text-align: center; }
        .stamp { font-size: 1rem; font-weight: 900; color: #22c55e; border: 3px solid #22c55e; padding: 10px 20px; text-transform: uppercase; display: inline-block; transform: rotate(-15deg); border-radius: 10px; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="no-print text-center mt-5 mb-4">
        <button class="btn btn-primary rounded-pill px-5 fw-900 shadow" onclick="window.print()">
            <i class="fas fa-print me-2"></i> PRINT INVOICE NODES
        </button>
        <button class="btn btn-outline-secondary rounded-pill px-4 ms-3" onclick="window.close()">CLOSE WINDOW</button>
    </div>

    <div class="invoice-paper animate-fade-in shadow-lg">
        <div class="invoice-header d-flex justify-content-between align-items-end">
            <div>
                <img src="../img/logo.png" style="height: 45px; margin-bottom: 20px;">
                <div class="invoice-title">BILLING INVOICE</div>
            </div>
            <div class="text-end">
                <div class="detail-item">
                    <span>Reference ID:</span>
                    <strong>#<?php echo $invoice['reference']; ?></strong>
                </div>
                <div class="detail-item">
                    <span>Transaction Date:</span>
                    <strong><?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></strong>
                </div>
            </div>
        </div>

        <div class="invoice-details">
            <div>
                <div class="detail-item">
                    <span>Issued To:</span>
                    <strong><?php echo htmlspecialchars($invoice['school_name']); ?></strong>
                    <div class="opacity-75"><?php echo $invoice['school_id_str']; ?></div>
                </div>
            </div>
            <div class="text-end">
                <div class="detail-item">
                    <span>Payment Status:</span>
                    <strong class="text-success"><?php echo strtoupper($invoice['status']); ?></strong>
                    <div class="small opacity-50">Authorized via Paystack Gateway</div>
                </div>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Operational Service / Package</th>
                    <th class="text-center">Resource Quantity</th>
                    <th class="text-end">Line Total (₦)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($invoice['package_name']); ?></strong>
                        <div class="tiny-text opacity-75 mt-1 text-muted">Tier-level institutional credit replenishment.</div>
                    </td>
                    <td class="text-center fw-800"><?php echo number_format($invoice['pkg_credits']); ?> Credits</td>
                    <td class="text-end fw-900 text-blue">₦<?php echo number_format($invoice['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <span class="text-muted small">Sub-Total:</span>
                <span class="fw-bold">₦<?php echo number_format($invoice['amount'], 2); ?></span>
            </div>
            <div class="total-row">
                <span class="text-muted small">VAT / Service Tax (0%):</span>
                <span class="fw-bold">₦0.00</span>
            </div>
            <div class="total-row grand">
                <span class="grand-title">GRAND TOTAL:</span>
                <span class="grand-value">₦<?php echo number_format($invoice['amount'], 2); ?></span>
            </div>

            <div class="stamp-container text-end mt-4">
                <div class="stamp">OFFICIALLY PAID</div>
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="invoice-footer">
            <p class="mb-1">This is a system-generated financial document for the EduRemarks Institutional Network.</p>
            <p class="mb-0">Powered by EduRemarks SaaS • Innovating Educational Management Excellence</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
</body>
</html>
