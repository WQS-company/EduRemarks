<?php
// user/generate_transcript.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}
if (!$active_school) { header('Location: dashboard.php'); exit(); }

$school_id = $active_school['id'];
$school_type = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (
    strpos($school_type, 'tertiary') !== false || 
    strpos($school_type, 'vocational') !== false || 
    strpos($school_type, 'polytechnic') !== false || 
    strpos($school_type, 'university') !== false || 
    strpos($school_type, 'college') !== false
);

// Fetch all students in this school
$stu_stmt = $pdo->prepare("SELECT id, full_name, admission_no, gender, image_path FROM students WHERE school_id = ? ORDER BY full_name ASC");
$stu_stmt->execute([$school_id]);
$all_students = $stu_stmt->fetchAll();

// Fetch all sessions for this school
$sess_stmt = $pdo->prepare("SELECT id, name FROM academic_sessions WHERE school_id = ? ORDER BY created_at ASC");
$sess_stmt->execute([$school_id]);
$all_sessions = $sess_stmt->fetchAll();

// Fetch all classes for this school
$cls_stmt = $pdo->prepare("SELECT id, name, code FROM classes WHERE school_id = ? ORDER BY name ASC");
$cls_stmt->execute([$school_id]);
$all_classes = $cls_stmt->fetchAll();

// Selected student, class, and session range
$sel_student = intval($_GET['student_id'] ?? 0);
$sel_class = intval($_GET['class_id'] ?? 0);
$from_session_id = intval($_GET['from_session'] ?? 0);
$to_session_id = intval($_GET['to_session'] ?? 0);
$mode = $_GET['mode'] ?? 'single'; // 'single' or 'batch'

// Student data
$student_data = null;
if ($sel_student) {
    $stu_q = $pdo->prepare("
        SELECT s.*, sch.school_name, sch.logo_path, sch.motto, sch.school_address, sch.school_type
        FROM students s 
        JOIN schools sch ON sch.id = s.school_id 
        WHERE s.id = ? AND s.school_id = ?
    ");
    $stu_q->execute([$sel_student, $school_id]);
    $student_data = $stu_q->fetch();
}

// Batch mode: fetch all students in the selected class with their result counts
$batch_students = [];
if ($mode === 'batch' && $sel_class && $from_session_id && $to_session_id) {
    $batch_stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.admission_no, s.gender, s.image_path,
               COUNT(sr.id) as result_count,
               ROUND(AVG(sr.total), 1) as avg_score
        FROM students s
        JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = ?
        LEFT JOIN student_results sr ON sr.student_id = s.id AND sr.session_id >= ? AND sr.session_id <= ? AND sr.school_id = ?
        WHERE sc.class_id = ? AND s.school_id = ?
        GROUP BY s.id
        ORDER BY s.full_name ASC
    ");
    $batch_stmt->execute([$school_id, $from_session_id, $to_session_id, $school_id, $sel_class, $school_id]);
    $batch_students = $batch_stmt->fetchAll();
}

// Default session range
if (empty($all_sessions)) {
    $from_session_id = 0;
    $to_session_id = 0;
} else {
    if (!$from_session_id) $from_session_id = $all_sessions[0]['id'];
    if (!$to_session_id) $to_session_id = end($all_sessions)['id'];
}

$from_session_name = '';
$to_session_name = '';
if ($from_session_id) {
    $stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
    $stmt->execute([$from_session_id]);
    $from_session_name = $stmt->fetchColumn() ?: '';
}
if ($to_session_id) {
    $stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
    $stmt->execute([$to_session_id]);
    $to_session_name = $stmt->fetchColumn() ?: '';
}

// Fetch results
$results = [];
$terms_summary = [];
if ($sel_student && $from_session_id && $to_session_id) {
    $res_stmt = $pdo->prepare("
        SELECT r.*, s.name as subject_name, s.code as subject_code, s.credit_units,
               sess.name as session_name, t.name as term_name,
               c.name as class_name
        FROM student_results r
        JOIN subjects s ON s.id = r.subject_id
        JOIN academic_sessions sess ON sess.id = r.session_id
        JOIN academic_terms t ON t.id = r.term_id
        LEFT JOIN classes c ON c.id = r.class_id
        WHERE r.student_id = ? AND r.school_id = ? AND r.session_id >= ? AND r.session_id <= ?
        ORDER BY sess.created_at ASC, t.created_at ASC, s.name ASC
    ");
    $res_stmt->execute([$sel_student, $school_id, $from_session_id, $to_session_id]);
    $results = $res_stmt->fetchAll();

    foreach ($results as $r) {
        $key = $r['session_id'] . '_' . $r['term_id'];
        if (!isset($terms_summary[$key])) {
            $terms_summary[$key] = [
                'session_id' => $r['session_id'],
                'session_name' => $r['session_name'],
                'term_id' => $r['term_id'],
                'term_name' => $r['term_name'],
                'class_name' => $r['class_name'],
                'subjects' => [],
                'total_score' => 0,
                'count' => 0,
                'total_credits' => 0,
                'total_points' => 0,
            ];
        }
        $terms_summary[$key]['subjects'][] = $r;
        $terms_summary[$key]['total_score'] += $r['total'];
        $terms_summary[$key]['count']++;
        if ($is_higher_ed && $r['credit_units'] > 0) {
            $grade_point = 0;
            $score = $r['total'];
            if ($score >= 70) $grade_point = 5;
            elseif ($score >= 60) $grade_point = 4;
            elseif ($score >= 50) $grade_point = 3;
            elseif ($score >= 45) $grade_point = 2;
            elseif ($score >= 40) $grade_point = 1;
            $terms_summary[$key]['total_points'] += ($grade_point * $r['credit_units']);
            $terms_summary[$key]['total_credits'] += $r['credit_units'];
        }
    }
}

// Calculate cumulative stats
$total_score_sum = 0;
$total_count = 0;
$cumulative_points = 0;
$cumulative_credits = 0;
$all_subjects = [];

foreach ($terms_summary as $ts) {
    $total_score_sum += $ts['total_score'];
    $total_count += $ts['count'];
    $cumulative_points += $ts['total_points'];
    $cumulative_credits += $ts['total_credits'];
    foreach ($ts['subjects'] as $sub) {
        $all_subjects[$sub['subject_id']] = $sub['subject_name'];
    }
}

$overall_avg = $total_count > 0 ? round($total_score_sum / $total_count, 1) : 0;
$cgpa = $cumulative_credits > 0 ? round($cumulative_points / $cumulative_credits, 2) : 0;

function staffCalcGrade($total, $is_higher_ed = false) {
    if ($is_higher_ed) {
        if ($total >= 70) return 'A'; if ($total >= 60) return 'B'; if ($total >= 50) return 'C';
        if ($total >= 45) return 'D'; if ($total >= 40) return 'E'; return $total > 0 ? 'F' : '-';
    } else {
        if ($total >= 75) return 'A1'; if ($total >= 70) return 'B2'; if ($total >= 65) return 'B3';
        if ($total >= 60) return 'C4'; if ($total >= 55) return 'C5'; if ($total >= 50) return 'C6';
        if ($total >= 45) return 'D7'; if ($total >= 40) return 'E8'; return $total > 0 ? 'F9' : '-';
    }
}
function staffCalcRemark($grade) {
    $g = substr($grade, 0, 1);
    if ($g === 'A') return 'Excellent'; if ($g === 'B') return 'Very Good';
    if ($g === 'C') return 'Good'; if ($g === 'D') return 'Fair';
    if ($g === 'E' || $grade === 'E8') return 'Pass'; if ($g === 'F') return 'Fail';
    return '-';
}
function ste($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

$logoSrc = (!empty($active_school['logo_path'])) ? '../' . ltrim($active_school['logo_path'], '/') : '';
$wmText = strtoupper($active_school['school_name']);
$has_transcript = !empty($terms_summary) && $student_data;

$pageTitle = $is_higher_ed ? 'Transcript Generation' : get_label('Broadsheet') . ' Generation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | <?php echo htmlspecialchars($active_school['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url ?? ''; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .transcript-preview { display: none; }
        .transcript-preview.active { display: block; }
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; }
        }
        .print-only { display: none; }

        /* Transcript print styles */
        .tp-page {
            width: 210mm; min-height: 297mm; background: #fffef5; border: 1.5px solid #bbb;
            padding: 7mm 8mm 6mm; margin: 0 auto; font-family: Arial, Helvetica, sans-serif;
            position: relative; overflow: hidden;
        }
        .tp-watermark { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .tp-watermark-inner { width: 100%; height: 100%; display: flex; flex-wrap: wrap; opacity: .04; transform: rotate(-22deg) scale(1.45); }
        .tp-watermark-text { font-size: 9px; font-weight: bold; color: #8b6914; white-space: nowrap; padding: 5px 6px; letter-spacing: 1px; }
        .tp-content { position: relative; z-index: 1; }
        .tp-hdr { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
        .tp-logo { width: 60px; height: 60px; border-radius: 50%; border: 2px solid #8b1a1a; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f5f0e8; flex-shrink: 0; }
        .tp-logo img { width: 100%; height: 100%; object-fit: cover; }
        .tp-hdr-text { flex: 1; text-align: center; }
        .tp-school-name { font-size: 18px; font-weight: 900; color: #8b1a1a; text-transform: uppercase; font-style: italic; display: block; line-height: 1.1; }
        .tp-motto { font-size: 8.5px; color: #555; display: block; font-style: italic; }
        .tp-address { font-size: 8.5px; color: #333; display: block; line-height: 1.4; }
        .tp-divider { border-top: 1.5px solid #222; margin: 3px 0; }
        .tp-divider-thin { border-top: 1px solid #aaa; margin: 1px 0; }
        .tp-tag { font-size: 11px; font-weight: bold; color: #444; text-transform: uppercase; display: block; margin-top: 2px; letter-spacing: 1px; }
        .tp-stu-photo { width: 55px; height: 66px; border-radius: 30px; background: #f5f0e8; flex-shrink: 0; overflow: hidden; }
        .tp-stu-photo img { width: 100%; height: 100%; object-fit: cover; }
        .tp-info-sec { margin-top: 3px; }
        .tp-info-row { display: flex; align-items: baseline; margin-bottom: 3px; gap: 3px; }
        .tp-info-row .lbl { font-weight: bold; font-size: 11px; white-space: nowrap; }
        .tp-info-row .val { border-bottom: 1px solid #333; flex: 1; height: 14px; font-size: 10.5px; padding-left: 3px; overflow: hidden; }
        .tp-info-row .grp { display: flex; align-items: baseline; gap: 3px; flex: 1; margin-left: 8px; }
        .tp-sec-title { text-align: center; font-weight: bold; font-size: 12px; text-transform: uppercase; text-decoration: underline; margin: 6px 0 3px; }
        .tp-summary-box { display: flex; gap: 8px; margin: 6px 0; flex-wrap: wrap; }
        .tp-summary-item { flex: 1; min-width: 80px; background: #f8f4ee; border: 1px solid #d4c5a0; border-radius: 4px; padding: 5px 8px; text-align: center; }
        .tp-s-label { font-size: 8px; font-weight: 700; color: #8b6914; text-transform: uppercase; display: block; }
        .tp-s-value { font-size: 14px; font-weight: 900; color: #1a2b4a; display: block; }
        .tp-gt { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 4px; }
        .tp-gt th { border: 1px solid #333; text-align: center; padding: 3px 2px; background: #f8f4ee; font-size: 9px; }
        .tp-gt th.subj-hd { color: #8b1a1a; text-align: left; padding-left: 4px; }
        .tp-gt td { border: 1px solid #333; padding: 1px 2px; text-align: center; height: 16px; font-size: 9.5px; }
        .tp-gt td.subj-td { text-align: left; padding-left: 4px; font-weight: bold; }
        .tp-gt td.total-td { font-weight: 900; color: #1a2b4a; }
        .tp-gt td.grade-td { font-weight: 800; }
        .tp-term-header { background: #1a2b4a; color: #fff; padding: 4px 10px; font-size: 10px; font-weight: 800; margin-top: 8px; border-radius: 3px; display: flex; justify-content: space-between; align-items: center; }
        .tp-term-avg { color: #fbbf24; font-weight: 900; }
        .tp-grade-note { margin-top: 3px; font-size: 8px; display: flex; justify-content: space-between; }
        .tp-grade-note strong { color: #c0392b; }
        .tp-cum-box { margin-top: 8px; padding: 8px 12px; border: 2px solid #8b1a1a; border-radius: 6px; background: #fdf8f0; }
        .tp-cum-title { font-size: 11px; font-weight: 900; color: #8b1a1a; text-transform: uppercase; text-align: center; margin-bottom: 5px; text-decoration: underline; }
        .tp-cum-stats { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .tp-cum-stat { text-align: center; }
        .tp-c-val { font-size: 16px; font-weight: 900; color: #1a2b4a; display: block; }
        .tp-c-lbl { font-size: 8px; font-weight: 700; color: #8b6914; text-transform: uppercase; display: block; }
        .tp-footer { padding-top: 5px; margin-top: auto; margin-bottom: 30px; }
        .tp-foot-line { display: flex; align-items: baseline; margin-bottom: 8px; }
        .tp-foot-lbl { font-weight: bold; font-size: 10px; white-space: nowrap; text-transform: uppercase; }
        .tp-foot-ul { flex: 1; border-bottom: 1px solid #333; margin-left: 5px; height: 12px; }
    </style>
</head>
<body class="bg-light">

<?php include '../includes/spinner.php'; ?>

<div class="dashboard-wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <?php include '../includes/dashboard_top_nav.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
            <div>
                <h3 class="fw-bold mb-0"><?php echo $pageTitle; ?></h3>
                <p class="text-muted small mb-0">Generate official academic transcripts for students across selected sessions.</p>
            </div>
        </div>

        <!-- Selection Form -->
        <div class="glass-card p-4 mb-4 no-print" style="border-radius: 20px;">
            <!-- Mode Tabs -->
            <div class="d-flex gap-2 mb-4">
                <a href="?mode=single&from_session=<?php echo $from_session_id; ?>&to_session=<?php echo $to_session_id; ?>" 
                   class="btn btn-sm rounded-pill px-4 fw-800 <?php echo $mode === 'single' ? 'btn-primary' : 'btn-light border'; ?>" style="font-size: 0.78rem;">
                    <i class="fas fa-user me-1"></i> Single Student
                </a>
                <a href="?mode=batch&class_id=<?php echo $sel_class; ?>&from_session=<?php echo $from_session_id; ?>&to_session=<?php echo $to_session_id; ?>" 
                   class="btn btn-sm rounded-pill px-4 fw-800 <?php echo $mode === 'batch' ? 'btn-primary' : 'btn-light border'; ?>" style="font-size: 0.78rem;">
                    <i class="fas fa-users me-1"></i> Class Batch
                </a>
            </div>

            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                
                <?php if ($mode === 'single'): ?>
                <div class="col-md-4">
                    <label class="form-label small fw-800 text-muted uppercase">Select Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select rounded-3 px-4 py-3 fw-600 border-light shadow-sm" required id="studentSelect">
                        <option value="">— Choose a student —</option>
                        <?php foreach ($all_students as $stu): ?>
                            <option value="<?php echo $stu['id']; ?>" <?php echo $sel_student == $stu['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($stu['full_name']); ?> (<?php echo htmlspecialchars($stu['admission_no']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="col-md-4">
                    <label class="form-label small fw-800 text-muted uppercase">Select <?php echo get_label('Class'); ?> <span class="text-danger">*</span></label>
                    <select name="class_id" class="form-select rounded-3 px-4 py-3 fw-600 border-light shadow-sm" required>
                        <option value="">— Choose a <?php echo get_label('Class'); ?> —</option>
                        <?php foreach ($all_classes as $cls): ?>
                            <option value="<?php echo $cls['id']; ?>" <?php echo $sel_class == $cls['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls['name']); ?> (<?php echo htmlspecialchars($cls['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label class="form-label small fw-800 text-muted uppercase">From Session</label>
                    <select name="from_session" class="form-select rounded-3 px-4 py-3 fw-600 border-light shadow-sm">
                        <?php foreach ($all_sessions as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $from_session_id == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-800 text-muted uppercase">To Session</label>
                    <select name="to_session" class="form-select rounded-3 px-4 py-3 fw-600 border-light shadow-sm">
                        <?php foreach ($all_sessions as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $to_session_id == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-3 fw-bold w-100">
                        <i class="fas fa-search me-2"></i>Generate
                    </button>
                </div>
            </form>
        </div>

        <?php if ($sel_student && $student_data): ?>
        <!-- Transcript Preview -->
        <div class="no-print mb-3 d-flex justify-content-end gap-2">
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-bold">
                <i class="fas fa-print me-2"></i>Print / Save as PDF
            </button>
        </div>

        <div class="transcript-preview active" id="transcriptArea">
            <div class="tp-page" id="transcriptPage">
                <div class="tp-watermark"><div class="tp-watermark-inner" id="wmInner"></div></div>
                
                <div class="tp-content">
                    <!-- Header -->
                    <div class="tp-hdr">
                        <div class="tp-logo">
                            <?php if ($logoSrc): ?>
                                <img src="<?php echo $logoSrc; ?>" alt="Logo"/>
                            <?php else: ?>
                                <div style="color:#8b1a1a; font-size:9px; font-weight:900;">LOGO</div>
                            <?php endif; ?>
                        </div>
                        <div class="tp-hdr-text">
                            <span class="tp-school-name"><?php echo ste($active_school['school_name']); ?></span>
                            <?php if (!empty($active_school['motto'])): ?>
                                <span class="tp-motto">"<?php echo ste($active_school['motto']); ?>"</span>
                            <?php endif; ?>
                            <span class="tp-address"><?php echo nl2br(ste($active_school['school_address'])); ?></span>
                            <div class="tp-divider-thin"></div>
                            <span class="tp-tag"><?php echo $is_higher_ed ? 'OFFICIAL ACADEMIC TRANSCRIPT' : strtoupper(get_label('Broadsheet')); ?></span>
                        </div>
                        <div class="tp-stu-photo">
                            <img src="<?php echo !empty($student_data['image_path']) ? '../'.ste($student_data['image_path']) : '../img/default_picture.png'; ?>" alt="" onerror="this.src='../img/default_picture.png'"/>
                        </div>
                    </div>

                    <div class="tp-divider"></div>

                    <!-- Student Info -->
                    <div class="tp-info-sec">
                        <div class="tp-info-row">
                            <span class="lbl">Name:</span>
                            <div class="val">&nbsp;<?php echo ste($student_data['full_name']); ?></div>
                        </div>
                        <div class="tp-info-row">
                            <span class="lbl"><?php echo get_label('Admission No'); ?>:</span>
                            <div class="val" style="max-width:130px;">&nbsp;<?php echo ste($student_data['admission_no']); ?></div>
                            <div class="grp"><span class="lbl">Gender:</span><div class="val" style="max-width:80px;">&nbsp;<?php echo ste($student_data['gender']); ?></div></div>
                        </div>
                        <div class="tp-info-row">
                            <span class="lbl">Period:</span>
                            <div class="val">&nbsp;<?php echo ste($from_session_name); ?> — <?php echo ste($to_session_name); ?></div>
                        </div>
                    </div>

                    <div class="tp-divider"></div>

                    <?php if (!$has_transcript): ?>
                        <div style="text-align:center; padding: 60px 30px;">
                            <i class="fas fa-folder-open" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                            <h5 style="font-weight: 800; color: #475569; margin-bottom: 8px;">No Results Found</h5>
                            <p style="color: #94a3b8; font-size: 0.9rem;">No academic results found for this student in the selected period.</p>
                        </div>
                    <?php else: ?>
                        <!-- Summary Stats -->
                        <div class="tp-summary-box">
                            <div class="tp-summary-item">
                                <span class="tp-s-label">Sessions</span>
                                <span class="tp-s-value"><?php echo count(array_unique(array_column($results, 'session_id'))); ?></span>
                            </div>
                            <div class="tp-summary-item">
                                <span class="tp-s-label"><?php echo get_label('Terms'); ?></span>
                                <span class="tp-s-value"><?php echo count($terms_summary); ?></span>
                            </div>
                            <div class="tp-summary-item">
                                <span class="tp-s-label"><?php echo get_label('Subjects'); ?></span>
                                <span class="tp-s-value"><?php echo count($all_subjects); ?></span>
                            </div>
                            <div class="tp-summary-item">
                                <span class="tp-s-label">Total Entries</span>
                                <span class="tp-s-value"><?php echo $total_count; ?></span>
                            </div>
                            <?php if ($is_higher_ed): ?>
                            <div class="tp-summary-item">
                                <span class="tp-s-label">CGPA</span>
                                <span class="tp-s-value" style="color:#8b1a1a;"><?php echo number_format($cgpa, 2); ?></span>
                            </div>
                            <?php else: ?>
                            <div class="tp-summary-item">
                                <span class="tp-s-label">Overall Average</span>
                                <span class="tp-s-value" style="color:#8b1a1a;"><?php echo number_format($overall_avg, 1); ?>%</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Results by Term -->
                        <?php 
                        $current_session = 0;
                        foreach ($terms_summary as $ts): 
                            $term_avg = $ts['count'] > 0 ? round($ts['total_score'] / $ts['count'], 1) : 0;
                            $term_gpa = $ts['total_credits'] > 0 ? round($ts['total_points'] / $ts['total_credits'], 2) : 0;
                            $is_new_session = ($ts['session_id'] != $current_session);
                            $current_session = $ts['session_id'];
                        ?>
                        
                        <?php if ($is_new_session): ?>
                        <div class="tp-term-header" style="background: #2d5faa; margin-top: 10px;">
                            <span><i class="fas fa-calendar-alt me-1"></i> <?php echo ste($ts['session_name']); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="tp-term-header" style="background: #8b6914;">
                            <span><?php echo ste($ts['term_name']); ?> — <?php echo ste($ts['class_name']); ?></span>
                            <span class="tp-term-avg">
                                <?php if ($is_higher_ed): ?>
                                    GPA: <?php echo number_format($term_gpa, 2); ?>
                                <?php else: ?>
                                    Avg: <?php echo number_format($term_avg, 1); ?>%
                                <?php endif; ?>
                            </span>
                        </div>

                        <table class="tp-gt">
                            <thead>
                                <tr>
                                    <th class="subj-hd" style="width:25%;"><?php echo strtoupper(get_label('Subject')); ?></th>
                                    <?php if ($is_higher_ed): ?>
                                        <th style="width:10%;">CA (40)</th>
                                    <?php else: ?>
                                        <th style="width:8%;">CA1 (20)</th>
                                        <th style="width:8%;">CA2 (20)</th>
                                    <?php endif; ?>
                                    <th style="width:10%;">EXAM (60)</th>
                                    <th style="width:10%;">TOTAL</th>
                                    <th style="width:10%;">GRADE</th>
                                    <?php if ($is_higher_ed): ?>
                                        <th style="width:8%;">CR</th>
                                    <?php endif; ?>
                                    <th>REMARK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ts['subjects'] as $sub):
                                    $grade = staffCalcGrade($sub['total'], $is_higher_ed);
                                ?>
                                <tr>
                                    <td class="subj-td"><?php echo ste($sub['subject_name']); ?></td>
                                    <?php if ($is_higher_ed): ?>
                                        <td><?php echo $sub['ca1']; ?></td>
                                    <?php else: ?>
                                        <td><?php echo $sub['ca1']; ?></td>
                                        <td><?php echo $sub['ca2']; ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $sub['exam']; ?></td>
                                    <td class="total-td"><?php echo $sub['total']; ?></td>
                                    <td class="grade-td"><?php echo $grade; ?></td>
                                    <?php if ($is_higher_ed): ?>
                                        <td><?php echo $sub['credit_units'] ?: '-'; ?></td>
                                    <?php endif; ?>
                                    <td style="font-size: 8.5px;"><?php echo staffCalcRemark($grade); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($is_higher_ed): ?>
                        <div class="tp-grade-note">
                            <span><strong>Scale:</strong> A=70-100 · B=60-69 · C=50-59 · D=45-49 · E=40-44 · F=0-39</span>
                            <span><strong>CR = Credit Units</strong></span>
                        </div>
                        <?php else: ?>
                        <div class="tp-grade-note">
                            <span><strong>Scale:</strong> A1=75-100 · B2=70-74 · B3=65-69 · C4=60-64 · C5=55-59 · C6=50-54 · D7=45-49 · E8=40-44 · F9=0-39</span>
                        </div>
                        <?php endif; ?>

                        <?php endforeach; ?>

                        <!-- Cumulative Summary -->
                        <div class="tp-cum-box">
                            <div class="tp-cum-title">
                                <?php echo $is_higher_ed ? 'Cumulative Summary' : 'Overall Summary'; ?>
                                (<?php echo ste($from_session_name); ?> — <?php echo ste($to_session_name); ?>)
                            </div>
                            <div class="tp-cum-stats">
                                <?php if ($is_higher_ed): ?>
                                <div class="tp-cum-stat">
                                    <span class="tp-c-val"><?php echo number_format($cgpa, 2); ?></span>
                                    <span class="tp-c-lbl">Cumulative GPA</span>
                                </div>
                                <div class="tp-cum-stat">
                                    <span class="tp-c-val"><?php echo $cumulative_credits; ?></span>
                                    <span class="tp-c-lbl">Total Credits</span>
                                </div>
                                <?php else: ?>
                                <div class="tp-cum-stat">
                                    <span class="tp-c-val"><?php echo number_format($overall_avg, 1); ?>%</span>
                                    <span class="tp-c-lbl">Overall Average</span>
                                </div>
                                <?php endif; ?>
                                <div class="tp-cum-stat">
                                    <span class="tp-c-val"><?php echo $total_count; ?></span>
                                    <span class="tp-c-lbl">Total Entries</span>
                                </div>
                                <div class="tp-cum-stat">
                                    <span class="tp-c-val"><?php echo count(array_unique(array_column($results, 'session_id'))); ?></span>
                                    <span class="tp-c-lbl">Sessions</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="tp-footer">
                        <div class="tp-foot-line"><span class="tp-foot-lbl"><?php echo get_label('Head Teacher'); ?>'s Signature & Stamp</span><div class="tp-foot-ul"></div></div>
                        <div class="tp-foot-line" style="margin-bottom:0;"><span class="tp-foot-lbl">Date Issued</span><div class="tp-foot-ul"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($mode === 'batch' && $sel_class && $from_session_id && $to_session_id): ?>
        <!-- Batch View -->
        <div class="glass-card p-4 mb-4" style="border-radius: 20px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-900 mb-1 text-dark">
                        <i class="fas fa-users me-2 text-primary"></i><?php echo get_label('Class'); ?> Batch Transcripts
                    </h5>
                    <p class="text-muted small mb-0">
                        <?php echo count($batch_students); ?> <?php echo strtolower(get_label('Pupils')); ?> in selected <?php echo strtolower(get_label('Class')); ?> 
                        · <?php echo ste($from_session_name); ?> — <?php echo ste($to_session_name); ?>
                    </p>
                </div>
                <button onclick="printAllTranscripts()" class="btn btn-primary rounded-pill px-4 fw-bold no-print">
                    <i class="fas fa-print me-2"></i>Print All
                </button>
            </div>

            <?php if (empty($batch_students)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users-slash text-muted opacity-25" style="font-size: 3rem;"></i>
                    <h6 class="fw-bold text-muted mt-3">No <?php echo strtolower(get_label('Pupils')); ?> found</h6>
                    <p class="text-muted small">No <?php echo strtolower(get_label('Pupils')); ?> are enrolled in this <?php echo strtolower(get_label('Class')); ?> for the selected period.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th><?php echo get_label('Pupils'); ?></th>
                                <th><?php echo get_label('Admission No'); ?></th>
                                <th class="text-center">Results</th>
                                <th class="text-center"><?php echo $is_higher_ed ? 'CGPA' : 'Average'; ?></th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batch_students as $idx => $bstu): ?>
                            <tr class="<?php echo $bstu['result_count'] == 0 ? 'table-warning' : ''; ?>">
                                <td class="text-muted fw-bold"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo !empty($bstu['image_path']) ? '../'.$bstu['image_path'] : '../img/default_picture.png'; ?>" 
                                             alt="" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;"
                                             onerror="this.src='../img/default_picture.png'">
                                        <div>
                                            <div class="fw-700 text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($bstu['full_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($bstu['admission_no']); ?></td>
                                <td class="text-center">
                                    <?php if ($bstu['result_count'] > 0): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-700" style="font-size: 0.72rem;">
                                            <?php echo $bstu['result_count']; ?> entries
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 fw-700" style="font-size: 0.72rem;">
                                            No results
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-800">
                                    <?php if ($bstu['result_count'] > 0): ?>
                                        <?php if ($is_higher_ed): ?>
                                            <?php
                                            // Calculate CGPA for this student
                                            $bstu_res = $pdo->prepare("
                                                SELECT r.*, s.credit_units
                                                FROM student_results r
                                                JOIN subjects s ON s.id = r.subject_id
                                                WHERE r.student_id = ? AND r.session_id >= ? AND r.session_id <= ? AND r.school_id = ?
                                            ");
                                            $bstu_res->execute([$bstu['id'], $from_session_id, $to_session_id, $school_id]);
                                            $bstu_scores = $bstu_res->fetchAll();
                                            $bstu_pts = 0; $bstu_cr = 0;
                                            foreach ($bstu_scores as $bs) {
                                                if ($bs['credit_units'] > 0) {
                                                    $gp = 0;
                                                    if ($bs['total'] >= 70) $gp = 5;
                                                    elseif ($bs['total'] >= 60) $gp = 4;
                                                    elseif ($bs['total'] >= 50) $gp = 3;
                                                    elseif ($bs['total'] >= 45) $gp = 2;
                                                    elseif ($bs['total'] >= 40) $gp = 1;
                                                    $bstu_pts += ($gp * $bs['credit_units']);
                                                    $bstu_cr += $bs['credit_units'];
                                                }
                                            }
                                            $bstu_cgpa = $bstu_cr > 0 ? round($bstu_pts / $bstu_cr, 2) : 0;
                                            ?>
                                            <span style="color: #1a2b4a; font-size: 0.9rem;"><?php echo number_format($bstu_cgpa, 2); ?></span>
                                        <?php else: ?>
                                            <span style="color: #1a2b4a; font-size: 0.9rem;"><?php echo number_format($bstu['avg_score'], 1); ?>%</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($bstu['result_count'] > 0): ?>
                                        <a href="generate_transcript.php?mode=single&student_id=<?php echo $bstu['id']; ?>&from_session=<?php echo $from_session_id; ?>&to_session=<?php echo $to_session_id; ?>" 
                                           class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-700" target="_blank" style="font-size: 0.72rem;">
                                            <i class="fas fa-print me-1"></i> Print
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light rounded-pill px-3 fw-700 text-muted" disabled style="font-size: 0.72rem;">
                                            <i class="fas fa-ban me-1"></i> No Data
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Footer -->
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div class="text-muted small">
                        Showing <strong><?php echo count($batch_students); ?></strong> <?php echo strtolower(get_label('Pupils')); ?>
                        · <strong><?php echo count(array_filter($batch_students, fn($s) => $s['result_count'] > 0)); ?></strong> with results
                    </div>
                    <div class="d-flex gap-2">
                        <?php
                        // Collect all student IDs with results for batch print
                        $printable_ids = array_column(array_filter($batch_students, fn($s) => $s['result_count'] > 0), 'id');
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Watermark
(function() {
    const wm = document.getElementById('wmInner');
    if (!wm) return;
    const txt = '<?php echo addslashes($wmText); ?>';
    for (let i = 0; i < 150; i++) {
        const s = document.createElement('span');
        s.className = 'tp-watermark-text';
        s.textContent = txt + ' ';
        wm.appendChild(s);
    }
})();

// Batch print - opens each student's transcript in a new tab for printing
function printAllTranscripts() {
    const rows = document.querySelectorAll('table tbody tr');
    const urls = [];
    rows.forEach(row => {
        const printBtn = row.querySelector('a[target="_blank"]');
        if (printBtn && !row.querySelector('button[disabled]')) {
            urls.push(printBtn.href);
        }
    });
    
    if (urls.length === 0) {
        alert('No transcripts available to print.');
        return;
    }
    
    if (confirm('This will open ' + urls.length + ' transcript(s) in new tabs. Each can be printed individually. Continue?')) {
        urls.forEach((url, i) => {
            setTimeout(() => window.open(url, '_blank'), i * 300);
        });
    }
}
</script>

</body>
</html>
