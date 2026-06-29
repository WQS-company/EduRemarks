<?php
// user/report_management.php - High-fidelity Report Orchestration Hub
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = intval($_GET['class_id'] ?? 0);

if (!$class_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch class details
$cls_stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND school_id = ?");
$cls_stmt->execute([$class_id, $school_id]);
$class = $cls_stmt->fetch();

if (!$class) {
    header('Location: dashboard.php');
    exit();
}

$class_show_pos = $class['show_position'] ?? 1;

// Fetch all sessions for filtering
$sessions_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = ? ORDER BY id DESC");
$sessions_stmt->execute([$school_id]);
$sessions = $sessions_stmt->fetchAll();

// Get Context Period
$current_session_id = intval($_GET['session_id'] ?? $active_school['current_session_id'] ?? 0);
$current_term_id = intval($_GET['term_id'] ?? $active_school['current_term_id'] ?? 0);

// Fetch terms for selected session
$terms_stmt = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id = ? AND school_id = ?");
$terms_stmt->execute([$current_session_id, $school_id]);
$terms = $terms_stmt->fetchAll();

if (!$current_session_id || !$current_term_id) {
    // Should handle missing session/term gracefully
}

// Fetch Students in Class
$stu_stmt = $pdo->prepare("
    SELECT s.id, s.full_name, s.admission_no, s.gender, s.image_path
    FROM students s
    JOIN student_classes sc ON sc.student_id = s.id
    WHERE sc.class_id = ? AND sc.school_id = ?
    ORDER BY s.full_name
");
$stu_stmt->execute([$class_id, $school_id]);
$students = $stu_stmt->fetchAll();

// Fetch Credit Balance
$stmt = $pdo->prepare("SELECT credits FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school_credits = $stmt->fetchColumn();

// Fetch Credit Cost per Result
$stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'credit_student_result' LIMIT 1");
$stmt->execute();
$credit_cost_per_result = $stmt->fetchColumn() ?: 1;
$total_cost = count($students) * $credit_cost_per_result;

// Fetch other classes assigned to this staff for switching
$my_other_classes = [];
$sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$sd->execute([$user_id, $school_id]);
$sd_row = $sd->fetch();
if ($sd_row) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name FROM staff_class_subjects scs
        JOIN classes c ON c.id = scs.class_id
        WHERE scs.staff_detail_id=? AND scs.school_id=? AND c.id != ?
        ORDER BY c.name
    ");
    $stmt->execute([$sd_row['id'], $school_id, $class_id]);
    $my_other_classes = $stmt->fetchAll();
}

// Tertiary Check
$type = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (
    strpos($type, 'tertiary') !== false || 
    strpos($type, 'vocational') !== false || 
    strpos($type, 'polytechnic') !== false || 
    strpos($type, 'university') !== false || 
    strpos($type, 'college') !== false
);

// Fetch Orchestration Settings for notifications
$orch_stmt = $pdo->prepare("SELECT * FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
$orch_stmt->execute([$school_id, $current_session_id, $current_term_id]);
$orchestration = $orch_stmt->fetch();

// Check for allocated courses in this term if staff
$has_allocations = true;
if ($role === 'staff' && $sd_row) {
    $alloc_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM staff_class_subjects scs
        JOIN subjects s ON s.id = scs.subject_id
        WHERE scs.staff_detail_id = ? AND scs.class_id = ? 
        AND (s.semester_id = ? OR s.semester_id IS NULL OR s.semester_id = 0)
    ");
    $alloc_stmt->execute([$sd_row['id'], $class_id, $current_term_id]);
    $has_allocations = (bool)$alloc_stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Management | <?php echo htmlspecialchars($class['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-size: 0.85rem; }
        .student-row { transition: all 0.3s ease; border-radius: 12px; border: 1px solid #eef2f6; margin-bottom: 10px; position: relative; padding: 12px !important; }
        .student-row:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(31, 60, 136, 0.06); border-color: var(--primary-blue); }
        .avatar-node { width: 40px; height: 40px; border-radius: 10px; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary-blue); border: 1px solid #e2e8f0; flex-shrink: 0; font-size: 0.9rem; }
        .assessment-sheet { display: none; }
        .assessment-sheet.active { display: block; }
        .excel-table { min-width: 800px; font-size: 0.8rem; }
        .excel-table th { background: #f1f5f9; color: #475569; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px; border: 1px solid #dee2e6; }
        .excel-table td { padding: 0; border: 1px solid #dee2e6; }
        .excel-input { width: 100%; border: none; padding: 10px; text-align: center; font-weight: 700; color: var(--primary-blue); background: transparent; transition: 0.2s; font-size: 0.85rem; }
        .excel-input:focus { outline: none; background: #fff; box-shadow: inset 0 0 0 1.5px var(--primary-blue); }
        .auto-calc { background: #f8fafc; color: #64748b; font-weight: 800; padding: 10px; text-align: center; font-size: 0.8rem; }
        .trait-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .trait-node { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; transition: 0.3s; }
        .trait-node:focus-within { border-color: var(--primary-blue); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        .trait-label { font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; letter-spacing: 0.3px; }
        .form-select-sm { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
        
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 15px 35px rgba(31, 60, 136, 0.05); border-radius: 24px; transition: transform 0.3s ease; }
        
        .student-row { background: #fff; border-radius: 18px; border: 1px solid #f1f5f9; transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); margin-bottom: 12px; }
        .student-row:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(31, 60, 136, 0.08); border-color: var(--primary-blue); z-index: 5; }
        
        @media (max-width: 768px) {
            .orchestration-header { flex-direction: column; align-items: stretch !important; gap: 12px; }
            .liquidity-stats { 
                width: 100%; 
                padding: 12px 15px; 
                background: #f8fafc; 
                border-radius: 16px; 
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            .liquidity-stats .vr { display: none !important; }
            .liquidity-stats button { grid-column: span 2; width: 100%; height: 45px !important; margin-top: 5px; }
            
            .student-row { padding: 18px !important; flex-direction: column !important; align-items: stretch !important; }
            .student-info-meta { margin-bottom: 15px; }
            
            .student-actions { display: flex !important; flex-wrap: nowrap; gap: 4px; width: 100% !important; justify-content: space-between; }
            .student-actions .btn { 
                flex: 1; 
                font-size: 0.62rem !important; 
                padding: 6px 4px !important; 
                border-radius: 8px !important; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                white-space: nowrap; 
                letter-spacing: -0.3px;
                min-width: 0;
                overflow: hidden;
            }
            .student-actions .btn i { margin: 0 3px 0 0 !important; font-size: 0.7rem; }
            .student-actions .btn.px-3 { padding-left: 2px !important; padding-right: 2px !important; }
            
            .status-node { display: none !important; }
            .sa-main-content { padding: 12px !important; }
        }

        /* Modal Overrides for High Frequency Entry */
        .modal-fullscreen-node { max-width: 95%; }
        .assessment-modal-content { border-radius: 24px; border: none; overflow: hidden; }
        .assessment-modal-header { background: #f8fafc; border-bottom: 1px solid #eef2f6; padding: 20px 25px; }
        .trait-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
        .trait-node { border: 1.5px solid #f1f5f9; padding: 10px; background: #fff; border-radius: 12px; }
        .trait-label { font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .excel-input { font-size: 0.85rem; height: 38px; }
        .auto-calc { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/success_overlay.php'; ?>
    <?php include '../includes/notifications.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="container-fluid py-3">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="class_view.php?class_id=<?php echo $class_id; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">
                        <i class="fas fa-arrow-left me-1"></i> BACK
                    </a>
                    <div>
                        <h4 class="fw-900 mb-0"><?php echo get_label('Report Sheets'); ?> Orchestration</h4>
                        <p class="text-muted mb-0" style="font-size: 0.75rem;">Academic Synchronization for <strong><?php echo htmlspecialchars($class['name']); ?></strong></p>
                    </div>
                </div>
                
                <div class="liquidity-stats d-flex align-items-center gap-4 bg-white p-3 rounded-4 shadow-sm border">
                    <div class="text-end">
                        <div class="extra-small fw-800 text-muted uppercase tracking-2" style="font-size: 0.55rem;">Institutional Liquidity</div>
                        <div class="fw-900 text-primary mb-0" style="font-size: 1.1rem;"><?php echo number_format($school_credits); ?> <small class="fw-700 opacity-50" style="font-size: 0.6rem;">Units</small></div>
                    </div>
                    <div class="vr bg-dark opacity-10 d-none d-md-block" style="height: 30px;"></div>
                    <div class="text-end me-2">
                        <div class="extra-small fw-800 text-muted uppercase tracking-2" style="font-size: 0.55rem;">Resource requirement</div>
                        <div class="fw-900 text-warning mb-0" style="font-size: 1.1rem;"><?php echo number_format($total_cost); ?> <small class="fw-700 opacity-50" style="font-size: 0.6rem;">Units</small></div>
                    </div>
                    <?php if ($staff_permissions['can_edit_history']): ?>
                    <a href="history_master_entry.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold shadow-sm h-100 d-flex align-items-center" style="font-size: 0.75rem; height: 40px !important;">
                        <i class="fas fa-history me-2"></i>History Audit Hub
                    </a>
                    <?php endif; ?>
                    <?php if (!$is_higher_ed || ($role !== 'staff')): ?>
                    <button class="btn btn-dark btn-sm rounded-pill px-4 fw-bold shadow-sm h-100" style="font-size: 0.75rem; height: 40px !important;" id="downloadAllBtn" onclick="openTemplateSelector()" <?php echo ($school_credits < $total_cost) ? 'disabled title="Insufficient Credits"' : ''; ?>>
                        <i class="fas fa-file-pdf me-2"></i>Download <?php echo get_label('Report Sheet'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Entry Window Notification -->
            <?php if ($orchestration && $orchestration['global_status'] === 'open' && $orchestration['entry_deadline']): ?>
                <div class="alert alert-primary border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; min-width: 45px;">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div>
                        <div class="fw-900 extra-small uppercase tracking-1 text-primary mb-1">Result Entry Window Active</div>
                        <div class="small fw-700">The result entry for <strong><?php echo htmlspecialchars($active_school['session_name'] ?? ''); ?> (<?php echo htmlspecialchars($active_school['term_name'] ?? ''); ?>)</strong> is currently OPEN and will be closed on <strong><?php echo date('F j, Y', strtotime($orchestration['entry_deadline'])); ?></strong>. Please ensure all entries are synchronized before this date.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- No Allocation Notification for Staff -->
            <?php if ($role === 'staff' && !$has_allocations): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4 d-flex align-items-center gap-3 bg-white">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; min-width: 55px;">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-900 extra-small uppercase tracking-1 text-warning mb-1">Attention: No <?php echo get_label('Subject'); ?> Assignment</div>
                        <div class="small fw-700 text-dark">We couldn't find any <?php echo strtolower(get_label('Subjects')); ?> allocated to you for the <strong><?php echo htmlspecialchars($active_school['term_name'] ?? ('selected ' . strtolower(get_label('Term')))); ?></strong>. If this is an error, please contact the academic administrator to update your <?php echo strtolower(get_label('Subject')); ?> mapping.</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Academic Context Filter -->
            <div class="glass-card p-4 mb-4 border-0 shadow-sm bg-white" style="border-radius: 20px;">
                <form method="GET" class="row g-3 align-items-center">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <div class="col-md-3">
                        <label class="extra-small fw-800 text-muted uppercase tracking-1 mb-2">Academic Session</label>
                        <select name="session_id" class="form-select border-0 bg-light rounded-pill fw-700" onchange="this.form.submit()">
                            <?php foreach($sessions as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $current_session_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="extra-small fw-800 text-muted uppercase tracking-1 mb-2"><?php echo get_label('Term'); ?> Context</label>
                        <select name="term_id" class="form-select border-0 bg-light rounded-pill fw-700" onchange="this.form.submit()">
                            <?php foreach($terms as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $current_term_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end pt-md-4">
                        <div class="badge bg-soft-primary text-primary px-4 py-2 rounded-pill fw-800 uppercase tracking-1" style="font-size: 0.65rem;">
                            <i class="fas fa-history me-2"></i> Data Orchestration Active
                        </div>
                    </div>
                </form>
            </div>

            <!-- Student List Section -->
            <div class="glass-card p-4 bg-white border-0 shadow-sm" style="border-radius: 24px;">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <h5 class="fw-900 mb-0 text-dark">Institutional Roster</h5>
                    
                    <div class="d-flex align-items-center gap-4">
                        <?php if (!$is_higher_ed): ?>
                        <div class="form-check form-switch d-flex align-items-center m-0 p-0">
                            <input class="form-check-input m-0" type="checkbox" id="globalPositionToggle" <?php echo $class_show_pos ? 'checked' : ''; ?> style="width: 2.8em; height: 1.4em; cursor: pointer; flex-shrink: 0;">
                            <label class="form-check-label ms-2 fw-800 small text-dark uppercase tracking-1" for="globalPositionToggle" style="font-size: 0.75rem; cursor: pointer;">Display Position</label>
                        </div>
                        <?php endif; ?>
                        <div class="small text-muted fw-bold">Active <?php echo get_label('Session'); ?>: <span class="text-primary"><?php echo $active_school['session_name'] ?? 'Active'; ?></span></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <?php foreach($students as $stu): ?>
                        <div class="student-row p-3 bg-white d-flex align-items-center justify-content-between" id="stu_row_<?php echo $stu['id']; ?>">
                                <div class="student-info-meta d-flex align-items-center gap-3">
                                    <div class="avatar-node overflow-hidden">
                                        <?php if($stu['image_path']): ?>
                                            <img src="../<?php echo $stu['image_path']; ?>" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?>
                                            <img src="../img/default_picture.png" style="width:100%;height:100%;object-fit:cover;">
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-800 text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($stu['full_name']); ?></div>
                                        <div class="extra-small text-muted fw-bold uppercase"><?php echo get_label('Admission No'); ?>: <?php echo htmlspecialchars($stu['admission_no']); ?> &bull; <?php echo $stu['gender']; ?></div>
                                    </div>
                                </div>
                                <div class="student-actions d-flex gap-2 align-items-center">
                                    <a href="assessment_entry.php?student_id=<?php echo $stu['id']; ?>&class_id=<?php echo $class_id; ?>&session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">
                                        <i class="fas fa-edit me-1"></i> <?php echo (get_label('Subject') === 'Course') ? 'Result Entry' : 'Assessment'; ?>
                                    </a>
                                    <?php if (!$is_higher_ed): ?>
                                    <a href="skills_entry.php?student_id=<?php echo $stu['id']; ?>&class_id=<?php echo $class_id; ?>&session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold mx-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-brain me-1"></i> Skills
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!$is_higher_ed || ($role !== 'staff')): ?>
                                    <button class="btn btn-outline-info btn-sm rounded-pill px-3 fw-bold" style="font-size: 0.75rem;" onclick="openTemplateSelector(<?php echo $stu['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i> Preview
                                    </button>
                                    <?php endif; ?>
                                    <a href="student_history_audit.php?student_id=<?php echo $stu['id']; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-bold" style="font-size: 0.75rem;">
                                        <i class="fas fa-history me-1"></i> History
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CLASS_ID = <?php echo $class_id; ?>;
        const SESSION_ID = <?php echo $current_session_id; ?>;
        const TERM_ID = <?php echo $current_term_id; ?>;

        function defaultDomains(studentId, type) {
            const affectiveTraits = ['Punctuality', 'Attendance', 'Reliability', 'Neatness', 'Politeness', 'Honesty', 'Relationship with Students', 'Self Control', 'Attentiveness', 'Perseverance'];
            const psychomotorTraits = ['Handwriting', 'Games', 'Sports', 'Drawing & Painting', 'Crafts', 'Musical Skills'];
            const names = (type === 'affective') ? affectiveTraits : psychomotorTraits;
            const traits = names.map(n => ({ name: n, type: type, rating: 5 })); // Default to 5 (Excellent) for high-performance nodes

            if (typeof Spinner !== 'undefined') Spinner.show('Applying Institutional Defaults...');
            $.ajax({
                url: '../ajax/save_student_traits.php',
                type: 'POST',
                data: { student_id: studentId, class_id: CLASS_ID, session_id: SESSION_ID, term_id: TERM_ID, traits: traits },
                success: function(res) {
                    if (typeof Spinner !== 'undefined') Spinner.hide();
                    if(res.success) {
                        if (typeof Notif !== 'undefined') Notif.show(`Institutional defaults applied (${type})`, 'success');
                    } else if (typeof Notif !== 'undefined') Notif.show(res.message, 'error');
                },
                dataType: 'json'
            });
        }

        function openTemplateSelector(studentId = 0) {
            const showPos = document.getElementById('globalPositionToggle').checked ? 1 : 0;
            window.location.href = `report_templates.php?class_id=${CLASS_ID}&session_id=${SESSION_ID}&term_id=${TERM_ID}&student_id=${studentId}&show_pos=${showPos}`;
        }

        $(document).ready(function() {
            $('#globalPositionToggle').on('change', function() {
                const showPos = $(this).is(':checked') ? 1 : 0;
                $.ajax({
                    url: '../ajax/toggle_class_position.php',
                    type: 'POST',
                    data: { class_id: CLASS_ID, show_position: showPos },
                    success: function(res) {
                        if (typeof Notif !== 'undefined') {
                            if (res.success) {
                                Notif.show('Position display status saved', 'success');
                            } else {
                                Notif.show(res.message || 'Error updating status', 'error');
                            }
                        }
                    },
                    dataType: 'json'
                });
            });
        });
    </script>
</body>
</html>
