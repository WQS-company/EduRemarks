<?php
// user/cbt_generate_report.php — Professional Assessment Report Engine
require_once '../includes/auth_check.php';

if ($role !== 'staff') { die("Unauthorized"); }

$school_id  = $_SESSION['school_id'];
$student_ids = isset($_POST['student_ids']) ? explode(',', $_POST['student_ids']) : [];
$class_id    = $_POST['class_id'] ?? null;
$subject_id  = $_POST['subject_id'] ?? null;
$inc_1st     = isset($_POST['include_1st_ca']);
$inc_2nd     = isset($_POST['include_2nd_ca']);
$inc_exam    = isset($_POST['include_exam']);
$format      = $_POST['format'] ?? 'pdf';

if (empty($student_ids) || !$class_id || !$subject_id) {
    die("Insufficient data to generate report.");
}

// === CREDIT SYSTEM INTEGRATION ===
$credit_cost = count($student_ids) * 2; // 2 credits per student
$activity_log = "Generated Assessment Report for " . count($student_ids) . " students (" . $_POST['format'] . ")";
if (!deductCredits($pdo, $school_id, $credit_cost, $activity_log)) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'>
            <h2 style='color:#dc3545;'>INSUFFICIENT CREDITS</h2>
            <p>Your institution requires " . $credit_cost . " credits to generate this report, but your balance is low.</p>
            <p>Please contact your administrator to top up your account.</p>
            <a href='dashboard.php' style='display:inline-block; margin-top:20px; text-decoration:none; padding:10px 20px; background:#1F3C88; color:white; border-radius:5px;'>Return to Dashboard</a>
         </div>");
}


// 1. Fetch Class and Subject names
$stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class_name = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
$stmt->execute([$subject_id]);
$subject_name = $stmt->fetchColumn();

// 2. Map Assessments available for this class/subject/school
// We need to find the specific CBT IDs for 1st CA, 2nd CA, and Exam
$assessments = [];

if ($inc_1st) {
    $stmt = $pdo->prepare("SELECT id FROM cbt_exams WHERE school_id=? AND class_id=? AND subject_id=? AND assessment_type='test' AND test_category='1st_ca' LIMIT 1");
    $stmt->execute([$school_id, $class_id, $subject_id]);
    $assessments['1st_ca'] = $stmt->fetchColumn();
}
if ($inc_2nd) {
    $stmt = $pdo->prepare("SELECT id FROM cbt_exams WHERE school_id=? AND class_id=? AND subject_id=? AND assessment_type='test' AND test_category='2nd_ca' LIMIT 1");
    $stmt->execute([$school_id, $class_id, $subject_id]);
    $assessments['2nd_ca'] = $stmt->fetchColumn();
}
if ($inc_exam) {
    $stmt = $pdo->prepare("SELECT id FROM cbt_exams WHERE school_id=? AND class_id=? AND subject_id=? AND assessment_type='exam' LIMIT 1");
    $stmt->execute([$school_id, $class_id, $subject_id]);
    $assessments['exam'] = $stmt->fetchColumn();
}

// 3. Fetch Students data and their scores
$placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
$stmt = $pdo->prepare("SELECT id, full_name, admission_no FROM students WHERE id IN ($placeholders) ORDER BY full_name ASC");
$stmt->execute($student_ids);
$students = $stmt->fetchAll();

$report_data = [];
foreach ($students as $s) {
    $row = [
        'name' => $s['full_name'],
        'adm'  => $s['admission_no'],
        'scores' => []
    ];

    foreach ($assessments as $key => $exam_id) {
        if ($exam_id) {
            $score_stmt = $pdo->prepare("SELECT total_score, status FROM cbt_student_attempts WHERE exam_id = ? AND student_id = ?");
            $score_stmt->execute([$exam_id, $s['id']]);
            $res = $score_stmt->fetch();
            
            if ($res && in_array($res['status'], ['submitted', 'timed_out'])) {
                $row['scores'][$key] = $res['total_score'];
            } else {
                $row['scores'][$key] = 'ABSENT';
            }
        } else {
            $row['scores'][$key] = 'N/A';
        }
    }
    $report_data[] = $row;
}

// 4. Handle Excel Format (CSV)
if ($format === 'excel') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Assessment_Report_' . str_replace(' ', '_', $class_name) . '.csv"');
    
    $output = fopen('php://output', 'w');
    $header = ['S/N', 'Student Name', 'Admission No'];
    if ($inc_1st) $header[] = '1st C.A';
    if ($inc_2nd) $header[] = '2nd C.A';
    if ($inc_exam) $header[] = 'Exam';
    $header[] = 'Total';
    
    fputcsv($output, $header);
    
    foreach ($report_data as $i => $r) {
        $csv_row = [($i+1), $r['name'], $r['adm']];
        $total = 0;
        if ($inc_1st) { $csv_row[] = $r['scores']['1st_ca']; if(is_numeric($r['scores']['1st_ca'])) $total += $r['scores']['1st_ca']; }
        if ($inc_2nd) { $csv_row[] = $r['scores']['2nd_ca']; if(is_numeric($r['scores']['2nd_ca'])) $total += $r['scores']['2nd_ca']; }
        if ($inc_exam) { $csv_row[] = $r['scores']['exam']; if(is_numeric($r['scores']['exam'])) $total += $r['scores']['exam']; }
        $csv_row[] = $total;
        fputcsv($output, $csv_row);
    }
    fclose($output);
    exit;
}

// 5. PDF / High-End HTML Print View
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assessment Report - <?php echo $class_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none; } body { background: white; } }
        body { background: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .report-paper { 
            background: white; width: 210mm; min-height: 297mm; margin: 30px auto; 
            padding: 20mm; box-shadow: 0 0 20px rgba(0,0,0,0.1); border-radius: 4px;
        }
        .report-header { text-align: center; border-bottom: 2px solid #1F3C88; padding-bottom: 20px; margin-bottom: 30px; }
        .school-name { font-size: 1.8rem; font-weight: 800; color: #1F3C88; text-transform: uppercase; }
        .report-title { font-size: 1.1rem; color: #64748b; font-weight: 600; margin-top: 5px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-item { background: #f8fafc; padding: 12px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .info-label { font-size: 0.65rem; text-transform: uppercase; font-weight: 700; color: #94a3b8; display: block; }
        .info-value { font-size: 0.95rem; font-weight: 700; color: #1F3C88; }

        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .report-table th { background: #1F3C88; color: white; padding: 12px; font-size: 0.75rem; text-transform: uppercase; text-align: left; }
        .report-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
        .report-table tr:nth-child(even) { background: #fbfbfc; }
        
        .absent-text { color: #dc3545; font-weight: 800; font-size: 0.7rem; }
        .score-val { font-weight: 700; }
        
        .footer-note { margin-top: 50px; font-size: 0.75rem; color: #94a3b8; text-align: center; border-top: 1px dashed #e2e8f0; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="no-print text-center mt-4">
        <button class="btn btn-primary px-5 rounded-pill shadow" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Report / Save as PDF
        </button>
        <p class="text-muted small mt-2">Adjust your printer settings to "Save as PDF" if you want a digital copy.</p>
    </div>

    <div class="report-paper">
        <div class="report-header">
            <?php if (!empty($active_school['logo_path'])): ?>
                <img src="../<?php echo $active_school['logo_path']; ?>" style="height: 80px; margin-bottom: 15px;" alt="Logo">
            <?php endif; ?>
            <div class="school-name"><?php echo htmlspecialchars($active_school['school_name'] ?? 'EduRemarks Education'); ?></div>
            <div class="report-title">CONSOLIDATED ASSESSMENT PERFORMANCE SHEET</div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Standard / Class</span>
                <span class="info-value"><?php echo $class_name; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Subject Title</span>
                <span class="info-value"><?php echo $subject_name; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Academic Session</span>
                <span class="info-value"><?php echo date('Y') . '/' . (date('Y')+1); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Generation Date</span>
                <span class="info-value"><?php echo date('F d, Y - h:i A'); ?></span>
            </div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 40px;">S/N</th>
                    <th>Student Name</th>
                    <th>Admission No</th>
                    <?php if ($inc_1st): ?><th>1st C.A</th><?php endif; ?>
                    <?php if ($inc_2nd): ?><th>2nd C.A</th><?php endif; ?>
                    <?php if ($inc_exam): ?><th>Exam</th><?php endif; ?>
                    <th style="background:#F4B400; color:#1F3C88;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $i => $r): 
                    $total = 0;
                ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($r['name']); ?></td>
                    <td><code><?php echo $r['adm']; ?></code></td>
                    
                    <?php if ($inc_1st): ?>
                    <td>
                        <?php if ($r['scores']['1st_ca'] === 'ABSENT'): ?>
                            <span class="absent-text">ABSENT</span>
                        <?php else: ?>
                            <span class="score-val"><?php echo $r['scores']['1st_ca']; ?></span>
                            <?php if(is_numeric($r['scores']['1st_ca'])) $total += $r['scores']['1st_ca']; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <?php if ($inc_2nd): ?>
                    <td>
                        <?php if ($r['scores']['2nd_ca'] === 'ABSENT'): ?>
                            <span class="absent-text">ABSENT</span>
                        <?php else: ?>
                            <span class="score-val"><?php echo $r['scores']['2nd_ca']; ?></span>
                            <?php if(is_numeric($r['scores']['2nd_ca'])) $total += $r['scores']['2nd_ca']; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <?php if ($inc_exam): ?>
                    <td>
                        <?php if ($r['scores']['exam'] === 'ABSENT'): ?>
                            <span class="absent-text">ABSENT</span>
                        <?php else: ?>
                            <span class="score-val"><?php echo $r['scores']['exam']; ?></span>
                            <?php if(is_numeric($r['scores']['exam'])) $total += $r['scores']['exam']; ?>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>

                    <td style="background:#fef9e7; font-weight:900;"><?php echo $total; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer-note">
            <p>This document is an official assessment record generated via the EduRemarks CBT Management Protocol.</p>
            <p>&copy; <?php echo date('Y'); ?> EduRemarks SaaS. All student performance data is encrypted and secured.</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
    <?php include '../includes/feedback_modal.php'; ?>
    <script>
        // Trigger feedback after a few seconds of browsing the report
        setTimeout(() => {
            EduRemarks.showFeedback('Assessment Report', '<?php echo addslashes($class_name . " " . $subject_name); ?> Report');
        }, 3000);
    </script>
</body>
</html>
