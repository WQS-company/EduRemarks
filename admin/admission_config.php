<?php
// admin/admission_config.php - Admission Portal Configuration
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

// Global Feature Access Guard
if (strpos($active_school['feature_access'], 'ADMISSION_PORTAL') === false && $role !== 'super_admin') {
    $_SESSION['sys_error'] = "The Admission Portal configuration node is currently locked for your institution.";
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $active_school_id;

// Fetch Sessions for targeting
$sess_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = ? ORDER BY id DESC");
$sess_stmt->execute([$school_id]);
$sessions = $sess_stmt->fetchAll();

// Fetch current config
$config_stmt = $pdo->prepare("SELECT * FROM admission_forms WHERE school_id = ?");
$config_stmt->execute([$school_id]);
$config = $config_stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure Admission Portal | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .nav-tabs .nav-link { 
            color: #64748b; 
            background: transparent !important; 
            border: none; 
            margin-right: 10px;
            transition: 0.3s;
        }
        .nav-tabs .nav-link.active { 
            color: #1F3C88; 
            background: white !important; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .h-60px { height: 60px !important; }
        .rounded-top-4 { border-top-left-radius: 16px !important; border-top-right-radius: 16px !important; }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
            
            <div class="p-4">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admission_portal.php" class="text-decoration-none">Admission Hub</a></li>
                        <li class="breadcrumb-item active">Portal Configuration</li>
                    </ol>
                </nav>

                <div class="row g-4 justify-content-center">
                    <div class="col-lg-8">
                        <div class="glass-card shadow-lg">
                            <div class="p-4 border-bottom">
                                <h5 class="fw-900 mb-1"><i class="fas fa-tools me-2 text-primary"></i> Portal Architecture</h5>
                                <p class="extra-small text-muted mb-0">Configure your professional public-facing admission environment.</p>
                            </div>
                            <div class="p-0">
                                <ul class="nav nav-tabs border-0 bg-light px-4 pt-3" id="configTabs" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active fw-800 border-0 rounded-top-4 px-4 py-3" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                                            <i class="fas fa-cog me-2"></i> GENERAL
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link fw-800 border-0 rounded-top-4 px-4 py-3" id="fields-tab" data-bs-toggle="tab" data-bs-target="#fields" type="button">
                                            <i class="fas fa-list-check me-2"></i> FIELD CONTROLS
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link fw-800 border-0 rounded-top-4 px-4 py-3" id="style-tab" data-bs-toggle="tab" data-bs-target="#style" type="button">
                                            <i class="fas fa-palette me-2"></i> STYLE & BRAND
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link fw-800 border-0 rounded-top-4 px-4 py-3" id="messaging-tab" data-bs-toggle="tab" data-bs-target="#messaging" type="button">
                                            <i class="fas fa-comment-dots me-2"></i> MESSAGING
                                        </button>
                                    </li>
                                </ul>
                                
                                <form id="configForm">
                                    <div class="tab-content p-4" id="configTabsContent">
                                        <!-- General Tab -->
                                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                                            <div class="row g-3">
                                                <div class="col-12 mb-3">
                                                    <div class="form-check form-switch p-0 ps-5 bg-white rounded-4 p-3 border shadow-sm">
                                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo ($config['is_active'] ?? 1) ? 'checked' : ''; ?> style="width: 2.5em; height: 1.25em;">
                                                        <label class="form-check-label small fw-800 ms-2" for="isActive">Public Intake Gateway Active</label>
                                                        <div class="extra-small text-muted ms-2 ps-4">When disabled, the public link will refuse new transmissions.</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="small fw-800 mb-1">Institutional Portal Title</label>
                                                    <input type="text" class="form-control rounded-3 p-3 fw-700 h-60px bg-light border-0" name="title" value="<?php echo htmlspecialchars($config['title'] ?? 'Public Admission Portal'); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="small fw-800 mb-1">Target Academic Session</label>
                                                    <select class="form-select rounded-3 p-3 fw-700 h-60px bg-light border-0" name="target_session_id">
                                                        <option value="">Select Target Session</option>
                                                        <?php foreach ($sessions as $s): ?>
                                                            <option value="<?php echo $s['id']; ?>" <?php echo ($config['target_session_id'] ?? 0) == $s['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($s['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="small fw-800 mb-1">Institutional Slot Allocation</label>
                                                    <input type="number" class="form-control rounded-3 p-3 fw-700 h-60px bg-light border-0" name="max_slots" value="<?php echo htmlspecialchars($config['max_slots'] ?? 0); ?>" min="0">
                                                    <div class="extra-small text-muted mt-1 opacity-75">Set to <b>0</b> for unlimited applicants.</div>
                                                </div>
                                                <div class="col-12 mt-3">
                                                    <label class="small fw-800 mb-1">Welcome Narrative / Entrance Note</label>
                                                    <textarea class="form-control rounded-4 p-3 bg-light border-0" name="description" rows="4" placeholder="Welcome applicants to your institution..."><?php echo htmlspecialchars($config['description'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Fields Tab -->
                                        <div class="tab-pane fade" id="fields" role="tabpanel">
                                            <div class="alert alert-info rounded-4 border-0 extra-small fw-800">
                                                <i class="fas fa-info-circle me-2"></i> Primary identity fields (Name, Gender, Parent Phone) are strictly required for synchronizing with the central registry.
                                            </div>
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <div class="p-3 border rounded-4 bg-white shadow-sm d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div class="small fw-900 mb-0">Require Email Address</div>
                                                            <div class="extra-small text-muted">Guardian's email for transcripts.</div>
                                                        </div>
                                                        <div class="form-check form-switch m-0 p-0">
                                                            <input class="form-check-input" type="checkbox" name="require_email" <?php echo ($config['require_email'] ?? 1) ? 'checked' : ''; ?> style="width: 2.5em; height: 1.25em; float: none; margin: 0;">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 border rounded-4 bg-white shadow-sm d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div class="small fw-900 mb-0">Require Date of Birth</div>
                                                            <div class="extra-small text-muted">Mandatory age verification.</div>
                                                        </div>
                                                        <div class="form-check form-switch m-0 p-0">
                                                            <input class="form-check-input" type="checkbox" name="require_dob" <?php echo ($config['require_dob'] ?? 1) ? 'checked' : ''; ?> style="width: 2.5em; height: 1.25em; float: none; margin: 0;">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="p-3 border rounded-4 bg-white shadow-sm d-flex align-items-center justify-content-between">
                                                        <div>
                                                            <div class="small fw-900 mb-0">Require Residential Address</div>
                                                            <div class="extra-small text-muted">For institutional distance metrics.</div>
                                                        </div>
                                                        <div class="form-check form-switch m-0 p-0">
                                                            <input class="form-check-input" type="checkbox" name="require_address" <?php echo ($config['require_address'] ?? 1) ? 'checked' : ''; ?> style="width: 2.5em; height: 1.25em; float: none; margin: 0;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Style Tab -->
                                        <div class="tab-pane fade" id="style" role="tabpanel">
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <label class="small fw-800 mb-2">Institutional Brand Color</label>
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <input type="color" class="form-control form-control-color border-0 rounded-circle" name="theme_color" value="<?php echo htmlspecialchars($config['theme_color'] ?? '#1F3C88'); ?>" style="width: 60px; height: 60px; padding: 0;">
                                                        <div>
                                                            <div class="small fw-900">Primary Theme</div>
                                                            <div class="extra-small text-muted">This color will dominate the public portal buttons and highlights.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="small fw-800 mb-1">Support Contact Node (Email)</label>
                                                    <input type="email" class="form-control rounded-3 p-3 fw-700 h-60px bg-light border-0" name="contact_email" value="<?php echo htmlspecialchars($config['contact_email'] ?? ''); ?>" placeholder="admissions@school.edu">
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="small fw-800 mb-1">Support Contact Node (Phone)</label>
                                                    <input type="text" class="form-control rounded-3 p-3 fw-700 h-60px bg-light border-0" name="contact_phone" value="<?php echo htmlspecialchars($config['contact_phone'] ?? ''); ?>" placeholder="+234 ...">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Messaging Tab -->
                                        <div class="tab-pane fade" id="messaging" role="tabpanel">
                                            <div class="row g-4">
                                                <div class="col-12">
                                                    <label class="small fw-800 mb-1">Admission Requirements & Guidelines</label>
                                                    <textarea class="form-control rounded-4 p-3 bg-light border-0" name="requirements" rows="4" placeholder="List items needed for successful clearance..."><?php echo htmlspecialchars($config['requirements'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <label class="small fw-800 mb-1">Honorable Declaration / Terms</label>
                                                    <textarea class="form-control rounded-4 p-3 bg-light border-0" name="declaration_text" rows="3" placeholder="I hereby declare that all information provided is accurate..."><?php echo htmlspecialchars($config['declaration_text'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <label class="small fw-800 mb-1">Synchronization Success Narrative</label>
                                                    <textarea class="form-control rounded-4 p-3 bg-light border-0" name="success_message" rows="3" placeholder="Thank you! Your application has been successfully synchronized..."><?php echo htmlspecialchars($config['success_message'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 pt-0">
                                        <button type="button" class="btn btn-primary btn-lg rounded-pill fw-900 w-100 py-3 shadow-lg" onclick="saveConfig()">
                                            <i class="fas fa-save me-2"></i> COMMISSION PORTAL ARCHITECTURE
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="glass-card shadow-sm mb-4">
                            <div class="p-4 border-bottom bg-light">
                                <h6 class="fw-900 mb-0">System Intelligence</h6>
                            </div>
                            <div class="p-4 small">
                                <div class="mb-3">
                                    <div class="fw-800 text-primary mb-1"><i class="fas fa-bolt me-1"></i> Live Synchronization</div>
                                    <p class="text-muted mb-0">Changes saved here will reflect immediately on the public link. Disable the gateway during off-peak periods to prevent accidental applications.</p>
                                </div>
                                <div class="mb-0">
                                    <div class="fw-800 text-warning mb-1"><i class="fas fa-sms me-1"></i> Automated SMS Protocol</div>
                                    <p class="text-muted mb-0">Ensure you have sufficient credits to enable automated successful applicant alerts during the processing phase.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function saveConfig() {
            const formData = $('#configForm').serialize();
            Spinner.show('Updating Portal Architecture...');
            
            $.post('../ajax/admission_handler.php', {
                action: 'save_config',
                data: formData
            }, function(res) {
                Spinner.hide();
                if(res.success) {
                    Notif.show('Admission configuration synchronized', 'success');
                } else {
                    Notif.show(res.message, 'error');
                }
            }, 'json');
        }
    </script>
</body>
</html>
