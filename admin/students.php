<?php
// admin/students.php
require_once '../includes/auth_check.php';
if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}
if (!$active_school) { header('Location: dashboard.php'); exit(); }

$school_id = $active_school['id'];

// Check if there is an explicit class query
$class_id_filter = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$class_context = null;

// Fetch classes for allocation dropdown
if ($role === 'staff') {
    // Only classes assigned to this staff
    $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
    $sd_stmt->execute([$user_id, $school_id]);
    $sd_row = $sd_stmt->fetch();
    if ($sd_row) {
        $stmt2 = $pdo->prepare("
            SELECT DISTINCT c.id, c.name, c.code, c.section 
            FROM classes c
            JOIN staff_class_subjects scs ON scs.class_id = c.id
            WHERE scs.staff_detail_id = ? AND scs.school_id = ?
            ORDER BY c.name
        ");
        $stmt2->execute([$sd_row['id'], $school_id]);
        $classes = $stmt2->fetchAll();
    } else {
        $classes = [];
    }
} else {
    $stmt2 = $pdo->prepare("SELECT id, name, code, section FROM classes WHERE school_id=? ORDER BY name");
    $stmt2->execute([$school_id]);
    $classes = $stmt2->fetchAll();
}

// Fetch Departments (Sections)
$dept_stmt = $pdo->prepare("SELECT id, section_name, section_code FROM school_sections WHERE school_id = ? ORDER BY section_name ASC");
$dept_stmt->execute([$school_id]);
$departments = $dept_stmt->fetchAll();

if ($class_id_filter) {
    $cstmt = $pdo->prepare("SELECT name FROM classes WHERE id=? AND school_id=?");
    $cstmt->execute([$class_id_filter, $school_id]);
    $class_context = $cstmt->fetchColumn();
}

// Fetch students with allocated class name
$students = [];
if ($class_id_filter) {
    // If a class filter is active, further ensure staff can only see it if assigned
    if ($role === 'staff') {
        $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
        $sd_stmt->execute([$user_id, $school_id]);
        $sd_row = $sd_stmt->fetch();
        if ($sd_row) {
            $assigned = $pdo->prepare("SELECT id FROM staff_class_subjects WHERE staff_detail_id=? AND class_id=? AND school_id=? LIMIT 1");
            $assigned->execute([$sd_row['id'], $class_id_filter, $school_id]);
            if (!$assigned->fetch()) {
                // If filtering for a class not assigned to them, show empty
                $students = [];
            } else {
            $stmt = $pdo->prepare("
                    SELECT s.*, c.name AS class_name, c.id AS class_id_alloc, scs_dept.section_name AS department_name
                    FROM students s
                    JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
                    JOIN classes c ON c.id = sc.class_id
                    LEFT JOIN school_sections scs_dept ON scs_dept.id = s.department_id
                    WHERE s.school_id = ? AND sc.class_id = ?
                    ORDER BY s.created_at DESC
                ");
                $stmt->execute([$school_id, $class_id_filter]);
                $students = $stmt->fetchAll();
            }
        }
    } else {
            $stmt = $pdo->prepare("
            SELECT s.*, c.name AS class_name, c.id AS class_id_alloc, scs_dept.section_name AS department_name
            FROM students s
            JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
            JOIN classes c ON c.id = sc.class_id
            LEFT JOIN school_sections scs_dept ON scs_dept.id = s.department_id
            WHERE s.school_id = ? AND sc.class_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$school_id, $class_id_filter]);
        $students = $stmt->fetchAll();
    }
} else {
    // No class filter - if staff, only show students from their assigned classes
    if ($role === 'staff') {
        $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
        $sd_stmt->execute([$user_id, $school_id]);
        $sd_row = $sd_stmt->fetch();
        if ($sd_row) {
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.*, c.name AS class_name, c.id AS class_id_alloc, scs_dept.section_name AS department_name
                FROM students s
                JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
                JOIN classes c ON c.id = sc.class_id
                JOIN staff_class_subjects scs ON scs.class_id = c.id
                LEFT JOIN school_sections scs_dept ON scs_dept.id = s.department_id
                WHERE s.school_id = ? AND scs.staff_detail_id = ?
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$school_id, $sd_row['id']]);
            $students = $stmt->fetchAll();
        }
    } else {
        // Owner/Super Admin: All students
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS class_name, c.id AS class_id_alloc, scs_dept.section_name AS department_name
            FROM students s
            LEFT JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
            LEFT JOIN classes c ON c.id = sc.class_id
            LEFT JOIN school_sections scs_dept ON scs_dept.id = s.department_id
            WHERE s.school_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$school_id]);
        $students = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .glass-card { background: #fff; border-radius: 20px; border: 1px solid #eef2f6; box-shadow: 0 4px 20px rgba(0,0,0,0.015); transition: 0.3s; }
        .student-avatar { background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
        .bulk-bar { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 16px; padding: 12px 24px; display: none; align-items: center; justify-content: space-between; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15); border: 1px solid rgba(255,255,255,0.05); }
        .bulk-bar.visible { display: flex; animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
        
        #searchInput { border-radius: 50px; padding-left: 15px; border-color: #e2e8f0; background: #f8fafc; box-shadow: inset 0 2px 4px rgba(0,0,0,0.01); }
        #searchInput:focus { background: #fff; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        
        .row-hover:hover { background: #f8fafc !important; }
        .row-hover:last-child { border-bottom: none !important; }
        
        .upload-img-btn { transition: 0.2s; border-color: #cbd5e1 !important; }
        .upload-img-btn:hover { border-color: #3b82f6 !important; background: #eff6ff !important; color: #3b82f6; }
        
        .student-add-row { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-color: #f1f5f9 !important; }
        .student-add-row:hover { border-color: #e2e8f0 !important; box-shadow: 0 8px 16px rgba(0,0,0,0.03) !important; z-index: 2; transform: translateY(-2px); }
        .stu-input-field { background: #f8fafc !important; border: 1px solid transparent !important; transition: 0.2s; }
        .stu-input-field:focus { background: #fff !important; border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59,130,246,0.1) !important; }

        @media (max-width: 991px) {
            #addStudentsPanel { display: none; margin-top: 20px; }
            #addStudentsPanel.show { display: block; animation: fadeIn 0.3s ease; }
            .header-buttons { flex-wrap: nowrap !important; }
            .header-buttons a { flex: 1 1 50%; width: 100%; text-align: center; justify-content: center; display: flex; white-space: nowrap; padding-left: 8px !important; padding-right: 8px !important; font-size: 0.85rem !important; margin-bottom: 0 !important; }
        }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
        
        /* Modern generic scrollbar */
        .student-list-container { max-height: 65vh; overflow-y: auto; padding-right: 10px; }
        .student-list-container::-webkit-scrollbar { width: 6px; }
        .student-list-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .student-list-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .student-list-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .btn-modern { font-weight: 700; letter-spacing: 0.3px; padding: 10px 24px; border-radius: 50px; transition: 0.3s; }
        .btn-modern-primary { background: #2563eb; color: #fff; border: 1px solid #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .btn-modern-primary:hover { background: #1d4ed8; color: #fff; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(37,99,235,0.3); }
        .btn-modern-light { background: #fff; color: #475569; border: 1px solid #e2e8f0; }
        .btn-modern-light:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
        
        .action-circle-btn { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #e2e8f0; color: #64748b; transition: 0.2s; }
        .action-circle-btn:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; transform: translateY(-2px); }
        .action-circle-btn.text-danger:hover { background: #fef2f2; color: #dc2626 !important; border-color: #fecaca; }
        .action-circle-btn.text-primary:hover { background: #eff6ff; color: #2563eb !important; border-color: #bfdbfe; }

        /* Powerful Mobile Responsiveness */
        @media (max-width: 768px) {
            .student-list-row { padding: 10px !important; gap: 4px; }
            .student-list-row > .d-flex.gap-3 { gap: 6px !important; width: auto !important; flex-grow: 1; min-width: 0; }
            .student-list-row > .d-flex > .text-muted.opacity-25 { display: none !important; } /* Hide sequence number */
            .student-avatar { width: 34px !important; height: 34px !important; font-size: 0.75rem; }
            .student-list-row .fw-800 { font-size: 0.8rem !important; letter-spacing: 0 !important; }
            .student-list-row .small.fw-600 { font-size: 0.6rem !important; }
            .action-circle-btn { width: 30px !important; height: 30px !important; font-size: 0.7rem !important; }
            .btn-modern { padding: 8px 16px !important; font-size: 0.8rem !important; }
            .student-add-row .align-items-start { flex-direction: column; align-items: center !important; gap: 10px !important; }
            .student-add-row .flex-grow-1 { width: 100%; }
            .student-add-row .upload-img-btn { width: 50px !important; height: 50px !important; }
            #searchInput { min-width: 120px !important; font-size: 0.75rem !important; }
            .small.fw-600 .badge { font-size: 0.55rem !important; padding: 2px 6px !important; }
        }
        @media (max-width: 480px) {
            .student-add-row .gap-2 { flex-direction: column; }
            .student-add-row .stu-gender { width: 100% !important; }
            .action-circle-btn { width: 28px !important; height: 28px !important; font-size: 0.65rem !important; }
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

        <!-- Modern Header -->
        <div class="row align-items-center mb-4">
            <div class="col-lg-7 col-md-12 d-flex align-items-center gap-3 mb-3 mb-lg-0">
                <div class="bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width:55px; height:55px; font-size:1.5rem;">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="text-mobile-center">
                    <h3 class="fw-900 mb-0 text-dark d-flex align-items-center gap-2 flex-mobile-column" style="font-size:1.4rem; letter-spacing:-0.5px;"><i class="fas fa-user-friends text-primary d-lg-none"></i> Manage <?php echo get_label('Pupils'); ?></h3>
                    <?php if ($class_context): ?>
                        <div class="text-muted fw-700 mt-1 uppercase" style="font-size:0.75rem; letter-spacing:0.8px;"><?php echo htmlspecialchars($class_context); ?> &bull; <?php echo $active_school['session_name'] ?? 'Active Term'; ?></div>
                    <?php else: ?>
                        <div class="text-muted fw-700 mt-1 uppercase" style="font-size:0.75rem; letter-spacing:0.8px;"><?php echo htmlspecialchars($active_school['school_name']); ?> &bull; <?php echo $active_school['session_name'] ?? 'Active Term'; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 col-md-12 header-buttons d-flex justify-content-lg-end gap-2 flex-wrap flex-md-nowrap">

                <a href="academics.php" class="btn-modern btn-modern-light d-flex align-items-center text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i> <?php echo get_label('Classes'); ?>
                </a>
                <a href="student_portal.php" class="btn-modern btn-modern-primary d-flex align-items-center text-decoration-none shadow-sm" style="background: linear-gradient(135deg,#3b82f6,#2563eb); border:none;">
                    <i class="fas fa-user-shield me-2"></i> Manage Portal
                </a>
                <a href="student_bulk_upload.php" class="btn-modern btn-modern-primary d-flex align-items-center text-decoration-none shadow-sm">
                    <i class="fas fa-file-upload me-2"></i> Bulk Upload
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Students List -->
            <div class="col-xl-8 col-lg-7">
                <div class="glass-card p-4 h-100 position-relative">
                     <div class="d-flex justify-content-between align-items-center mb-3">
                         <h5 class="fw-900 mb-0 d-flex align-items-center gap-3 text-dark">
                             <span class="d-flex align-items-center gap-2"><i class="fas fa-list-ul text-muted opacity-50"></i> <?php echo get_label('Pupils'); ?></span>
                             <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 shadow-sm" style="font-size:0.8rem;"><?php echo count($students); ?></span>
                         </h5>
                         <div class="d-flex gap-2">
                             <button class="btn btn-primary btn-sm d-lg-none rounded-pill fw-bold shadow-sm px-3 text-nowrap" onclick="$('#addStudentsPanel').toggleClass('show'); window.scrollTo({top:0, behavior:'smooth'});">
                                 <i class="fas fa-plus me-1"></i> Add <?php echo get_label('Pupils'); ?>
                             </button>
                         </div>
                     </div>
                     
                     <!-- Top Tools -->
                     <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-4">
                         <div class="d-flex align-items-center gap-3">
                             <div class="form-check m-0 d-flex flex-nowrap align-items-center bg-white py-2 rounded-pill border shadow-sm" style="padding-left: 1.2rem !important; padding-right: 1.5rem !important;">
                                 <input type="checkbox" id="checkAll" class="form-check-input float-none m-0 me-2 flex-shrink-0" onchange="toggleAll(this)" style="cursor:pointer; width: 1.1rem; height: 1.1rem;">
                                 <label class="form-check-label fw-900 small text-blue uppercase mb-0" for="checkAll" style="font-size:0.68rem; cursor:pointer; letter-spacing: 0.5px;">Mark All Nodes</label>
                             </div>
                             <button class="btn btn-primary shadow-sm rounded-pill btn-sm px-4 fw-800 d-none h-100" id="bulkAllocBtn" data-bs-toggle="modal" data-bs-target="#allocateClassModal"><i class="fas fa-rocket me-2"></i> Promote Selected</button>
                         </div>
                         <div class="position-relative">
                             <i class="fas fa-search position-absolute text-muted opacity-50" style="top:50%; transform:translateY(-50%); left:15px;"></i>
                             <input type="text" id="searchInput" class="form-control form-control-sm fw-600 rounded-pill py-2" placeholder="Search students..." oninput="filterStudents()" style="padding-left:35px; min-width:200px; font-size:0.85rem;">
                         </div>
                     </div>

                     <!-- Bulk Bar Overlay -->
                     <div class="bulk-bar" id="bulkBar">
                         <div class="d-flex align-items-center">
                             <div class="bg-primary bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                 <i class="fas fa-users-rays text-primary"></i>
                             </div>
                             <div>
                                 <div class="fw-900 small mb-0"><span id="selectedCount">0</span> <?php echo get_label('Pupils'); ?> Synchronized</div>
                                 <div class="extra-small opacity-75 fw-600">Pending Promotion / Status Update</div>
                             </div>
                         </div>
                         <div class="d-flex gap-2">
                             <button class="btn btn-sm btn-primary border-0 fw-900 rounded-pill px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#allocateClassModal">
                                 <i class="fas fa-rocket me-2"></i>PROMOTE <?php echo strtoupper(get_label('Pupils')); ?>
                             </button>
                             <button class="btn btn-sm btn-outline-light border-0 fw-bold rounded-pill px-3" onclick="clearSelection()" title="Clear Markings">
                                 <i class="fas fa-times"></i>
                             </button>
                         </div>
                     </div>

                     <div class="student-list-container">
                         <?php if (empty($students)): ?>
                             <div class="text-center py-5">
                                 <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;">
                                     <i class="fas fa-user-graduate text-muted opacity-50 fa-2x"></i>
                                 </div>
                                 <h5 class="fw-bold text-dark mb-1">No Students Found</h5>
                                 <p class="text-muted small">Register your first student securely.</p>
                             </div>
                         <?php else: ?>
                             <?php foreach ($students as $i => $st): ?>
                             <div class="d-flex align-items-center justify-content-between py-3 border-bottom row-hover student-list-row bg-white rounded-4 px-3 mb-2 shadow-sm border" data-search="<?php echo htmlspecialchars(strtolower($st['full_name'] . ' ' . $st['admission_no'])); ?>" id="row-<?php echo $st['id']; ?>">
                              <div class="d-flex align-items-center gap-2 gap-md-3 flex-grow-1 min-width-0">
                                  <span class="text-muted fw-900 opacity-25 d-none d-sm-block" style="width:25px; font-size:0.8rem;"><?php echo str_pad($i + 1, 2, '0', STR_PAD_LEFT); ?></span>
                                  <div class="form-check m-0">
                                      <input type="checkbox" class="form-check-input student-check" value="<?php echo $st['id']; ?>" onchange="updateBulkBar()" style="cursor:pointer;">
                                  </div>
                                  <div class="student-avatar flex-shrink-0 overflow-hidden rounded-circle border border-2 border-white shadow-sm" style="width:40px;height:40px;">
                                      <?php if (!empty($st['image_path'])): ?>
                                          <img src="../<?php echo $st['image_path']; ?>" style="width:100%;height:100%;object-fit:cover;">
                                      <?php else: ?>
                                          <i class="fas fa-user opacity-50 fa-lg"></i>
                                      <?php endif; ?>
                                  </div>
                                  <div class="min-width-0">
                                         <div class="fw-800 text-dark text-truncate" style="font-size:0.95rem; letter-spacing:-0.2px;">
                                             <?php echo htmlspecialchars($st['full_name']); ?>
                                         </div>
                                         <div class="small fw-600 text-muted d-flex align-items-center gap-1 mt-1 text-truncate">
                                             <span style="font-size:0.75rem;"><?php echo htmlspecialchars($st['gender']); ?></span>
                                             <span class="mx-1 opacity-25">&bull;</span>
                                             <?php if ($st['class_name']): ?>
                                                 <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-2 py-1 rounded-pill" style="font-size:0.6rem; letter-spacing:0.5px;">
                                                     <?php echo htmlspecialchars($st['class_name']); ?>
                                                 </span>
                                             <?php else: ?>
                                                 <span class="text-warning small" style="font-size:0.7rem;"><i class="fas fa-exclamation-circle"></i> Unallocated</span>
                                             <?php endif; ?>
                                             <?php if (!empty($st['department_name'])): ?>
                                                 <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-10 px-2 py-1 rounded-pill" style="font-size:0.6rem; letter-spacing:0.5px;">
                                                     <i class="fas fa-university me-1"></i><?php echo htmlspecialchars($st['department_name']); ?>
                                                 </span>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                 </div>
                                 <div class="d-flex gap-2 justify-content-end align-items-center flex-shrink-0">
                                      <button class="action-circle-btn text-warning" title="Student Portal Credentials" onclick="generatePortalKey(<?php echo $st['id']; ?>, '<?php echo addslashes($st['full_name']); ?>')">
                                           <i class="fas fa-key" style="font-size:0.75rem;"></i>
                                       </button>
                                       <button class="action-circle-btn text-primary" title="Edit Student Profile" 
                                          onclick="openEdit(
                                              <?php echo $st['id']; ?>,
                                              '<?php echo addslashes($st['full_name']); ?>',
                                              '<?php echo addslashes($st['admission_no']); ?>',
                                              '<?php echo addslashes($st['gender']); ?>',
                                              '<?php echo $st['dob'] ?? ''; ?>',
                                              '<?php echo addslashes($st['guardian_name'] ?? ''); ?>',
                                              '<?php echo addslashes($st['guardian_phone'] ?? ''); ?>',
                                              '<?php echo addslashes($st['address'] ?? ''); ?>',
                                              '<?php echo $st['class_id_alloc'] ?? ''; ?>',
                                              '<?php echo !empty($st['image_path']) ? '../' . $st['image_path'] : '../img/default_student.png'; ?>'
                                          )">
                                          <i class="fas fa-pen" style="font-size:0.8rem;"></i>
                                      </button>
                                      <button class="action-circle-btn text-danger" title="Remove Profile" onclick="deleteStudent(<?php echo $st['id']; ?>, '<?php echo addslashes($st['full_name']); ?>')">
                                          <i class="fas fa-trash" style="font-size:0.8rem;"></i>
                                      </button>
                                 </div>
                             </div>
                             <?php endforeach; ?>
                         <?php endif; ?>
                     </div>
                </div>
            </div>

            <!-- Right Column: Add Students widget -->
            <div class="col-xl-4 col-lg-5" id="addStudentsPanel">
                <div class="glass-card p-4 h-100 position-relative border border-primary border-opacity-10 shadow-sm" style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-900 mb-0 text-dark d-flex align-items-center gap-2" style="font-size:1.1rem; letter-spacing:-0.3px;">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                    <i class="fas fa-user-plus" style="font-size:0.8rem;"></i>
                                </div> 
                                Bulk Add
                            </h6>
                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-800" onclick="openAddModal()" style="font-size:0.7rem;">Single Entry</button>
                        </div>
                        <button class="btn btn-light bg-white border btn-sm rounded-circle text-muted d-lg-none" onclick="$('#addStudentsPanel').removeClass('show');" style="width:32px; height:32px;"><i class="fas fa-times"></i></button>
                    </div>
                    <p class="text-muted small fw-600 mb-4" style="line-height:1.6;">Add one or more students. Tap a camera icon to optionally upload a photo. Blank rows are automatically skipped.</p>
                    
                    <div class="mb-3 bg-white p-3 rounded-4 border shadow-sm">
                        <label class="form-label small fw-800 text-dark text-uppercase tracking-1 mb-2" style="font-size:0.7rem;"><i class="fas fa-university text-primary me-2"></i>Department</label>
                        <select class="form-select bg-light border-0 fw-bold px-3 py-2 text-dark shadow-none" id="multiTargetDept" style="border-radius:10px;">
                            <option value="">— Select Department —</option>
                            <?php foreach ($departments as $d): ?>
                               <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4 bg-white p-3 rounded-4 border shadow-sm">
                        <label class="form-label small fw-800 text-dark text-uppercase tracking-1 mb-2" style="font-size:0.7rem;"><i class="fas fa-layer-group text-primary me-2"></i>Target Environment (<?php echo get_label('Class'); ?>) <span class="text-danger">*</span></label>
                        <select class="form-select bg-light border-0 fw-bold px-3 py-2 text-dark shadow-none" id="multiTargetClass" style="border-radius:10px;">
                            <option value="">— Select Target <?php echo get_label('Class'); ?> —</option>
                            <?php foreach ($classes as $c): ?>
                               <option value="<?php echo $c['id']; ?>" <?php echo ($class_id_filter == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="dynamicAddRows" style="max-height: 48vh; overflow-y: auto; overflow-x: hidden; padding-right: 5px;">
                        <!-- JS injected rows -->
                    </div>

                    <div class="border-top pt-4 mt-3">
                        <div class="d-flex gap-2 mb-3">
                            <button class="btn btn-light border bg-white fw-bold rounded-pill flex-grow-1 shadow-sm text-dark d-flex align-items-center justify-content-center gap-2" onclick="addDynamicRow()" style="font-size:0.85rem; padding:10px;">
                                <i class="fas fa-plus-circle text-primary"></i> Another Row
                            </button>
                            <button class="btn btn-light border bg-white fw-bold rounded-pill text-muted d-flex align-items-center justify-content-center gap-2" onclick="clearDynamicRows()" style="font-size:0.85rem; padding:10px 20px;" title="Reset Flow">
                                <i class="fas fa-sync"></i> Clear
                            </button>
                        </div>

                        <button class="btn btn-success fw-bold rounded-pill w-100 shadow-sm py-2 px-4 text-white d-flex align-items-center justify-content-center gap-2" style="background:#10b981; border:none; font-size:1rem; letter-spacing:0.5px;" onclick="saveDynamicStudents()">
                            <i class="fas fa-cloud-upload-alt pb-1"></i> Save All Students
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
<?php if ($role !== 'staff'): ?>
</div>
<?php endif; ?>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg" style="border-radius:24px; overflow:hidden;">
            <div class="modal-header bg-light border-0 pb-4 pt-4 px-4">
                <h5 class="modal-title fw-900 text-dark"><i class="fas fa-user-edit text-primary me-2"></i>Edit Student Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="full_name" id="edit_full_name" required></div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1">Department</label>
                            <select class="form-select bg-light border-0 fw-bold px-3 py-2" name="department_id" id="edit_department_id">
                                <option value="">— No Department —</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1"><?php echo get_label('Admission No'); ?> <span class="text-danger">*</span></label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="admission_no" id="edit_admission_no" required></div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1"><?php echo get_label('Class'); ?> <span class="text-danger">*</span></label>
                            <select class="form-select bg-light border-0 fw-bold px-3 py-2" name="student_class" id="edit_student_class" required>
                                <option value="">— Choose a <?php echo get_label('Class'); ?> —</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Gender</label><select class="form-select bg-light border-0 fw-bold px-3 py-2" name="gender" id="edit_gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Date of Birth</label><input type="date" class="form-control bg-light border-0 fw-bold px-3 py-2" name="dob" id="edit_dob"></div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Guardian Name</label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="guardian_name" id="edit_guardian_name"></div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Guardian Phone</label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="guardian_phone" id="edit_guardian_phone"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1">Profile Picture</label>
                            <div class="d-flex align-items-center gap-3">
                                <div id="edit_img_preview_container" class="bg-light rounded-circle shadow-sm border overflow-hidden" style="width:50px; height:50px; min-width:50px;">
                                    <img id="edit_img_preview" src="../img/default_student.png" alt="Preview" class="w-100 h-100 object-fit-cover">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control bg-light border-0 fw-bold px-3 py-2" name="student_image" id="edit_student_image" accept="image/*" onchange="previewEditImage(this)">
                                    <div class="extra-small text-muted mt-1">Leaves blank to keep current photo.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label fw-800 text-muted small uppercase tracking-1">Residential Address</label><textarea class="form-control bg-light border-0 fw-bold px-3 py-2" name="address" id="edit_address" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 bg-light">
                    <button type="button" class="btn btn-outline-dark fw-bold rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm"><i class="fas fa-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Single Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg" style="border-radius:24px; overflow:hidden;">
            <div class="modal-header bg-primary text-white border-0 pb-4 pt-4 px-4">
                <h5 class="modal-title fw-900"><i class="fas fa-user-plus me-2"></i>Register New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm">
                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="full_name" required placeholder="John Doe"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1">Department</label>
                            <select class="form-select bg-light border-0 fw-bold px-3 py-2" name="department_id">
                                <option value="">— No Department —</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1"><?php echo get_label('Admission No'); ?></label>
                            <?php if ($active_school['adm_no_type'] === 'manual'): ?>
                                <input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="admission_no" required placeholder="ADM/2024/001">
                            <?php else: ?>
                                <input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2 text-muted" name="admission_no" readonly value="Auto-generated">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1"><?php echo get_label('Class'); ?> <span class="text-danger">*</span></label>
                            <select class="form-select bg-light border-0 fw-bold px-3 py-2" name="student_class" required>
                                <option value="">— Choose a <?php echo get_label('Class'); ?> —</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($class_id_filter == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Gender</label><select class="form-select bg-light border-0 fw-bold px-3 py-2" name="gender"><option>Male</option><option>Female</option><option>Other</option></select></div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Date of Birth</label><input type="date" class="form-control bg-light border-0 fw-bold px-3 py-2" name="dob"></div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Guardian Name</label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="guardian_name"></div>
                        <div class="col-md-6"><label class="form-label fw-800 text-muted small uppercase tracking-1">Guardian Phone</label><input type="text" class="form-control bg-light border-0 fw-bold px-3 py-2" name="guardian_phone"></div>
                        <div class="col-md-6">
                            <label class="form-label fw-800 text-muted small uppercase tracking-1">Profile Picture</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light rounded-circle shadow-sm border overflow-hidden" style="width:50px; height:50px; min-width:50px;">
                                    <img id="add_img_preview" src="../img/default_student.png" alt="Preview" class="w-100 h-100 object-fit-cover">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control bg-light border-0 fw-bold px-3 py-2" name="student_image" accept="image/*" onchange="previewAddImage(this)">
                                </div>
                            </div>
                        </div>
                        <div class="col-12"><label class="form-label fw-800 text-muted small uppercase tracking-1">Residential Address</label><textarea class="form-control bg-light border-0 fw-bold px-3 py-2" name="address" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 bg-light">
                    <button type="button" class="btn btn-outline-dark fw-bold rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm"><i class="fas fa-check-circle me-2"></i>Complete Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Student Promotion Hub Modal -->
<div class="modal fade" id="allocateClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg overflow-hidden" style="border-radius:28px;">
            <div class="modal-header border-0 pb-4 pt-5 px-5 bg-white text-center d-block">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="fas fa-rocket"></i>
                </div>
                <h4 class="modal-title fw-900 text-dark mb-1">Student Promotion Hub</h4>
                <p class="text-muted extra-small fw-700 uppercase tracking-2 mb-0">Academic Transitions & Environment Assignment</p>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-4" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-5 pt-2 bg-white text-center">
                <?php if (empty($classes)): ?>
                <div class="alert alert-warning border-0 fw-bold rounded-4"><i class="fas fa-exclamation-triangle me-2"></i>Institutional failure: No classrooms detected. <a href="academics.php" class="text-underline">Initialize academics first.</a></div>
                <?php else: ?>
                <div class="text-start mb-4">
                    <label class="form-label fw-900 text-dark small uppercase tracking-1 mb-2 ms-2">Destination Environment <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <i class="fas fa-layer-group position-absolute text-primary opacity-50" style="left: 20px; top: 50%; transform: translateY(-50%);"></i>
                        <select class="form-select bg-light border-0 fw-900 px-5 py-3 shadow-none rounded-4" id="allocateClassSelect" style="font-size: 0.95rem;">
                            <option value="">— Select Target <?php echo get_label('Class'); ?> —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="bg-light p-3 rounded-4 border-dashed mb-0 d-flex align-items-center text-start">
                    <i class="fas fa-info-circle text-primary h4 mb-0 me-3"></i>
                    <p class="text-muted small mb-0 fw-600">Promoting students will update their academic context across all modules including attendance and assessment grids.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-0 p-5 pt-0 bg-white">
                <button class="btn btn-primary fw-900 rounded-pill w-100 py-3 shadow-lg btn-modern-primary" style="font-size: 1rem; letter-spacing: 0.5px;" onclick="allocateClass()">
                    <i class="fas fa-sync-alt me-2"></i> EXECUTE PROMOTION
                </button>
                <div class="w-100 text-center mt-3">
                    <button type="button" class="btn btn-link link-secondary text-decoration-none fw-800 small uppercase" data-bs-dismiss="modal">Abort Operation</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Portal Credential Modal -->
<div class="modal fade" id="portalPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0 shadow-lg overflow-hidden" style="border-radius: 28px;">
            <div class="modal-header border-0 pb-0 pt-5 px-5 text-center d-block position-relative">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2.2rem;">
                    <i class="fas fa-key"></i>
                </div>
                <h4 class="fw-900 mb-1">Student Portal Access</h4>
                <p class="text-muted extra-small fw-800 uppercase tracking-2">Security Credential Broadcast</p>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-4" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-5 pt-2">
                <div class="text-center mb-4">
                    <p class="text-muted small fw-600 mb-4">Securely generated login credentials for <strong id="portalStudentName" class="text-dark"></strong>. Please distribute these to the student or parent.</p>
                    
                    <div class="bg-light p-4 rounded-4 border-dashed mb-3 text-start">
                        <div class="mb-3">
                            <label class="extra-small fw-900 text-muted uppercase tracking-2 d-block mb-1"><?php echo get_label('Admission No'); ?></label>
                            <div class="h5 fw-900 text-primary mb-0" id="portalAdmissionNo">---</div>
                        </div>
                        <div>
                            <label class="extra-small fw-900 text-muted uppercase tracking-2 d-block mb-1">Student Password</label>
                            <div class="d-flex align-items-center gap-2">
                                <div class="h4 fw-900 text-dark mb-0 font-monospace" id="portalPassword">---</div>
                                <button class="btn btn-sm btn-light rounded-pill px-3 fw-bold" onclick="copyToClipboard('portalPassword')">Copy</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info border-0 rounded-4 small mb-0 text-start">
                        <i class="fas fa-info-circle me-2"></i> The student can now login to the portal using these credentials to view performance reports and charts.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-5 pt-0">
                <button type="button" class="btn btn-dark fw-900 rounded-pill w-100 py-3 shadow-lg" data-bs-dismiss="modal">Close Terminal</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body p-5 text-center">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width:70px; height:70px;">
                    <i class="fas fa-trash fa-2x"></i>
                </div>
                <h4 class="fw-900 mb-2 text-dark">Eradicate Node?</h4>
                <p class="text-muted fw-bold mb-4">Are you absolutely sure you want to permanently delete <span id="deleteStudentName" class="text-danger text-decoration-underline"></span>?</p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold text-dark border shadow-sm" data-bs-dismiss="modal">Abort</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" id="confirmDeleteBtn">Yes, Purge</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ADM_TYPE = "<?php echo $active_school['adm_no_type']; ?>";

    // ---- DYNAMIC ADD STUDENTS UI ----
    let rowCounter = 0;
    
    function addDynamicRow() {
        rowCounter++;
        const manualAdmHtml = (ADM_TYPE === 'manual') 
            ? `<input type="text" class="form-control border-0 fw-bold px-3 py-2 small stu-input-field stu-adm" placeholder="ADM No.">`
            : `<input type="text" class="form-control border-0 fw-bold px-3 py-2 small stu-input-field stu-adm text-muted" readonly value="AUTO" title="Auto Generated">`;
            
        const rowHtml = `
            <div class="student-add-row border rounded-4 p-3 mb-3 bg-white position-relative shadow-sm" id="dyn-row-${rowCounter}">
                <button tabindex="-1" class="btn btn-light bg-white border shadow-sm btn-sm rounded-circle position-absolute text-muted" style="top:-10px; right:-10px; width:28px; height:28px; z-index:10; display:flex; align-items:center; justify-content:center;" onclick="removeDynamicRow(${rowCounter})" title="Remove Row">
                    <i class="fas fa-minus" style="font-size:0.7rem;"></i>
                </button>
                <div class="d-flex gap-3 align-items-start">
                    <div class="upload-img-btn rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0 cursor-pointer overflow-hidden position-relative border" style="width:58px; height:58px; border-style:dashed!important; border-width:2px!important;" onclick="document.getElementById('file-d-${rowCounter}').click()">
                        <i class="fas fa-camera text-muted opacity-50"></i>
                        <img src="" style="width:100%;height:100%;object-fit:cover;display:none; z-index:5;" class="position-absolute top-0 start-0 grid-img-preview">
                        <input type="file" id="file-d-${rowCounter}" class="d-none stu-file" accept="image/*" onchange="previewGridImg(this)">
                    </div>
                    <div class="flex-grow-1">
                        <div class="mb-2">
                             <input type="text" class="form-control border-0 fw-bold px-3 py-2 flex-grow-1 stu-input-field stu-name" placeholder="Full Student Name">
                        </div>
                        <div class="d-flex gap-2">
                            <select class="form-select border-0 fw-bold px-3 py-2 small stu-input-field stu-gender" style="width:110px;">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <div class="flex-grow-1">
                                ${manualAdmHtml}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#dynamicAddRows').append(rowHtml);
        
        // Scroll to bottom of dynamic pane
        const pane = document.getElementById('dynamicAddRows');
        pane.scrollTop = pane.scrollHeight;
    }

    function removeDynamicRow(id) {
        $(`#dyn-row-${id}`).fadeOut(200, function() { $(this).remove(); });
    }

    function clearDynamicRows() {
        $('#dynamicAddRows').html('');
        addDynamicRow(); // always keep at least one
    }

    // ---- DYNAMIC ASSET HANDLING ----
    function previewGridImg(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const $row = $(input).closest('.student-add-row');
                $row.find('.fa-camera').hide();
                $row.find('.grid-img-preview').attr('src', e.target.result).fadeIn();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Initialize with one row
    $(document).ready(function() {
        addDynamicRow();
    });

    async function saveDynamicStudents() {
        const targetClass = $('#multiTargetClass').val();
        const targetDept = $('#multiTargetDept').val();
        if (!targetClass) {
            Notif.show('You must select a Target Environment (Class) first.', 'warning');
            $('#multiTargetClass').focus();
            return;
        }

        const rows = $('.student-add-row');
        const tasks = [];

        rows.each(function() {
            const name = $(this).find('.stu-name').val().trim();
            if(!name) return; // Silent skip for completely blank rows

            const gender = $(this).find('.stu-gender').val();
            const adm = $(this).find('.stu-adm').val().trim();
            const fileInput = $(this).find('.stu-file')[0];
            
            const fd = new FormData();
            fd.append('full_name', name);
            fd.append('student_class', targetClass);
            fd.append('gender', gender);
            fd.append('department_id', targetDept);
            if (adm && adm !== 'AUTO') fd.append('admission_no', adm);
            if (fileInput.files.length > 0) fd.append('student_image', fileInput.files[0]);

            tasks.push(fd);
        });

        if (tasks.length === 0) {
            Notif.show('Provide at least one student name to commit.', 'warning');
            return;
        }

        Spinner.show(`Synchronizing ${tasks.length} node(s)...`);
        
        let successCount = 0;
        let failCount = 0;
        let lastError = '';

        for(let i=0; i<tasks.length; i++) {
            try {
                const r = await fetch('../ajax/register_student.php', { method: 'POST', body: tasks[i] });
                const text = await r.text();
                let d;
                try {
                    d = JSON.parse(text);
                } catch (pe) {
                    console.error("Malformed JSON Response:", text);
                    failCount++; lastError = "Non-JSON response from server."; continue;
                }
                
                if (d.success) successCount++;
                else { failCount++; lastError = d.message; }
            } catch (err) {
                console.error("Synchronization Fetch Error:", err);
                failCount++;
                lastError = "Network oscillation failure.";
            }
        }

        Spinner.hide();
        if (failCount === 0) {
            Notif.show(`${successCount} student(s) synchronized flawlessly!`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else if (successCount > 0) {
            Notif.show(`Partial Sync: ${successCount} saved, ${failCount} failed. Last error: ${lastError}`, 'warning');
            setTimeout(() => location.reload(), 2500);
        } else {
            Notif.show(`Synchronization Failed. Reason: ${lastError}`, 'error');
        }
    }


    // ---- SINGLE ADD MODAL LOGIC ----
    function openAddModal() {
        $('#addStudentForm')[0].reset();
        $('#add_img_preview').attr('src', '../img/default_student.png');
        new bootstrap.Modal(document.getElementById('addStudentModal')).show();
    }

    function previewAddImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#add_img_preview').attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.getElementById('addStudentForm').onsubmit = e => {
        e.preventDefault();
        Spinner.show('Commissioning Student...');
        fetch('../ajax/register_student.php', {method:'POST', body: new FormData(e.target)})
        .then(r=>r.json()).then(d => {
            Spinner.hide();
            if(d.success){ 
                Notif.show("Student Registered successfully", "success"); 
                bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
                setTimeout(()=>location.reload(),1500); 
            }
            else Notif.show(d.message,'error');
        }).catch(err => {
            Spinner.hide();
            Notif.show("Network oscillation failure.", "error");
        });
    };


    // ---- EXISTING LOGIC ----
    function openEdit(id, name, admNo, gender, dob, gName, gPhone, address, classId = '', imgPath = '', deptId = '') {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_full_name').value = name;
        document.getElementById('edit_admission_no').value = admNo;
        document.getElementById('edit_dob').value = dob;
        document.getElementById('edit_guardian_name').value = gName;
        document.getElementById('edit_guardian_phone').value = gPhone;
        document.getElementById('edit_address').value = address;
        document.getElementById('edit_student_class').value = classId;
        document.getElementById('edit_department_id').value = deptId || '';
        
        // Image Preview logic
        const previewImg = document.getElementById('edit_img_preview');
        if (previewImg) previewImg.src = imgPath || '../img/default_student.png';
        
        // Clear file input
        const fileInput = document.getElementById('edit_student_image');
        if (fileInput) fileInput.value = '';

        const sel = document.getElementById('edit_gender');
        for(let o of sel.options) o.selected = (o.value === gender);
        new bootstrap.Modal(document.getElementById('editStudentModal')).show();
    }

    function previewEditImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('edit_img_preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.getElementById('editStudentForm').onsubmit = e => {
        e.preventDefault();
        Spinner.show('Executing Patch...');
        fetch('../ajax/update_student.php', {method:'POST', body: new FormData(e.target)})
        .then(r=>r.json()).then(d => {
            Spinner.hide();
            if(d.success){ Notif.show("Profile Updated successfully", "success"); setTimeout(()=>location.reload(),1500); }
            else Notif.show(d.message,'error');
        }).catch(err => {
            Spinner.hide();
            Notif.show("Network oscillation failure.", "error");
        });
    };

    let deleteTargetId = null;
    function deleteStudent(id, name) {
        deleteTargetId = id;
        document.getElementById('deleteStudentName').textContent = name;
        new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!deleteTargetId) return;
        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
        Spinner.show('Obliterating node...');
        fetch('../ajax/delete_student.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${deleteTargetId}`
        })
        .then(r => r.json())
        .then(d => {
            Spinner.hide();
            if (d.success) {
                Notif.show("Node Eradicated successfully", "success");
                setTimeout(()=>location.reload(),1000);
            } else {
                Notif.show(d.message, 'error');
            }
        });
    });

    function toggleAll(chk) {
        document.querySelectorAll('.student-check').forEach(c => c.checked = chk.checked);
        updateBulkBar();
    }
    function updateBulkBar() {
        const count = document.querySelectorAll('.student-check:checked').length;
        document.getElementById('selectedCount').textContent = count;
        if(count > 0) {
            document.getElementById('bulkBar').classList.add('visible');
            document.getElementById('bulkAllocBtn').classList.remove('d-none');
        } else {
            document.getElementById('bulkBar').classList.remove('visible');
            document.getElementById('bulkAllocBtn').classList.add('d-none');
        }
        document.getElementById('checkAll').checked = (count === document.querySelectorAll('.student-check').length && count > 0);
    }
    function clearSelection() {
        document.querySelectorAll('.student-check, #checkAll').forEach(c => c.checked = false);
        updateBulkBar();
    }

    let singleAllocationIds = null;
    function openAllocation(ids = null, currentClassId = '', name = '') {
        singleAllocationIds = ids;
        const sel = document.getElementById('allocateClassSelect');
        if (sel) sel.value = currentClassId;
        new bootstrap.Modal(document.getElementById('allocateClassModal')).show();
    }

    function allocateClass() {
        const classId = document.getElementById('allocateClassSelect')?.value;
        if (!classId) return Notif.show('Target class is strictly required', 'warning');
        
        let ids = singleAllocationIds;
        if (!ids) {
            ids = [...document.querySelectorAll('.student-check:checked')].map(c => c.value);
        }
        
        if (!ids || !ids.length) return Notif.show('Empty payload detected', 'warning');

        Spinner.show('Assigning Topology...');
        const fd = new FormData();
        fd.append('class_id', classId);
        ids.forEach(id => fd.append('student_ids[]', id));
        fetch('../ajax/allocate_student_class.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(d => {
            Spinner.hide();
            if(d.success){ 
                bootstrap.Modal.getInstance(document.getElementById('allocateClassModal')).hide(); 
                Notif.show("Topology assigned correctly", "success"); 
                setTimeout(()=>location.reload(),1400); 
            } else Notif.show(d.message,'error');
        });
    }

    function filterStudents() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('.student-list-row').forEach(row => {
            if (row.getAttribute('data-search').includes(q)) {
                row.classList.remove('d-none');
            } else {
                row.classList.add('d-none');
            }
        });
    }
    function generatePortalKey(id, name) {
        if (!confirm(`Are you sure you want to generate/reset the portal password for ${name}?`)) return;
        
        Spinner.show('Generating security key...');
        fetch('../ajax/manage_student_portal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `student_id=${id}&action=generate`
        })
        .then(r => r.json())
        .then(data => {
            Spinner.hide();
            if (data.success) {
                document.getElementById('portalStudentName').textContent = name;
                document.getElementById('portalAdmissionNo').textContent = data.admission_no;
                document.getElementById('portalPassword').textContent = data.password;
                new bootstrap.Modal(document.getElementById('portalPasswordModal')).show();
            } else {
                Notif.show(data.message, 'error');
            }
        });
    }

    function copyToClipboard(id) {
        const text = document.getElementById(id).textContent;
        navigator.clipboard.writeText(text).then(() => {
            Notif.show('Copied to clipboard');
        });
    }

    function deleteStudent(id, name) {
        deleteTargetId = id;
        document.getElementById('deleteStudentName').innerText = name;
        new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
    }
</script>
</body>
</html>
