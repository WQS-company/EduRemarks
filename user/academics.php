<?php
// admin/academics.php
require_once '../includes/auth_check.php';
if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}
if ($role === 'staff' && empty($staff_permissions['can_manage_academics'])) {
    header('Location: dashboard.php');
    exit();
}
if (!$active_school) { header('Location: dashboard.php'); exit(); }

// Determine if institution is tertiary (course mode)
$school_type = strtolower($active_school['school_type'] ?? '');
$is_course   = (str_contains($school_type,'university') || str_contains($school_type,'polytechnic') || str_contains($school_type,'college') || str_contains($school_type,'tertiary') || str_contains($school_type,'vocational')) ? 1 : 0;
$label       = $is_course ? 'Course' : 'Subject';
$label_pl    = $is_course ? 'Courses' : 'Subjects';

// Fetch existing classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE school_id=? ORDER BY name");
$stmt->execute([$active_school['id']]);
$classes = $stmt->fetchAll();

// Fetch existing subjects
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE school_id=? ORDER BY name");
$stmt->execute([$active_school['id']]);
$subjects = $stmt->fetchAll();

// Fetch class_subjects mapping
$csMap = [];
$stmt2 = $pdo->prepare("SELECT cs.class_id, cs.subject_id FROM class_subjects cs JOIN classes c ON c.id=cs.class_id WHERE c.school_id=?");
$stmt2->execute([$active_school['id']]);
foreach ($stmt2->fetchAll() as $row) $csMap[$row['class_id']][] = $row['subject_id'];

// Fetch Academic Sessions
$sessions = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id=? ORDER BY created_at DESC");
$sessions->execute([$active_school['id']]);
$all_sessions = $sessions->fetchAll();

// Fetch Terms for active session if any
$current_session_id = $active_school['current_session_id'];
$active_terms = [];
if ($current_session_id) {
    $terms = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id=? AND school_id=? ORDER BY created_at ASC");
    $terms->execute([$current_session_id, $active_school['id']]);
    $active_terms = $terms->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academics | <?php echo htmlspecialchars($active_school['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .builder-row { background:#f8faff; border:1px solid #e3eaff; border-radius:10px; padding:12px 16px; margin-bottom:10px; transition:box-shadow .2s; }
        .builder-row:hover { box-shadow:0 4px 14px rgba(31,60,136,.09); }
        .btn-add-row { border:2px dashed #2D6CDF; color:#2D6CDF; background:transparent; border-radius:10px; width:100%; padding:10px; font-weight:600; transition:.2s; }
        .btn-add-row:hover { background:#f0f4ff; }
        .subject-badge { font-size:.72rem; padding:4px 10px; border-radius:20px; background:#EEF2FB; color:#1F3C88; border:1px solid #d0daff; font-weight:600; margin:2px; display:inline-block; }
        .tab-pane { animation: fadeInUp .3s ease; }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
        .section-title { font-size:.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#94a3b8; font-weight:700; margin-bottom:12px; }
        .existing-item { display:flex; align-items:center; gap:10px; padding:10px 14px; background:#fff; border:1px solid #eef2fb; border-radius:10px; margin-bottom:8px; }
        .existing-item .item-info { flex:1; }
        .existing-item { display:flex; align-items:center; gap:12px; padding:12px; border-bottom:1px solid #edf2f7; transition:.2s; }
        .existing-item:hover { background:#f8fafc; }
        .existing-item .item-info { flex-grow:1; min-width:0; }
        .existing-item .item-name { font-weight:600; font-size:.95rem; color:#1e293b; }
        .existing-item .item-meta { font-size:.75rem; color:#64748b; margin-top:2px; }
        
        @media (max-width: 576px) {
            .existing-item { padding:10px; }
            .item-name { font-size:.85rem; }
            .item-meta { font-size:.7rem; }
            .btn-sm i { font-size:.75rem; }
        }
        .mapping-card { border-radius:12px; border:1px solid #eef2fb; overflow:hidden; margin-bottom:10px; }
        .mapping-card .mapping-head { background:#F4F7FF; padding:12px 16px; font-weight:600; font-size:.875rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; }
        .mapping-card .mapping-body { padding:14px 16px; }
        .check-label { cursor:pointer; padding:6px 12px; border-radius:8px; border:1px solid #e3eaff; font-size:.82rem; font-weight:500; transition:.15s; display:inline-flex; align-items:center; gap:6px; margin:3px; }
        .check-label input:checked ~ span, .check-label:has(input:checked) { background:#EEF2FB; border-color:#2D6CDF; color:#1F3C88; }
        .check-label input:checked ~ span, .check-label:has(input:checked) { background:#EEF2FB; border-color:#2D6CDF; color:#1F3C88; }
        .confirm-modal-icon { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; background: #FFEBEE; color: #C62828; }
        .confirm-modal-icon.success { background: #E8F5E9; color: #2E7D32; }
    </style>
</head>
<body class="bg-light">
<?php include '../includes/spinner.php'; ?>

<?php if ($role === 'staff'): ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>
    <main class="sa-main-content">
<?php else: ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('active')"></div>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
<?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0"><?php echo (get_label('Term') === 'Semester') ? 'Academic Registry' : 'Academics'; ?></h3>
                <p class="text-muted small mb-0">Manage <?php echo strtolower(get_label('Classes')); ?> and <?php echo strtolower($label_pl); ?> for <?php echo htmlspecialchars($active_school['school_name']); ?></p>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-pills mb-4 gap-2" id="acadTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active px-4 fw-600" data-bs-toggle="pill" data-bs-target="#tabClasses"><i class="fas fa-layer-group me-2"></i><?php echo get_label('Classes'); ?></button></li>
            <li class="nav-item"><button class="nav-link px-4 fw-600" data-bs-toggle="pill" data-bs-target="#tabSubjects"><i class="fas fa-book-open me-2"></i><?php echo $label_pl; ?></button></li>
            <li class="nav-item"><button class="nav-link px-4 fw-600" data-bs-toggle="pill" data-bs-target="#tabMapping"><i class="fas fa-link me-2"></i><?php echo get_label('Class'); ?> &rarr; <?php echo get_label('Subject'); ?> Mapping</button></li>
            <li class="nav-item"><button class="nav-link px-4 fw-600" data-bs-toggle="pill" data-bs-target="#tabSessions"><i class="fas fa-calendar-alt me-2"></i>Sessions & <?php echo get_label('Terms'); ?></button></li>
        </ul>

        <div class="tab-content">
            <!-- ===== TAB 1: CLASSES ===== -->
            <div class="tab-pane fade show active" id="tabClasses">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="glass-card p-4">
                            <p class="section-title">Add New <?php echo get_label('Classes'); ?></p>
                            <div id="classBuilderRows"></div>
                            <button class="btn-add-row mt-2" onclick="addClassRow()"><i class="fas fa-plus me-2"></i>Add Another <?php echo get_label('Class'); ?></button>
                            <div class="text-end mt-4">
                                <button class="btn btn-primary px-5 py-2" onclick="saveClasses()"><i class="fas fa-save me-2"></i>Save All <?php echo get_label('Classes'); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="glass-card p-4">
                            <p class="section-title">Existing <?php echo get_label('Classes'); ?> (<?php echo count($classes); ?>)</p>
                            <?php if (empty($classes)): ?>
                                <p class="text-muted small text-center py-3">No <?php echo strtolower(get_label('Classes')); ?> created yet.</p>
                            <?php else: foreach ($classes as $c): ?>
                            <div class="existing-item" id="class-item-<?php echo $c['id']; ?>">
                                <div class="item-info">
                                    <div class="item-name"><?php echo htmlspecialchars($c['name']); ?></div>
                                    <div class="item-meta"><?php if (!$is_course): ?>Code: <strong><?php echo htmlspecialchars($c['code']); ?></strong><?php endif; ?><?php echo $c['section'] ? ($is_course ? '' : ' &bull; ') . get_label('Section') . ': '.$c['section'] : ''; ?></div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="editClass(<?php echo $c['id']; ?>,'<?php echo addslashes($c['name']); ?>','<?php echo addslashes($c['code']); ?>','<?php echo addslashes($c['section']); ?>','<?php echo $c['sequence_level']; ?>')"><i class="fas fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteClass(<?php echo $c['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== TAB 2: SUBJECTS/COURSES ===== -->
            <div class="tab-pane fade" id="tabSubjects">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="glass-card p-4">
                            <p class="section-title">Add New <?php echo $label_pl; ?></p>
                            <div id="subjectBuilderRows"></div>
                            <button class="btn-add-row mt-2" onclick="addSubjectRow()"><i class="fas fa-plus me-2"></i>Add Another <?php echo $label; ?></button>
                            <div class="text-end mt-4">
                                <button class="btn btn-primary px-5 py-2" onclick="saveSubjects()"><i class="fas fa-save me-2"></i>Save All <?php echo $label_pl; ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="glass-card p-4">
                            <p class="section-title">Existing <?php echo $label_pl; ?> (<?php echo count($subjects); ?>)</p>
                            <?php if (empty($subjects)): ?>
                                <p class="text-muted small text-center py-3">No <?php echo strtolower($label_pl); ?> created yet.</p>
                            <?php else: foreach ($subjects as $s): ?>
                            <div class="existing-item" id="sub-item-<?php echo $s['id']; ?>">
                                <div class="item-info">
                                    <div class="item-name"><?php echo htmlspecialchars($s['name']); ?></div>
                                    <div class="item-meta">Code: <strong><?php echo htmlspecialchars($s['code']); ?></strong><?php echo $s['period'] ? ' &bull; Scheduled: '.date("M j, Y - g:i A", strtotime($s['period'])) : ''; ?></div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="editSubject(<?php echo $s['id']; ?>,'<?php echo addslashes($s['name']); ?>','<?php echo addslashes($s['code']); ?>','<?php echo addslashes($s['period']); ?>')"><i class="fas fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSubject(<?php echo $s['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== TAB 3: MAPPING ===== -->
            <div class="tab-pane fade" id="tabMapping">
                <div class="glass-card p-4">
                    <p class="section-title">Assign <?php echo $label_pl; ?> to <?php echo get_label('Classes'); ?></p>
                    <?php if (empty($classes)): ?>
                        <p class="text-muted text-center py-4">Create <?php echo strtolower(get_label('Classes')); ?> first, then map <?php echo strtolower($label_pl); ?> here.</p>
                    <?php elseif (empty($subjects)): ?>
                        <p class="text-muted text-center py-4">Create <?php echo strtolower($label_pl); ?> first, then map them to <?php echo strtolower(get_label('Classes')); ?> here.</p>
                    <?php else: foreach ($classes as $c): $mapped = $csMap[$c['id']] ?? []; ?>
                    <div class="mapping-card">
                        <div class="mapping-head" onclick="this.nextElementSibling.classList.toggle('d-none')">
                            <span><i class="fas fa-layer-group me-2 text-primary"></i><?php echo htmlspecialchars($c['name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($c['code']); ?>)</small></span>
                            <i class="fas fa-chevron-down text-muted"></i>
                        </div>
                        <div class="mapping-body">
                            <div class="mb-3">
                                <?php foreach ($subjects as $s): ?>
                                <label class="check-label">
                                    <input type="checkbox" name="sub_<?php echo $c['id']; ?>[]" value="<?php echo $s['id']; ?>" <?php echo in_array($s['id'],$mapped) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($s['name']); ?> <small class="text-muted">(<?php echo $s['code']; ?>)</small></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <button class="btn btn-sm btn-primary px-4" onclick="saveMapping(<?php echo $c['id']; ?>)"><i class="fas fa-save me-1"></i>Save Mapping</button>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- ===== TAB 4: SESSIONS & TERMS ===== -->
            <div class="tab-pane fade" id="tabSessions">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="glass-card p-4 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <p class="section-title mb-0">Academic Sessions</p>
                                <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="openSessionModal()">
                                    <i class="fas fa-plus me-1"></i>New Session
                                </button>
                            </div>
                            
                            <?php if (empty($all_sessions)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-day text-light mb-3" style="font-size:3rem;"></i>
                                    <p class="text-muted">No academic sessions created yet.</p>
                                </div>
                            <?php else: foreach ($all_sessions as $sess): ?>
                                <div class="existing-item <?php echo $active_school['current_session_id'] == $sess['id'] ? 'border-primary bg-light' : ''; ?>">
                                    <div class="item-info">
                                        <div class="item-name">
                                            <?php echo htmlspecialchars($sess['name']); ?>
                                            <?php if ($active_school['current_session_id'] == $sess['id']): ?>
                                                <span class="badge bg-primary ms-2" style="font-size:0.6rem;">ACTIVE</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="item-meta">Status: <?php echo ucfirst($sess['status']); ?></div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <?php if ($sess['status'] === 'active' && $active_school['current_session_id'] != $sess['id']): ?>
                                            <button class="btn btn-sm btn-outline-success border-0" onclick="setActiveSession(<?php echo $sess['id']; ?>)" title="Make Active"><i class="fas fa-check-circle"></i></button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary border-0" onclick="archiveSession(<?php echo $sess['id']; ?>)" title="Archive"><i class="fas fa-archive"></i></button>
                                        <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteSession(<?php echo $sess['id']; ?>, '<?php echo addslashes($sess['name']); ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="glass-card p-4 h-100">
                            <p class="section-title">Academic <?php echo get_label('Terms'); ?> (Current Session)</p>
                            <?php if (!$current_session_id): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clock text-light mb-3" style="font-size:3rem;"></i>
                                    <p class="text-muted small">Please select/create an active session first.</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex gap-2 mb-3">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill w-100" onclick="quickCreateTerms()">
                                        <i class="fas fa-magic me-1"></i>Auto-Generate <?php echo get_label('Terms'); ?>
                                    </button>
                                </div>
                                
                                <?php if (empty($active_terms)): ?>
                                    <p class="text-muted small text-center">No <?php echo strtolower(get_label('Terms')); ?> defined for this session.</p>
                                <?php else: foreach ($active_terms as $t): ?>
                                    <div class="existing-item <?php echo $active_school['current_term_id'] == $t['id'] ? 'border-success bg-light text-success' : ''; ?>">
                                        <div class="item-info">
                                            <div class="item-name"><?php echo htmlspecialchars(get_label($t['name'])); ?></div>
                                            <div class="item-meta"><?php echo $active_school['current_term_id'] == $t['id'] ? 'Currently Active' : 'Off-'.get_label('Term'); ?></div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <?php if ($active_school['current_term_id'] != $t['id']): ?>
                                                <button class="btn btn-sm btn-outline-success border-0" onclick="setActiveTerm(<?php echo $t['id']; ?>)" title="Set Active"><i class="fas fa-play"></i></button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteTerm(<?php echo $t['id']; ?>, '<?php echo addslashes($t['name']); ?>')" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>

                                <div class="mt-4 p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3">
                                    <h6 class="fw-bold text-warning-emphasis small"><i class="fas fa-rocket me-2"></i>Promote <?php echo get_label('Pupils'); ?></h6>
                                    <p class="text-muted" style="font-size:0.75rem;">Transition <?php echo strtolower(get_label('Pupils')); ?> from their current <?php echo strtolower(get_label('Class')); ?> to the next sequence level (e.g., Level 100 &rarr; Level 200). This is usually done at the end of the last <?php echo strtolower(get_label('Term')); ?>.</p>
                                    <button class="btn btn-warning btn-sm w-100 fw-bold rounded-pill" onclick="openPromotionModal()">
                                        START AUTOMATIC PROMOTION
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0">Create Academic Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Session Name (e.g. 2025/2026)</label>
                    <input type="text" id="sessName" class="form-control" placeholder="2025/2026">
                </div>
                <div class="alert alert-info border-0 rounded-4 small">
                    <i class="fas fa-info-circle me-2"></i>New sessions are created as 'Active' by default. You can archive them later.
                </div>
                <button class="btn btn-primary w-100 rounded-pill py-3 fw-bold" onclick="saveSession()">
                    CREATE SESSION & ACTIVATE
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold mb-0"><?php echo get_label('Pupils'); ?> Promotion Control</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Explicitly define the academic path for each <?php echo strtolower(get_label('Class')); ?>. <?php echo get_label('Pupils'); ?> will be moved into their target <?php echo strtolower(get_label('Class')); ?> once the promotion is executed. Leave target blank for graduation candidates.</p>
                
                <div class="table-responsive mb-4">
                    <table class="table table-sm small align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Source <?php echo get_label('Class'); ?></th>
                                <th>Target Promotion <?php echo get_label('Class'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($classes as $c): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($c['name']); ?></td>
                                <td>
                                    <select class="form-select form-select-sm next-class-select" data-id="<?php echo $c['id']; ?>">
                                        <option value="">-- Graduation / Exit --</option>
                                        <?php foreach($classes as $tc): if($tc['id'] == $c['id']) continue; ?>
                                            <option value="<?php echo $tc['id']; ?>" <?php echo ($c['next_class_id'] == $tc['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tc['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning border-0 rounded-4 small mb-4">
                    <i class="fas fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will update the 'student_class' for ALL students. Ensure your target mappings are correct!
                </div>

                <button class="btn btn-warning w-100 rounded-pill py-3 fw-bold shadow-sm" onclick="processPromotion()">
                    <i class="fas fa-rocket me-2"></i>CONFIRM & EXECUTE PROMOTION
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body p-5 text-center">
                <div class="confirm-modal-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4 class="fw-bold mb-2">Delete <span id="deleteTypeLabel"></span>?</h4>
                <p class="text-muted mb-4">Are you sure you want to remove "<span id="deleteItemName" class="fw-bold text-dark"></span>"? This action cannot be undone and will affect all associated data.</p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDeleteBtn">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body p-5 text-center">
                <div class="confirm-modal-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <h4 class="fw-bold mb-2">Success!</h4>
                <p class="text-muted mb-4" id="successMessage">The record has been deleted successfully.</p>
                <button type="button" class="btn btn-primary rounded-pill px-5" onclick="location.reload()">Great!</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const IS_COURSE = <?php echo $is_course ? 'true' : 'false'; ?>;
const LABEL     = IS_COURSE ? 'Course' : 'Subject';
const CLASS_NAME_LABEL = '<?php echo get_label("Class Name"); ?>';
const SECTION_LABEL    = '<?php echo get_label("Section"); ?>';
const SUBJECT_NAME_LABEL = '<?php echo get_label("Subject Name"); ?>';
const SUBJECT_CODE_LABEL = '<?php echo get_label("Subject Code"); ?>';
let classRowId = 0, subRowId = 0;

// ---- CLASS BUILDER ----
function addClassRow(id='', name='', code='', section='', seq='0') {
    const rid = ++classRowId;
    const codeField = IS_COURSE ? `<input type="hidden" value="${code || 'AUTO'}" data-field="code">` 
                                : `<div class="col-md-2"><input class="form-control form-control-sm" placeholder="Code *" value="${code}" data-field="code"></div>`;
    const html = `<div class="builder-row" id="crow-${rid}">
        <div class="row g-2 align-items-center">
            <input type="hidden" name="class_id" value="${id}">
            <div class="col-md-${IS_COURSE ? '4' : '3'}"><input class="form-control form-control-sm" placeholder="${CLASS_NAME_LABEL} *" value="${name}" data-field="name"></div>
            ${IS_COURSE ? '' : codeField}
            <div class="col-md-3">
                <input class="form-control form-control-sm" placeholder="${SECTION_LABEL}" value="${section}" data-field="section">
            </div>
            <div class="col-md-2" title="Sequence for Promotion (1, 2, 3...)">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-sort-numeric-down"></i></span>
                    <input type="number" class="form-control bg-white border-start-0" placeholder="Seq" value="${seq}" data-field="seq">
                </div>
            </div>
            <div class="col-md-2 text-end"><button class="btn btn-sm btn-outline-danger" onclick="document.getElementById('crow-${rid}').remove()"><i class="fas fa-minus"></i></button></div>
        </div>
        ${IS_COURSE ? codeField : ''}
    </div>`;
    document.getElementById('classBuilderRows').insertAdjacentHTML('beforeend', html);
}

function saveClasses() {
    const rows = document.querySelectorAll('#classBuilderRows .builder-row');
    const classes = [];
    rows.forEach(r => {
        const codeEl = r.querySelector('[data-field="code"]');
        classes.push({
            id: r.querySelector('[name="class_id"]').value,
            name: r.querySelector('[data-field="name"]').value.trim(),
            code: codeEl ? codeEl.value.trim() : '',
            section: r.querySelector('[data-field="section"]').value.trim(),
            seq: r.querySelector('[data-field="seq"]').value
        });
    });
    if (!classes.length) return Notif.show('Add at least one class row', 'warning');
    Spinner.show('Saving classes...');
    fetch('../ajax/save_classes.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({classes}) })
    .then(r=>r.json()).then(d => { Spinner.hide(); if(d.success){Notif.show(d.message); setTimeout(()=>location.reload(),1500);}else Notif.show(d.message,'error'); });
}

function editClass(id, name, code, section, seq) {
    document.getElementById('classBuilderRows').innerHTML = '';
    addClassRow(id, name, code, section, seq);
    document.querySelector('[data-bs-target="#tabClasses"]').click();
    document.getElementById('classBuilderRows').scrollIntoView({behavior:'smooth'});
}

function deleteClass(id) {
    const itemName = document.querySelector(`#class-item-${id} .item-name`).textContent;
    openDeleteModal(id, 'class', itemName);
}

// ---- SUBJECT BUILDER ----
function addSubjectRow(id='', name='', code='', period='') {
    const rid = ++subRowId;
    const periodLabel = IS_COURSE ? 'Start Date & Time' : 'Date & Time';
    const html = `<div class="builder-row" id="srow-${rid}">
        <div class="row g-2 align-items-center">
            <input type="hidden" name="subj_id" value="${id}">
            <div class="col-md-4"><input class="form-control form-control-sm" placeholder="${SUBJECT_NAME_LABEL} *" value="${name}" data-field="name"></div>
            <div class="col-md-3"><input class="form-control form-control-sm" placeholder="${SUBJECT_CODE_LABEL} *" value="${code}" data-field="code"></div>
            <div class="col-md-3"><input class="form-control form-control-sm time-picker" placeholder="${periodLabel} *" value="${period}" data-field="period" readonly></div>
            <div class="col-md-2 text-end"><button class="btn btn-sm btn-outline-danger" onclick="document.getElementById('srow-${rid}').remove()"><i class="fas fa-minus"></i></button></div>
        </div>
    </div>`;
    document.getElementById('subjectBuilderRows').insertAdjacentHTML('beforeend', html);
    
    // Initialize Flatpickr for the new row
    flatpickr(`#srow-${rid} .time-picker`, {
        enableTime: true,
        dateFormat: "Y-m-d h:i K",
        altInput: true,
        altFormat: "F j, Y - h:i K",
        defaultDate: period || null
    });
}

function saveSubjects() {
    const rows = document.querySelectorAll('#subjectBuilderRows .builder-row');
    const subjects = [];
    rows.forEach(r => {
        subjects.push({
            id: r.querySelector('[name="subj_id"]').value,
            name: r.querySelector('[data-field="name"]').value.trim(),
            code: r.querySelector('[data-field="code"]').value.trim(),
            period: r.querySelector('[data-field="period"]').value.trim()
        });
    });
    if (!subjects.length) return Notif.show('Add at least one row', 'warning');
    Spinner.show(`Saving ${LABEL}s...`);
    fetch('../ajax/save_subjects.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({subjects, is_course: IS_COURSE}) })
    .then(r=>r.json()).then(d => { Spinner.hide(); if(d.success){Notif.show(d.message); setTimeout(()=>location.reload(),1500);}else Notif.show(d.message,'error'); });
}

function editSubject(id, name, code, period) {
    document.getElementById('subjectBuilderRows').innerHTML = '';
    addSubjectRow(id, name, code, period);
    document.querySelector('[data-bs-target="#tabSubjects"]').click();
    document.getElementById('subjectBuilderRows').scrollIntoView({behavior:'smooth'});
}

function deleteSubject(id) {
    const itemName = document.querySelector(`#sub-item-${id} .item-name`).textContent;
    openDeleteModal(id, 'subject', itemName);
}

// ---- SHARED DELETE LOGIC ----
let deleteTargetId = null;
let deleteTargetType = null;

function openDeleteModal(id, type, name) {
    deleteTargetId = id;
    deleteTargetType = type;
    let label = '';
    if(type === 'class') label = CLASS_NAME_LABEL.replace(' Name', '');
    else if(type === 'subject') label = LABEL;
    else if(type === 'session') label = 'Academic Session';
    else if(type === 'term') label = 'Academic ' + (IS_COURSE ? 'Semester' : 'Term');
    
    document.getElementById('deleteTypeLabel').textContent = label;
    document.getElementById('deleteItemName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteTargetId || !deleteTargetType) return;
    
    // Close confirm modal
    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
    
    Spinner.show('Deleting...');
    let endpoint = '';
    if(deleteTargetType === 'class') endpoint = '../ajax/delete_class.php';
    else if(deleteTargetType === 'subject') endpoint = '../ajax/delete_subject.php';
    else if(deleteTargetType === 'session') endpoint = '../ajax/delete_academic_session.php';
    else if(deleteTargetType === 'term') endpoint = '../ajax/delete_academic_term.php';

    const fd = new FormData();
    fd.append('id', deleteTargetId);

    fetch(endpoint, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success) {
            new bootstrap.Modal(document.getElementById('successModal')).show();
        } else {
            Notif.show(d.message, 'error');
        }
    });
});

// ---- MAPPING ----
function saveMapping(classId) {
    const checks = document.querySelectorAll(`[name="sub_${classId}[]"]:checked`);
    const ids = [...checks].map(c => c.value);
    Spinner.show('Saving mapping...');
    const fd = new FormData();
    fd.append('class_id', classId);
    ids.forEach(id => fd.append('subject_ids[]', id));
    fetch('../ajax/save_class_subjects.php', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        Spinner.hide(); if(d.success) Notif.show(d.message); else Notif.show(d.message,'error');
    });
}

// ---- SESSIONS & TERMS ----
function openSessionModal() {
    new bootstrap.Modal(document.getElementById('sessionModal')).show();
}

function saveSession() {
    const name = document.getElementById('sessName').value.trim();
    if(!name) return Notif.show('Session name is required', 'error');
    
    Spinner.show('Creating session...');
    fetch('../ajax/save_academic_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ name })
    }).then(r=>r.json()).then(d => {
        Spinner.hide();
        if(d.success) {
            Notif.show(d.message);
            setTimeout(()=>location.reload(), 1500);
        } else Notif.show(d.message, 'error');
    });
}

function setActiveSession(id) {
    Spinner.show('Switching active session...');
    fetch('../ajax/set_active_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ session_id: id })
    }).then(r=>r.json()).then(d => {
        Spinner.hide();
        if(d.success) location.reload(); else Notif.show(d.message, 'error');
    });
}

function quickCreateTerms() {
    Spinner.show('Generating terms...');
    fetch('../ajax/save_academic_term.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'auto_generate' })
    }).then(r=>r.json()).then(d => {
        Spinner.hide();
        if(d.success) location.reload(); else Notif.show(d.message, 'error');
    });
}

function setActiveTerm(id) {
    Spinner.show('Switching term...');
    fetch('../ajax/set_active_term.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ term_id: id })
    }).then(r=>r.json()).then(d => {
        Spinner.hide();
        if(d.success) location.reload(); else Notif.show(d.message, 'error');
    });
}

function archiveSession(id) {
    if(!confirm("Are you sure you want to archive this session? All current activities will be locked.")) return;
    Spinner.show('Archiving...');
    fetch('../ajax/archive_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ session_id: id })
    }).then(r=>r.json()).then(d => {
        Spinner.hide();
        if(d.success) location.reload(); else Notif.show(d.message, 'error');
    });
}

function deleteSession(id, name) {
    openDeleteModal(id, 'session', name);
}

function deleteTerm(id, name) {
    openDeleteModal(id, 'term', name);
}


// ---- PROMOTION ----
function openPromotionModal() {
    new bootstrap.Modal(document.getElementById('promotionModal')).show();
}

function processPromotion() {
    const mappings = [];
    document.querySelectorAll('.next-class-select').forEach(s => {
        mappings.push({ class_id: s.dataset.id, next_class_id: s.value });
    });

    if(!confirm("CRITICAL ACTION: This will move all students to their new classes based on the mappings you set. Proceed?")) return;

    Spinner.show('Promoting students... this may take a moment.');
    fetch('../ajax/promote_students.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ mappings: mappings })
    }).then(r=>r.json()).then(d => {
        Spinner.hide();
        if(d.success) {
            Notif.show(d.message, 'success');
            setTimeout(()=>location.reload(), 2000);
        } else Notif.show(d.message, 'error');
    });
}




// Add first rows on load
addClassRow(); addSubjectRow();
</script>
</body>
</html>
