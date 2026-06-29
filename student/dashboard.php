<?php
// student/dashboard.php
require_once 'auth.php';

// Determine if Higher Ed / Tertiary
$type = strtolower($student['school_type'] ?? '');
$is_higher_ed = (
    strpos($type, 'tertiary') !== false || 
    strpos($type, 'vocational') !== false || 
    strpos($type, 'polytechnic') !== false || 
    strpos($type, 'university') !== false || 
    strpos($type, 'college') !== false
);

// Fetch school-specific sessions for filtering
$sessions_stmt = $pdo->prepare("SELECT id, name FROM academic_sessions WHERE school_id = ? ORDER BY created_at DESC");
$sessions_stmt->execute([$school_id]);
$sessions = $sessions_stmt->fetchAll();

// Context Period Selection
$current_session_id = intval($_GET['session_id'] ?? $active_school['current_session_id'] ?? 0);

// Fetch terms for selected session
$terms_stmt = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id = ? AND school_id = ? ORDER BY created_at ASC");
$terms_stmt->execute([$current_session_id, $school_id]);
$terms = $terms_stmt->fetchAll();

$current_term_id = intval($_GET['term_id'] ?? $active_school['current_term_id'] ?? 0);
// If switching session via GET but no term specifically provided, default to first term of that session
if (isset($_GET['session_id']) && !isset($_GET['term_id'])) {
    $current_term_id = !empty($terms) ? $terms[0]['id'] : 0;
}

// Current term/session names for UI
$term_name = ''; $session_name = '';
if ($current_term_id) {
    $t = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
    $t->execute([$current_term_id]); $term_name = $t->fetchColumn() ?: '';
}
if ($current_session_id) {
    $s = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
    $s->execute([$current_session_id]); $session_name = $s->fetchColumn() ?: '';
}

// Fetch current class
$cls_stmt = $pdo->prepare("SELECT c.id, c.name FROM classes c JOIN student_classes sc ON sc.class_id = c.id WHERE sc.student_id = ? AND sc.school_id = ? LIMIT 1");
$cls_stmt->execute([$student_id, $school_id]);
$current_class = $cls_stmt->fetch();

// 1. Fetch ALL assigned courses for this class/level for the entire session
// We group them by semester_id to populate the 'Session Courses' tabs
$session_workload_stmt = $pdo->prepare("
    SELECT s.*, cs.id as mapping_id
    FROM class_subjects cs
    JOIN subjects s ON s.id = cs.subject_id
    WHERE cs.class_id = ?
");
$session_workload_stmt->execute([$current_class['id'] ?? 0]);
$all_assigned_courses = $session_workload_stmt->fetchAll();

$grouped_workload = [];
foreach ($all_assigned_courses as $ac) {
    if ($ac['semester_id']) {
        $grouped_workload[$ac['semester_id']][] = $ac;
    }
}

// Context/hero assigned courses (for the selected context)
$assigned_courses = $grouped_workload[$current_term_id] ?? [];

// 2. Session wide results grouped by term for tabs
$all_res_stmt = $pdo->prepare("
    SELECT r.*, s.name as subject_name, s.code as subject_code, s.credit_units
    FROM student_results r
    JOIN subjects s ON s.id = r.subject_id
    WHERE r.student_id = ? AND r.session_id = ?
    ORDER BY s.name ASC
");
$all_res_stmt->execute([$student_id, $current_session_id]);
$all_session_results = $all_res_stmt->fetchAll();

$grouped_results = [];
foreach ($all_session_results as $r) {
    $grouped_results[$r['term_id']][$r['subject_id']] = $r;
}

// 3. Contextual results (for selected semester/session) — used for some logic
$current_results = array_values($grouped_results[$current_term_id] ?? []);

// 4. Stats Calculation
// "Courses" count now reflects assigned workload for the selected semester
$total_subjects = count($assigned_courses); 

// GPA / Average Logic
$total_points = 0;
$total_credits = 0;
$total_score_sum = 0;
$results_with_scores = 0;

foreach ($current_results as $res) {
    $total_score_sum += $res['total'];
    $results_with_scores++;
    
    // GPA Logic (Tertiary only)
    if ($is_higher_ed && isset($res['credit_units']) && $res['credit_units'] > 0) {
        $grade_point = 0;
        $score = $res['total'];
        if($score >= 70) $grade_point = 5;
        elseif($score >= 60) $grade_point = 4;
        elseif($score >= 50) $grade_point = 3;
        elseif($score >= 45) $grade_point = 2;
        elseif($score >= 40) $grade_point = 1;
        
        $total_points += ($grade_point * $res['credit_units']);
        $total_credits += $res['credit_units'];
    }
}

$avg_score = $results_with_scores ? round($total_score_sum / $results_with_scores, 1) : 0;
$gpa = $total_credits > 0 ? round($total_points / $total_credits, 2) : 0.00;

// Best subject
$best_subject = null;
$worst_subject = null;
if (!empty($current_results)) {
    $sorted = $current_results;
    usort($sorted, fn($a, $b) => $b['total'] <=> $a['total']);
    $best_subject = $sorted[0];
    $worst_subject = end($sorted);
}

// Performance trend (last 6 terms)
$trend_stmt = $pdo->prepare("
    SELECT t.name as term_name, sess.name as session_name, AVG(r.total) as avg_score
    FROM student_results r
    JOIN academic_terms t ON t.id = r.term_id
    JOIN academic_sessions sess ON sess.id = r.session_id
    WHERE r.student_id = ?
    GROUP BY r.session_id, r.term_id
    ORDER BY sess.created_at DESC, t.created_at DESC
    LIMIT 6
");
$trend_stmt->execute([$student_id]);
$trend_data = array_reverse($trend_stmt->fetchAll());

$chart_labels = [];
$chart_scores = [];
foreach ($trend_data as $row) {
    $chart_labels[] = $row['term_name'];
    $chart_scores[] = round($row['avg_score'], 1);
}

// Subject radar (top 7)
$subjects_radar = [];
$scores_radar = [];
foreach (array_slice($current_results, 0, 7) as $res) {
    $subjects_radar[] = $res['subject_name'];
    $scores_radar[] = $res['total'];
}

// Position calculation
$position = '--';
if ($current_class && $current_session_id && $current_term_id) {
    // Get all students in the class
    $pos_stmt = $pdo->prepare("
        SELECT student_id, SUM(total) as total_score
        FROM student_results
        WHERE class_id = ? AND session_id = ? AND term_id = ?
        GROUP BY student_id
        ORDER BY total_score DESC
    ");
    $pos_stmt->execute([$current_class['id'], $current_session_id, $current_term_id]);
    $all_positions = $pos_stmt->fetchAll();
    $pos = 1;
    foreach ($all_positions as $p) {
        if ($p['student_id'] == $student_id) {
            $position = $pos;
            break;
        }
        $pos++;
    }
    if (is_numeric($position)) {
        $suf = match($position % 10) { 1 => ($position == 11 ? 'th' : 'st'), 2 => ($position == 12 ? 'th' : 'nd'), 3 => ($position == 13 ? 'th' : 'rd'), default => 'th' };
        $position = $position . $suf;
    }
}

// Fetch Active CBT Exams for Dashboard
$active_exams = [];
if ($current_class) {
    $active_stmt = $pdo->prepare("
        SELECT e.*, s.name as subject_name, s.code as subject_code
        FROM cbt_exams e
        JOIN subjects s ON s.id = e.subject_id
        WHERE e.class_id = ? AND e.school_id = ? AND e.status = 'active'
            AND e.end_time > NOW()
        ORDER BY e.start_time ASC
    ");
    $active_stmt->execute([$current_class['id'], $school_id]);
    $active_exams = $active_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo htmlspecialchars($student['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="includes/student.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
<div class="stu-layout">
    <?php include 'includes/nav.php'; ?>

    <main class="stu-main">
        <!-- Welcome Hero -->
        <div class="stu-hero">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-4 position-relative" style="z-index:2;">
                <div class="d-flex align-items-center gap-4">
                    <img src="<?php echo $student['image_path'] ? '../'.$student['image_path'] : '../img/default_picture.png'; ?>" 
                         class="d-none d-md-block"
                         style="width:85px; height:85px; border-radius:22px; border:4px solid rgba(255,255,255,0.15); object-fit:cover; box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                    <div class="flex-grow-1 w-100">
                        <div class="text-white opacity-75 small fw-bold uppercase tracking-2 mb-1">
                            <?php echo !empty($student['department_name']) ? get_label('Section') . ' of ' . htmlspecialchars($student['department_name']) : 'Academic Node Access'; ?>
                        </div>
                        <h4 class="fw-800 mb-1 text-white d-none d-md-flex align-items-center gap-2" style="font-size: clamp(1.1rem, 4vw, 1.4rem); white-space: nowrap; letter-spacing: -0.5px;">
                            Welcome, <?php echo htmlspecialchars(explode(' ', $student['full_name'])[0]); ?>! 👋
                        </h4>
                        <div class="d-flex gap-2 flex-wrap justify-content-between align-items-center w-100 mt-2">
                            <div class="d-flex gap-2">
                                <?php if ($current_class): ?>
                                <span class="badge bg-white bg-opacity-10 rounded-pill px-3 py-2" style="font-size:0.7rem; border: 1px solid rgba(255,255,255,0.1);">
                                    <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($current_class['name']); ?>
                                </span>
                                <?php endif; ?>
                                <span class="badge bg-white bg-opacity-20 rounded-pill px-3 py-2 text-warning" style="font-size:0.7rem; border: 1px solid rgba(255,255,255,0.2); font-weight: 800;">
                                    <?php if ($is_higher_ed): ?>
                                        GPA: <?php echo number_format($gpa, 2); ?>
                                    <?php else: ?>
                                        Average: <?php echo number_format($avg_score, 1); ?>%
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge bg-white bg-opacity-10 rounded-pill px-3 py-2" style="font-size:0.7rem; border: 1px solid rgba(255,255,255,0.1);">
                                    <i class="fas fa-fingerprint me-1"></i><?php echo htmlspecialchars($student['admission_no']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_class): ?>
                <div class="text-lg-end d-flex flex-column align-items-lg-end gap-3">
                    <div class="bg-white bg-opacity-10 rounded-4 px-4 py-3 border border-white border-opacity-10 shadow-sm" style="backdrop-filter: blur(15px); min-width: 280px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                             <div class="extra-small opacity-75 fw-800 uppercase tracking-2" style="font-size: 0.62rem; color: #fff;">Academic Node Context</div>
                             <div class="badge bg-warning text-dark rounded-pill fw-900 px-2 py-1" style="font-size: 0.55rem;">ACTIVE NODE</div>
                        </div>
                        <form method="GET" class="d-flex flex-column gap-2">
                            <select name="session_id" class="form-select form-select-sm bg-transparent border-0 text-white fw-800 p-0 shadow-none cursor-pointer" style="font-size: 0.85rem;" onchange="this.form.submit()">
                                <?php foreach($sessions as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $current_session_id == $s['id'] ? 'selected' : ''; ?> class="text-dark"><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-calendar-alt text-warning" style="font-size: 0.8rem;"></i>
                                <select name="term_id" class="form-select form-select-sm bg-transparent border-0 text-white fw-700 p-0 shadow-none cursor-pointer" style="font-size: 0.8rem;" onchange="this.form.submit()">
                                    <?php foreach($terms as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo $current_term_id == $t['id'] ? 'selected' : ''; ?> class="text-dark"><?php echo htmlspecialchars(get_label($t['name'])); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="view_report.php?session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>&class_id=<?php echo $current_class['id']; ?>" 
                           class="btn btn-warning btn-sm rounded-pill px-4 fw-800 shadow-lg" style="height: 42px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-file-invoice"></i> View Result
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <a href="transcript.php" class="stu-card text-decoration-none d-flex flex-column align-items-center text-center p-4 position-relative overflow-hidden" style="border-radius: 18px; background: linear-gradient(135deg, #1a2b4a 0%, #2d5faa 100%); color: #fff; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(26,43,74,0.2);">
                    <div class="position-absolute top-0 end-0 p-2 opacity-10"><i class="fas fa-scroll fa-4x"></i></div>
                    <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-scroll" style="font-size: 1.2rem;"></i>
                    </div>
                    <div class="fw-800 mb-1" style="font-size: 0.85rem;"><?php echo $is_higher_ed ? 'Transcript' : get_label('Broadsheet'); ?></div>
                    <div class="opacity-75" style="font-size: 0.68rem;">Download your full academic record</div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="view_report.php?session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>&class_id=<?php echo $current_class['id']; ?>" class="stu-card text-decoration-none d-flex flex-column align-items-center text-center p-4 position-relative overflow-hidden" style="border-radius: 18px; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #fff; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(5,150,105,0.2);">
                    <div class="position-absolute top-0 end-0 p-2 opacity-10"><i class="fas fa-file-invoice fa-4x"></i></div>
                    <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-file-invoice" style="font-size: 1.2rem;"></i>
                    </div>
                    <div class="fw-800 mb-1" style="font-size: 0.85rem;"><?php echo get_label('Report Card'); ?></div>
                    <div class="opacity-75" style="font-size: 0.68rem;">Current <?php echo strtolower(get_label('Term')); ?> results</div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="performance.php" class="stu-card text-decoration-none d-flex flex-column align-items-center text-center p-4 position-relative overflow-hidden" style="border-radius: 18px; background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); color: #fff; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(124,58,237,0.2);">
                    <div class="position-absolute top-0 end-0 p-2 opacity-10"><i class="fas fa-chart-line fa-4x"></i></div>
                    <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-chart-line" style="font-size: 1.2rem;"></i>
                    </div>
                    <div class="fw-800 mb-1" style="font-size: 0.85rem;">Performance</div>
                    <div class="opacity-75" style="font-size: 0.68rem;">Track your academic progress</div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="academic_audit.php" class="stu-card text-decoration-none d-flex flex-column align-items-center text-center p-4 position-relative overflow-hidden" style="border-radius: 18px; background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); color: #fff; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(234,88,12,0.2);">
                    <div class="position-absolute top-0 end-0 p-2 opacity-10"><i class="fas fa-history fa-4x"></i></div>
                    <div class="bg-white bg-opacity-20 rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-history" style="font-size: 1.2rem;"></i>
                    </div>
                    <div class="fw-800 mb-1" style="font-size: 0.85rem;"><?php echo get_label('Academic Audit'); ?></div>
                    <div class="opacity-75" style="font-size: 0.68rem;">Full academic timeline</div>
                </a>
            </div>
        </div>

        <!-- Active Assessments Section -->
        <?php if (!empty($active_exams)): ?>
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-head mb-0 text-primary">
                    <i class="fas fa-laptop-code pulse-icon me-2"></i> Active Assessments
                </div>
                <?php 
                    $test_count = 0; $exam_count = 0;
                    foreach($active_exams as $ae) {
                        if (stripos($ae['assessment_type'], 'test') !== false) $test_count++;
                        else $exam_count++;
                    }
                    $badge_label = 'Assessments';
                    if ($test_count > 0 && $exam_count == 0) $badge_label = $test_count > 1 ? 'Tests' : 'Test';
                    elseif ($exam_count > 0 && $test_count == 0) $badge_label = $exam_count > 1 ? 'Exams' : 'Exam';
                ?>
                <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-800" style="font-size:0.7rem;">
                    <?php echo count($active_exams); ?> Live <?php echo $badge_label; ?>
                </div>
            </div>
            <div class="row g-3">
                <?php foreach ($active_exams as $ex): 
                    $is_ongoing = strtotime($ex['start_time'] ?? '') <= time();
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="stu-card border-0 shadow-sm overflow-hidden position-relative" style="background: white; border-radius: 24px;">
                        <div class="p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge <?php echo $is_ongoing ? 'bg-success' : 'bg-warning'; ?> rounded-pill mb-2 px-3 py-1 fw-800 uppercase tracking-1" style="font-size:0.6rem;">
                                        <?php echo $is_ongoing ? '<i class="fas fa-circle-play me-1"></i> Ongoing' : '<i class="fas fa-clock me-1"></i> Upcoming'; ?>
                                    </span>
                                    <h6 class="fw-800 mb-1 text-dark text-truncate" style="max-width: 180px;"><?php echo htmlspecialchars($ex['title']); ?></h6>
                                    <div class="text-muted extra-small fw-600"><?php echo htmlspecialchars($ex['subject_name']); ?> (<?php echo htmlspecialchars($ex['subject_code']); ?>)</div>
                                </div>
                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 d-flex flex-column align-items-center justify-content-center" style="min-width: 50px;">
                                    <div class="fw-800 small"><?php echo $ex['duration_mins']; ?></div>
                                    <div class="extra-small opacity-75 fw-bold">MINS</div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-4">
                                <a href="cbt.php?token=<?php echo $ex['token']; ?>" class="btn btn-primary btn-sm flex-grow-1 rounded-pill fw-800 py-2 <?php echo !$is_ongoing ? 'disabled' : ''; ?>" style="font-size: 0.72rem;">
                                    <i class="fas fa-play me-1"></i> Start <?php echo (stripos($ex['assessment_type'], 'test') !== false) ? 'Test' : 'Exam'; ?>
                                </a>
                                <a href="exam_schedule.php?exam_id=<?php echo $ex['id']; ?>" target="_blank" class="btn btn-light btn-sm rounded-pill fw-800 px-3 py-2 border" style="font-size: 0.72rem;">
                                    <i class="fas fa-print"></i> Slip
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stu-stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-book-open"></i></div>
                    <div class="stat-value"><?php echo $total_subjects; ?></div>
                    <div class="stat-label"><?php echo get_label('Subjects'); ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stu-stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.1); color:#10b981;"><i class="fas fa-chart-line"></i></div>
                    <?php if ($is_higher_ed): ?>
                        <div class="stat-value" style="color:#10b981;"><?php echo number_format($gpa, 2); ?></div>
                        <div class="stat-label">Calculated GPA</div>
                    <?php else: ?>
                        <div class="stat-value" style="color:#10b981;"><?php echo number_format($avg_score, 1); ?>%</div>
                        <div class="stat-label">Average Score</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stu-stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.1); color:#f59e0b;"><i class="fas fa-trophy-alt"></i></div>
                    <div class="stat-value" style="color:#f59e0b;"><?php echo $position; ?></div>
                    <div class="stat-label"><?php echo get_label('Class'); ?> Standing</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stu-stat-card">
                    <div class="stat-icon" style="background:rgba(99,102,241,0.1); color:#6366f1;"><i class="fas fa-award"></i></div>
                    <div class="stat-value text-truncate w-100 px-2" style="color:#6366f1; font-size: 0.95rem !important; font-weight: 800; letter-spacing: -0.5px;">
                        <?php echo $best_subject ? htmlspecialchars($best_subject['subject_name']) : 'N/A'; ?>
                    </div>
                    <div class="stat-label">Top Performance</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="stu-card">
                    <div class="section-head"><i class="fas fa-chart-line"></i> Performance Trend</div>
                    <div class="chart-container" style="position: relative; height: 320px; width: 100%;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="stu-card">
                    <div class="section-head"><i class="fas fa-bullseye"></i> Skill Radar</div>
                    <div class="chart-container" style="position: relative; height: 320px; width: 100%;">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Courses -->
        <div class="stu-card">
            <div class="d-flex justify-content-between align-items-md-center flex-column flex-md-row gap-3 mb-4">
                <div class="section-head mb-0"><i class="fas fa-book-bookmark"></i> Session <?php echo get_label('Subjects'); ?></div>
                
                <ul class="nav nav-pills bg-light p-1 rounded-pill" id="scoreTabs" role="tablist" style="font-size: 0.75rem;">
                    <?php if (empty($terms)): ?>
                        <li class="nav-item"><span class="nav-link disabled rounded-pill">No <?php echo get_label('Terms'); ?> Found</span></li>
                    <?php else: ?>
                        <?php foreach($terms as $idx => $t): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link rounded-pill fw-800 <?php echo $current_term_id == $t['id'] ? 'active' : ''; ?>" 
                                    id="term-tab-<?php echo $t['id']; ?>" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#term-pane-<?php echo $t['id']; ?>" 
                                    type="button" role="tab" aria-selected="<?php echo $current_term_id == $t['id'] ? 'true' : 'false'; ?>">
                                    <?php echo htmlspecialchars(get_label($t['name'])); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                
                <a href="performance.php" class="btn btn-light btn-sm rounded-pill px-3 fw-700 text-decoration-none d-none d-md-inline-block" style="font-size:0.75rem;">View All Progress</a>
            </div>
 
            <div class="tab-content" id="scoreTabsContent">
                <?php if (empty($terms)): ?>
                    <div class="stu-empty py-4">
                        <div class="stu-empty-icon"><i class="fas fa-calendar-times"></i></div>
                        <h6 class="fw-bold text-muted">No academic <?php echo strtolower(get_label('Terms')); ?> structured</h6>
                    </div>
                <?php else: ?>
                    <?php foreach($terms as $t): 
                        $ac_list = $grouped_workload[$t['id']] ?? [];
                        $res_lookup = $grouped_results[$t['id']] ?? [];
                    ?>
                    <div class="tab-pane fade <?php echo $current_term_id == $t['id'] ? 'show active' : ''; ?>" 
                         id="term-pane-<?php echo $t['id']; ?>" role="tabpanel" tabindex="0">
                        
                        <?php if (empty($ac_list)): ?>
                            <div class="stu-empty py-4">
                                <div class="stu-empty-icon"><i class="fas fa-inbox"></i></div>
                                <h6 class="fw-bold text-muted">No <?php echo strtolower(get_label('Subjects')); ?> assigned for <?php echo htmlspecialchars(get_label($t['name'])); ?></h6>
                                <p class="text-muted small">Academic registry has not mapped <?php echo strtolower(get_label('Subjects')); ?> for this period.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="score-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-3"><?php echo get_label('Subject'); ?></th>
                                            <?php if ($is_higher_ed): ?>
                                                <th>C.A (40)</th><th>EXAM (60)</th>
                                            <?php else: ?>
                                                <th>C.A 1 (20)</th><th>C.A 2 (20)</th><th>EXAM (60)</th>
                                            <?php endif; ?>
                                            <th>Total</th><th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($ac_list as $ac):
                                        $res = $res_lookup[$ac['id']] ?? null;
                                        $gc = 'grade-c';
                                        if($res) {
                                            if ($is_higher_ed) {
                                                if($res['total'] >= 70) $gc = 'grade-a';
                                                elseif($res['total'] >= 60) $gc = 'grade-b';
                                                elseif($res['total'] < 40) $gc = 'grade-f';
                                            } else {
                                                if($res['total'] >= 75) $gc = 'grade-a';
                                                elseif($res['total'] >= 65) $gc = 'grade-b';
                                                elseif($res['total'] < 40) $gc = 'grade-f';
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td class="ps-3" data-label="<?php echo get_label('Subject'); ?>">
                                                <div class="subject-name"><?php echo htmlspecialchars($ac['name']); ?></div>
                                                <div class="text-muted" style="font-size:0.65rem;"><?php echo htmlspecialchars($ac['code']); ?><?php if ($is_higher_ed): ?> • <?php echo $ac['credit_units']; ?> Credits<?php endif; ?></div>
                                            </td>
                                            <?php if ($is_higher_ed): ?>
                                                <td class="fw-600 text-muted" data-label="C.A (40)"><?php echo $res ? $res['ca1'] : '-'; ?></td>
                                            <?php else: ?>
                                                <td class="fw-600 text-muted" data-label="C.A 1 (20)"><?php echo $res ? $res['ca1'] : '-'; ?></td>
                                                <td class="fw-600 text-muted" data-label="C.A 2 (20)"><?php echo $res ? $res['ca2'] : '-'; ?></td>
                                            <?php endif; ?>
                                            <td class="fw-600 text-muted" data-label="EXAM (60)"><?php echo $res ? $res['exam'] : '-'; ?></td>
                                            <td data-label="Total">
                                                <?php if($res): ?>
                                                    <span class="fw-900 text-primary"><?php echo $res['total']; ?>%</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted rounded-pill fw-800" style="font-size:0.6rem;">PENDING</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Grade">
                                                <?php if($res): ?>
                                                    <div class="grade-pill <?php echo $gc; ?>"><?php echo $res['grade']; ?></div>
                                                <?php else: ?>
                                                    <div class="grade-pill bg-light text-muted"><i class="fas fa-ellipsis-h"></i></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Performance Trend
new Chart(document.getElementById('trendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Average %',
            data: <?php echo json_encode($chart_scores); ?>,
            borderColor: '#1a4da1',
            backgroundColor: 'rgba(26,77,161,0.06)',
            borderWidth: 4,
            fill: true,
            tension: 0.4,
            pointRadius: 6,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#1a4da1',
            pointBorderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 2000,
            easing: 'easeOutQuart'
        },
        plugins: { 
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                padding: 12,
                titleFont: { size: 14, weight: 'bold' },
                bodyFont: { size: 13 },
                cornerRadius: 10,
                displayColors: false
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                max: 100, 
                grid: { color: 'rgba(0,0,0,0.03)', borderDash: [5, 5] },
                ticks: { font: { size: 11, weight: '600' }, color: '#64748b' }
            },
            x: { 
                grid: { display: false },
                ticks: { font: { size: 11, weight: '600' }, color: '#64748b' }
            }
        }
    }
});

// Skill Radar
new Chart(document.getElementById('radarChart').getContext('2d'), {
    type: 'radar',
    data: {
        labels: <?php echo json_encode($subjects_radar); ?>,
        datasets: [{
            label: 'Score',
            data: <?php echo json_encode($scores_radar); ?>,
            backgroundColor: 'rgba(26,77,161,0.08)',
            borderColor: '#1a4da1',
            borderWidth: 2,
            pointBackgroundColor: '#1a4da1',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#1a4da1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { 
            r: { 
                beginAtZero: true, 
                max: 100, 
                ticks: { display: false },
                grid: { color: 'rgba(0,0,0,0.05)' },
                angleLines: { color: 'rgba(0,0,0,0.05)' },
                pointLabels: { font: { size: 10, weight: '700' }, color: '#64748b' }
            } 
        }
    }
});
</script>
</body>
</html>
