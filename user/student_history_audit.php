<?php
// user/student_history_audit.php - Staff view of student academic history
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$student_id = intval($_GET['student_id'] ?? 0);
$school_id = $_SESSION['school_id'];

if (!$student_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch student details
$st_stmt = $pdo->prepare("
    SELECT s.full_name, s.admission_no, s.image_path, sch.school_type 
    FROM students s
    JOIN schools sch ON sch.id = s.school_id
    WHERE s.id = ? AND s.school_id = ?
");
$st_stmt->execute([$student_id, $school_id]);
$student = $st_stmt->fetch();

if (!$student) {
    header('Location: dashboard.php');
    exit();
}

// Determine if Higher Ed
$type = strtolower($student['school_type'] ?? '');
$is_higher_ed = (
    strpos($type, 'tertiary') !== false || 
    strpos($type, 'vocational') !== false || 
    strpos($type, 'polytechnic') !== false || 
    strpos($type, 'university') !== false || 
    strpos($type, 'college') !== false
);

// Check if current user can edit history
$can_edit_history = $staff_permissions['can_edit_history'] ?? false;

// Helper for GP calculation
function calculateGP($total, $is_higher_ed) {
    if (!$is_higher_ed) return 0;
    if ($total >= 70) return 5;
    if ($total >= 60) return 4;
    if ($total >= 50) return 3;
    if ($total >= 45) return 2;
    if ($total >= 40) return 1;
    return 0;
}

// Fetch all results across history
$stmt = $pdo->prepare("
    SELECT r.*, s.name as subject_name, s.code as subject_code, s.credit_units,
           sess.name as session_name, t.name as term_name, c.name as class_name
    FROM student_results r
    JOIN subjects s ON s.id = r.subject_id
    JOIN academic_sessions sess ON sess.id = r.session_id
    JOIN academic_terms t ON t.id = r.term_id
    JOIN classes c ON c.id = r.class_id
    WHERE r.student_id = ? AND r.school_id = ?
    ORDER BY r.session_id DESC, r.term_id DESC, s.name ASC
");
$stmt->execute([$student_id, $school_id]);
$all_results = $stmt->fetchAll();

// Grouping logic (Same as student side)
$history = [];
foreach ($all_results as $r) {
    $session_key = $r['session_id'];
    $term_key = $r['term_id'];
    if (!isset($history[$session_key])) $history[$session_key] = ['name' => $r['session_name'], 'terms' => []];
    if (!isset($history[$session_key]['terms'][$term_key])) $history[$session_key]['terms'][$term_key] = ['name' => $r['term_name'], 'class_id' => $r['class_id'], 'class_name' => $r['class_name'], 'results' => []];
    $history[$session_key]['terms'][$term_key]['results'][] = $r;
}

// GPA/CGPA Logic
$cumulative_credits = 0;
$cumulative_points = 0;
$chronological_history = array_reverse($history, true);
foreach ($chronological_history as $sess_id => &$sess) {
    ksort($sess['terms']);
    foreach ($sess['terms'] as $term_id => &$term) {
        $term_credits = 0; $term_points = 0;
        foreach ($term['results'] as $res) {
            $gp = calculateGP($res['total'], $is_higher_ed);
            $term_credits += ($res['credit_units'] ?: 0);
            $term_points += ($gp * ($res['credit_units'] ?: 0));
        }
        $term['gpa'] = $term_credits > 0 ? round($term_points / $term_credits, 2) : 0;
        $cumulative_credits += $term_credits;
        $cumulative_points += $term_points;
        $term['cgpa'] = $cumulative_credits > 0 ? round($cumulative_points / $cumulative_credits, 2) : 0;
    }
}
$display_history = array_reverse($chronological_history, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historical Audit | <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .audit-card { background: #fff; border-radius: 20px; border: 1px solid #eef2f6; overflow: hidden; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .audit-header { background: #f8fafc; padding: 20px 25px; border-bottom: 1px solid #eef2f6; }
        .audit-stats { background: #fff; padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 30px; }
        .course-table { font-size: 0.85rem; margin-bottom: 0; }
        .course-table th { background: #fff; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.7rem; border-top: none; }
        .grade-node { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 900; }
        .grade-A { background: #ecfdf5; color: #10b981; }
        .grade-B { background: #eff6ff; color: #3b82f6; }
        .grade-C { background: #fefce8; color: #ca8a04; }
        .grade-D, .grade-E { background: #fff7ed; color: #f97316; }
        .grade-F { background: #fef2f2; color: #ef4444; }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="javascript:history.back()" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                    <div>
                        <h4 class="fw-900 mb-0">Academic Result History</h4>
                        <p class="text-muted small mb-0">Auditing results for <strong><?php echo htmlspecialchars($student['full_name']); ?></strong> (<?php echo $student['admission_no']; ?>)</p>
                    </div>
                </div>
                <?php if ($can_edit_history): ?>
                    <div class="badge bg-soft-info text-info p-2 px-3 rounded-pill fw-800" style="font-size: 0.7rem;">
                        <i class="fas fa-unlock me-2"></i> MASTER AUDIT ACCESS ENABLED
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($display_history)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                    <i class="fas fa-folder-open text-muted mb-3 fa-3x"></i>
                    <h5 class="fw-bold">No Records Found</h5>
                    <p class="text-muted">This student does not have any recorded results yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($display_history as $sess_id => $sess): ?>
                    <div class="mb-5">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <i class="fas fa-calendar-alt text-primary"></i>
                            <h5 class="fw-900 mb-0"><?php echo htmlspecialchars($sess['name']); ?></h5>
                            <div class="flex-grow-1 border-bottom"></div>
                        </div>

                        <?php foreach (array_reverse($sess['terms'], true) as $term_id => $term): ?>
                            <div class="audit-card">
                                <div class="audit-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-900 mb-0"><?php echo get_label($term['name']); ?></h6>
                                        <div class="small fw-700 text-muted"><?php echo get_label('Class'); ?>: <?php echo htmlspecialchars($term['class_name']); ?></div>
                                    </div>
                                    <?php if ($can_edit_history): ?>
                                        <a href="assessment_entry.php?student_id=<?php echo $student_id; ?>&class_id=<?php echo $term['class_id']; ?>&session_id=<?php echo $sess_id; ?>&term_id=<?php echo $term_id; ?>" class="btn btn-primary btn-sm rounded-pill fw-bold px-3">
                                            <i class="fas fa-edit me-1"></i> Update Results
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="audit-stats">
                                    <?php if ($is_higher_ed): ?>
                                        <div class="stat-item text-center">
                                            <div class="extra-small fw-800 text-muted uppercase tracking-1">GPA</div>
                                            <div class="fw-900 text-primary"><?php echo number_format($term['gpa'], 2); ?></div>
                                        </div>
                                        <div class="stat-item text-center">
                                            <div class="extra-small fw-800 text-muted uppercase tracking-1">CGPA</div>
                                            <div class="fw-900 text-dark"><?php echo number_format($term['cgpa'], 2); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="stat-item text-center">
                                            <div class="extra-small fw-800 text-muted uppercase tracking-1">Average Score</div>
                                            <?php 
                                                $scores = array_column($term['results'], 'total');
                                                $avg = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
                                            ?>
                                            <div class="fw-900 text-primary"><?php echo round($avg, 1); ?>%</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="table-responsive">
                                    <table class="table course-table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php echo get_label('Subject'); ?></th>
                                                <th class="text-center"><?php echo $is_higher_ed ? 'Units' : 'C.A'; ?></th>
                                                <th class="text-center"><?php echo $is_higher_ed ? 'Grade' : 'Exam'; ?></th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($term['results'] as $res): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-800 text-dark"><?php echo htmlspecialchars($res['subject_name']); ?></div>
                                                        <div class="extra-small text-muted fw-bold"><?php echo htmlspecialchars($res['subject_code']); ?></div>
                                                    </td>
                                                    <td class="text-center fw-700">
                                                        <?php echo $is_higher_ed ? $res['credit_units'] : ($res['ca1'] + $res['ca2']); ?>
                                                    </td>
                                                    <td class="text-center d-flex justify-content-center">
                                                        <?php if ($is_higher_ed): ?>
                                                            <div class="grade-node grade-<?php echo substr($res['grade'], 0, 1); ?>">
                                                                <?php echo $res['grade']; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="fw-700"><?php echo $res['exam']; ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center fw-900 text-dark"><?php echo $res['total']; ?>%</td>
                                                    <td class="text-center small fw-700 opacity-75"><?php echo $res['remark']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php include '../includes/dashboard_footer.php'; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
