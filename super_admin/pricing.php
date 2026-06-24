<?php
// super_admin/pricing.php - Professional Platform Economics & Resource Pricing Node
require_once 'auth_check.php';

// Fetch all packages
try {
    $packages = $pdo->query("SELECT * FROM pricing_packages ORDER BY price_naira ASC")->fetchAll();
} catch (Exception $e) {
    $packages = [];
    $db_error = $e->getMessage();
}

// Fetch Global Resource Pricing Settings
$qp = $pdo->query("SELECT * FROM platform_settings");
$pricing_nodes = [];
while($row = $qp->fetch()) {
    $pricing_nodes[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Economics | EduRemarks Control Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; --sa-gold: #F4B400; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; transition: 0.3s; }
        .glass-card { border-radius: 20px; border: none; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 30px; margin-bottom: 30px; }
        
        .resource-pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .pricing-input-node { background: #f8fafc; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .pricing-input-node:focus-within { border-color: var(--sa-blue); background: #fff; box-shadow: 0 4px 15px rgba(30, 64, 175, 0.05); }
        .pricing-input-node label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 10px; display: block; }
        
        .package-card { background: #fff; border-radius: 20px; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); overflow: hidden; position: relative; }
        .package-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        
        @media (max-width: 991px) { .sa-main-content { margin-left: 0; padding: 15px; } }
        
        @media (max-width: 576px) {
            .glass-card { padding: 18px; margin-bottom: 20px; }
            .resource-pricing-grid { grid-template-columns: 1fr; gap: 10px; }
            .pricing-header { 
                flex-direction: column !important; 
                align-items: flex-start !important; 
                gap: 12px; 
            }
            .pricing-header h5 { 
                white-space: nowrap; 
                font-size: 0.95rem !important; 
                margin-bottom: 0 !important;
            }
            .btn-sync { 
                width: 100% !important; 
                padding: 10px 15px !important; 
                font-size: 0.72rem !important;
                letter-spacing: 0.5px;
            }
            .pricing-input-node { padding: 15px; }
            .pricing-input-node label { font-size: 0.68rem; margin-bottom: 5px; }
            .pricing-input-node .fw-bold { font-size: 0.9rem !important; }

            .package-card { padding: 18px !important; }
            .package-card .bg-primary { width: 45px !important; height: 45px !important; }
            .package-card .bg-primary i { font-size: 1.1rem !important; }
            .package-card h5 { font-size: 1rem !important; margin-bottom: 2px !important; }
            .package-card .h2 { font-size: 1.25rem !important; }
            
            .header-flex-force { 
                display: flex !important; 
                flex-direction: row !important; 
                justify-content: space-between !important; 
                align-items: center !important; 
                gap: 5px !important;
            }
            .header-flex-force h5 { font-size: 0.9rem !important; white-space: nowrap; }
            .btn-node { 
                padding: 8px 12px !important; 
                font-size: 0.65rem !important; 
                white-space: nowrap !important;
                min-width: 110px;
                text-align: center;
            }
        }

        @media (max-width: 320px) {
            .pricing-header h5 { font-size: 0.85rem !important; }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="row mb-5">
        <div class="col-12">
            <h4 class="fw-900 mb-1">Platform Economics Node</h4>
            <p class="text-muted small">Synchronize global credit value and institutional monetization packages.</p>
        </div>
    </div>

    <!-- Global Resource Pricing Settings (MANDATORY UPDATE) -->
    <div class="glass-card mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4 pricing-header">
            <div>
                <h5 class="fw-800 text-blue mb-1"><i class="fas fa-microchip me-2"></i>Resource Billing Logic</h5>
                <p class="text-muted tiny-text mb-0">These settings dictate how institutions are billed for various activities.</p>
            </div>
            <button class="btn btn-warning rounded-pill px-4 fw-bold btn-sync btn-sm shadow-sm" form="resourcePricingForm">
                <i class="fas fa-sync-alt me-2 tiny-text"></i>GLOBAL SYNC
            </button> 
        </div>

        <form id="resourcePricingForm">
            <div class="resource-pricing-grid">
                <div class="pricing-input-node">
                    <label>Result Generation (Per Student)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" name="credit_student_result" class="form-control border-0 bg-transparent fw-bold" value="<?php echo $pricing_nodes['credit_student_result'] ?? 1; ?>">
                        <span class="input-group-text bg-white border-0 text-muted small fw-bold">CREDITS</span>
                    </div>
                </div>

                <div class="pricing-input-node">
                    <label>Answer Booklets (Per Booklet)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" name="credit_answer_sheet" class="form-control border-0 bg-transparent fw-bold" value="<?php echo $pricing_nodes['credit_answer_sheet'] ?? 1; ?>">
                        <span class="input-group-text bg-white border-0 text-muted small fw-bold">CREDITS</span>
                    </div>
                </div>

                <div class="pricing-input-node">
                    <label>CBT EXAM (Per Student)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" name="credit_cbt_exam" class="form-control border-0 bg-transparent fw-bold" value="<?php echo $pricing_nodes['credit_cbt_exam'] ?? 2; ?>">
                        <span class="input-group-text bg-white border-0 text-muted small fw-bold">CREDITS</span>
                    </div>
                </div>

                <div class="pricing-input-node">
                    <label>SMS Campaign (Per Recipient)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" name="credit_per_sms" class="form-control border-0 bg-transparent fw-bold" value="<?php echo $pricing_nodes['credit_per_sms'] ?? 10; ?>">
                        <span class="input-group-text bg-white border-0 text-muted small fw-bold">CREDITS</span>
                    </div>
                </div>

                <div class="pricing-input-node">
                    <label>ID Card Generation (Per Card)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" name="credit_cost_id_card" class="form-control border-0 bg-transparent fw-bold" value="<?php echo $pricing_nodes['credit_cost_id_card'] ?? 10; ?>">
                        <span class="input-group-text bg-white border-0 text-muted small fw-bold">CREDITS</span>
                    </div>
                </div>
                
                <div class="pricing-input-node">
                    <label>Admission Portal (Per Applicant)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" name="credit_admission_applicant" class="form-control border-0 bg-transparent fw-bold" value="<?php echo $pricing_nodes['credit_admission_applicant'] ?? 5; ?>">
                        <span class="input-group-text bg-white border-0 text-muted small fw-bold">CREDITS</span>
                    </div>
                </div>
            </div>
            <div class="mt-4 p-3 bg-light rounded-4 border-dashed small d-flex align-items-center">
                <i class="fas fa-info-circle text-blue h4 mb-0 me-3"></i>
                <div class="text-muted">
                    Institutional changes will be reflected in the <strong>Terms of Service</strong> page automatically. Values are synchronized across all schools.
                </div>
            </div>
        </form>
    </div>

    <!-- Monetization Packages -->
    <div class="d-flex justify-content-between align-items-center mb-4 header-flex-force">
        <h5 class="fw-800 text-blue mb-0"><i class="fas fa-tags me-2"></i>Monetization Nodes</h5>
        <button class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm btn-node" onclick="openNewPackageModal()">
            <i class="fas fa-plus me-1 extra-small"></i>Create New Node
        </button>
    </div>

    <div class="row g-4">
        <?php foreach($packages as $pkg): ?>
        <div class="col-xl-4 col-md-6">
            <div class="package-card p-4 border shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bolt h3 mb-0"></i>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm rounded-circle shadow-sm" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2 rounded-3">
                            <li><a class="dropdown-item small" href="#" onclick="editPackage(<?php echo htmlspecialchars(json_encode($pkg), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-edit me-2"></i> Edit Node</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item small text-danger" href="#" onclick="deletePackage(<?php echo $pkg['id']; ?>)"><i class="fas fa-trash-alt me-2"></i> Decommission</a></li>
                        </ul>
                    </div>
                </div>

                <h5 class="fw-900 text-blue mb-1"><?php echo htmlspecialchars($pkg['name']); ?></h5>
                <p class="text-muted small mb-4"><?php echo number_format($pkg['credits']); ?> Operational Units</p>
                
                <div class="h2 fw-900 text-dark">₦<?php echo number_format($pkg['price_naira']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Package Modal -->
<div class="modal fade" id="packageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="packageForm">
            <div class="modal-header border-0 p-4 bg-light">
                <h5 class="fw-bold mb-0" id="modalTitle">Configure Monetization Node</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="pkgId">
                <div class="mb-3">
                    <label class="small fw-bold mb-1">Package Name</label>
                    <input type="text" name="name" id="pkgName" class="form-control rounded-3" placeholder="e.g. Bronze Starter" required>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="small fw-bold mb-1">Credit Payload</label>
                        <input type="number" name="credits" id="pkgCredits" class="form-control rounded-3" required>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold mb-1">Price (NGN)</label>
                        <input type="number" name="price" id="pkgPrice" class="form-control rounded-3" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" id="submitBtn" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">SYNCHRONIZE PACKAGE</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const EDUREMARKS_CSRF_TOKEN = '<?php echo Security::csrf_token(); ?>';
    
    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': EDUREMARKS_CSRF_TOKEN }, data: { csrf_token: EDUREMARKS_CSRF_TOKEN } });
    });

    const pkgModal = new bootstrap.Modal(document.getElementById('packageModal'));

    // Global Resource Pricing Sync
    $('#resourcePricingForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Synchronizing billing logic...');
        $.post('../ajax/sa_save_resource_pricing.php', $(this).serialize(), function(res) {
            Spinner.hide();
            if(res.success) showSuccess('Billing Synced', res.message, { reload: true });
            else alert(res.message);
        }, 'json');
    });

    function openNewPackageModal() {
        document.getElementById('packageForm').reset();
        document.getElementById('pkgId').value = '';
        document.getElementById('modalTitle').innerText = 'New Pricing Package';
        pkgModal.show();
    }

    $('#packageForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Updating packages...');
        $.post('../ajax/sa_save_package.php', $(this).serialize(), function(res) {
            Spinner.hide();
            if(res.success) { pkgModal.hide(); showSuccess('Package Updated', 'Monetization node synchronized.', { reload: true }); }
            else alert(res.message);
        }, 'json');
    });

    function editPackage(pkg) {
        document.getElementById('modalTitle').innerText = 'Edit Pricing Package';
        document.getElementById('pkgId').value = pkg.id;
        document.getElementById('pkgName').value = pkg.name;
        document.getElementById('pkgCredits').value = pkg.credits;
        document.getElementById('pkgPrice').value = pkg.price_naira;
        pkgModal.show();
    }

    function deletePackage(id) {
        if(confirm('Terminate this pricing node?')) {
            Spinner.show('Deleting...');
            $.post('../ajax/sa_save_package.php', { id: id, delete: true }, function(res) {
                Spinner.hide();
                if(res.success) showSuccess('Deleted', 'Node decommissioned.', { reload: true });
            }, 'json');
        }
    }
</script>
</body>
</html>
