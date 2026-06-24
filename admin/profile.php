<?php
// admin/profile.php
require_once '../includes/auth_check.php';

// Ensure $active_school is always defined to avoid undefined variable errors
if (!isset($active_school) || !$active_school) {
    $active_school = [];
}

$school_sections = [];
if (!empty($active_school['id'])) {
    $stmtSec = $pdo->prepare("SELECT * FROM school_sections WHERE school_id = ? ORDER BY id ASC");
    $stmtSec->execute([$active_school['id']]);
    $school_sections = $stmtSec->fetchAll();
}

if ($role !== 'owner' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

// Fetch Billing Logic
$pending_billing_req = null;
if (!empty($active_school['id'])) {
    $stmtB = $pdo->prepare("SELECT * FROM billing_requests WHERE school_id = ? AND status = 'pending' LIMIT 1");
    $stmtB->execute([$active_school['id']]);
    $pending_billing_req = $stmtB->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Profile | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }
        .sa-main-content { padding: 25px !important; }
        
        /* Premium Navigation */
        .breadcrumb-pill { background: #fff; padding: 6px 16px; border-radius: 50px; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .breadcrumb-pill i { color: #2563eb; }

        .glass-card { background: #fff; border-radius: 16px; border: 1px solid #eef2f6; box-shadow: 0 4px 15px rgba(0,0,0,0.015); overflow: hidden; margin-bottom: 25px; transition: transform 0.3s; }
        .card-header-premium { padding: 20px 25px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff; }
        .card-header-premium h5 { margin: 0; font-size: 1rem; font-weight: 800; display: flex; align-items: center; gap: 10px; color: #0f172a; }
        .card-body-premium { padding: 25px; }

        /* Logo & Brand Styling */
        .brand-upload-zone { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 30px; text-align: center; position: relative; transition: 0.2s; cursor: pointer; background: #fcfdfe; }
        .brand-upload-zone:hover { border-color: #2563eb; background: #f1f5f9; }
        .logo-preview-box { width: 120px; height: 120px; margin: 0 auto 15px; border-radius: 12px; overflow: hidden; background: #fff; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .logo-preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        /* Signature Controls */
        .sig-dock { background: #f8fafc; border: 1.5px dashed #cbd5e1 !important; border-radius: 12px; height: 120px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; transition: 0.3s; }
        .sig-dock:hover { border-color: #2563eb !important; background: #fff; }
        .sig-placeholder { text-align: center; opacity: 0.4; }
        .sig-placeholder i { font-size: 2rem; margin-bottom: 5px; }
        .sig-placeholder span { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Section Manager */
        .section-node { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from{opacity: 0; transform: translateY(10px);} to{opacity:1; transform:translateY(0);} }
        .btn-rm-node { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 10px; color: #ef4444; border: 1px solid #fee2e2; background: #fef2f2; transition: 0.2s; }
        .btn-rm-node:hover { background: #fee2e2; transform: scale(0.95); }

        /* Infoside Styling */
        .info-pill { padding: 15px; border-radius: 12px; background: #f1f5f9; border-left: 4px solid #2563eb; margin-bottom: 15px; }
        .info-pill span { display: block; font-size: 0.65rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        .info-pill strong { font-size: 0.9rem; color: #0f172a; font-weight: 800; }

        .btn-sync { background: #0f172a; color: #fff; border-radius: 50px; padding: 12px 35px; font-weight: 800; font-size: 0.9rem; border: none; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2); transition: 0.3s; }
        .btn-sync:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(15, 23, 42, 0.3); background: #000; }

        @media (max-width: 768px) {
            .sa-main-content { padding: 15px !important; }
            .card-body-premium { padding: 20px; }
            .btn-sync { width: 100%; }
        }
    </style>
</head>
<body class="bg-light">

<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<div class="dashboard-wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    
    <main class="main-content">
        <?php include '../includes/dashboard_top_nav.php'; ?>
        
        <div class="sa-main-content">
            <!-- Navigation -->
            <div class="breadcrumb-pill">
                <i class="fas fa-university"></i>
                Institutional Hub / <span class="text-dark">Profile Settings</span>
            </div>

            <!-- Page Header -->
            <div class="mb-4">
                <h3 class="fw-900 mb-1" style="letter-spacing: -0.8px;">Identity & Branding</h3>
                <p class="text-muted small">Synchronize your school's global identity and academic protocols.</p>
            </div>

            <form id="profileForm" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">
                
                <div class="row g-4">
                    <!-- Main Content (Left) -->
                    <div class="col-xl-8 col-lg-7">
                        
                        <!-- Core Branding -->
                        <div class="glass-card">
                            <div class="card-header-premium">
                                <h5><i class="fas fa-palette text-primary"></i> Institutional Assets</h5>
                            </div>
                            <div class="card-body-premium">
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-4">
                                        <div class="brand-upload-zone" onclick="document.getElementById('logoInput').click()">
                                            <div class="logo-preview-box">
                                                <img src="../<?php echo !empty($active_school['logo_path']) ? htmlspecialchars($active_school['logo_path']) : 'img/logo.png'; ?>" id="logoPreview">
                                            </div>
                                            <span class="extra-small fw-800 text-primary text-uppercase tracking-1">Change Logo</span>
                                            <input type="file" name="school_logo" id="logoInput" class="d-none" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="row g-2">
                                            <div class="col-md-8">
                                                <label class="form-label small fw-800 text-muted uppercase">Institutional Name</label>
                                                <input type="text" class="form-control py-3 bg-light border-0 fw-bold shadow-none" value="<?php echo htmlspecialchars($active_school['school_name'] ?? ''); ?>" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-800 text-muted uppercase">Entity Type</label>
                                                <select name="school_type" class="form-select py-3 fw-bold border shadow-none">
                                                    <option value="Private" <?php echo (($active_school['school_type'] ?? '') == 'Private') ? 'selected' : ''; ?>>Private</option>
                                                    <option value="Public" <?php echo (($active_school['school_type'] ?? '') == 'Public') ? 'selected' : ''; ?>>Public</option>
                                                    <option value="Mission" <?php echo (($active_school['school_type'] ?? '') == 'Mission') ? 'selected' : ''; ?>>Mission</option>
                                                    <option value="International" <?php echo (($active_school['school_type'] ?? '') == 'International') ? 'selected' : ''; ?>>International</option>
                                                    <option value="Tertiary / Vocational" <?php echo (($active_school['school_type'] ?? '') == 'Tertiary / Vocational') ? 'selected' : ''; ?>>Tertiary / Vocational</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <label class="form-label small fw-800 text-muted uppercase">Motto / Slogan</label>
                                            <input type="text" name="motto" class="form-control py-3 fw-bold shadow-none border" value="<?php echo htmlspecialchars($active_school['motto'] ?? ''); ?>" placeholder="Enter official school slogan...">
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="form-label small fw-800 text-muted uppercase">Physical Campus Address</label>
                                    <textarea name="school_address" class="form-control p-3 fw-600 shadow-none border" rows="2" placeholder="Street, City, State, Country..."><?php echo htmlspecialchars($active_school['school_address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Contact & Comms -->
                        <div class="glass-card">
                            <div class="card-header-premium">
                                <h5><i class="fas fa-envelope-open-text text-warning"></i> Communication Channels</h5>
                            </div>
                            <div class="card-body-premium">
                                <div class="row g-3">
                                    <div class="col-md-12 mb-2">
                                        <label class="form-label small fw-800 text-muted uppercase">Official Primary Email</label>
                                        <input type="email" name="contact_email" class="form-control py-3 fw-600 shadow-none border" value="<?php echo htmlspecialchars($active_school['contact_email'] ?? ''); ?>" placeholder="principal@yourschool.com">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-800 text-muted uppercase">Admissions Line</label>
                                        <input type="text" name="phone_1" class="form-control py-3 fw-600 shadow-none border" value="<?php echo htmlspecialchars($active_school['phone_1'] ?? ''); ?>" placeholder="+123...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-800 text-muted uppercase">Administrative Support</label>
                                        <input type="text" name="phone_2" class="form-control py-3 fw-600 shadow-none border" value="<?php echo htmlspecialchars($active_school['phone_2'] ?? ''); ?>" placeholder="+123...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-800 text-muted uppercase">Emergency Line</label>
                                        <input type="text" name="phone_3" class="form-control py-3 fw-600 shadow-none border" value="<?php echo htmlspecialchars($active_school['phone_3'] ?? ''); ?>" placeholder="+123...">
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label small fw-800 text-muted uppercase">Detailed Bio / Description</label>
                                        <textarea name="description" class="form-control p-3 fw-600 shadow-none border" rows="4" placeholder="Briefly describe the institution..."><?php echo htmlspecialchars($active_school['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Signatures & Authentication -->
                        <div class="glass-card">
                            <div class="card-header-premium">
                                <h5><i class="fas fa-stamp text-info"></i> Security & Authentication Assets</h5>
                            </div>
                            <div class="card-body-premium">
                                <p class="text-muted extra-small fw-600 mb-4">Upload these to automate the orchestration of official reports and academic transcripts.</p>
                                <div class="row g-4">
                                    <?php 
                                    $sigNodes = [
                                        ['key' => 'proprietor_signature', 'label' => 'Proprietor Signature', 'id' => 'propSig'],
                                        ['key' => 'director_signature', 'label' => 'Director Signature', 'id' => 'dirSig'],
                                        ['key' => 'school_stamp', 'label' => 'Official School Stamp', 'id' => 'stamp']
                                    ];
                                    foreach($sigNodes as $node):
                                        $val = $active_school[$node['key']] ?? '';
                                    ?>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-800 text-muted uppercase mb-2"><?php echo $node['label']; ?></label>
                                        <div class="sig-dock" onclick="this.querySelector('input').click()">
                                            <?php if($val): ?>
                                                <img src="../<?php echo $val; ?>" id="<?php echo $node['id']; ?>Preview" style="max-height: 80%; max-width: 90%; object-fit: contain;">
                                            <?php else: ?>
                                                <div id="<?php echo $node['id']; ?>Placeholder" class="sig-placeholder">
                                                    <i class="fas fa-file-signature text-muted"></i>
                                                    <span>Click to Assign</span>
                                                </div>
                                                <img id="<?php echo $node['id']; ?>Preview" class="d-none" style="max-height: 80%; max-width: 90%; object-fit: contain;">
                                            <?php endif; ?>
                                            <input type="file" name="<?php echo $node['key']; ?>" class="d-none" onchange="previewSig(this, '<?php echo $node['id']; ?>Preview', '<?php echo $node['id']; ?>Placeholder')">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Academic Sections Directory -->
                        <div class="glass-card">
                            <div class="card-header-premium">
                                <h5><i class="fas fa-layer-group text-success"></i> Academic Roster Nodes</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" id="btnAddSection"><i class="fas fa-plus"></i> Add New Section</button>
                            </div>
                            <div class="card-body-premium">
                                <div id="sectionsContainer">
                                    <?php if (!empty($school_sections)): ?>
                                        <?php foreach ($school_sections as $sec): ?>
                                            <div class="section-node">
                                                <input type="text" class="form-control py-3 fw-600 border shadow-none" name="sections[]" value="<?php echo htmlspecialchars($sec['section_name']); ?>" placeholder="Primary, Secondary, etc.">
                                                <button type="button" class="btn-rm-node btn-remove-section"><i class="fas fa-trash-alt"></i></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="section-node">
                                            <input type="text" class="form-control py-3 fw-600 border shadow-none" name="sections[]" placeholder="e.g., Senior Secondary">
                                            <button type="button" class="btn-rm-node btn-remove-section" disabled><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>


                        <!-- Enrollment Logic -->
                        <div class="glass-card">
                            <div class="card-header-premium">
                                <h5><i class="fas fa-fingerprint text-danger"></i> Admission Orchestration</h5>
                            </div>
                            <div class="card-body-premium">
                                <div class="row g-4">
                                    <div class="col-md-5">
                                        <label class="form-label small fw-800 text-muted uppercase">ID Assignment Strategy</label>
                                        <select class="form-select py-3 fw-bold shadow-none" name="adm_no_type" id="admNoType">
                                            <option value="system" <?php echo (($active_school['adm_no_type'] ?? 'system') == 'system') ? 'selected' : ''; ?>>Random System Engine</option>
                                            <option value="pattern" <?php echo (($active_school['adm_no_type'] ?? 'system') == 'pattern') ? 'selected' : ''; ?>>Custom Sequence Logic</option>
                                            <option value="manual" <?php echo (($active_school['adm_no_type'] ?? 'system') == 'manual') ? 'selected' : ''; ?>>Legacy Manual Entry</option>
                                        </select>
                                    </div>
                                    <div class="col-md-7" id="patternControls" style="<?php echo (($active_school['adm_no_type'] ?? 'system') == 'pattern') ? '' : 'display:none;'; ?>">
                                        <div class="row g-2">
                                            <div class="col-8">
                                                <label class="form-label small fw-800 text-muted uppercase">Logic Template</label>
                                                <input type="text" name="adm_no_pattern" class="form-control py-3 fw-bold" value="<?php echo htmlspecialchars($active_school['adm_no_pattern'] ?? '{YEAR}/{ID}'); ?>">
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small fw-800 text-muted uppercase">Start Offset</label>
                                                <input type="number" name="adm_no_counter" class="form-control py-3 fw-bold" value="<?php echo htmlspecialchars($active_school['adm_no_counter'] ?? 1); ?>">
                                            </div>
                                        </div>
                                        <div class="extra-small text-muted mt-2 fw-600">
                                            Variables: <code>{YEAR}</code> Current Year | <code>{ID}</code> Increment | <code>{SCH}</code> Node ID.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Feature Visibility -->
                        <div class="glass-card">
                            <div class="card-header-premium">
                                <h5><i class="fas fa-eye text-primary"></i> Feature Transparency & Access</h5>
                            </div>
                            <div class="card-body-premium">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-4 border">
                                    <div>
                                        <h6 class="fw-800 mb-1">Staff Curriculum Visibility</h6>
                                        <p class="extra-small text-muted mb-0">Toggle whether your teaching staff can see the Institutional Curriculum Guide.</p>
                                    </div>
                                    <div class="form-check form-switch m-0 h4">
                                        <input class="form-check-input" type="checkbox" name="show_curriculum" id="showCurriculum" <?php echo ($active_school['show_curriculum'] ?? 1) ? 'checked' : ''; ?> style="cursor: pointer;">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div> <!-- End Col-8 -->

                    <!-- Info Sidebar (Right) -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="glass-card">
                            <div class="card-header-premium bg-light bg-opacity-50">
                                <h5><i class="fas fa-shield-alt text-premium-gold"></i> Node Metadata</h5>
                            </div>
                            <div class="card-body-premium">
                                <div class="info-pill">
                                    <span>Institutional Unique ID</span>
                                    <strong><?php echo $active_school['unique_id'] ?? 'ER-PENDING'; ?></strong>
                                </div>
                                <div class="info-pill" style="border-left-color: #10b981;">
                                    <span>Account Status</span>
                                    <strong class="text-success"><i class="fas fa-check-circle me-1"></i> Global Active</strong>
                                </div>
                                <div class="info-pill" style="border-left-color: #f59e0b;">
                                    <span>Registered Since</span>
                                    <strong><?php echo date('M d, Y', strtotime($active_school['created_at'] ?? 'now')); ?></strong>
                                </div>

                                <div class="bg-light p-3 rounded-4 mt-4 border">
                                    <h6 class="fw-800 small text-uppercase mb-2"><i class="fas fa-info-circle text-primary me-2"></i> Support Protocol</h6>
                                    <p class="extra-small text-muted mb-0" style="line-height: 1.6;">Your school name is currently locked. To request a structural re-branding, please open an administrative ticket in the **Support Hub**.</p>
                                </div>

                                <!-- Billing Status Card -->
                                <div class="glass-card mt-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 1px solid #e2e8f0 !important;">
                                    <div class="p-3 border-bottom d-flex align-items-center gap-2">
                                        <i class="fas fa-file-invoice-dollar text-primary"></i>
                                        <h6 class="fw-800 mb-0 uppercase tracking-1" style="font-size: 0.7rem;">Billing & Subscription</h6>
                                    </div>
                                    <div class="p-3">
                                        <?php if($active_school['billing_mode'] === 'subscription' && $active_school['subscription_active']): ?>
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <div class="bg-success bg-opacity-10 text-success rounded-3 p-2">
                                                    <i class="fas fa-calendar-check fa-lg"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-800 small">Institutional Plan</div>
                                                    <div class="extra-small text-success fw-bold uppercase"><?php echo htmlspecialchars($active_school['subscription_type']); ?></div>
                                                </div>
                                            </div>
                                            <a href="print_agreement.php?id=<?php echo $active_school['id']; ?>" target="_blank" class="btn btn-sm btn-outline-success w-100 rounded-pill fw-bold">
                                                <i class="fas fa-print me-1"></i> Print Agreement Slip
                                            </a>
                                        <?php elseif($pending_billing_req): ?>
                                            <div class="text-center py-2">
                                                <div class="spinner-grow spinner-grow-sm text-warning mb-2"></div>
                                                <div class="fw-800 small text-warning">Approval Pending</div>
                                                <div class="extra-small text-muted px-2">Administrative review for <strong><?php echo htmlspecialchars($pending_billing_req['requested_plan']); ?></strong> is in progress.</div>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                                                    <i class="fas fa-bolt fa-lg"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-800 small">Credit-Based Mode</div>
                                                    <div class="extra-small text-muted fw-bold uppercase">Pay-As-You-Measure</div>
                                                </div>
                                            </div>
                                            <a href="../pricing.php" class="btn btn-sm btn-primary w-100 rounded-pill fw-bold">
                                                <i class="fas fa-rocket me-1"></i> Request Transition
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mt-5 text-center">
                                    <button type="submit" class="btn-sync">
                                        <i class="fas fa-sync-alt me-2"></i> Synchronize Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- End Row -->
            </form>
        </div>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    // Signature & Asset Preview Logic
    function previewSig(input, imgId, placeholderId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById(imgId);
                const ph = document.getElementById(placeholderId);
                if (img) {
                    img.src = e.target.result;
                    img.classList.remove('d-none');
                }
                if (ph) ph.style.display = 'none';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Logo Change Orchestrator
    const logoInput = document.getElementById('logoInput');
    const logoPreview = document.getElementById('logoPreview');
    logoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => { logoPreview.src = ev.target.result; };
            reader.readAsDataURL(file);
        }
    });

    // Patterns Toggle
    document.getElementById('admNoType').addEventListener('change', function() {
        document.getElementById('patternControls').style.display = (this.value === 'pattern') ? 'block' : 'none';
    });

    // Dynamic Academic Sections
    const container = document.getElementById('sectionsContainer');
    document.getElementById('btnAddSection').addEventListener('click', () => {
        const node = document.createElement('div');
        node.className = 'section-node';
        node.innerHTML = `
            <input type="text" class="form-control py-3 fw-600 border shadow-none" name="sections[]" placeholder="Section Name">
            <button type="button" class="btn-rm-node btn-remove-section"><i class="fas fa-trash-alt"></i></button>
        `;
        container.appendChild(node);
        node.querySelector('.btn-remove-section').onclick = () => node.remove();
    });

    // Remove logic for existing nodes
    container.addEventListener('click', (e) => {
        if (e.target.closest('.btn-remove-section')) {
            const row = e.target.closest('.section-node');
            if (container.querySelectorAll('.section-node').length > 1 || row.querySelector('input').value) {
                row.remove();
                if (container.querySelectorAll('.section-node').length === 0) {
                     document.getElementById('btnAddSection').click(); 
                }
            }
        }
    });

    // Global Async Sync
    const profileForm = document.getElementById('profileForm');
    profileForm.onsubmit = (e) => {
        e.preventDefault();
        Spinner.show('Synchronizing Institutional State...');
        fetch('../ajax/save_school_profile.php', { method: 'POST', body: new FormData(profileForm) })
            .then(r => r.json()).then(d => {
                Spinner.hide();
                if (d.success) {
                    showSuccess('Nexus Updated!', d.message, { reload: true });
                } else {
                    Notif.show(d.message, 'error');
                }
            }).catch(() => {
                Spinner.hide();
                Notif.show('Cloud Synchronization Failed.', 'error');
            });
    };
</script>
</body>
</html>

