<?php
// student/exam.php — World-Class Live Examination Environment
require_once '../config/db.php';

$token      = $_GET['token'] ?? '';
$student_id = $_GET['uid'] ?? '';

if (!$token || !$student_id) die("Access Denied");

// 1. Fetch Exam & Student details
$stmt = $pdo->prepare("
    SELECT e.*, c.name as class_name, s.name as subject_name, sch.school_name, sch.logo_path
    FROM cbt_exams e
    JOIN classes c ON c.id = e.class_id
    JOIN subjects s ON s.id = e.subject_id
    JOIN schools sch ON sch.id = e.school_id
    WHERE e.token = ?
");
$stmt->execute([$token]);
$exam = $stmt->fetch();

if (!$exam || $exam['status'] !== 'active') die("Assessment not accessible.");

$term = ($exam['assessment_type'] === 'test') ? 'Test' : 'Examination';
$term_lower = strtolower($term);
$school_logo_url = ($exam['logo_path']) ? '../' . $exam['logo_path'] : '../img/logo.png';

// Verify student exists and belongs to this class
$stmt = $pdo->prepare("
    SELECT s.id, s.full_name, s.student_class, s.admission_no 
    FROM students s
    JOIN classes c ON c.id = ?
    WHERE s.id = ? AND s.student_class = c.name
");
$stmt->execute([$exam['class_id'], $student_id]);
$student = $stmt->fetch();
if (!$student) die("Access Denied: Student-to-class verification failed.");

// 2. Manage Attempt
$stmt = $pdo->prepare("SELECT id, status, start_time, time_extension_mins FROM cbt_student_attempts WHERE exam_id=? AND student_id=?");
$stmt->execute([$exam['id'], $student_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $stmt = $pdo->prepare("INSERT INTO cbt_student_attempts (exam_id, student_id, status) VALUES (?, ?, 'started')");
    $stmt->execute([$exam['id'], $student_id]);
    $attempt_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT id, status, start_time, time_extension_mins FROM cbt_student_attempts WHERE id=?");
    $stmt->execute([$attempt_id]);
    $attempt = $stmt->fetch();
} else {
    if ($attempt['status'] === 'submitted') die("You have already submitted this $term_lower.");
    if ($attempt['status'] === 'timed_out') die("$term time has expired for this attempt.");
    $attempt_id = $attempt['id'];
}

// 3. Exam Content Construction
$order_sql = "ORDER BY id ASC";
if ($exam['order_type'] === 'random') $order_sql = "ORDER BY RAND()";
elseif ($exam['order_type'] === 'desc') $order_sql = "ORDER BY id DESC";

$limit_sql = "";
$params = [$exam['id']];
if ($exam['total_questions'] > 0) {
    $limit_sql = " LIMIT " . (int)$exam['total_questions'];
}

$stmt = $pdo->prepare("SELECT * FROM cbt_questions WHERE exam_id=? $order_sql $limit_sql");
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Guard: If the exam has no questions yet
if (empty($questions)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $term; ?> Not Ready | EduRemarks</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; }
            .info-card { background: white; border-radius: 24px; padding: 50px; text-align: center; max-width: 500px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
            .info-icon { width: 90px; height: 90px; border-radius: 50%; background: #FFF3E0; color: #F57C00; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 25px; }
        </style>
    </head>
    <body>
        <div class="info-card">
            <div class="info-icon"><i class="fas fa-clipboard-question"></i></div>
            <h3 class="fw-bold mb-3"><?php echo $term; ?> Not Ready Yet</h3>
            <p class="text-muted mb-4">Your teacher has not added any questions to <strong><?php echo htmlspecialchars($exam['title']); ?></strong> yet. Please check back later or contact your <?php echo $term_lower; ?> coordinator.</p>
            <a href="cbt.php?token=<?php echo urlencode($token); ?>" class="btn btn-primary rounded-pill px-4">Go Back</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 4. Fetch Existing Answers for auto-resume
$stmt = $pdo->prepare("SELECT question_id, answer_text FROM cbt_student_answers WHERE attempt_id=?");
$stmt->execute([$attempt_id]);
$saved_answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 5. Calculate remaining time
$timeStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(NOW()) as now, UNIX_TIMESTAMP(?) as start");
$timeStmt->execute([$attempt['start_time']]);
$ts = $timeStmt->fetch();

$exam_duration_seconds = ($exam['duration_mins'] * 60) + (($attempt['time_extension_mins'] ?? 0) * 60);
$end_time_ts = $ts['start'] + $exam_duration_seconds;
$remaining_seconds = $end_time_ts - $ts['now'];

if ($remaining_seconds <= 0) {
    $pdo->prepare("UPDATE cbt_student_attempts SET status='timed_out', end_time=NOW() WHERE id=?")->execute([$attempt_id]);
    die("$term time has expired.");
}

$total_q = count($questions);
$answered_q = count(array_filter($saved_answers));
$progress_pct = $total_q > 0 ? round(($answered_q / $total_q) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($exam['title']); ?> | EduRemarks CBT</title>
    <?php 
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : '../' . $sidebar_logo_raw;
    ?>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1F3C88;
            --primary-light: #EEF2FB;
            --accent: #F4B400;
            --success: #2E7D32;
            --danger: #C62828;
            --surface: #ffffff;
            --bg: #f0f2f5;
            --text: #1a1a2e;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 16px;
        }
        * { box-sizing: border-box; }
        body { 
            background: var(--bg); height: 100vh; overflow: hidden; 
            display: flex; flex-direction: column; font-family: 'Inter', sans-serif;
            color: var(--text); -webkit-user-select: none; user-select: none;
        }

        /* ─── Exam Header ─── */
        .exam-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 10px 24px;
            flex-shrink: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .brand-logo { height: 32px; border-radius: 6px; }
        .exam-title { font-size: 0.95rem; font-weight: 700; color: var(--text); }
        .student-info { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; }

        /* ─── Timer ─── */
        .timer-box {
            padding: 8px 18px; border-radius: 12px;
            font-weight: 800; font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 1.15rem; letter-spacing: 2px;
            transition: all 0.5s ease;
        }
        .timer-safe { background: #E8F5E9; color: #2E7D32; }
        .timer-warning { background: #FFF3E0; color: #E65100; animation: pulse-timer 1s infinite; }
        .timer-danger { background: #FFEBEE; color: #C62828; animation: pulse-timer 0.5s infinite; }
        @keyframes pulse-timer {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.03); }
        }

        /* ─── Progress Bar ─── */
        .progress-strip {
            height: 4px; background: var(--border); flex-shrink: 0;
        }
        .progress-strip .bar {
            height: 100%; background: linear-gradient(90deg, var(--primary), #4F8AFF);
            transition: width 0.5s ease; border-radius: 0 4px 4px 0;
        }

        /* ─── Layout ─── */
        .exam-container { flex-grow: 1; display: flex; overflow: hidden; }

        /* ─── Navigation Sidebar ─── */
        .nav-sidebar { 
            width: 280px; background: var(--surface); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; flex-shrink: 0;
        }
        .nav-header { padding: 16px 20px; border-bottom: 1px solid var(--border); }
        .nav-bridge { flex-grow: 1; overflow-y: auto; padding: 16px 20px; }
        .q-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .q-nav-btn {
            width: 100%; aspect-ratio: 1; border: 2px solid var(--border); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.82rem; cursor: pointer;
            transition: all 0.2s ease; position: relative; background: var(--surface);
        }
        .q-nav-btn:hover { border-color: var(--primary); color: var(--primary); transform: scale(1.05); }
        .q-nav-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(31,60,136,0.3); }
        .q-nav-btn.answered { border-color: var(--success); background: #f0fdf4; color: var(--success); }
        .q-nav-btn.answered::after { 
            content: '✓'; position: absolute; top: -6px; right: -6px;
            background: var(--success); color: white; width: 16px; height: 16px;
            border-radius: 50%; font-size: 0.6rem; display: flex; align-items: center; justify-content: center;
        }
        .q-nav-btn.flagged { border-color: #F57C00; background: #FFF3E0; }
        .q-nav-btn.flagged::before {
            content: '⚑'; position: absolute; top: -6px; left: -4px;
            color: #F57C00; font-size: 0.7rem;
        }

        /* ─── Sidebar Footer ─── */
        .nav-footer { padding: 16px 20px; border-top: 1px solid var(--border); }
        .nav-legend { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.68rem; color: var(--text-muted); font-weight: 500; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; }

        /* ─── Main Question Area ─── */
        .q-area { flex-grow: 1; overflow-y: auto; padding: 32px; display: flex; flex-direction: column; }
        .q-content-card { 
            max-width: 780px; margin: 0 auto; background: var(--surface);
            border-radius: var(--radius); padding: 40px; width: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.03);
            border: 1px solid var(--border);
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .q-badge { 
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            padding: 6px 14px; border-radius: 8px; margin-bottom: 20px;
        }
        .q-badge-primary { background: var(--primary-light); color: var(--primary); }
        .q-badge-type { background: #f1f5f9; color: var(--text-muted); }

        .q-text { font-size: 1.1rem; font-weight: 600; line-height: 1.7; margin-bottom: 28px; color: var(--text); }

        /* ─── Options ─── */
        .option-item {
            border: 2px solid var(--border); border-radius: 14px;
            padding: 16px 20px; margin-bottom: 12px; cursor: pointer;
            transition: all 0.25s ease; display: flex; align-items: center; gap: 14px;
        }
        .option-item:hover { border-color: #a8bef0; background: #fbfcff; transform: translateX(4px); }
        .option-item.selected { border-color: var(--primary); background: var(--primary-light); box-shadow: 0 0 0 3px rgba(31,60,136,0.1); }
        .option-item input { display: none; }
        .option-letter {
            width: 36px; height: 36px; border-radius: 10px; background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.85rem; color: var(--text-muted); flex-shrink: 0;
            transition: all 0.25s ease;
        }
        .option-item.selected .option-letter { background: var(--primary); color: white; }
        .option-text { font-size: 0.95rem; font-weight: 500; }

        /* ─── Navigation Controls ─── */
        .q-controls {
            max-width: 780px; margin: 20px auto 0; width: 100%;
            display: flex; justify-content: space-between; align-items: center;
        }
        .ctrl-btn {
            border: 2px solid var(--border); background: var(--surface);
            padding: 10px 24px; border-radius: 12px; font-weight: 600; font-size: 0.88rem;
            cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 8px;
            color: var(--text);
        }
        .ctrl-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .ctrl-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .ctrl-btn.flag-btn { border-color: #FFF3E0; color: #F57C00; background: #FFF8F0; }
        .ctrl-btn.flag-btn.flagged { border-color: #F57C00; background: #F57C00; color: white; }

        /* ─── Auto-save indicator ─── */
        .save-indicator {
            position: fixed; bottom: 24px; right: 24px; padding: 10px 18px;
            border-radius: 12px; font-size: 0.78rem; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.3s ease; opacity: 0; transform: translateY(10px);
            z-index: 999; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .save-indicator.show { opacity: 1; transform: translateY(0); }
        .save-indicator.saving { background: #EEF2FB; color: var(--primary); }
        .save-indicator.saved { background: #E8F5E9; color: var(--success); }
        .save-indicator.error { background: #FFEBEE; color: var(--danger); }

        /* ─── Tab Warning Overlay ─── */
        .tab-warning {
            position: fixed; inset: 0; background: rgba(0,0,0,0.92);
            display: none; align-items: center; justify-content: center;
            z-index: 9999; flex-direction: column; padding: 40px;
        }
        .tab-warning.show { display: flex; }
        .tab-warning-card {
            background: white; border-radius: 24px; padding: 50px 40px;
            text-align: center; max-width: 460px; width: 100%;
        }

        /* ─── Mobile ─── */
        @media (max-width: 768px) {
            .nav-sidebar { 
                position: fixed; right: -300px; height: 100%;
                z-index: 200; transition: 0.3s ease; box-shadow: -4px 0 24px rgba(0,0,0,0.1);
            }
            .nav-sidebar.active { right: 0; }
            .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 199; display: none; }
            .sidebar-overlay.show { display: block; }
            .q-area { padding: 16px; }
            .q-content-card { padding: 24px; }
            .q-controls { padding: 0 4px; }
            .ctrl-btn { padding: 8px 16px; font-size: 0.82rem; }
        }
    </style>
</head>
<body>
    <!-- Auto-save indicator -->
    <div class="save-indicator" id="saveIndicator">
        <i class="fas fa-circle-notch fa-spin" id="saveIcon"></i>
        <span id="saveText">Saving...</span>
    </div>

    <!-- Tab Switch Warning -->
    <div class="tab-warning" id="tabWarning">
        <div class="tab-warning-card">
            <div style="width:70px;height:70px;border-radius:50%;background:#FFEBEE;color:#C62828;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 20px;">
                <i class="fas fa-eye-slash"></i>
            </div>
            <h4 class="fw-bold mb-2">Focus Required</h4>
            <p class="text-muted small mb-1">You have navigated away from the exam window.</p>
            <p class="text-muted small mb-3">Warning count: <span class="fw-bold text-danger" id="tabSwitchCount">0</span> / 3</p>
            <p class="small text-danger fw-bold mb-4">Exceeding the limit may result in automatic submission.</p>
            <button class="btn btn-primary rounded-pill px-5 fw-bold" onclick="dismissTabWarning()">Return to Exam</button>
        </div>
    </div>

    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>

    <!-- Header -->
    <header class="exam-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <img src="<?php echo $school_logo_url; ?>" class="brand-logo d-none d-md-block" alt="Logo">
                <div class="vr d-none d-md-block" style="height:30px;"></div>
                <div>
                    <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                    <div class="student-info">
                        <i class="fas fa-user-graduate me-1"></i><?php echo htmlspecialchars($student['full_name']); ?> 
                        <span class="ms-1 opacity-75">(<?php echo $student['admission_no']; ?>)</span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">Time Remaining</div>
                    <div id="timer" class="timer-box timer-safe">00:00:00</div>
                </div>
                <button class="btn btn-light border d-md-none" style="border-radius:10px;" onclick="toggleSidebar()">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Progress Bar -->
    <div class="progress-strip">
        <div class="bar" id="progressBar" style="width: <?php echo $progress_pct; ?>%;"></div>
    </div>

    <div class="exam-container">
        <!-- Sidebar Navigation -->
        <aside class="nav-sidebar" id="navSidebar">
            <div class="nav-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0" style="font-size:0.9rem;">Question Map</h6>
                    <span class="badge bg-primary rounded-pill" style="font-size:0.7rem;">
                        <span id="answeredCount"><?php echo $answered_q; ?></span> / <?php echo $total_q; ?>
                    </span>
                </div>
            </div>
            <div class="nav-bridge">
                <div class="q-grid">
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="q-nav-btn <?php echo isset($saved_answers[$q['id']]) ? 'answered' : ''; ?>" 
                             id="nav-<?php echo $i; ?>" 
                             onclick="showQuestion(<?php echo $i; ?>)">
                            <?php echo $i + 1; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="nav-footer">
                <div class="nav-legend">
                    <div class="legend-item"><div class="legend-dot" style="background:var(--primary);"></div> Current</div>
                    <div class="legend-item"><div class="legend-dot" style="background:var(--success);"></div> Answered</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#F57C00;"></div> Flagged</div>
                    <div class="legend-item"><div class="legend-dot" style="background:var(--border);"></div> Not visited</div>
                </div>
                <button class="btn btn-warning w-100 fw-bold py-2 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#submitModal" style="font-size:0.88rem;">
                    <i class="fas fa-paper-plane me-2"></i>SUBMIT <?php echo strtoupper($term); ?>
                </button>
            </div>
        </aside>

        <!-- Main Question Area -->
        <main class="q-area">
            <div class="q-content-card" id="questionCard">
                <!-- Content injected via JS -->
            </div>

            <div class="q-controls" id="qControls">
                <button class="ctrl-btn" id="prevBtn" onclick="prevQuestion()" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button class="ctrl-btn flag-btn" id="flagBtn" onclick="toggleFlag()" title="Flag for review">
                    <i class="fas fa-flag"></i> <span class="d-none d-sm-inline">Flag</span>
                </button>
                <div class="text-muted fw-bold" style="font-size:0.78rem;letter-spacing:0.5px;">
                    <span id="currentQNum">1</span> / <?php echo $total_q; ?>
                </div>
                <button class="ctrl-btn" id="nextBtn" onclick="nextQuestion()">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </main>
    </div>

    <!-- Submit Confirmation Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
                <div class="modal-body p-5 text-center">
                    <div style="width:70px;height:70px;border-radius:50%;background:#FFF3E0;color:#F4B400;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 20px;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Review & Submit</h4>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <div class="p-3 rounded-3" style="background:#E8F5E9;">
                                <div style="font-size:1.5rem;font-weight:800;color:#2E7D32;" id="modalAnswered">0</div>
                                <div style="font-size:0.7rem;font-weight:600;color:#2E7D32;">Answered</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded-3" style="background:#FFEBEE;">
                                <div style="font-size:1.5rem;font-weight:800;color:#C62828;" id="modalUnanswered"><?php echo $total_q; ?></div>
                                <div style="font-size:0.7rem;font-weight:600;color:#C62828;">Unanswered</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 rounded-3" style="background:#FFF3E0;">
                                <div style="font-size:1.5rem;font-weight:800;color:#F57C00;" id="modalFlagged">0</div>
                                <div style="font-size:0.7rem;font-weight:600;color:#F57C00;">Flagged</div>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small mb-4">Once submitted, you <strong class="text-danger">cannot modify</strong> your answers. Make sure you've reviewed all questions.</p>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg rounded-pill fw-bold py-3 shadow-sm" onclick="submitExam()" style="font-size:0.95rem;">
                            CONFIRM SUBMISSION <i class="fas fa-check-double ms-2"></i>
                        </button>
                        <button class="btn btn-light rounded-pill text-muted fw-bold" data-bs-dismiss="modal" style="font-size:0.88rem;">
                            Continue <?php echo $term; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ─── Core State ───
        const questions = <?php echo json_encode($questions); ?>;
        const savedAnswers = <?php echo json_encode($saved_answers); ?>;
        const attemptId = <?php echo $attempt_id; ?>;
        const examId = <?php echo $exam['id']; ?>;
        const totalQ = <?php echo $total_q; ?>;
        const examDuration = <?php echo $exam_duration_seconds; ?>;
        let currentIdx = 0;
        let remainingSeconds = <?php echo $remaining_seconds; ?>;
        let flaggedQuestions = new Set();
        let tabSwitchCount = 0;

        // ─── Timer ───
        function updateTimer() {
            if (remainingSeconds <= 0) { submitExam(true); return; }
            const hours = Math.floor(remainingSeconds / 3600);
            const mins = Math.floor((remainingSeconds % 3600) / 60);
            const secs = remainingSeconds % 60;
            const el = document.getElementById('timer');
            el.textContent = `${String(hours).padStart(2,'0')}:${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
            
            // Color transitions
            const pct = remainingSeconds / examDuration;
            el.className = 'timer-box ' + (pct > 0.25 ? 'timer-safe' : pct > 0.08 ? 'timer-warning' : 'timer-danger');
            
            remainingSeconds--;
        }
        setInterval(updateTimer, 1000);
        updateTimer();

        // ─── Show Question ───
        function showQuestion(idx) {
            if (!questions.length) return;
            currentIdx = idx;
            const q = questions[idx];
            if (!q) return;

            const isFlagged = flaggedQuestions.has(idx);
            const qTypeLabel = q.type === 'objective' ? 'Multiple Choice' : q.type === 'tf' ? 'True / False' : 'Essay';
            
            const html = `
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <span class="q-badge q-badge-primary"><i class="fas fa-question-circle me-1"></i>Question ${idx + 1}</span>
                    <span class="q-badge q-badge-type"><i class="fas fa-tag me-1"></i>${qTypeLabel}</span>
                    ${isFlagged ? '<span class="q-badge" style="background:#FFF3E0;color:#F57C00;"><i class="fas fa-flag me-1"></i>Flagged</span>' : ''}
                </div>
                <div class="q-text">${q.question_text.replace(/\n/g, '<br>')}</div>
                ${q.image_path ? `<div class="mb-4"><img src="../${q.image_path}" class="img-fluid rounded-3 border shadow-sm" style="max-height:300px;"></div>` : ''}
                <div class="options-container">
                    ${renderOptions(q)}
                </div>
            `;
            
            const card = document.getElementById('questionCard');
            card.style.animation = 'none';
            card.offsetHeight; // trigger reflow
            card.style.animation = 'fadeIn 0.3s ease';
            card.innerHTML = html;
            
            document.getElementById('currentQNum').textContent = idx + 1;

            // Highlight nav
            document.querySelectorAll('.q-nav-btn').forEach(b => b.classList.remove('active'));
            const navBtn = document.getElementById(`nav-${idx}`);
            if (navBtn) navBtn.classList.add('active');

            // Button states
            document.getElementById('prevBtn').disabled = (idx === 0);
            const nextBtn = document.getElementById('nextBtn');
            if (idx === questions.length - 1) {
                nextBtn.innerHTML = '<i class="fas fa-flag-checkered me-1"></i> Finish';
            } else {
                nextBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
            }

            // Flag button state
            const flagBtn = document.getElementById('flagBtn');
            if (isFlagged) {
                flagBtn.classList.add('flagged');
                flagBtn.innerHTML = '<i class="fas fa-flag"></i> <span class="d-none d-sm-inline">Unflag</span>';
            } else {
                flagBtn.classList.remove('flagged');
                flagBtn.innerHTML = '<i class="fas fa-flag"></i> <span class="d-none d-sm-inline">Flag</span>';
            }

            updateStats();
        }

        // ─── Render Options ───
        function renderOptions(q) {
            const saved = savedAnswers[q.id] || null;
            if (q.type === 'objective') {
                const opts = JSON.parse(q.options);
                return Object.entries(opts).map(([key, val]) => `
                    <div class="option-item ${saved === key ? 'selected' : ''}" onclick="saveAnswer('${q.id}', '${key}', this)">
                        <input type="radio" name="option" value="${key}" ${saved === key ? 'checked' : ''}>
                        <span class="option-letter">${key}</span>
                        <span class="option-text">${val}</span>
                    </div>
                `).join('');
            } else if (q.type === 'tf') {
                return ['True', 'False'].map(val => `
                    <div class="option-item ${saved === val ? 'selected' : ''}" onclick="saveAnswer('${q.id}', '${val}', this)">
                        <span class="option-letter">${val === 'True' ? 'T' : 'F'}</span>
                        <span class="option-text">${val}</span>
                    </div>
                `).join('');
            } else if (q.type === 'essay') {
                return `
                    <textarea class="form-control" rows="8" placeholder="Type your answer here..." 
                              onblur="saveAnswer('${q.id}', this.value)" 
                              style="border-radius:14px;border:2px solid var(--border);font-size:0.95rem;padding:16px;font-family:'Inter',sans-serif;">${saved || ''}</textarea>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <i class="fas fa-circle-info text-muted" style="font-size:0.7rem;"></i>
                        <span class="small text-muted">Auto-saved when you navigate away</span>
                    </div>
                `;
            }
            return '';
        }

        // ─── Save Answer ───
        function saveAnswer(qId, value, el) {
            if (el) {
                const container = el.parentElement;
                container.querySelectorAll('.option-item').forEach(i => i.classList.remove('selected'));
                el.classList.add('selected');
            }
            
            savedAnswers[qId] = value;
            const navBtn = document.getElementById(`nav-${currentIdx}`);
            if (navBtn) navBtn.classList.add('answered');
            updateStats();
            showSaveIndicator('saving');

            fetch('../ajax/cbt_submit_answer.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `attempt_id=${attemptId}&question_id=${qId}&answer=${encodeURIComponent(value)}`
            })
            .then(r => r.json())
            .then(d => {
                showSaveIndicator(d.success ? 'saved' : 'error');
            })
            .catch(() => showSaveIndicator('error'));
        }

        // ─── Save Indicator ───
        function showSaveIndicator(state) {
            const el = document.getElementById('saveIndicator');
            const icon = document.getElementById('saveIcon');
            const text = document.getElementById('saveText');
            
            el.className = 'save-indicator show ' + state;
            if (state === 'saving') {
                icon.className = 'fas fa-circle-notch fa-spin';
                text.textContent = 'Saving...';
            } else if (state === 'saved') {
                icon.className = 'fas fa-check-circle';
                text.textContent = 'Saved';
            } else {
                icon.className = 'fas fa-exclamation-circle';
                text.textContent = 'Save failed';
            }
            
            setTimeout(() => el.classList.remove('show'), 2000);
        }

        // ─── Stats ───
        function updateStats() {
            const answered = Object.keys(savedAnswers).filter(k => savedAnswers[k]).length;
            const unanswered = totalQ - answered;
            const flagged = flaggedQuestions.size;
            
            document.getElementById('answeredCount').textContent = answered;
            const progressBar = document.getElementById('progressBar');
            if (progressBar) progressBar.style.width = ((answered / totalQ) * 100) + '%';

            // Modal stats
            const ma = document.getElementById('modalAnswered');
            const mu = document.getElementById('modalUnanswered');
            const mf = document.getElementById('modalFlagged');
            if (ma) ma.textContent = answered;
            if (mu) mu.textContent = unanswered;
            if (mf) mf.textContent = flagged;
        }

        // ─── Navigation ───
        function nextQuestion() {
            if (currentIdx < questions.length - 1) showQuestion(currentIdx + 1);
            else toggleSidebar(true);
        }
        function prevQuestion() {
            if (currentIdx > 0) showQuestion(currentIdx - 1);
        }

        // ─── Flag for Review ───
        function toggleFlag() {
            const navBtn = document.getElementById(`nav-${currentIdx}`);
            if (flaggedQuestions.has(currentIdx)) {
                flaggedQuestions.delete(currentIdx);
                if (navBtn) navBtn.classList.remove('flagged');
            } else {
                flaggedQuestions.add(currentIdx);
                if (navBtn) navBtn.classList.add('flagged');
            }
            showQuestion(currentIdx); // re-render
        }

        // ─── Keyboard Navigation ───
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') return;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); nextQuestion(); }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); prevQuestion(); }
            if (e.key === 'f' || e.key === 'F') { e.preventDefault(); toggleFlag(); }
            if (e.key >= '1' && e.key <= '9') {
                const idx = parseInt(e.key) - 1;
                if (idx < questions.length) { e.preventDefault(); showQuestion(idx); }
            }
        });

        // ─── Mobile Sidebar Toggle ───
        function toggleSidebar(forceOpen) {
            const sidebar = document.getElementById('navSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const isActive = sidebar.classList.contains('active');
            
            if (forceOpen === true || (!isActive && forceOpen !== false)) {
                sidebar.classList.add('active');
                overlay.classList.add('show');
            } else {
                sidebar.classList.remove('active');
                overlay.classList.remove('show');
            }
        }

        // ─── Anti-Cheat: Tab Switch Detection ───
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                tabSwitchCount++;
                document.getElementById('tabSwitchCount').textContent = tabSwitchCount;
                document.getElementById('tabWarning').classList.add('show');
                
                if (tabSwitchCount >= 3) {
                    submitExam(false); // forceful submit after 3 tab switches
                }
            }
        });
        function dismissTabWarning() {
            document.getElementById('tabWarning').classList.remove('show');
        }

        // ─── Anti-Cheat: Disable right-click ───
        document.addEventListener('contextmenu', e => e.preventDefault());

        // ─── Submit Exam ───
        function submitExam(timedOut = false) {
            document.body.innerHTML = `
                <div style="height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;background:linear-gradient(135deg,#1F3C88,#2D6CDF);font-family:'Inter',sans-serif;">
                    <div style="background:white;border-radius:24px;padding:60px 50px;text-align:center;max-width:460px;width:90%;box-shadow:0 25px 50px rgba(0,0,0,0.3);">
                        <div class="spinner-border text-primary mb-4" style="width:48px;height:48px;border-width:4px;"></div>
                        <h4 class="fw-bold mb-2">Finalizing Your <?php echo $term; ?></h4>
                        <p class="text-muted small">Scoring and recording your answers.<br>Please do not close this window.</p>
                    </div>
                </div>
            `;

            fetch('../ajax/cbt_finish.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `attempt_id=${attemptId}&timed_out=${timedOut ? 1 : 0}`
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    window.location.href = `cbt_finished.php?exam=${examId}&token=${encodeURIComponent('<?php echo $token; ?>')}`;
                } else {
                    alert("Submission failed. Please contact your coordinator.");
                }
            });
        }

        // ─── Initialize ───
        showQuestion(0);
    </script>
</body>
</html>
