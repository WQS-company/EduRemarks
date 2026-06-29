<?php
// user/history_master_entry.php - History Audit Hub (Student-centric view)
require_once '../includes/auth_check.php';

// Permission Guard
if (empty($staff_permissions['can_edit_history'])) {
    header('Location: dashboard.php');
    exit();
}

$school_id    = $_SESSION['school_id'];
$school_type  = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (
    str_contains($school_type, 'tertiary')    ||
    str_contains($school_type, 'vocational')  ||
    str_contains($school_type, 'polytechnic') ||
    str_contains($school_type, 'university')  ||
    str_contains($school_type, 'college')
);

$term_label    = get_label('Term');
$class_label   = get_label('Class');
$subject_label = get_label('Subject');

// Context Filters
$session_id = intval($_GET['session_id'] ?? $active_school['current_session_id'] ?? 0);
$term_id    = intval($_GET['term_id']    ?? $active_school['current_term_id']    ?? 0);

// Fetch All Sessions
$sessions_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = ? ORDER BY id DESC");
$sessions_stmt->execute([$school_id]);
$all_sessions = $sessions_stmt->fetchAll();

// Fetch Terms for selected session
$terms = [];
if ($session_id) {
    $t_stmt = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id = ? AND school_id = ? ORDER BY id ASC");
    $t_stmt->execute([$session_id, $school_id]);
    $terms = $t_stmt->fetchAll();
}

// Fetch Classes with student counts and graded counts
$classes_data = [];
if ($session_id && $term_id) {
    $cls_stmt = $pdo->prepare("
        SELECT 
            c.id   AS class_id,
            c.name AS class_name,
            (SELECT COUNT(*) FROM student_classes sc2 WHERE sc2.class_id = c.id AND sc2.school_id = c.school_id) AS total_students,
            (SELECT COUNT(DISTINCT sr.student_id) FROM student_results sr 
             WHERE sr.session_id = :sid AND sr.term_id = :tid AND sr.school_id = c.school_id
               AND sr.student_id IN (SELECT sc3.student_id FROM student_classes sc3 WHERE sc3.class_id = c.id)
               AND (sr.ca1 IS NOT NULL OR sr.ca2 IS NOT NULL OR sr.exam IS NOT NULL)) AS graded_students
        FROM classes c
        WHERE c.school_id = :school
        ORDER BY c.name ASC
    ");
    $cls_stmt->execute([':sid' => $session_id, ':tid' => $term_id, ':school' => $school_id]);
    $classes_data = $cls_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Audit Hub | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)($platform_favicon ?? ''); ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .hub-header    { background:#fff; border-radius:24px; padding:25px; margin-bottom:25px; border:1px solid #eef2f6; box-shadow:0 10px 30px rgba(31,60,136,.03); }
        .class-section { background:#fff; border-radius:20px; border:1px solid #eef2f6; box-shadow:0 8px 25px rgba(31,60,136,.04); margin-bottom:25px; overflow:hidden; }
        .class-header  { padding:20px 25px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; cursor:pointer; background:#fafbfd; }
        .class-header:hover { background:#f0f7ff; }
        .class-body    { padding:20px 25px; }
        .student-table { margin-bottom:0; }
        .student-table th { background:#f8fafc; color:#64748b; font-size:.62rem; text-transform:uppercase; letter-spacing:1px; padding:12px 15px; border:none; font-weight:800; }
        .student-table td { vertical-align:middle; border-top:1px solid #f1f5f9; padding:12px 15px; }
        .student-row:hover { background:#f8fafc; }
        .stu-img  { width:36px; height:36px; border-radius:10px; object-fit:cover; background:#e2e8f0; }
        .progress-slim { height:5px; border-radius:10px; }
        .filter-chip       { padding:8px 16px; border-radius:50px; border:1px solid #e2e8f0; background:#fff; font-weight:600; font-size:.85rem; color:#64748b; transition:.2s; cursor:pointer; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
        .filter-chip.active { background:#2563eb; color:#fff; border-color:#2563eb; }
        .filter-chip:hover:not(.active) { background:#f8fafc; border-color:#cbd5e1; }
        .history-badge { background:linear-gradient(135deg,#1e3a8a,#2563eb); color:#fff; border-radius:50px; padding:4px 12px; font-size:.62rem; font-weight:900; letter-spacing:1px; text-transform:uppercase; }
        .edit-btn { padding:7px 18px; border-radius:8px; font-weight:700; font-size:.72rem; border:1.5px solid #2563eb; background:#eff6ff; color:#2563eb; text-decoration:none; transition:.2s; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; }
        .edit-btn:hover { background:#2563eb; color:#fff; }
        .collapse-icon { transition:transform .3s; }
        .collapsed .collapse-icon { transform:rotate(-90deg); }
        @media(max-width:576px) {
            .class-body { padding:12px; }
            .student-table th:nth-child(3), .student-table td:nth-child(3) { display:none; }
        }
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
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
<?php endif; ?>

    <div class="p-3 p-md-4">

        <!-- Hub Header -->
        <div class="hub-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="history-badge"><i class="fas fa-history me-1"></i>History Audit Hub</span>
                    </div>
                    <h3 class="fw-900 mb-1">Historical Result Editor</h3>
                    <p class="text-muted small mb-0">Select a student to edit all their previous results — just like normal result entry.</p>
                </div>
                <a href="report_management.php" class="btn btn-light rounded-pill px-4 border">
                    <i class="fas fa-arrow-left me-2"></i>Back to Reports
                </a>
            </div>

            <!-- Filters -->
            <form action="" method="GET" class="row g-3 mt-3 pt-3 border-top">
                <div class="col-md-4">
                    <label class="form-label extra-small fw-800 text-uppercase text-muted">Academic Session</label>
                    <select name="session_id" class="form-select rounded-3 shadow-none border" onchange="this.form.submit()">
                        <option value="">— Select Session —</option>
                        <?php foreach ($all_sessions as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $session_id == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label extra-small fw-800 text-uppercase text-muted"><?php echo $term_label; ?></label>
                    <select name="term_id" class="form-select rounded-3 shadow-none border" onchange="this.form.submit()">
                        <option value="">— Select <?php echo $term_label; ?> —</option>
                        <?php foreach ($terms as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $term_id == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(get_label($t['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <?php if ($session_id && $term_id): ?>
                    <div class="small text-muted fw-700">
                        <i class="fas fa-info-circle me-1 text-primary"></i>
                        Click a class below to expand and select a student.
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Content -->
        <?php if (!$session_id || !$term_id): ?>
            <div class="text-center py-5 glass-card">
                <div class="mb-3 text-muted opacity-25"><i class="fas fa-search-location fa-4x"></i></div>
                <h5 class="fw-bold text-dark">Awaiting Context Selection</h5>
                <p class="text-muted small">Please choose a session and <?php echo strtolower($term_label); ?> above to begin.</p>
            </div>

        <?php elseif (empty($classes_data)): ?>
            <div class="text-center py-5 glass-card">
                <div class="mb-3 text-muted opacity-25"><i class="fas fa-folder-open fa-4x"></i></div>
                <h5 class="fw-bold text-dark">No Classes Found</h5>
                <p class="text-muted small">No classes are set up for this school.</p>
            </div>

        <?php else: ?>
            <?php foreach ($classes_data as $ci => $cls): ?>
            <?php
                // Fetch students in this class with their grading status for this session/term
                $stu_stmt2 = $pdo->prepare("
                    SELECT s.id, s.full_name, s.admission_no, s.image_path,
                           (SELECT COUNT(DISTINCT sr2.subject_id) FROM student_results sr2
                            WHERE sr2.student_id = s.id AND sr2.session_id = ? AND sr2.term_id = ? AND sr2.school_id = ?
                              AND (sr2.ca1 IS NOT NULL OR sr2.exam IS NOT NULL)) AS graded_count,
                           (SELECT COUNT(*) FROM class_subjects csj WHERE csj.class_id = ?) AS subject_count
                    FROM students s
                    JOIN student_classes sc ON sc.student_id = s.id AND sc.class_id = ? AND sc.school_id = ?
                    WHERE s.school_id = ?
                    ORDER BY s.full_name ASC
                ");
                $stu_stmt2->execute([$session_id, $term_id, $school_id, $cls['class_id'], $cls['class_id'], $school_id, $school_id]);
                $class_students = $stu_stmt2->fetchAll();
                $pct = $cls['total_students'] > 0 ? round($cls['graded_students'] / $cls['total_students'] * 100) : 0;
                $isOpen = ($ci === 0); // first class open by default
            ?>
            <div class="class-section">
                <div class="class-header" data-bs-toggle="collapse" data-bs-target="#classBody<?php echo $cls['class_id']; ?>" aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3" style="width:42px;height:42px;font-size:1rem;">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <div class="fw-900 text-dark"><?php echo htmlspecialchars($cls['class_name']); ?></div>
                            <div class="extra-small text-muted fw-700">
                                <?php echo $cls['total_students']; ?> Students &bull;
                                <?php echo $cls['graded_students']; ?> / <?php echo $cls['total_students']; ?> graded
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:100px;">
                            <div class="d-flex justify-content-between extra-small fw-800 text-muted mb-1">
                                <span>Progress</span><span><?php echo $pct; ?>%</span>
                            </div>
                            <div class="progress progress-slim">
                                <div class="progress-bar <?php echo $pct == 100 ? 'bg-success' : 'bg-primary'; ?>" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down collapse-icon text-muted <?php echo $isOpen ? '' : 'collapsed'; ?>"></i>
                    </div>
                </div>

                <div class="collapse <?php echo $isOpen ? 'show' : ''; ?>" id="classBody<?php echo $cls['class_id']; ?>">
                    <div class="class-body">
                        <?php if (empty($class_students)): ?>
                            <p class="text-muted small text-center py-3 mb-0">No students enrolled in this class.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table student-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>Student</th>
                                        <th><?php echo $subject_label; ?>s Graded</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_students as $si => $stu): ?>
                                    <?php
                                        $sub_total = intval($stu['subject_count']);
                                        $sub_graded = intval($stu['graded_count']);
                                        $stu_pct    = $sub_total > 0 ? round($sub_graded / $sub_total * 100) : 0;
                                    ?>
                                    <tr class="student-row">
                                        <td class="text-muted fw-800 extra-small"><?php echo $si + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($stu['image_path']): ?>
                                                    <img src="../<?php echo htmlspecialchars($stu['image_path']); ?>" class="stu-img">
                                                <?php else: ?>
                                                    <div class="stu-img d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary fw-900 rounded-3">
                                                        <?php echo strtoupper(substr($stu['full_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-800 text-dark"><?php echo htmlspecialchars($stu['full_name']); ?></div>
                                                    <div class="extra-small text-muted fw-700"><?php echo htmlspecialchars($stu['admission_no']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress progress-slim flex-grow-1" style="width:80px;">
                                                    <div class="progress-bar <?php echo $stu_pct == 100 ? 'bg-success' : 'bg-primary'; ?>" style="width:<?php echo $stu_pct; ?>%"></div>
                                                </div>
                                                <span class="extra-small fw-800 text-muted"><?php echo $sub_graded; ?>/<?php echo $sub_total; ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="batch_history_entry.php?student_id=<?php echo $stu['id']; ?>&class_id=<?php echo $cls['class_id']; ?>&session_id=<?php echo $session_id; ?>&term_id=<?php echo $term_id; ?>" class="edit-btn">
                                                <i class="fas fa-edit"></i> Edit Results
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- /p-3 p-md-4 -->

    <?php include '../includes/dashboard_footer.php'; ?>
</main>
<?php if ($role !== 'staff'): ?>
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle chevron icon on collapse
    document.querySelectorAll('.class-header').forEach(function(header) {
        header.addEventListener('click', function() {
            const icon = this.querySelector('.collapse-icon');
            icon.classList.toggle('collapsed');
        });
    });
</script>
</body>
</html>
