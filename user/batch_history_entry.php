<?php
// user/batch_history_entry.php - History Entry (Mirrors assessment_entry.php UX)
require_once '../includes/auth_check.php';

// Permission Guard
if (!isset($staff_permissions['can_edit_history']) || !$staff_permissions['can_edit_history']) {
    header('Location: dashboard.php');
    exit();
}

$school_id   = $_SESSION['school_id'];
$student_id  = intval($_GET['student_id'] ?? 0);
$class_id    = intval($_GET['class_id'] ?? 0);
$session_id  = intval($_GET['session_id'] ?? 0);
$term_id     = intval($_GET['term_id'] ?? 0);

if (!$student_id || !$class_id || !$session_id || !$term_id) {
    header('Location: history_master_entry.php');
    exit();
}

// Fetch Student
$stu_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
$stu_stmt->execute([$student_id, $school_id]);
$student = $stu_stmt->fetch();
if (!$student) {
    header('Location: history_master_entry.php');
    exit();
}

// Fetch Class Name
$cls_stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND school_id = ?");
$cls_stmt->execute([$class_id, $school_id]);
$class_name = $cls_stmt->fetchColumn() ?: 'Unknown Class';

// Fetch Session Name
$sn_stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ? AND school_id = ?");
$sn_stmt->execute([$session_id, $school_id]);
$session_name = $sn_stmt->fetchColumn() ?: 'Session';

// Fetch Term Name
$tn_stmt = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ? AND school_id = ?");
$tn_stmt->execute([$term_id, $school_id]);
$term_name = $tn_stmt->fetchColumn() ?: 'Term';

// Fetch all students in class for pagination
$all_stu_stmt = $pdo->prepare("
    SELECT s.id, s.full_name
    FROM students s
    JOIN student_classes sc ON sc.student_id = s.id
    WHERE sc.class_id = ? AND sc.school_id = ?
    ORDER BY s.full_name ASC
");
$all_stu_stmt->execute([$class_id, $school_id]);
$all_students = $all_stu_stmt->fetchAll();

$total_students = count($all_students);
$current_index  = 0;
$next_student_id = null;

foreach ($all_students as $index => $s) {
    if ($s['id'] == $student_id) {
        $current_index = $index + 1;
        if (isset($all_students[$index + 1])) {
            $next_student_id = $all_students[$index + 1]['id'];
        }
    }
}

// Tertiary / Higher Ed check
$school_type = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (
    strpos($school_type, 'tertiary')    !== false ||
    strpos($school_type, 'vocational')  !== false ||
    strpos($school_type, 'polytechnic') !== false ||
    strpos($school_type, 'university')  !== false ||
    strpos($school_type, 'college')     !== false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Edit: <?php echo htmlspecialchars($student['full_name']); ?> | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="<?php echo (string)($platform_favicon ?? ''); ?>" type="image/x-icon">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ======================== IDENTICAL TO assessment_entry.php ======================== */
        .audit-header { background:#fff; border-radius:24px; padding:25px; margin-bottom:25px; border:1px solid #eef2f6; box-shadow:0 10px 30px rgba(31,60,136,.03); }
        .excel-card   { background:#fff; border-radius:24px; border:1px solid #eef2f6; box-shadow:0 15px 40px rgba(31,60,136,.05); overflow:hidden; }
        .excel-table  { min-width:900px; margin-bottom:0; }
        .excel-table th { background:#f8fafc; color:#64748b; font-size:.65rem; text-transform:uppercase; letter-spacing:1px; padding:15px; border:none; font-weight:800; }
        .excel-table td { vertical-align:middle; border-top:1px solid #f1f5f9; padding:0; }
        .subject-cell { padding:15px 20px !important; font-weight:800; color:#1e293b; background:#fff; border-right:2px solid #f8fafc; font-size:.85rem; }
        .excel-input  { width:100%; border:none; padding:15px; text-align:center; font-weight:800; color:#1e293b; background:transparent; transition:.2s; font-size:.95rem; }
        .excel-input:focus { outline:none; background:#f0f7ff; box-shadow:inset 0 0 0 2px #3b82f6; z-index:5; position:relative; }
        .auto-calc { background:#fcfdfe; color:#64748b; font-weight:800; padding:15px; text-align:center; font-size:.85rem; }
        .total-node { color:#0f172a; font-weight:900; font-size:1rem; }

        /* Premium Grade Badges */
        .grade-badge   { width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; font-weight:900; font-size:.8rem; box-shadow:0 4px 10px rgba(0,0,0,.05); }
        .grade-A       { background:#ecfdf5; color:#059669; border:1px solid #10b98130; }
        .grade-B       { background:#eff6ff; color:#2563eb; border:1px solid #3b82f630; }
        .grade-C       { background:#fffbeb; color:#d97706; border:1px solid #f59e0b30; }
        .grade-D       { background:#fff7ed; color:#ea580c; border:1px solid #fb923c30; }
        .grade-E       { background:#fff7ed; color:#ea580c; border:1px solid #fb923c30; }
        .grade-F       { background:#fef2f2; color:#dc2626; border:1px solid #ef444430; }
        .grade-empty   { background:#f8fafc; color:#94a3b8; border:1px solid #e2e8f0; }
        .remark-node   { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; }
        .mobile-label  { display:none; }

        /* History badge */
        .history-badge { background: linear-gradient(135deg,#1e3a8a,#2563eb); color:#fff; border-radius:50px; padding:5px 14px; font-size:.65rem; font-weight:900; letter-spacing:1px; text-transform:uppercase; }

        /* Bottom Action Card */
        .se-action-card   { background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 4px 15px rgba(0,0,0,.02); display:flex; flex-direction:column; gap:17px; margin-top:5px; margin-bottom:20px; }
        .se-action-top    { display:flex; justify-content:space-between; align-items:center; }
        .se-action-bottom { display:flex; align-items:center; gap:12px; overflow-x:auto; padding-bottom:5px; border-top:1px solid #f1f5f9; padding-top:17px; }
        .se-action-bottom::-webkit-scrollbar { display:none; }
        .se-action-group  { display:flex; align-items:center; gap:10px; }

        .btn-se         { padding:9px 18px; border-radius:8px; font-weight:700; font-size:.75rem; display:flex; align-items:center; gap:8px; justify-content:center; border:1.5px solid #e2e8f0; background:#fff; color:#334155; transition:.2s; cursor:pointer; text-decoration:none; word-break:keep-all; white-space:nowrap; }
        .btn-se:hover   { background:#f8fafc; color:#0f172a; border-color:#cbd5e1; }
        .btn-se-primary { background:#2563eb; color:#fff; border-color:#2563eb; }
        .btn-se-primary:hover { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }
        .btn-se-success { background:#ecfdf5; color:#059669; border-color:#a7f3d0; }
        .btn-se-success:hover { background:#d1fae5; border-color:#6ee7b7; color:#047857; }

        .stu-pag-pill       { background:#fff; border:1.5px solid #e2e8f0; border-radius:50px; padding:7px 16px; font-size:.7rem; font-weight:700; color:#64748b; white-space:nowrap; cursor:pointer; transition:.2s; text-decoration:none; display:flex; align-items:center; gap:6px; }
        .stu-pag-pill:hover { background:#f8fafc; color:#0f172a; border-color:#cbd5e1; }
        .stu-pag-pill.active    { background:#0f172a; color:#fff !important; border-color:#0f172a; }
        .stu-pag-pill.completed { background:#e6fffa; color:#047857; border-color:#a7f3d0; }
        .stu-pag-pill .num { opacity:.7; }
        .stu-pagination { display:flex; align-items:center; gap:10px; overflow-x:auto; padding-bottom:10px; -webkit-overflow-scrolling:touch; }
        .stu-pagination::-webkit-scrollbar { height:4px; }
        .stu-pagination::-webkit-scrollbar-thumb { background:#e2e8f0; border-radius:10px; }

        @media (max-width:768px) {
            .sa-main-content { padding:10px !important; }
            .audit-header { padding:15px; border-radius:16px; margin-bottom:15px; }
            .excel-table, .excel-table thead, .excel-table tbody, .excel-table th, .excel-table td, .excel-table tr { display:block; }
            .excel-table { min-width:auto; border:none; }
            .excel-table thead { display:none; }
            .subject-row-node { background:#fff; border:1px solid #eef2f6; border-radius:20px; margin-bottom:20px; padding:18px; box-shadow:0 5px 20px rgba(0,0,0,.03); display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
            .subject-cell { grid-column:span 2; font-size:1.1rem !important; color:var(--primary-blue); border:none !important; padding:0 0 8px 0 !important; margin-bottom:5px; border-bottom:1.5px solid #f1f5f9 !important; }
            .excel-table td:not(.subject-cell) { border:none !important; background:#f8fafc; border-radius:12px; padding:10px !important; }
            .mobile-label { display:block; font-size:.55rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:.5px; margin-bottom:4px; }
            .excel-input { padding:0 !important; height:30px; font-size:1rem; text-align:left; }
            .auto-calc { padding:10px !important; text-align:left !important; }
            .total-cell  { grid-column:span 1; background:#eff6ff !important; }
            .total-node  { font-size:1.1rem !important; }
            .pos-cell    { grid-column:span 1; }
            .grade-cell  { grid-column:span 1; }
            .remark-cell { grid-column:span 1; }
            .se-action-card { padding:12px; gap:12px; }
            .se-action-top  { flex-direction:column; align-items:stretch; gap:12px; }
            .se-action-group { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
            .btn-se { min-height:50px; font-size:.7rem; }
            .btn-se-primary { grid-column:span 2; }
            .stu-pagination { padding:8px 0; border-top:1px solid #f1f5f9; margin-top:10px; }
            .stu-pagination::-webkit-scrollbar { display:none; }
            .stu-pag-pill { padding:10px 18px; font-size:.8rem; }
        }
        @media (max-width:360px) {
            .audit-header h4 { font-size:.85rem; }
            .btn-se { font-size:.52rem; padding:5px 1px; }
            .avatar-node { display:none; }
            .breadcrumb-node-text { display:none; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/notifications.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="container-fluid">

            <!-- Breadcrumb Navigation -->
            <div class="d-flex align-items-center gap-2 mb-3 mb-md-4 flex-wrap">
                <a href="history_master_entry.php?session_id=<?php echo $session_id; ?>&term_id=<?php echo $term_id; ?>" class="btn btn-white btn-sm rounded-pill shadow-sm fw-bold extra-small">
                    <i class="fas fa-arrow-left me-1 me-md-2"></i>Back to Audit Hub
                </a>
                <span class="text-muted small">/</span>
                <span class="extra-small fw-bold text-primary text-uppercase tracking-1 breadcrumb-node-text">History Edit Node</span>
                <span class="history-badge ms-2"><i class="fas fa-history me-1"></i>Historical Record</span>
            </div>

            <!-- Audit Header (identical structure to assessment_entry.php) -->
            <div class="audit-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <a href="history_master_entry.php?session_id=<?php echo $session_id; ?>&term_id=<?php echo $term_id; ?>" class="btn-se" style="width:45px;height:45px;padding:0;">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <div>
                        <h4 class="fw-900 mb-0">History Audit</h4>
                        <p class="extra-small text-muted mb-0 fw-700 uppercase tracking-1">
                            <?php echo htmlspecialchars($class_name); ?> &bull; <?php echo htmlspecialchars(get_label($term_name)); ?> — <?php echo htmlspecialchars($session_name); ?>
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-node" style="width:55px;height:55px;font-size:1.2rem;border-radius:15px;">
                        <?php if ($student['image_path']): ?>
                            <img src="../<?php echo $student['image_path']; ?>" style="width:100%;height:100%;object-fit:cover;border-radius:15px;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="fw-900 mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                        <div class="extra-small fw-800 text-muted uppercase tracking-2">
                            <?php echo get_label('Class'); ?>: <span class="text-primary"><?php echo htmlspecialchars($class_name); ?></span> &bull;
                            ID: <span class="text-dark"><?php echo htmlspecialchars($student['admission_no']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Excel Entry Grid (identical to assessment_entry.php) -->
            <div class="excel-card">
                <div class="table-responsive">
                    <table class="table excel-table" id="assessmentTable">
                        <thead>
                            <tr>
                                <th style="width:300px;"><?php echo get_label('Subject'); ?> Node</th>
                                <?php if ($is_higher_ed): ?>
                                    <th class="text-center">C.A (40)</th>
                                    <th class="text-center">Exam (60)</th>
                                <?php else: ?>
                                    <th class="text-center">C.A 1 (20)</th>
                                    <th class="text-center">C.A 2 (20)</th>
                                    <th class="text-center">Exams (60)</th>
                                <?php endif; ?>
                                <th class="text-center">Total</th>
                                <th class="text-center"><?php echo $is_higher_ed ? 'Point Gotten' : 'Pos'; ?></th>
                                <th class="text-center">Grade</th>
                                <th class="text-center">Remark</th>
                            </tr>
                        </thead>
                        <tbody id="assessmentBody">
                            <!-- Dynamic via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Card (identical to assessment_entry.php) -->
            <div class="se-action-card">
                <div class="se-action-top">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2 extra-small text-muted fw-700">
                            <i class="fas fa-shield-alt text-primary"></i>
                            History Edit Mode — Changes saved to historical records
                        </div>
                    </div>
                    <div class="se-action-group">
                        <button class="btn-se" onclick="saveOnly()">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <?php if ($next_student_id): ?>
                        <button class="btn-se btn-se-primary" onclick="saveAndNext()">
                            Save &amp; Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn-se btn-se-success" onclick="saveOnly()">
                            <i class="fas fa-check-circle"></i> Save &amp; Finish
                        </button>
                        <?php endif; ?>
                        <a href="history_master_entry.php?session_id=<?php echo $session_id; ?>&term_id=<?php echo $term_id; ?>" class="btn-se">
                            <i class="fas fa-times"></i> Exit
                        </a>
                    </div>
                </div>
                <div class="se-action-bottom">
                    <?php
                        $start = max(0, $current_index - 3);
                        $end   = min($total_students, $current_index + 2);
                        for ($i = $start; $i < $end; $i++) {
                            $s = $all_students[$i];
                            $is_active  = ($s['id'] == $student_id) ? 'active' : '';
                            $name_short = explode(' ', $s['full_name'])[0];
                            $check_icon     = ($i < ($current_index - 1)) ? '<i class="fas fa-check text-success"></i> ' : '<span class="num">'.($i+1).'.</span> ';
                            $completed_cls  = ($i < ($current_index - 1)) ? 'completed' : '';
                            echo '<a href="batch_history_entry.php?student_id='.$s['id'].'&class_id='.$class_id.'&session_id='.$session_id.'&term_id='.$term_id.'" class="stu-pag-pill '.$is_active.' '.$completed_cls.'">'.$check_icon.$name_short.'</a>';
                        }
                    ?>
                </div>
            </div>

        </div><!-- /container-fluid -->

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const STUDENT_ID    = <?php echo $student_id; ?>;
        const CLASS_ID      = <?php echo $class_id; ?>;
        const SESSION_ID    = <?php echo $session_id; ?>;
        const TERM_ID       = <?php echo $term_id; ?>;
        const NEXT_STUDENT_ID = <?php echo $next_student_id ?: 'null'; ?>;
        const IS_HIGHER_ED  = <?php echo $is_higher_ed ? 'true' : 'false'; ?>;

        let subjects = [];

        $(document).ready(function() { fetchSubjects(); });

        function fetchSubjects() {
            Spinner.show('Loading Subjects...');
            $.get('../ajax/get_class_subjects.php', { class_id: CLASS_ID, term_id: TERM_ID }, function(res) {
                if (res.success) {
                    subjects = res.subjects;
                    loadResults();
                } else {
                    Spinner.hide();
                    Notif.show('Failed to load subjects', 'error');
                }
            }, 'json');
        }

        function loadResults() {
            const body = $('#assessmentBody').empty();
            $.get('../ajax/get_student_results.php', {
                student_id: STUDENT_ID,
                class_id:   CLASS_ID,
                session_id: SESSION_ID,
                term_id:    TERM_ID
            }, function(res) {
                Spinner.hide();
                const results = res.results || {};
                const posLabel = IS_HIGHER_ED ? 'Point Gotten' : 'Position';

                subjects.forEach(sub => {
                    const r = results[sub.id] || { ca1: '', ca2: '', exam: '', total: 0, grade: '-', remark: '-', position: '-' };
                    let inputsHtml = '';

                    if (IS_HIGHER_ED) {
                        inputsHtml = `
                            <td class="ca1-cell"><label class="mobile-label">C.A (40)</label><input type="number" step="0.1" max="40" class="excel-input ca1-input" value="${r.ca1 || ''}" placeholder="0"></td>
                            <td class="d-none ca2-cell"><input type="number" class="ca2-input" value="0"></td>
                            <td class="exam-cell"><label class="mobile-label">Exam (60)</label><input type="number" step="0.1" max="60" class="excel-input exam-input" value="${r.exam || ''}" placeholder="0"></td>
                        `;
                    } else {
                        inputsHtml = `
                            <td class="ca1-cell"><label class="mobile-label">C.A 1 (20)</label><input type="number" step="0.1" max="20" class="excel-input ca1-input" value="${r.ca1 || ''}" placeholder="0"></td>
                            <td class="ca2-cell"><label class="mobile-label">C.A 2 (20)</label><input type="number" step="0.1" max="20" class="excel-input ca2-input" value="${r.ca2 || ''}" placeholder="0"></td>
                            <td class="exam-cell"><label class="mobile-label">Exam (60)</label><input type="number" step="0.1" max="60" class="excel-input exam-input" value="${r.exam || ''}" placeholder="0"></td>
                        `;
                    }

                    const initialGrade = r.grade && r.grade !== '-' ? r.grade : '-';
                    const badgeClass  = initialGrade !== '-' ? 'grade-' + initialGrade.charAt(0) : 'grade-empty';

                    body.append(`
                        <tr class="subject-row-node" data-subject-id="${sub.id}" data-credits="${sub.credit_units || 0}">
                            <td class="subject-cell">${sub.name}</td>
                            ${inputsHtml}
                            <td class="auto-calc total-cell"><label class="mobile-label">Total Score</label><span class="total-node">${r.total || '0'}</span></td>
                            <td class="auto-calc pos-cell"><label class="mobile-label">${posLabel}</label><span class="pos-node extra-small text-muted fw-800">${r.position || '-'}</span></td>
                            <td class="auto-calc grade-cell"><label class="mobile-label">Grade</label><div class="grade-badge ${badgeClass}">${initialGrade}</div></td>
                            <td class="auto-calc remark-cell"><label class="mobile-label">Remark</label><span class="remark-node opacity-75">${r.remark || '-'}</span></td>
                        </tr>
                    `);
                });

                bindCalcs();
                bindArrows();
            }, 'json');
        }

        // ── Arrow-key navigation (identical to assessment_entry.php) ──────────────
        function bindArrows() {
            $('.excel-input').on('keydown', function(e) {
                const td     = $(this).closest('td');
                const tr     = $(this).closest('tr');
                const colIdx = td.index();
                switch (e.which) {
                    case 37: td.prevAll('.ca1-cell,.ca2-cell,.exam-cell').first().find('input').focus(); break;
                    case 38: tr.prev().find('td:eq(' + colIdx + ') input').focus(); break;
                    case 39: td.nextAll('.ca1-cell,.ca2-cell,.exam-cell').first().find('input').focus(); break;
                    case 40: tr.next().find('td:eq(' + colIdx + ') input').focus(); break;
                }
            });
        }

        // ── Real-time calculation (identical to assessment_entry.php) ─────────────
        function bindCalcs() {
            $('.excel-input').on('keyup change', function() {
                const row     = $(this).closest('tr');
                let   ca1     = parseFloat(row.find('.ca1-input').val());
                let   ca2     = parseFloat(row.find('.ca2-input').val());
                let   exam    = parseFloat(row.find('.exam-input').val());
                const credits = parseFloat(row.data('credits')) || 0;

                if (IS_HIGHER_ED) {
                    if (ca1 > 40) { ca1 = 40; row.find('.ca1-input').val(40); }
                    ca2 = 0;
                } else {
                    if (ca1 > 20) { ca1 = 20; row.find('.ca1-input').val(20); }
                    if (ca2 > 20) { ca2 = 20; row.find('.ca2-input').val(20); }
                }
                if (exam > 60) { exam = 60; row.find('.exam-input').val(60); }

                const total = Math.min(100, (isNaN(ca1)?0:ca1) + (isNaN(ca2)?0:ca2) + (isNaN(exam)?0:exam));
                row.find('.total-node').text(total % 1 === 0 ? total : total.toFixed(1));

                const g     = calculateGrade(total);
                const badge = row.find('.grade-badge');
                badge.text(g.grade);
                badge.attr('class', 'grade-badge grade-' + (g.grade !== '-' ? g.grade.charAt(0) : 'empty'));
                row.find('.remark-node').text(g.remark);

                if (IS_HIGHER_ED) {
                    const pts = credits * g.gp;
                    row.find('.pos-node').text(pts % 1 === 0 ? pts : pts.toFixed(2));
                }
            });
        }

        // ── Grading scale (identical to assessment_entry.php) ────────────────────
        function calculateGrade(total) {
            if (IS_HIGHER_ED) {
                if (total >= 70) return { grade:'A', remark:'Distinction', gp:5 };
                if (total >= 60) return { grade:'B', remark:'Very Good',   gp:4 };
                if (total >= 50) return { grade:'C', remark:'Good',        gp:3 };
                if (total >= 45) return { grade:'D', remark:'Pass',        gp:2 };
                if (total >= 40) return { grade:'E', remark:'Pass',        gp:1 };
                return { grade:'F', remark:'Fail', gp:0 };
            } else {
                if (total >= 75) return { grade:'A1', remark:'Excellent', gp:0 };
                if (total >= 70) return { grade:'B2', remark:'Very Good',  gp:0 };
                if (total >= 65) return { grade:'B3', remark:'Good',       gp:0 };
                if (total >= 60) return { grade:'C4', remark:'Credit',     gp:0 };
                if (total >= 55) return { grade:'C5', remark:'Credit',     gp:0 };
                if (total >= 50) return { grade:'C6', remark:'Credit',     gp:0 };
                if (total >= 45) return { grade:'D7', remark:'Pass',       gp:0 };
                if (total >= 40) return { grade:'E8', remark:'Pass',       gp:0 };
                return { grade:'F9', remark:'Fail', gp:0 };
            }
        }

        // ── Collect all rows ──────────────────────────────────────────────────────
        function collectData() {
            const results = [];
            $('.subject-row-node').each(function() {
                const g = calculateGrade(parseFloat($(this).find('.total-node').text()) || 0);
                results.push({
                    subject_id: $(this).data('subject-id'),
                    ca1:     $(this).find('.ca1-input').val(),
                    ca2:     $(this).find('.ca2-input').val(),
                    exam:    $(this).find('.exam-input').val(),
                    total:   $(this).find('.total-node').text(),
                    grade:   $(this).find('.grade-badge').text(),
                    remark:  $(this).find('.remark-node').text(),
                    points:  (parseFloat($(this).data('credits')) || 0) * g.gp
                });
            });
            return results;
        }

        // ── Save helpers ──────────────────────────────────────────────────────────
        function doSave(callback) {
            const results = collectData();
            Spinner.show('Saving Historical Records...');
            fetch('../ajax/save_batch_results.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: STUDENT_ID,
                    class_id:   CLASS_ID,
                    session_id: SESSION_ID,
                    term_id:    TERM_ID,
                    results:    results
                })
            })
            .then(r => r.json())
            .then(data => {
                Spinner.hide();
                if (data.success) {
                    Notif.show('Historical records saved.', 'success');
                    if (callback) callback();
                } else {
                    Notif.show(data.message || 'Save failed.', 'error');
                }
            })
            .catch(err => {
                Spinner.hide();
                console.error(err);
                Notif.show('Network error. Please try again.', 'error');
            });
        }

        function saveOnly() {
            doSave(null);
        }

        function saveAndNext() {
            doSave(function() {
                if (NEXT_STUDENT_ID) {
                    window.location.href = `batch_history_entry.php?student_id=${NEXT_STUDENT_ID}&class_id=${CLASS_ID}&session_id=${SESSION_ID}&term_id=${TERM_ID}`;
                } else {
                    setTimeout(() => {
                        window.location.href = `history_master_entry.php?session_id=${SESSION_ID}&term_id=${TERM_ID}`;
                    }, 1500);
                }
            });
        }
    </script>
</body>
</html>
