<?php
// student/exam_schedule.php - Professional Printable Examination Slip
require_once 'auth.php';

$exam_id = intval($_GET['exam_id'] ?? 0);

if (!$exam_id) {
    die("Invalid Examination Context.");
}

// Fetch Examination Details
$ex_stmt = $pdo->prepare("
    SELECT e.*, s.name as subject_name, s.code as subject_code
    FROM cbt_exams e
    JOIN subjects s ON s.id = e.subject_id
    WHERE e.id = ? AND e.school_id = ?
");
$ex_stmt->execute([$exam_id, $school_id]);
$exam = $ex_stmt->fetch();

if (!$exam) {
    die("Examination record not found.");
}

// Fetch Current Session/Term names from School Context
$sch_active_stmt = $pdo->prepare("
    SELECT t.name as term_name, sess.name as session_name 
    FROM schools sch
    JOIN academic_terms t ON t.id = sch.current_term_id
    JOIN academic_sessions sess ON sess.id = sch.current_session_id
    WHERE sch.id = ?
");
$sch_active_stmt->execute([$school_id]);
$sch_period = $sch_active_stmt->fetch();

$term_display = $sch_period['term_name'] ?? 'Current Term';
$session_display = $sch_period['session_name'] ?? 'Current Session';

// Fetch Student's Current Class/Level
$cls_stmt = $pdo->prepare("
    SELECT c.name 
    FROM classes c 
    JOIN student_classes sc ON sc.class_id = c.id 
    WHERE sc.student_id = ? AND sc.school_id = ? 
    LIMIT 1
");
$cls_stmt->execute([$student_id, $school_id]);
$current_class_display = $cls_stmt->fetchColumn() ?: '--';

// Format Dates
$start_time = strtotime($exam['start_time']);
$end_time = strtotime($exam['end_time']);

// Branding Asset
$school_logo_watermark = $student['logo_path'] ? '../'.$student['logo_path'] : '../img/logo.png';

// Assessment Type Context
$is_test = (stripos($exam['assessment_type'], 'test') !== false);
$is_tertiary = ($active_school['school_type'] === 'tertiary' || $active_school['school_type'] === 'vocational');

// Dynamic Theme Logic
if ($is_tertiary) {
    if ($is_test) {
        $theme_color = '#d97706'; // Amber Professional
        $theme_bg = '#fffbeb';
        $theme_title = "TEST ADMISSION SLIP";
    } else {
        $theme_color = '#1e3a8a'; // Navy Professional
        $theme_bg = '#eff6ff';
        $theme_title = "EXAMINATION ADMISSION SLIP";
    }
} else {
    if ($is_test) {
        $theme_color = '#059669'; // Emerald Academic
        $theme_bg = '#ecfdf5';
        $theme_title = "TEST ADMISSION SLIP";
    } else {
        $theme_color = '#334155'; // Slate Formal
        $theme_bg = '#f8fafc';
        $theme_title = "EXAM ADMISSION SLIP";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Slip | <?php echo htmlspecialchars($student['full_name']); ?></title>
    <?php 
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : '../' . $sidebar_logo_raw;
    ?>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-theme: <?php echo $theme_color; ?>;
            --accent-soft: <?php echo $theme_bg; ?>;
            --primary-dark: #1e293b;
            --print-gray: #f8fafc;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #e2e8f0;
            padding: 40px 0;
            color: var(--primary-dark);
        }
        .exam-slip-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 30px 45px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            border: 2px solid var(--primary-theme);
            border-radius: 12px;
            min-height: 1050px; /* Force A4-like height for screen feel */
        }
        /* Decorative Watermark */
        .exam-slip-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70%;
            height: 50%;
            background: url('<?php echo $school_logo_watermark; ?>') no-repeat center center;
            background-size: contain;
            opacity: 0.045;
            pointer-events: none;
            z-index: 0;
            filter: grayscale(100%);
        }

        .slip-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid var(--primary-theme);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .school-info { flex: 1; padding-left: 20px; }
        .school-info h2 { font-weight: 900; text-transform: uppercase; margin: 0; font-size: 1.5rem; letter-spacing: -0.5px; color: var(--primary-theme); }
        .school-info p { margin: 0; font-size: 0.85rem; opacity: 0.8; font-weight: 600; }
        
        .slip-title-box {
            background: var(--primary-theme);
            color: white;
            text-align: center;
            padding: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 1rem;
            box-shadow: 0 4px 12px <?php echo $theme_color; ?>33;
        }

        .student-snapshot {
            display: flex;
            gap: 25px;
            background: var(--accent-soft);
            padding: 15px 25px;
            border-radius: 16px;
            border: 1px solid <?php echo $theme_color; ?>22;
            margin-bottom: 20px;
        }
        .student-photo {
            width: 110px;
            height: 110px;
            border-radius: 8px;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px 40px;
            flex: 1;
        }
        .info-item label {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 2px;
        }
        .info-item span {
            display: block;
            font-size: 1rem;
            font-weight: 700;
        }

        .exam-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .exam-details-table th {
            background: #f1f5f9;
            text-align: left;
            padding: 10px 15px;
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 800;
            border: 1px solid #e2e8f0;
        }
        .exam-details-table td {
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .instructions-section {
            font-size: 0.82rem;
            line-height: 1.6;
            margin-bottom: 40px;
            background: var(--accent-soft);
            padding: 20px;
            border-radius: 12px;
            border-left: 6px solid var(--primary-theme);
        }
        .instructions-section h4 {
            font-size: 0.9rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--primary-theme);
            text-transform: uppercase;
        }

        .signature-area {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 15px;
        }
        .sig-box {
            text-align: center;
            width: 180px;
        }
        .sig-line {
            border-top: 2px solid var(--primary-theme);
            margin-bottom: 8px;
        }
        .sig-box p {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0;
            opacity: 0.7;
        }

        .print-controls {
            max-width: 850px;
            margin: 20px auto;
            text-align: right;
        }

        /* Print Overrides */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body { background: white; padding: 0; margin: 0; }
            .print-controls { display: none; }
            .exam-slip-container { 
                border: none; 
                box-shadow: none; 
                padding: 30mm 15mm; /* Professional print margins */
                height: 297mm;
                max-height: 297mm;
                min-height: auto;
                width: 210mm;
            }
            .exam-slip-container::before { opacity: 0.06; filter: grayscale(100%); width: 80%; }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 fw-bold">
            <i class="fas fa-print me-2"></i> Print Examination Slip
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary rounded-pill px-4 fw-bold ms-2">
            Close Page
        </button>
    </div>

    <div class="exam-slip-container">
        <!-- Header -->
        <header class="slip-header">
            <img src="<?php echo $student['logo_path'] ? '../'.$student['logo_path'] : '../img/logo.png'; ?>" style="height: 80px; width: 80px; object-fit: contain;" alt="Logo">
            <div class="school-info">
                <h2><?php echo htmlspecialchars($active_school['school_name']); ?></h2>
                <p><?php echo htmlspecialchars($active_school['school_address']); ?></p>
                <p><?php echo htmlspecialchars($active_school['motto']); ?></p>
            </div>
            <div class="text-end">
                <div class="fw-900" style="font-size: 1.2rem;"><?php echo htmlspecialchars($session_display); ?></div>
                <div class="badge bg-dark rounded-0 px-3"><?php echo htmlspecialchars(get_label($term_display)); ?></div>
            </div>
        </header>

        <div class="slip-title-box">
            <?php echo $theme_title; ?>
        </div>

        <!-- Student Snapshot -->
        <div class="student-snapshot">
            <img src="<?php echo $student['image_path'] ? '../'.$student['image_path'] : '../img/default_picture.png'; ?>" class="student-photo" alt="Student">
            <div class="info-grid">
                <div class="info-item">
                    <label>Full Name of Student</label>
                    <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Registration Number</label>
                    <span><?php echo htmlspecialchars($student['admission_no']); ?></span>
                </div>
                <div class="info-item">
                    <label>Assigned Node (<?php echo get_label('Class'); ?>)</label>
                    <span><?php echo htmlspecialchars($current_class_display); ?></span>
                </div>
                <div class="info-item">
                    <label>Institution Type</label>
                    <span><?php echo htmlspecialchars(ucfirst($active_school['school_type'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Assessment Details -->
        <table class="exam-details-table">
            <thead>
                <tr>
                    <th style="width: 40%;"><?php echo get_label('Subject'); ?></th>
                    <th>Schedule Date & Time</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="fw-800"><?php echo htmlspecialchars($exam['subject_name']); ?></div>
                        <div class="text-muted extra-small"><?php echo htmlspecialchars($exam['subject_code']); ?></div>
                    </td>
                    <td>
                        <div class="fw-700"><?php echo date('l, jS F Y', $start_time); ?></div>
                        <div class="text-primary small fw-800"><?php echo date('h:i A', $start_time); ?> - <?php echo date('h:i A', $end_time); ?></div>
                    </td>
                    <td><?php echo $exam['duration_mins']; ?> Minutes</td>
                </tr>
            </tbody>
        </table>

        <!-- Instructions -->
        <div class="instructions-section">
            <h4>Important Candidate Instructions</h4>
            <ol class="ps-3 mb-0">
                <li>Candidates are expected to be at the examination venue at least 30 minutes before the scheduled time.</li>
                <li>This admission slip must be presented to the invigilators before entrance into the CBT node.</li>
                <li>Electronic gadgets, unauthorized materials, and mobile phones are strictly prohibited in the exam hall.</li>
                <li>Identification must be verified against the school's digital records before commencement.</li>
                <li>Failure to comply with examination regulations may lead to immediate disqualification.</li>
            </ol>
        </div>

        <!-- Footer Signatures -->
        <div class="signature-area">
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>Candidate's Signature</p>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>Registrar / Admin</p>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>School Stamp / Date</p>
            </div>
        </div>

        <div class="mt-5 text-center text-muted" style="font-size: 0.65rem; border-top: 1px solid #eee; padding-top: 20px;">
            Generated by EduRemarks Unified Digital Infrastructure &bull; Time of issuance: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>

</body>
</html>
