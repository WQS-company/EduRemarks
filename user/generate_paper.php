<?php
// admin/generate_paper.php
require_once '../includes/auth_check.php';
require_once '../includes/functions.php';

if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    die("Unauthorized");
}

if (!$active_school || strpos($active_school['feature_access'] ?? '', 'CBT_EXAMS') === false) {
    die("Institutional feature access denied.");
}

if ($role === 'staff' && empty($staff_permissions['can_manage_cbt'])) {
    die("Access denied. Please contact your school administrator.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid Request");
}

$mode = $_POST['mode'] ?? 'plain';
$target_class_id = intval($_POST['target_class'] ?? 0);
$plain_copies = intval($_POST['plain_copies'] ?? 1);
$booklet_copies = intval($_POST['booklet_copies'] ?? 1);
$num_format = $_POST['num_format'] ?? '1';
$subject_id = intval($_POST['subject_id'] ?? 0);
$session = $_POST['session'] ?? '';
$term = $_POST['term'] ?? '';
$exam_type = $_POST['exam_type'] ?? '';
$instructions = $_POST['instructions'] ?? '';
$questions = json_decode($_POST['questions'] ?? '[]', true);

// Fetch School Info
$school_id = $_SESSION['school_id'];
$stmt_school = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt_school->execute([$school_id]);
$school = $stmt_school->fetch();

// Fetch Subject Info
$subject_name = "";
if ($subject_id) {
    $stmt_subject = $pdo->prepare("SELECT name FROM subjects WHERE id = ? AND school_id = ?");
    $stmt_subject->execute([$subject_id, $school_id]);
    $subject_name = $stmt_subject->fetchColumn();
}

// Fetch Students if booklet mode
$students = [];
if ($mode === 'booklet' && $target_class_id) {
    $stmt_students = $pdo->prepare("
        SELECT s.*, c.name as class_name 
        FROM students s
        JOIN student_classes sc ON sc.student_id = s.id AND sc.school_id = s.school_id
        JOIN classes c ON c.id = sc.class_id
        WHERE sc.class_id = ? AND s.school_id = ?
        ORDER BY s.full_name ASC
    ");
    $stmt_students->execute([$target_class_id, $school_id]);
    $students = $stmt_students->fetchAll();
}

$iterations = ($mode === 'booklet') ? ($target_class_id ? count($students) : $booklet_copies) : $plain_copies;

if ($iterations === 0) {
    die("<div style='text-align:center; margin-top: 50px; font-family:sans-serif;'><h4>No targets found to generate for.</h4><button onclick='window.close()'>Close</button></div>");
}

// Implement Resource Billing Economics
if ($role !== 'super_admin') {
    // $iterations is the number of booklets requested. We apply the global rate per booklet.
    $activity_log = "Answers Booklet Generation ($iterations copies)";
    $total_billable_units = $iterations;
    
    // Attempt deduction; function will automatically lookup 'credit_answer_sheet' if provided as last arg
    if (!deductCredits($pdo, $school_id, $total_billable_units, $activity_log, 'credit_answer_sheet')) {
        die("<div style='text-align:center; margin-top: 100px; font-family:sans-serif;'>
                <h2>Insufficient Institutional Credits</h2>
                <p>Your institution lacks the required operational credits to generate <strong>{$iterations}</strong> answer booklets.</p>
                <button onclick='window.close()' style='padding: 10px 20px; border: none; background: #dc2626; color: white; border-radius: 8px; cursor:pointer;'>Close Window</button>
             </div>");
    }
}

function getNumbering($index, $format) {
    $num = $index + 1;
    if ($format === 'A') {
        return chr(64 + $num); // A, B, C...
    } elseif ($format === 'i') {
        $map = [100=>'c', 90=>'xc', 50=>'l', 40=>'xl', 10=>'x', 9=>'ix', 5=>'v', 4=>'iv', 1=>'i'];
        $res = ''; $n = $num;
        foreach ($map as $val => $rom) {
            $matches = intval($n / $val);
            $res .= str_repeat($rom, $matches);
            $n = $n % $val;
        }
        return $res;
    }
    return $num; // 1, 2, 3...
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generated Examination Paper | EduRemarks</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&display=swap');
        body { font-family: 'Crimson Pro', serif; margin: 0; padding: 0; background: #f0f2f5; line-height: 1.6; color: #000; }
        .page { 
            width: 210mm; 
            padding: 20mm; 
            margin: 20px auto; 
            background: #fff; 
            box-shadow: 0 0 20px rgba(0,0,0,0.1); 
            position: relative; 
            min-height: 297mm; 
            box-sizing: border-box;
            z-index: 1;
        }
        @media print {
            @page { margin: 0; size: auto; }
            body { background: none; margin: 0; padding: 0; }
            .page { margin: 0; padding: 20mm; box-shadow: none; page-break-after: always; width: 100%; border: none; }
            .page:last-of-type { page-break-after: auto; }
            .no-print { display: none !important; visibility: hidden !important; height: 0 !important; overflow: hidden !important; }
        }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 25px; }
        .school-logo { max-height: 90px; margin-bottom: 12px; }
        .school-name { font-size: 28px; font-weight: 700; text-transform: uppercase; margin: 0; letter-spacing: 1px; }
        .school-motto { font-size: 15px; font-style: italic; margin-top: 5px; color: #444; }
        .exam-details { margin-top: 15px; font-size: 18px; font-weight: 700; color: #1a1a1a; letter-spacing: 0.5px; }
        
        .student-meta { border: 2px solid #000; padding: 15px; margin-bottom: 25px; font-size: 14px; background: #fafafa; border-radius: 4px; }
        .student-meta span { border-bottom: 1px dotted #000; display: inline-block; min-width: 180px; margin-left: 5px; font-weight: 600; }
        
        .instructions { font-size: 14px; font-weight: 700; border: 1.5px dashed #000; padding: 12px 18px; margin-bottom: 30px; background: #fffef0; }
        
        .question { margin-bottom: 45px; font-size: 16px; position: relative; } /* Increased gap from 30px to 45px */
        .question-num { font-weight: 700; float: left; width: 35px; }
        .question-text { 
            margin-left: 40px; 
            font-weight: 500; 
            word-wrap: break-word; 
            overflow-wrap: break-word; 
            hyphens: auto;
            max-width: calc(100% - 40px);
            box-sizing: border-box;
        }
        
        .options { margin-top: 12px; margin-left: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .option { margin-bottom: 4px; display: flex; align-items: baseline; }
        .option-key { font-weight: 700; margin-right: 8px; }
        
        .tf-options { margin-top: 12px; margin-left: 40px; font-weight: 700; }
        
        .q-attached-image { page-break-inside: avoid; }
        
        /* Ruled Paper Style for Essay */
        .ruled-section { 
            margin-top: 25px; 
            width: calc(100% + 40mm + 40px);
            margin-left: calc(-20mm - 40px);
            background-color: #fff;
            background-image: repeating-linear-gradient(to bottom, transparent, transparent 39px, #000 39px, #000 40px);
            background-size: 100% 40px;
            min-height: 310px; 
            position: relative;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            box-sizing: border-box;
        }

        .footer { position: absolute; bottom: 15mm; left: 0; right: 0; text-align: center; font-size: 12px; color: #666; font-style: italic; }
        .no-print-bar { background: #0f172a; color: #fff; padding: 15px 30px; text-align: center; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; display: flex; justify-content: space-between; align-items: center; }
        .print-btn { background: #dc2626; color: #fff; border: none; padding: 10px 25px; font-weight: 800; border-radius: 8px; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4); }
        .print-btn:hover { transform: translateY(-2px); background: #b91c1c; }
    </style>
</head>
<body>
    <div class="no-print-bar no-print">
        <div class="fw-bold"><i class="fas fa-check-circle text-success me-2"></i> Document Ready for Generation</div>
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-file-pdf me-2"></i> SAVE AS PROFESSIONAL PDF
        </button>
        <div style="opacity: 0.7; font-size: 13px;">Format: A4 Portrait | <?php echo ($mode === 'booklet' ? 'Booklet Mode: ' . count($students) . ' Students' : 'Plain Mode: ' . $plain_copies . ' Copies'); ?></div>
    </div>

    <?php     
    for ($i = 0; $i < $iterations; $i++):
        $student = ($mode === 'booklet' && $target_class_id && isset($students[$i])) ? $students[$i] : null;
    ?>
    <div class="page">
        <div class="header">
            <?php if ($school['logo_path']): ?>
                <img src="../<?php echo htmlspecialchars($school['logo_path']); ?>" class="school-logo">
            <?php endif; ?>
            <h1 class="school-name"><?php echo htmlspecialchars(html_entity_decode($school['school_name'], ENT_QUOTES), ENT_QUOTES); ?></h1>
            <?php if (isset($school['motto'])): ?>
                <p class="school-motto"><?php echo htmlspecialchars(html_entity_decode($school['motto'], ENT_QUOTES), ENT_QUOTES); ?></p>
            <?php endif; ?>
            <div class="exam-details">
                <?php echo strtoupper(htmlspecialchars(html_entity_decode($exam_type, ENT_QUOTES), ENT_QUOTES)); ?> - <?php echo htmlspecialchars(html_entity_decode($session, ENT_QUOTES), ENT_QUOTES); ?> (<?php echo htmlspecialchars(html_entity_decode($term, ENT_QUOTES), ENT_QUOTES); ?>)<br>
                SUBJECT: <?php echo strtoupper(htmlspecialchars(html_entity_decode($subject_name, ENT_QUOTES), ENT_QUOTES)); ?>
            </div>
        </div>

        <div class="student-meta">
            <?php if ($mode === 'booklet' && $student): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <div>NAME: <span><?php echo htmlspecialchars(html_entity_decode($student['full_name'], ENT_QUOTES), ENT_QUOTES); ?></span></div>
                    <div>CLASS: <span><?php echo htmlspecialchars(html_entity_decode($student['class_name'] ?? '', ENT_QUOTES), ENT_QUOTES); ?></span></div>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <div>ADMISSION NO: <span><?php echo htmlspecialchars(html_entity_decode($student['admission_no'], ENT_QUOTES), ENT_QUOTES); ?></span></div>
                    <div>DATE: <span>____________________</span></div>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 12px;">NAME: __________________________________________________________________</div>
                <div style="display: flex; justify-content: space-between;">
                    <div>ADMISSION NO: _______________________</div>
                    <div>CLASS: _______________________</div>
                    <div>DATE: _________</div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($instructions): ?>
        <div class="instructions">
            INSTRUCTIONS: <?php echo nl2br(htmlspecialchars(html_entity_decode($instructions, ENT_QUOTES), ENT_QUOTES)); ?>
        </div>
        <?php endif; ?>

        <div class="questions-list">
            <?php foreach ($questions as $idx => $q): ?>
            <div class="question" style="<?php echo ($q['type'] === 'essay' ? '' : 'page-break-inside: avoid;'); ?>">
                <div class="question-num"><?php echo getNumbering($idx, $num_format); ?>.</div>
                <div class="question-text">
                    <?php echo nl2br(htmlspecialchars(html_entity_decode($q['text'], ENT_QUOTES), ENT_QUOTES)); ?>

                    <?php if (!empty($q['image'])): ?>
                        <div class="q-attached-image" style="margin-top: 10px; margin-bottom: 10px;">
                            <img src="<?php echo $q['image']; ?>" style="max-width: 100%; max-height: 250px; border-radius: 8px; border: 1px solid #eef2f6; object-fit: contain;">
                        </div>
                    <?php endif; ?>

                    <?php if ($q['type'] === 'objective' && isset($q['options'])): ?>
                        <div class="options">
                            <div class="option"><span class="option-key">(A)</span> <?php echo htmlspecialchars(html_entity_decode($q['options']['A'] ?? '', ENT_QUOTES), ENT_QUOTES); ?></div>
                            <div class="option"><span class="option-key">(B)</span> <?php echo htmlspecialchars(html_entity_decode($q['options']['B'] ?? '', ENT_QUOTES), ENT_QUOTES); ?></div>
                            <div class="option"><span class="option-key">(C)</span> <?php echo htmlspecialchars(html_entity_decode($q['options']['C'] ?? '', ENT_QUOTES), ENT_QUOTES); ?></div>
                            <div class="option"><span class="option-key">(D)</span> <?php echo htmlspecialchars(html_entity_decode($q['options']['D'] ?? '', ENT_QUOTES), ENT_QUOTES); ?></div>
                        </div>
                    <?php elseif ($q['type'] === 'tf'): ?>
                        <div class="tf-options">
                            <span style="border: 1px solid #000; padding: 2px 10px; margin-right: 15px;">True</span> 
                            <span style="border: 1px solid #000; padding: 2px 10px;">False</span>
                        </div>
                    <?php elseif ($q['type'] === 'essay'): 
                        $space_multiplier = isset($q['space']) ? floatval($q['space']) : 0.5;
                        // Approximate usable A4 height is 250mm. 
                        // We calculate min-height in mm to maintain paper fidelity.
                        $min_height = 250 * $space_multiplier;
                    ?>
                        <div class="ruled-section" style="min-height: <?php echo $min_height; ?>mm;"></div>
                    <?php elseif ($q['type'] === 'fill_in_the_blank'): ?>
                        <div style="margin-top: 10px; margin-left: 20px;">
                            Answer: ____________________________________________________
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div style="text-align: center; margin-top: 50px; padding-top: 20px; border-top: 2px dashed #ccc; font-weight: bold; letter-spacing: 2px;">
                *** END OF PAPER ***
            </div>
        </div>

        <div class="footer">
            &bullet; Strictly Confidential &bullet; Generated by EduRemarks SaaS Platform &copy; <?php echo date('Y'); ?>
        </div>
    </div>
    <?php endfor; ?>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</body>
</html>
