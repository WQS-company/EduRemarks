<?php
// admin/id_cards.php
require_once '../includes/auth_check.php';
if (!$active_school) { header('Location: add_school.php'); exit(); }

$school_id = $active_school['id'];
$pageTitle = "ID Card Generator";

// Fetch templates
$templates = $pdo->query("SELECT * FROM id_card_templates WHERE is_active = 1 ORDER BY id")->fetchAll();

// Fetch credit cost per card
$cost_stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'credit_cost_id_card'");
$cost_stmt->execute();
$credit_per_card = (int)($cost_stmt->fetchColumn() ?: 10);

// Fetch students
$stud_stmt = $pdo->prepare("SELECT id, full_name, admission_no, student_class, gender, dob, image_path FROM students WHERE school_id = ? AND status = 'active' ORDER BY full_name");
$stud_stmt->execute([$school_id]);
$students = $stud_stmt->fetchAll();

// Fetch staff
$staff_stmt = $pdo->prepare("SELECT u.id, u.full_name, u.email, u.phone, u.profile_picture, sd.status, sd.created_at
    FROM staff_details sd JOIN users u ON sd.user_id = u.id
    WHERE sd.school_id = ? AND sd.status = 'active' ORDER BY u.full_name");
$staff_stmt->execute([$school_id]);
$staff_list = $staff_stmt->fetchAll();

// Fetch recent drafts
$drafts_stmt = $pdo->prepare("SELECT * FROM generated_id_cards WHERE school_id = ? ORDER BY created_at DESC LIMIT 5");
$drafts_stmt->execute([$school_id]);
$drafts = $drafts_stmt->fetchAll();

$school_credits = $active_school['credits'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> | EduRemarks</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../css/style.css">
<style>
:root { --primary-blue: #1F3C88; --secondary-blue: #2D6CDF; --accent-gold: #F4B400; }
.fw-900 { font-weight: 900; }
.fw-800 { font-weight: 800; }
.fw-700 { font-weight: 700; }
.extra-small { font-size: 0.65rem; }
.uppercase { text-transform: uppercase; }
.tracking-2 { letter-spacing: 2px; }

/* ── Glass Cards ── */
.glass-card { 
    background: #fff; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05);
    box-shadow: 0 10px 40px rgba(31, 60, 136, 0.05); 
}

/* ── Template Grid ── */
.tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.25rem; }
.tpl-card {
    position: relative; border-radius: 16px; overflow: hidden; cursor: pointer;
    border: 3px solid transparent; transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.tpl-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(31, 60, 136, 0.12); }
.tpl-card.selected { border-color: var(--accent-gold); box-shadow: 0 0 0 5px rgba(244, 180, 0, 0.15); }
.tpl-preview { height: 140px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
.tpl-check { 
    position: absolute; top: 12px; right: 12px; z-index: 5;
    width: 28px; height: 28px; background: var(--accent-gold); border-radius: 50%;
    display: none; align-items: center; justify-content: center; color: #000; font-size: 0.8rem; 
}
.tpl-card.selected .tpl-check { display: flex; }

/* ── Member Selector ── */
.member-list { max-height: 400px; overflow-y: auto; padding: 5px; }
.member-list::-webkit-scrollbar { width: 5px; }
.member-list::-webkit-scrollbar-thumb { background: rgba(31, 60, 136, 0.1); border-radius: 10px; }
.member-item {
    display: flex; align-items: center; gap: 1rem; padding: 12px 16px;
    border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: 0.2s;
    border: 1px solid transparent; background: #f8fafc;
}
.member-item:hover { background: #f1f5f9; transform: translateX(3px); }
.member-item.selected { background: #eff6ff; border-color: #3b82f6; }
.member-avatar-sm { width: 40px; height: 40px; border-radius: 12px; object-fit: cover; flex-shrink: 0; }
.member-check { width: 22px; height: 22px; border-radius: 8px; border: 2px solid #cbd5e0; display: flex; align-items: center; justify-content: center; }
.member-item.selected .member-check { background: #3b82f6; border-color: #3b82f6; color: #fff; }

/* ── Generation Loader ── */
#genLoader {
    position: fixed; inset: 0; background: rgba(15, 23, 42, 0.95); z-index: 9999;
    display: none; align-items: center; justify-content: center; flex-direction: column;
    backdrop-filter: blur(15px);
}
.spinner-box {
    width: 80px; height: 80px; border: 5px solid rgba(255, 255, 255, 0.1);
    border-top-color: var(--accent-gold); border-bottom-color: var(--secondary-blue);
    border-radius: 50%; animation: spin 1s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.gen-steps-container { display: flex; gap: 20px; margin-top: 40px; }
.gen-step { display: flex; flex-direction: column; align-items: center; opacity: 0.3; transition: 0.4s; }
.gen-step.active { opacity: 1; transform: scale(1.1); }
.gen-step.done { opacity: 0.8; }
.step-icon { 
    width: 40px; height: 40px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); 
    color: #fff; display: flex; align-items: center; justify-content: center; 
    font-weight: 800; margin-bottom: 10px; border: 2px solid transparent; 
}
.gen-step.active .step-icon { border-color: var(--accent-gold); background: rgba(244, 180, 0, 0.2); color: var(--accent-gold); }
.gen-step.done .step-icon { background: #10b981; border-color: #10b981; }
.step-label { font-size: 0.65rem; color: #fff; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; }

/* ── Draft Cards ── */
.draft-card {
    border-radius: 14px; border: 1px solid #e2e8f0; padding: 1rem;
    background: #fff; display: flex; align-items: center; gap: 1rem; transition: 0.2s;
}
.draft-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

/* ── Credit Meter ── */
.credit-meter { background: #1e293b; border-radius: 16px; padding: 1.5rem; color: #fff; }
.credit-bar { height: 8px; border-radius: 4px; background: #334155; overflow: hidden; margin: 10px 0; }
.credit-bar-fill { height: 100%; width: 0%; transition: 0.6s ease; }
.credit-bar-fill.success { background: #10b981; }
.credit-bar-fill.warn { background: #f59e0b; }
.credit-bar-fill.danger { background: #ef4444; }

@media(max-width: 576px) {
    .tpl-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .tpl-preview { height: 110px; }
    .gen-steps-container { flex-wrap: wrap; justify-content: center; }
}
</style>
</head>
<body class="bg-light">
<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<!-- GENERATION LOADER -->
<div id="genLoader">
    <div class="loader-content text-center">
        <div class="spinner-box mb-4"></div>
        <h4 class="fw-900 text-white mb-2" id="genStatus">Validating member records...</h4>
        <p class="text-white opacity-50 extra-small uppercase tracking-2 mb-4">Institutional Asset Pipeline in Progress</p>
        
        <div class="gen-steps-container">
            <div class="gen-step active" id="gstep1"><div class="step-icon">1</div><div class="step-label">Validation</div></div>
            <div class="gen-step" id="gstep2"><div class="step-icon">2</div><div class="step-label">Transaction</div></div>
            <div class="gen-step" id="gstep3"><div class="step-icon">3</div><div class="step-label">Assets</div></div>
            <div class="gen-step" id="gstep4"><div class="step-icon">4</div><div class="step-label">Rendering</div></div>
            <div class="gen-step" id="gstep5"><div class="step-icon">5</div><div class="step-label">Compiling</div></div>
            <div class="gen-step" id="gstep6"><div class="step-icon">6</div><div class="step-label">Archiving</div></div>
        </div>
    </div>
</div>

<?php if ($role === 'staff'): ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>
    <main class="sa-main-content">
<?php else: ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
<?php endif; ?>

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-800 mb-0"><i class="fas fa-id-card me-2 text-primary"></i>ID Card Generator</h3>
                <p class="text-muted small mb-0">Commission premium institutional identity cards for staff & students.</p>
            </div>
            <a href="id_cards_drafts.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                <i class="fas fa-archive me-2"></i>View All Drafts
            </a>
        </div>

        <div class="row g-4">
            <!-- LEFT: Template + Member Selection -->
            <div class="col-lg-8">

                <!-- STEP 1: Card Type -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:30px;height:30px;font-size:.85rem;">1</span>
                        <h6 class="fw-800 mb-0">Select Card Type</h6>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="card_type" id="typeStudent" value="student" checked>
                            <label class="btn btn-outline-primary w-100 py-3 rounded-3 fw-bold" for="typeStudent">
                                <i class="fas fa-user-graduate fa-lg d-block mb-2"></i>Students
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="card_type" id="typeStaff" value="staff">
                            <label class="btn btn-outline-success w-100 py-3 rounded-3 fw-bold" for="typeStaff">
                                <i class="fas fa-users-cog fa-lg d-block mb-2"></i>Staff
                            </label>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Template -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:30px;height:30px;font-size:.85rem;">2</span>
                        <h6 class="fw-800 mb-0">Choose Template</h6>
                        <span class="ms-auto extra-small text-muted">10 Premium Templates</span>
                    </div>
                    <div class="tpl-grid" id="templateGrid">
                        <?php
                        $tplStyles = [
                            'tpl_crimson_wave'    => 'background:linear-gradient(160deg,#c0392b 55%,#fff 55%)',
                            'tpl_azure_diamond'   => 'background:linear-gradient(135deg,#1a56db 50%,#bfdbfe 50%)',
                            'tpl_midnight_elite'  => 'background:linear-gradient(180deg,#0f172a 60%,#1e293b 60%)',
                            'tpl_sapphire_stripe' => 'background:repeating-linear-gradient(45deg,#2563eb,#2563eb 10px,#bfdbfe 10px,#bfdbfe 20px)',
                            'tpl_emerald_circuit' => 'background:linear-gradient(135deg,#059669 55%,#d1fae5 55%)',
                            'tpl_solar_wave'      => 'background:linear-gradient(160deg,#d97706 50%,#fef3c7 50%)',
                            'tpl_royal_prestige'  => 'background:linear-gradient(135deg,#7c3aed 55%,#ede9fe 55%)',
                            'tpl_navy_crest'      => 'background:linear-gradient(180deg,#1e3a5f 60%,#e0f2fe 60%)',
                            'tpl_crimson_diagonal'=> 'background:linear-gradient(135deg,#dc2626 45%,#1e293b 45%)',
                            'tpl_platinum_modern' => 'background:linear-gradient(160deg,#475569 50%,#f1f5f9 50%)',
                        ];
                        foreach($templates as $t):
                            $style = $tplStyles[$t['template_key']] ?? 'background:#1a56db';
                        ?>
                        <div class="tpl-card" data-key="<?php echo $t['template_key']; ?>" onclick="selectTemplate(this)">
                            <div class="tpl-check"><i class="fas fa-check"></i></div>
                            <div class="tpl-preview" style="<?php echo $style; ?>">
                                <!-- Mini ID card visual -->
                                <div style="width:80%;background:#fff;border-radius:6px;padding:6px;box-shadow:0 2px 8px rgba(0,0,0,.2);">
                                    <div style="height:22px;border-radius:3px;margin-bottom:5px;" class="<?php echo in_array($t['template_key'],['tpl_midnight_elite','tpl_crimson_diagonal','tpl_navy_crest']) ? 'bg-dark' : ''; ?>" style="background:<?php echo $t['preview_color']; ?>"></div>
                                    <div style="display:flex;gap:4px;align-items:center;">
                                        <div style="width:18px;height:22px;border-radius:3px;background:#e2e8f0;flex-shrink:0;"></div>
                                        <div style="flex:1;">
                                            <div style="height:5px;background:#334155;border-radius:2px;margin-bottom:3px;width:70%;"></div>
                                            <div style="height:3px;background:#94a3b8;border-radius:2px;width:50%;"></div>
                                        </div>
                                        <?php if($t['has_qr']): ?><div style="width:14px;height:14px;background:#222;border-radius:2px;"></div><?php endif; ?>
                                    </div>
                                    <?php if($t['has_barcode']): ?><div style="height:10px;background:repeating-linear-gradient(90deg,#222 0,#222 2px,#fff 2px,#fff 4px);border-radius:2px;margin-top:4px;"></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="p-2">
                                <div class="fw-bold small text-dark text-truncate"><?php echo $t['template_name']; ?></div>
                                <div class="d-flex gap-1 mt-1 flex-wrap">
                                    <?php if($t['has_qr']): ?><span class="tpl-badge bg-indigo-subtle text-indigo border" style="border-color:#6366f1;color:#6366f1;background:#eef2ff;">QR</span><?php endif; ?>
                                    <?php if($t['has_barcode']): ?><span class="tpl-badge border" style="color:#059669;background:#f0fdf4;border-color:#059669;">BAR</span><?php endif; ?>
                                    <span class="tpl-badge border" style="color:#64748b;background:#f8fafc;border-color:#e2e8f0;"><?php echo ucfirst($t['template_type']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 3: Select Members -->
                <div class="glass-card p-4">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:30px;height:30px;font-size:.85rem;">3</span>
                        <h6 class="fw-800 mb-0">Select Members</h6>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-sm btn-light rounded-pill px-3" onclick="selectAll()">All</button>
                            <button class="btn btn-sm btn-light rounded-pill px-3" onclick="clearAll()">Clear</button>
                        </div>
                    </div>
                    <!-- Search -->
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted small"></i></span>
                        <input type="text" id="memberSearch" class="form-control border-0 bg-light" placeholder="Search members..." oninput="filterMembers(this.value)">
                    </div>

                    <!-- Student List -->
                    <div id="studentPanel">
                        <?php if(empty($students)): ?>
                        <div class="text-center py-4 text-muted"><i class="fas fa-user-graduate fa-2x mb-2 opacity-25 d-block"></i>No active students found</div>
                        <?php else: ?>
                        <div class="member-list" id="studentList">
                        <?php foreach($students as $s): ?>
                        <div class="member-item" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['full_name']); ?>" onclick="toggleMember(this)">
                            <div class="member-avatar-sm">
                                <?php if(!empty($s['image_path'])): ?>
                                    <img src="../<?php echo $s['image_path']; ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <img src="../img/default_picture.png" style="width:100%;height:100%;object-fit:cover;">
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600 small text-truncate"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                <div class="extra-small text-muted"><?php echo htmlspecialchars($s['admission_no']); ?> · <?php echo htmlspecialchars($s['student_class']); ?></div>
                            </div>
                            <div class="member-check ms-auto"><i class="fas fa-check-circle text-primary opacity-0 transition"></i></div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Staff List -->
                    <div id="staffPanel" style="display:none;">
                        <?php if(empty($staff_list)): ?>
                        <div class="text-center py-4 text-muted"><i class="fas fa-users-cog fa-2x mb-2 opacity-25 d-block"></i>No active staff found</div>
                        <?php else: ?>
                        <div class="member-list" id="staffList">
                        <?php foreach($staff_list as $s): ?>
                        <div class="member-item" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['full_name']); ?>" onclick="toggleMember(this)">
                            <div class="member-avatar-sm">
                                <?php if(!empty($s['profile_picture'])): ?>
                                    <img src="../<?php echo $s['profile_picture']; ?>" style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <img src="../img/default_picture.png" style="width:100%;height:100%;object-fit:cover;">
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-600 small text-truncate"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                <div class="extra-small text-muted"><?php echo htmlspecialchars($s['email']); ?></div>
                            </div>
                            <div class="member-check ms-auto"><i class="fas fa-check-circle text-success opacity-0 transition"></i></div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Credit Summary + Generate -->
            <div class="col-lg-4">
                <!-- Credit Meter -->
                <div class="credit-meter mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="extra-small text-uppercase fw-800 opacity-50 mb-1">Available Credits</div>
                            <div class="h2 fw-900 mb-0" style="letter-spacing:-1px;"><?php echo number_format($school_credits); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="extra-small text-uppercase fw-800 opacity-50 mb-1">Per Card</div>
                            <div class="h4 fw-900 mb-0 text-warning"><?php echo $credit_per_card; ?></div>
                        </div>
                    </div>
                    <div class="credit-bar mb-2">
                        <div class="credit-bar-fill" id="creditBarFill" style="width:100%;"></div>
                    </div>
                    <div class="d-flex justify-content-between extra-small opacity-50">
                        <span id="selCountLabel">0 selected · 0 credits</span>
                        <span>Balance after: <strong id="balAfter"><?php echo number_format($school_credits); ?></strong></span>
                    </div>
                </div>

                <!-- Cost Breakdown -->
                <div class="glass-card p-4 mb-4">
                    <h6 class="fw-800 mb-3"><i class="fas fa-receipt me-2 text-warning"></i>Generation Summary</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="small text-muted">Selected Members</span>
                        <span class="fw-bold" id="summaryCount">0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="small text-muted">Template</span>
                        <span class="fw-bold small text-truncate ms-2" id="summaryTemplate" style="max-width:120px;">None selected</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="small text-muted">Cost per card</span>
                        <span class="fw-bold text-warning"><?php echo $credit_per_card; ?> cr</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-bold">Total Cost</span>
                        <span class="h5 fw-900 mb-0 text-danger" id="summaryTotal">0 cr</span>
                    </div>

                    <div id="insufficientWarn" class="alert alert-danger border-0 rounded-3 mt-3 small py-2 d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i>Insufficient credits.
                        <a href="pricing.php" class="fw-bold alert-link">Top Up</a>
                    </div>
                    <div id="noSelWarn" class="alert alert-warning border-0 rounded-3 mt-3 small py-2 d-none">
                        <i class="fas fa-info-circle me-2"></i>Select at least 1 member and 1 template.
                    </div>

                    <button class="btn btn-gold w-100 mt-3 rounded-3 fw-bold py-3 shadow-sm" id="generateBtn" onclick="startGeneration()">
                        <i class="fas fa-magic me-2"></i>Generate ID Cards
                    </button>
                    <div class="extra-small text-center text-muted mt-2">Output: Print-ready PDF · CR80 Plastic Card Format (300 DPI)</div>
                </div>

                <!-- Recent Drafts -->
                <?php if(!empty($drafts)): ?>
                <div class="glass-card p-4">
                    <h6 class="fw-800 mb-3"><i class="fas fa-archive me-2 text-indigo"></i>Recent Drafts</h6>
                    <?php foreach($drafts as $d): ?>
                    <div class="draft-card mb-2">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-600 small text-truncate"><?php echo ucfirst($d['card_type']); ?> · <?php echo $d['template_key']; ?></div>
                            <div class="extra-small text-muted"><?php echo count(explode(',',$d['member_ids'])); ?> cards · <?php echo $d['credits_used']; ?> cr · <?php echo date('M d, Y', strtotime($d['created_at'])); ?></div>
                        </div>
                        <?php if($d['status'] === 'generated' && $d['pdf_path']): ?>
                        <a href="../<?php echo $d['pdf_path']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3 flex-shrink-0">
                            <i class="fas fa-download"></i>
                        </a>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark rounded-pill">Processing</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <a href="id_cards_drafts.php" class="btn btn-sm btn-outline-secondary w-100 rounded-3 mt-2">View All</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
<?php if ($role !== 'staff'): ?>
</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
const CREDIT_PER_CARD = <?php echo $credit_per_card; ?>;
const SCHOOL_CREDITS  = <?php echo $school_credits; ?>;
let selectedTemplate  = null;
let selectedMembers   = new Set();
let currentCardType   = 'student';

// ── Card Type Toggle
document.querySelectorAll('input[name="card_type"]').forEach(r => r.addEventListener('change', function(){
    currentCardType = this.value;
    document.getElementById('studentPanel').style.display = this.value === 'student' ? '' : 'none';
    document.getElementById('staffPanel').style.display   = this.value === 'staff'   ? '' : 'none';
    selectedMembers.clear();
    document.querySelectorAll('.member-item.selected').forEach(el => el.classList.remove('selected'));
    document.querySelectorAll('.member-item .member-check i').forEach(i => i.style.opacity = 0);
    updateSummary();
}));

// ── Template Select
function selectTemplate(el) {
    document.querySelectorAll('.tpl-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedTemplate = el.dataset.key;
    const name = el.querySelector('.fw-bold.small').textContent;
    document.getElementById('summaryTemplate').textContent = name;
    updateSummary();
}

// ── Member Toggle
function toggleMember(el) {
    const id = el.dataset.id;
    if (selectedMembers.has(id)) {
        selectedMembers.delete(id);
        el.classList.remove('selected');
        el.querySelector('.member-check i').style.opacity = 0;
    } else {
        selectedMembers.add(id);
        el.classList.add('selected');
        el.querySelector('.member-check i').style.opacity = 1;
    }
    updateSummary();
}

function selectAll() {
    const panel = currentCardType === 'student' ? 'studentList' : 'staffList';
    document.querySelectorAll(`#${panel} .member-item`).forEach(el => {
        selectedMembers.add(el.dataset.id);
        el.classList.add('selected');
        el.querySelector('.member-check i').style.opacity = 1;
    });
    updateSummary();
}
function clearAll() {
    selectedMembers.clear();
    document.querySelectorAll('.member-item.selected').forEach(el => {
        el.classList.remove('selected');
        el.querySelector('.member-check i').style.opacity = 0;
    });
    updateSummary();
}

function filterMembers(q) {
    const panel = currentCardType === 'student' ? 'studentList' : 'staffList';
    document.querySelectorAll(`#${panel} .member-item`).forEach(el => {
        el.style.display = el.dataset.name.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

// ── Summary Update
function updateSummary() {
    const n = selectedMembers.size;
    const total = n * CREDIT_PER_CARD;
    const after = SCHOOL_CREDITS - total;
    const pct = Math.max(0, Math.min(100, (after / SCHOOL_CREDITS) * 100));

    document.getElementById('summaryCount').textContent = n;
    document.getElementById('summaryTotal').textContent = total.toLocaleString() + ' cr';
    document.getElementById('selCountLabel').textContent = `${n} selected · ${total.toLocaleString()} credits`;
    document.getElementById('balAfter').textContent = after.toLocaleString();

    const bar = document.getElementById('creditBarFill');
    bar.style.width = pct + '%';
    bar.className = 'credit-bar-fill' + (pct < 20 ? ' danger' : pct < 50 ? ' warn' : '');

    document.getElementById('insufficientWarn').classList.toggle('d-none', after >= 0);
    document.getElementById('noSelWarn').classList.toggle('d-none', n > 0 && selectedTemplate);
    document.getElementById('generateBtn').disabled = (after < 0 || n === 0 || !selectedTemplate);
}

// ── Generation Steps Animator
const genSteps = ['gstep1','gstep2','gstep3','gstep4','gstep5','gstep6'];
const stepTexts = [
    'Validating member records...','Processing credit transaction...','Generating QR & Barcode assets...',
    'Rendering ID card templates...','Compiling PDF document...','Saving draft to archive...'
];
function animateSteps(currentStep) {
    genSteps.forEach((id, i) => {
        const el = document.getElementById(id);
        if (i < currentStep) { el.className = 'gen-step done'; el.querySelector('.step-icon').innerHTML = '<i class="fas fa-check-circle text-success"></i>'; }
        else if (i === currentStep) { el.className = 'gen-step active'; }
        else { el.className = 'gen-step'; }
    });
    document.getElementById('genStatus').textContent = stepTexts[currentStep] || 'Finalizing...';
}

function startGeneration() {
    if (!selectedTemplate || selectedMembers.size === 0) {
        document.getElementById('noSelWarn').classList.remove('d-none'); return;
    }
    const total = selectedMembers.size * CREDIT_PER_CARD;
    if (total > SCHOOL_CREDITS) { return; }

    // Show loader
    const loader = document.getElementById('genLoader');
    loader.style.display = 'flex';
    let step = 0;
    animateSteps(0);

    // Animate steps
    const stepInterval = setInterval(() => {
        step++;
        if (step < genSteps.length) { animateSteps(step); }
        else { clearInterval(stepInterval); }
    }, 800);

    // Send AJAX
    const fd = new FormData();
    fd.append('template_key', selectedTemplate);
    fd.append('card_type', currentCardType);
    [...selectedMembers].forEach(id => fd.append('member_ids[]', id));

    fetch('../ajax/generate_id_cards.php', { method: 'POST', body: fd })
    .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); } 
        catch(e) { throw new Error('Server JSON error'); }
    })
    .then(d => {
        if (!d.success) {
            clearInterval(stepInterval);
            loader.style.display = 'none';
            Notif.show(d.message, 'error');
            return;
        }

        animateSteps(3); // Rendering
        
        animateSteps(3); // Rendering
        animateSteps(4); // Compiling JS PDF

        setTimeout(() => {
            // High resolution (~384DPI) CR80 strictly matched output
            const opt = {
                margin:       0,
                filename:     'idcards.pdf',
                image:        { type: 'jpeg', quality: 1.0 }, // max quality
                html2canvas:  { scale: 4, useCORS: true, allowTaint: true, logging: false },
                pagebreak:    { mode: 'css' },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Pass the pure HTML string directly to html2pdf. It internally manages a pristine visible iframe to capture accurately.
            html2pdf().set(opt).from(d.html_data).outputPdf('blob').then(blob => {
                animateSteps(5); // Saving to Drafts

                const upFd = new FormData();
                upFd.append('pdf_file', blob, 'id_cards.pdf');
                upFd.append('draft_id', d.draft_id);

                return fetch('../ajax/save_pdf_draft.php', { method: 'POST', body: upFd }).then(res => res.json());
            })
            .then(d2 => {
                 clearInterval(stepInterval);
                 loader.style.display = 'none';
                 if (d2.success) {
                     updateSummary(); // Refreshes available credits visually
                     showSuccess('ID Cards Generated!', d2.message, {
                         actions: [
                             { label: '<i class="fas fa-download me-1"></i>Download PDF', href: '../' + d2.pdf_path, class: 'btn-gold' },
                             { label: 'View Drafts', href: 'id_cards_drafts.php', class: 'btn-outline-secondary' }
                         ]
                     });
                 } else {
                     Notif.show(d2.message || 'Failed to finish draft', 'error');
                 }
            })
            .catch(err => {
                 clearInterval(stepInterval);
                 loader.style.display = 'none';
                 console.error('PDF Catch:', err);
                 Notif.show('PDF compilation failed. Please try again.', 'error');
            });
        }, 1500);
    })
    .catch((err) => {
        clearInterval(stepInterval);
        loader.style.display = 'none';
        Notif.show('Network generation failed. Please try again.', 'error');
    });
}
</script>
</body>
</html>
