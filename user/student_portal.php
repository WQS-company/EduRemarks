<?php
// user/student_portal.php
require_once '../includes/auth_check.php';
if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}
if (!$active_school) { header('Location: dashboard.php'); exit(); }

$school_id = $active_school['id'];

// Check if the feature is enabled
$portal_enabled = hasFeature('STUDENT_PORTAL');

// Fetch classes
$cls_stmt = $pdo->prepare("SELECT id, name, code FROM classes WHERE school_id = ? ORDER BY name");
$cls_stmt->execute([$school_id]);
$classes = $cls_stmt->fetchAll();

// Get class filter
$class_filter = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Fetch students with class info and portal status
if ($class_filter) {
    $stu_stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.admission_no, s.gender, s.image_path, 
               s.student_password, s.portal_active, s.guardian_name, s.guardian_phone,
               c.name AS class_name, c.id AS class_id
        FROM students s
        JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
        JOIN classes c ON c.id = sc.class_id
        WHERE s.school_id = ? AND sc.class_id = ?
        ORDER BY s.full_name ASC
    ");
    $stu_stmt->execute([$school_id, $class_filter]);
} else {
    $stu_stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.admission_no, s.gender, s.image_path,
               s.student_password, s.portal_active, s.guardian_name, s.guardian_phone,
               c.name AS class_name, c.id AS class_id
        FROM students s
        LEFT JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
        LEFT JOIN classes c ON c.id = sc.class_id
        WHERE s.school_id = ?
        ORDER BY s.full_name ASC
    ");
    $stu_stmt->execute([$school_id]);
}
$students = $stu_stmt->fetchAll();

// Portal stats
$total_students = count($students);
$active_portal = 0;
$has_password = 0;
foreach ($students as $st) {
    if ($st['portal_active']) $active_portal++;
    if (!empty($st['student_password'])) $has_password++;
}

// Get selected class name
$selected_class_name = 'All Classes';
if ($class_filter) {
    foreach ($classes as $c) {
        if ($c['id'] == $class_filter) {
            $selected_class_name = $c['name'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Management | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        .portal-hero {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 60%, #1e3a5f 100%);
            color: white;
            border-radius: 24px;
            padding: 30px;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .portal-hero::before {
            content: '';
            position: absolute;
            right: -60px;
            top: -60px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .portal-hero::after {
            content: '';
            position: absolute;
            right: 80px;
            bottom: -40px;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(244,180,0,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .stat-mini {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 18px 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-mini .stat-val {
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: -1px;
        }
        .stat-mini .stat-lbl {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.6;
            font-weight: 700;
        }
        
        .glass-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 20px rgba(0,0,0,0.015);
        }
        
        .student-portal-row {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            transition: 0.2s;
            gap: 12px;
        }
        .student-portal-row:hover { background: #f8fafc; }
        .student-portal-row:last-child { border-bottom: none; }
        
        .stu-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .stu-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .portal-badge {
            font-size: 0.6rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .portal-badge.active { background: #ecfdf5; color: #059669; }
        .portal-badge.inactive { background: #fef2f2; color: #dc2626; }
        .portal-badge.no-pass { background: #fef9c3; color: #a16207; }
        
        .action-btn {
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }
        .action-btn:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }
        .action-btn.btn-generate { border-color: #10b981; color: #10b981; }
        .action-btn.btn-generate:hover { background: #ecfdf5; }
        .action-btn.btn-deactivate { border-color: #ef4444; color: #ef4444; }
        .action-btn.btn-deactivate:hover { background: #fef2f2; }
        
        .portal-disabled-banner {
            background: linear-gradient(135deg, #fef3c7, #fef9c3);
            border: 1px solid #fcd34d;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
        }
        
        .class-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 50px;
            background: #fff;
            border: 1.5px solid #e2e8f0;
            font-weight: 700;
            font-size: 0.78rem;
            color: #64748b;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
        }
        .class-filter-pill:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }
        .class-filter-pill.active { background: #2563eb; color: #fff; border-color: #2563eb; }
        
        .credential-modal-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .copy-btn {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #2563eb;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 0.7rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .copy-btn:hover { background: #2563eb; color: #fff; }
        
        .bulk-bar {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border-radius: 12px;
            padding: 12px 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
        }
        .bulk-bar.visible { display: flex; animation: slideDown 0.3s ease-out; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-10px);} to{opacity:1;transform:translateY(0);} }
        
        .toggle-switch {
            position: relative;
            width: 40px;
            height: 22px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #cbd5e1;
            border-radius: 22px;
            transition: 0.3s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background: #10b981; }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(18px); }
        
        .search-wrapper {
            position: relative;
        }
        .search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .search-wrapper input {
            padding-left: 38px;
            border-radius: 50px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .search-wrapper input:focus {
            background: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* ── Print styles for credentials ── */
        @media print {
            body * { visibility: hidden; }
            #credentialsPrintArea, #credentialsPrintArea * { visibility: visible; }
            #credentialsPrintArea { 
                position: absolute; left: 0; top: 0; width: 100%;
                padding: 20px;
            }
        }

        /* ── Mobile responsiveness ── */
        @media (max-width: 768px) {
            .portal-hero { padding: 20px; border-radius: 16px; }
            .portal-hero h3 { font-size: 1.1rem !important; }
            .stat-mini { padding: 12px 14px; }
            .stat-mini .stat-val { font-size: 1.3rem; }
            .student-portal-row { padding: 10px 14px; flex-wrap: wrap; gap: 8px; }
            .stu-avatar { width: 36px; height: 36px; }
            .student-info { min-width: 0; }
            .student-info .fw-700 { font-size: 0.82rem !important; }
            .student-actions { width: 100%; justify-content: flex-end !important; margin-top: 4px; }
            .action-btn { padding: 5px 10px; font-size: 0.65rem; }
            .class-pills-scroll { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 6px; }
            .class-pills-scroll::-webkit-scrollbar { height: 0; }
            .bulk-bar { flex-direction: column; gap: 10px; text-align: center; }
        }
        @media (max-width: 480px) {
            .portal-hero { padding: 16px; }
            .stat-mini .stat-val { font-size: 1.1rem; }
            .stat-mini .stat-lbl { font-size: 0.55rem; }
        }
    </style>
</head>
<body class="bg-light">
<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>
<?php include '../includes/notifications.php'; ?>

<?php if ($role === 'staff'): ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>
    <main class="sa-main-content p-3 p-md-4">
<?php else: ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content p-3 p-md-4">
            <?php include '../includes/dashboard_top_nav.php'; ?>
<?php endif; ?>

    <!-- Feature disabled banner -->
    <?php if (!$portal_enabled && $role !== 'super_admin'): ?>
    <div class="portal-disabled-banner">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-warning bg-opacity-25 text-warning rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px; height:48px;">
                <i class="fas fa-lock fa-lg"></i>
            </div>
            <div>
                <h6 class="fw-900 text-dark mb-1">Student Portal Not Activated</h6>
                <p class="mb-0 text-muted small fw-600">This premium feature has not been enabled for your institution. Contact your system administrator to activate the Student Portal.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <div class="portal-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4 position-relative" style="z-index:1;">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="bg-white bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width:42px; height:42px;">
                        <i class="fas fa-user-shield fa-lg"></i>
                    </div>
                    <h3 class="fw-900 mb-0" style="letter-spacing: -0.5px;">Student Portal Management</h3>
                </div>
                <p class="mb-0 opacity-50 small fw-600"><?php echo htmlspecialchars($active_school['school_name']); ?> • Credential & Access Control Hub</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($class_filter): ?>
                <button class="btn btn-sm rounded-pill fw-800 px-3 shadow-sm" style="background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid rgba(16,185,129,0.3);" onclick="bulkGenerateClass(<?php echo $class_filter; ?>)" id="bulkClassBtn">
                    <i class="fas fa-key me-1"></i> Generate All (<?php echo htmlspecialchars($selected_class_name); ?>)
                </button>
                <?php endif; ?>
                <a href="students.php" class="btn btn-sm rounded-pill fw-800 px-3" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
                    <i class="fas fa-arrow-left me-1"></i> Students
                </a>
            </div>
        </div>
        
        <div class="row g-3 position-relative" style="z-index:1;">
            <div class="col-4 col-md-3">
                <div class="stat-mini">
                    <div class="stat-val"><?php echo $total_students; ?></div>
                    <div class="stat-lbl">Total Students</div>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="stat-mini">
                    <div class="stat-val text-success"><?php echo $has_password; ?></div>
                    <div class="stat-lbl">With Credentials</div>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="stat-mini">
                    <div class="stat-val" style="color: #60a5fa;"><?php echo $active_portal; ?></div>
                    <div class="stat-lbl">Portal Active</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="stat-mini">
                    <div class="stat-val" style="color: #f4b400;">
                        <?php echo $total_students > 0 ? round(($active_portal / $total_students) * 100) : 0; ?>%
                    </div>
                    <div class="stat-lbl">Activation Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Filter Pills -->
    <div class="mb-4 d-flex align-items-center gap-2 class-pills-scroll">
        <span class="fw-800 small text-muted text-uppercase me-1" style="letter-spacing:1px; font-size:0.65rem;">Filter:</span>
        <a href="student_portal.php" class="class-filter-pill <?php echo !$class_filter ? 'active' : ''; ?>">
            <i class="fas fa-globe" style="font-size:0.7rem;"></i> All Classes
        </a>
        <?php foreach ($classes as $c): ?>
        <a href="student_portal.php?class_id=<?php echo $c['id']; ?>" class="class-filter-pill <?php echo $class_filter == $c['id'] ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($c['name']); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="glass-card p-3 p-md-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check m-0 d-flex align-items-center gap-2 bg-light py-2 px-3 rounded-pill border">
                    <input type="checkbox" id="checkAll" class="form-check-input m-0" onchange="toggleAllChecks(this)" style="cursor:pointer;">
                    <label class="form-check-label fw-800 text-muted small text-uppercase" for="checkAll" style="font-size:0.65rem; letter-spacing:1px; cursor:pointer;">Select All</label>
                </div>
                <span class="text-muted fw-700 small"><?php echo $total_students; ?> student(s)</span>
            </div>
            <div class="search-wrapper" style="min-width:220px;">
                <i class="fas fa-search"></i>
                <input type="text" id="searchPortal" class="form-control form-control-sm py-2" placeholder="Search by name or admission no..." oninput="filterPortalStudents()">
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-bar mt-3" id="bulkBar">
            <span class="fw-bold"><i class="fas fa-users-cog me-2"></i><span id="selectedCount">0</span> selected</span>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm bg-white text-success border-0 fw-800 rounded-pill px-3 shadow-sm" onclick="bulkGenerate()">
                    <i class="fas fa-key me-1"></i> Generate Passwords
                </button>
                <button class="btn btn-sm bg-white text-primary border-0 fw-800 rounded-pill px-3 shadow-sm" onclick="bulkToggle(1)">
                    <i class="fas fa-check-circle me-1"></i> Activate All
                </button>
                <button class="btn btn-sm bg-white text-danger border-0 fw-800 rounded-pill px-3 shadow-sm" onclick="bulkToggle(0)">
                    <i class="fas fa-ban me-1"></i> Deactivate All
                </button>
                <button class="btn btn-sm btn-outline-light border-0 fw-bold rounded-pill px-3" onclick="clearSelection()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="glass-card overflow-hidden">
        <!-- Header -->
        <div class="d-flex align-items-center gap-2 px-4 py-3 border-bottom bg-light">
            <i class="fas fa-list-ul text-muted opacity-50"></i>
            <h6 class="fw-900 mb-0 text-dark">Portal Access Registry</h6>
            <?php if ($class_filter): ?>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 fw-700" style="font-size:0.7rem;"><?php echo htmlspecialchars($selected_class_name); ?></span>
            <?php endif; ?>
        </div>

        <div style="max-height: 60vh; overflow-y: auto;">
            <?php if (empty($students)): ?>
                <div class="text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                        <i class="fas fa-user-graduate text-muted opacity-50 fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No Students Found</h5>
                    <p class="text-muted small">No students are enrolled <?php echo $class_filter ? 'in this class' : ''; ?>. <a href="students.php" class="fw-bold">Add students first</a>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($students as $i => $st): 
                    $has_pass = !empty($st['student_password']);
                    $is_active = $st['portal_active'] == 1;
                    $status_class = !$has_pass ? 'no-pass' : ($is_active ? 'active' : 'inactive');
                    $status_text = !$has_pass ? 'No Password' : ($is_active ? 'Active' : 'Inactive');
                ?>
                <div class="student-portal-row" data-search="<?php echo htmlspecialchars(strtolower($st['full_name'] . ' ' . $st['admission_no'])); ?>" id="portal-row-<?php echo $st['id']; ?>">
                    <!-- Checkbox -->
                    <input type="checkbox" class="form-check-input portal-check m-0" value="<?php echo $st['id']; ?>" onchange="updateBulkBar()" style="cursor:pointer; flex-shrink:0;">
                    
                    <!-- Avatar -->
                    <div class="stu-avatar">
                        <?php if (!empty($st['image_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($st['image_path']); ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-user text-muted opacity-50"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="student-info flex-grow-1" style="min-width:0;">
                        <div class="fw-700 text-dark text-truncate" style="font-size:0.88rem;"><?php echo htmlspecialchars($st['full_name']); ?></div>
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                            <span class="text-muted small fw-600" style="font-size:0.72rem;">
                                <i class="fas fa-id-card me-1 opacity-50"></i><?php echo htmlspecialchars($st['admission_no']); ?>
                            </span>
                            <?php if ($st['class_name']): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2" style="font-size:0.55rem;"><?php echo htmlspecialchars($st['class_name']); ?></span>
                            <?php endif; ?>
                            <span class="portal-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="student-actions d-flex align-items-center gap-2 flex-shrink-0">
                        <!-- Toggle Switch -->
                        <?php if ($has_pass): ?>
                        <label class="toggle-switch" title="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?> portal access">
                            <input type="checkbox" <?php echo $is_active ? 'checked' : ''; ?> onchange="togglePortal(<?php echo $st['id']; ?>, this.checked ? 1 : 0)">
                            <span class="toggle-slider"></span>
                        </label>
                        <?php endif; ?>
                        
                        <!-- Generate/Regenerate -->
                        <button class="action-btn btn-generate" onclick="generatePassword(<?php echo $st['id']; ?>, '<?php echo addslashes($st['full_name']); ?>')">
                            <i class="fas fa-key me-1"></i> <?php echo $has_pass ? 'Reset' : 'Generate'; ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Student Portal Link Info -->
    <div class="glass-card p-4 mt-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px;">
                <i class="fas fa-link"></i>
            </div>
            <div>
                <h6 class="fw-900 mb-0 text-dark">Portal Access Link</h6>
                <p class="mb-0 text-muted small fw-600">Share this link with students and parents for self-service access</p>
            </div>
        </div>
        <div class="bg-light rounded-3 p-3 d-flex align-items-center justify-content-between gap-2 border">
            <code class="text-primary fw-700 small text-break" id="portalLink"><?php 
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $base = dirname(dirname($_SERVER['SCRIPT_NAME']));
                echo $protocol . '://' . $host . $base . '/student/login.php';
            ?></code>
            <button class="copy-btn flex-shrink-0" onclick="copyToClipboard(document.getElementById('portalLink').textContent, this)">
                <i class="fas fa-copy me-1"></i> Copy
            </button>
        </div>
    </div>

    <?php include '../includes/dashboard_footer.php'; ?>
</main>
<?php if ($role !== 'staff'): ?>
</div>
<?php endif; ?>

<!-- Credentials Result Modal -->
<div class="modal fade" id="credentialsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-header border-0 bg-success text-white pt-4 pb-4 px-4">
                <h5 class="modal-title fw-900"><i class="fas fa-check-circle me-2"></i> Credentials Generated</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="credentialsBody">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer border-0 p-4 bg-light">
                <button class="btn btn-outline-dark fw-bold rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" onclick="printCredentials()">
                    <i class="fas fa-print me-2"></i> Print Credentials
                </button>
                <button class="btn btn-success fw-bold rounded-pill px-4 shadow-sm" onclick="copyAllCredentials()">
                    <i class="fas fa-copy me-2"></i> Copy All
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Print Area -->
<div id="credentialsPrintArea" style="display:none;"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ═══════════════════════════════════════════════════════════
// GENERATE PASSWORD - Single Student
// ═══════════════════════════════════════════════════════════
function generatePassword(studentId, studentName) {
    if (!confirm(`Generate new portal password for ${studentName}? Any existing password will be overwritten.`)) return;
    
    Spinner.show('Generating secure credentials...');
    
    fetch('../ajax/manage_student_portal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=generate&student_id=${studentId}`
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success) {
            showCredentials([{
                full_name: d.full_name || studentName,
                admission_no: d.admission_no,
                password: d.password
            }]);
            // Update row UI
            const row = document.getElementById(`portal-row-${studentId}`);
            if (row) {
                const badge = row.querySelector('.portal-badge');
                if (badge) {
                    badge.className = 'portal-badge active';
                    badge.textContent = 'Active';
                }
            }
        } else {
            Notif.show(d.message, 'error');
        }
    })
    .catch(() => {
        Spinner.hide();
        Notif.show('Network error occurred.', 'error');
    });
}

// ═══════════════════════════════════════════════════════════
// TOGGLE PORTAL STATUS
// ═══════════════════════════════════════════════════════════
function togglePortal(studentId, status) {
    fetch('../ajax/manage_student_portal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle_status&student_id=${studentId}&status=${status}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            Notif.show(d.message, 'success');
            const badge = document.querySelector(`#portal-row-${studentId} .portal-badge`);
            if (badge) {
                badge.className = `portal-badge ${status ? 'active' : 'inactive'}`;
                badge.textContent = status ? 'Active' : 'Inactive';
            }
        } else {
            Notif.show(d.message, 'error');
        }
    });
}

// ═══════════════════════════════════════════════════════════
// BULK OPERATIONS
// ═══════════════════════════════════════════════════════════
function getSelectedIds() {
    return Array.from(document.querySelectorAll('.portal-check:checked')).map(c => c.value);
}

function bulkGenerate() {
    const ids = getSelectedIds();
    if (ids.length === 0) return Notif.show('No students selected.', 'warning');
    if (!confirm(`Generate portal passwords for ${ids.length} student(s)?`)) return;
    
    Spinner.show(`Generating ${ids.length} credential(s)...`);
    
    fetch('../ajax/manage_student_portal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=bulk_generate&student_ids=${JSON.stringify(ids)}`
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success && d.credentials) {
            showCredentials(d.credentials);
            Notif.show(d.message, 'success');
        } else {
            Notif.show(d.message || 'Failed.', 'error');
        }
    })
    .catch(() => { Spinner.hide(); Notif.show('Network error.', 'error'); });
}

function bulkGenerateClass(classId) {
    if (!confirm('Generate portal passwords for ALL students in this class?')) return;
    
    Spinner.show('Generating class credentials...');
    
    fetch('../ajax/manage_student_portal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=bulk_generate_class&class_id=${classId}`
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success && d.credentials) {
            showCredentials(d.credentials);
            Notif.show(d.message, 'success');
        } else {
            Notif.show(d.message || 'Failed.', 'error');
        }
    })
    .catch(() => { Spinner.hide(); Notif.show('Network error.', 'error'); });
}

function bulkToggle(status) {
    const ids = getSelectedIds();
    if (ids.length === 0) return Notif.show('No students selected.', 'warning');
    
    Spinner.show(`Updating ${ids.length} student(s)...`);
    
    fetch('../ajax/manage_student_portal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=bulk_toggle&student_ids=${JSON.stringify(ids)}&status=${status}`
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success) {
            Notif.show(d.message, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            Notif.show(d.message, 'error');
        }
    })
    .catch(() => { Spinner.hide(); Notif.show('Network error.', 'error'); });
}

// ═══════════════════════════════════════════════════════════
// CREDENTIALS DISPLAY
// ═══════════════════════════════════════════════════════════
let lastCredentials = [];

function showCredentials(credentials) {
    lastCredentials = credentials;
    let html = '<div class="alert alert-info border-0 rounded-3 small fw-600 mb-3"><i class="fas fa-info-circle me-2"></i>Share these credentials securely with students/parents. Passwords are shown in plain text only once.</div>';
    
    html += '<div style="max-height: 400px; overflow-y: auto;">';
    credentials.forEach((cred, idx) => {
        html += `
            <div class="credential-modal-item">
                <div style="min-width:0;">
                    <div class="fw-800 text-dark text-truncate" style="font-size:0.9rem;">${escapeHtml(cred.full_name)}</div>
                    <div class="d-flex gap-3 mt-1 flex-wrap">
                        <span class="small fw-600 text-muted"><i class="fas fa-id-card me-1 opacity-50"></i>${escapeHtml(cred.admission_no)}</span>
                        <span class="small fw-800 text-success"><i class="fas fa-key me-1"></i>${escapeHtml(cred.password)}</span>
                    </div>
                </div>
                <button class="copy-btn flex-shrink-0" onclick="copyToClipboard('Admission No: ${cred.admission_no}\\nPassword: ${cred.password}', this)">
                    <i class="fas fa-copy me-1"></i> Copy
                </button>
            </div>
        `;
    });
    html += '</div>';
    
    document.getElementById('credentialsBody').innerHTML = html;
    
    // Populate print area
    let printHtml = `
        <div style="font-family: Arial, sans-serif; padding: 20px;">
            <h2 style="text-align:center; margin-bottom: 5px;">Student Portal Credentials</h2>
            <p style="text-align:center; color: #666; margin-bottom: 20px;">${document.title.split('|')[0].trim()} — Generated ${new Date().toLocaleDateString()}</p>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">S/N</th>
                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Student Name</th>
                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Admission No</th>
                        <th style="border:1px solid #ddd; padding:8px; text-align:left;">Password</th>
                    </tr>
                </thead>
                <tbody>
    `;
    credentials.forEach((cred, idx) => {
        printHtml += `
            <tr>
                <td style="border:1px solid #ddd; padding:8px;">${idx + 1}</td>
                <td style="border:1px solid #ddd; padding:8px; font-weight:bold;">${escapeHtml(cred.full_name)}</td>
                <td style="border:1px solid #ddd; padding:8px;">${escapeHtml(cred.admission_no)}</td>
                <td style="border:1px solid #ddd; padding:8px; font-weight:bold; color: #059669;">${escapeHtml(cred.password)}</td>
            </tr>
        `;
    });
    printHtml += '</tbody></table><p style="text-align:center; margin-top:20px; color:#999; font-size:0.8rem;">Powered by EduRemarks • Keep these credentials confidential</p></div>';
    document.getElementById('credentialsPrintArea').innerHTML = printHtml;
    
    new bootstrap.Modal(document.getElementById('credentialsModal')).show();
}

function printCredentials() {
    const printArea = document.getElementById('credentialsPrintArea');
    printArea.style.display = 'block';
    window.print();
    setTimeout(() => { printArea.style.display = 'none'; }, 500);
}

function copyAllCredentials() {
    if (!lastCredentials.length) return;
    let text = 'STUDENT PORTAL CREDENTIALS\n';
    text += '═'.repeat(40) + '\n\n';
    lastCredentials.forEach((cred, idx) => {
        text += `${idx+1}. ${cred.full_name}\n`;
        text += `   Admission No: ${cred.admission_no}\n`;
        text += `   Password: ${cred.password}\n\n`;
    });
    text += 'Generated by EduRemarks';
    
    navigator.clipboard.writeText(text).then(() => {
        Notif.show('All credentials copied to clipboard!', 'success');
    });
}

// ═══════════════════════════════════════════════════════════
// UI HELPERS
// ═══════════════════════════════════════════════════════════
function toggleAllChecks(el) {
    document.querySelectorAll('.portal-check').forEach(c => {
        if (c.closest('.student-portal-row').style.display !== 'none') {
            c.checked = el.checked;
        }
    });
    updateBulkBar();
}

function updateBulkBar() {
    const count = document.querySelectorAll('.portal-check:checked').length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkBar').classList.toggle('visible', count > 0);
    
    const total = document.querySelectorAll('.portal-check').length;
    document.getElementById('checkAll').checked = (count === total && count > 0);
}

function clearSelection() {
    document.querySelectorAll('.portal-check, #checkAll').forEach(c => c.checked = false);
    updateBulkBar();
}

function filterPortalStudents() {
    const q = document.getElementById('searchPortal').value.toLowerCase();
    document.querySelectorAll('.student-portal-row').forEach(row => {
        const match = row.dataset.search.includes(q);
        row.style.display = match ? '' : 'none';
    });
}

function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied!';
        btn.style.background = '#10b981';
        btn.style.color = '#fff';
        btn.style.borderColor = '#10b981';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
</body>
</html>
