<?php
// student/assessments.php — CBT Tests & Manual Assessments
require_once 'auth.php';

// Get student's current class
$cls_stmt = $pdo->prepare("SELECT c.id, c.name FROM classes c JOIN student_classes sc ON sc.class_id = c.id WHERE sc.student_id = ? AND sc.school_id = ? LIMIT 1");
$cls_stmt->execute([$student_id, $school_id]);
$current_class = $cls_stmt->fetch();
$class_id = $current_class['id'] ?? 0;

// ── CBT Exam History ──
$cbt_results = [];
if ($class_id) {
    $cbt_stmt = $pdo->prepare("
            SELECT cr.*, e.title, e.assessment_type, e.duration_mins, (e.total_questions * e.marks_per_question) as total_marks,
                   s.name as subject_name, c.name as class_name
            FROM cbt_student_attempts cr
            JOIN cbt_exams e ON e.id = cr.exam_id
            JOIN subjects s ON s.id = e.subject_id
            JOIN classes c ON c.id = e.class_id
            WHERE cr.student_id = ? AND cr.status IN ('submitted', 'timed_out')
            ORDER BY cr.end_time DESC
        ");
        $cbt_stmt->execute([$student_id]);
        $cbt_results = $cbt_stmt->fetchAll();
    }

// ── Active/Upcoming CBT Exams ──
$active_exams = [];
if ($class_id) {
    $active_stmt = $pdo->prepare("
        SELECT e.*, s.name as subject_name
        FROM cbt_exams e
        JOIN subjects s ON s.id = e.subject_id
        WHERE e.class_id = ? AND e.school_id = ? AND e.status = 'active'
            AND e.end_time > NOW()
        ORDER BY e.start_time ASC
    ");
    $active_stmt->execute([$class_id, $school_id]);
    $active_exams = $active_stmt->fetchAll();
}

// ── Uploaded Manual Assessments ──
// These are CA scores and exam scores already in student_results
$sch_stmt = $pdo->prepare("SELECT current_session_id, current_term_id FROM schools WHERE id = ?");
$sch_stmt->execute([$school_id]);
$sch_active = $sch_stmt->fetch();
$current_session_id = $sch_active['current_session_id'] ?? 0;
$current_term_id = $sch_active['current_term_id'] ?? 0;

$manual_results = [];
if ($current_session_id && $current_term_id) {
    $man_stmt = $pdo->prepare("
        SELECT r.ca1, r.ca2, r.exam, r.total, r.grade, s.name as subject_name, s.code as subject_code
        FROM student_results r
        JOIN subjects s ON s.id = r.subject_id
        WHERE r.student_id = ? AND r.session_id = ? AND r.term_id = ?
        ORDER BY s.name
    ");
    $man_stmt->execute([$student_id, $current_session_id, $current_term_id]);
    $manual_results = $man_stmt->fetchAll();
}

// Term/session names
$term_name = ''; $session_name = '';
if ($current_term_id) { $t = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?"); $t->execute([$current_term_id]); $term_name = $t->fetchColumn() ?: ''; }
if ($current_session_id) { $s = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?"); $s->execute([$current_session_id]); $session_name = $s->fetchColumn() ?: ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessments | <?php echo htmlspecialchars($student['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="includes/student.css?v=<?php echo time(); ?>">
    <style>
        .tab-pills { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .tab-pill {
            padding: 10px 22px; border-radius: 50px; font-weight: 700; font-size: 0.8rem;
            cursor: pointer; transition: 0.3s; border: 1.5px solid var(--stu-border);
            background: #fff; color: #64748b;
        }
        .tab-pill:hover { border-color: var(--stu-accent); color: var(--stu-accent); background: var(--stu-accent-light); }
        .tab-pill.active { background: var(--stu-primary); color: #fff; border-color: var(--stu-primary); box-shadow: 0 5px 15px rgba(26,77,161,0.2); }
        .exam-card {
            background: #fff; border: 1px solid var(--stu-border); border-radius: 24px;
            padding: 24px; margin-bottom: 20px; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
        }
        .exam-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(31, 60, 136, 0.08); }
        .type-badge {
            display: inline-flex; align-items: center; gap: 4px; font-size: 0.65rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px;
            padding: 4px 12px; border-radius: 20px;
        }
        .type-test { background: #eff6ff; color: #2563eb; }
        .type-exam { background: #fef3c7; color: #d97706; }
        .score-ring {
            width: 54px; height: 54px; border-radius: 14px; display: flex;
            align-items: center; justify-content: center; font-weight: 800;
            font-size: 0.9rem; flex-shrink: 0;
        }
        .score-ring.high { background: #ecfdf5; color: #059669; }
        .score-ring.mid { background: #fffbeb; color: #d97706; }
        .score-ring.low { background: #fef2f2; color: #dc2626; }
        .live-exam-card {
            background: linear-gradient(135deg, #1e3c88 0%, #2d6cdf 100%);
            color: #fff; border-radius: 24px; padding: 24px; margin-bottom: 20px;
            box-shadow: 0 15px 35px rgba(31, 60, 136, 0.2); position: relative; overflow: hidden;
        }
        .live-exam-card::after {
            content: ''; position: absolute; right: -20px; top: -20px; width: 100px; height: 100px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }
        .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #10b981;
            display: inline-block; animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
    </style>
</head>
<body>
<div class="stu-layout">
    <?php include 'includes/nav.php'; ?>

    <main class="stu-main">
        <div class="mb-4">
            <h3 class="fw-900 mb-1" style="letter-spacing:-0.5px;">Assessments</h3>
            <p class="text-muted small fw-600">CBT exams, tests, and manual assessment scores</p>
        </div>

        <!-- Tabs -->
        <div class="tab-pills">
            <div class="tab-pill active" onclick="showTab('cbt')"><i class="fas fa-laptop-code me-1"></i> CBT Tests</div>
            <div class="tab-pill" onclick="showTab('manual')"><i class="fas fa-pen-ruler me-1"></i> Manual Scores</div>
        </div>

        <!-- ─── CBT TAB ─── -->
        <div id="tab-cbt">
            <?php if (!empty($active_exams)): ?>
                <div class="section-head text-success"><span class="pulse-dot me-2"></span> Active Exams</div>
                <?php foreach ($active_exams as $ex): 
                    // Check if student already took it
                    $taken = $pdo->prepare("SELECT COUNT(*) FROM cbt_student_attempts WHERE exam_id = ? AND student_id = ? AND status IN ('submitted', 'timed_out')");
                    $taken->execute([$ex['id'], $student_id]);
                    $already_taken = $taken->fetchColumn() > 0;
                ?>
                <div class="live-exam-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="fw-800" style="font-size:0.95rem;"><?php echo htmlspecialchars($ex['title']); ?></div>
                            <div class="opacity-75 small mt-1">
                                <i class="fas fa-book me-1"></i><?php echo get_label('Subject'); ?>: <?php echo htmlspecialchars($ex['subject_name']); ?> •
                                <i class="fas fa-clock me-1 ms-1"></i><?php echo $ex['duration_mins']; ?> mins •
                                Ends: <?php echo date('M d, h:i A', strtotime($ex['end_time'])); ?>
                            </div>
                        </div>
                        <?php if ($already_taken): ?>
                            <span class="badge bg-white bg-opacity-20 rounded-pill px-3 py-2 fw-700" style="font-size:0.7rem;">
                                <i class="fas fa-check-circle me-1"></i> Completed
                            </span>
                        <?php else: ?>
                            <div class="d-flex gap-2">
                                <a href="cbt.php?token=<?php echo $ex['token']; ?>" class="btn btn-light btn-sm rounded-pill fw-800 px-4 shadow-sm">
                                    <i class="fas fa-play me-1"></i> Take Now
                                </a>
                                <a href="exam_schedule.php?exam_id=<?php echo $ex['id']; ?>" target="_blank" class="btn btn-outline-light btn-sm rounded-pill fw-800 px-3 shadow-sm border-white border-opacity-25">
                                    <i class="fas fa-print"></i> Slip
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="mb-4"></div>
            <?php endif; ?>

            <?php if (!empty($cbt_results)): ?>
                <div class="section-head"><i class="fas fa-history"></i> Exam History</div>
                <?php foreach ($cbt_results as $cr):
                    $pct = $cr['total_marks'] > 0 ? round(($cr['total_score'] / $cr['total_marks']) * 100) : 0;
                    $ring_class = $pct >= 70 ? 'high' : ($pct >= 50 ? 'mid' : 'low');
                    $type_class = $cr['assessment_type'] === 'test' ? 'type-test' : 'type-exam';
                ?>
                <div class="exam-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="score-ring <?php echo $ring_class; ?>"><?php echo $pct; ?>%</div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="fw-800" style="font-size:0.88rem;"><?php echo htmlspecialchars($cr['title']); ?></div>
                            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                <span class="text-muted" style="font-size:0.68rem; font-weight:600;">
                                    <i class="fas fa-book me-1 opacity-50"></i><?php echo get_label('Subject'); ?>: <?php echo htmlspecialchars($cr['subject_name']); ?>
                                </span>
                                <span class="type-badge <?php echo $type_class; ?>"><?php echo ucfirst($cr['assessment_type']); ?></span>
                                <span class="text-muted" style="font-size:0.65rem;"><?php echo date('M d, Y', strtotime($cr['end_time'])); ?></span>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 d-none d-md-block">
                            <div class="fw-900 text-dark"><?php echo floatval($cr['total_score']); ?>/<?php echo $cr['total_marks']; ?></div>
                            <div class="text-muted" style="font-size:0.65rem;">Raw Score</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="stu-card">
                    <div class="stu-empty py-4">
                        <div class="stu-empty-icon"><i class="fas fa-laptop-code"></i></div>
                        <h6 class="fw-bold text-muted">No CBT Records</h6>
                        <p class="text-muted small">Your CBT exam results will appear here after you take tests.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ─── MANUAL SCORES TAB ─── -->
        <div id="tab-manual" style="display:none;">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-700" style="font-size:0.7rem;">
                    <i class="fas fa-calendar me-1"></i><?php echo htmlspecialchars(get_label($term_name)); ?> — <?php echo htmlspecialchars($session_name); ?>
                </span>
            </div>

            <?php if (!empty($manual_results)): ?>
                <div class="stu-card p-0" style="overflow:hidden;">
                    <div class="table-responsive">
                        <table class="score-table w-100 mb-0" style="border-spacing:0;">
                            <thead>
                                <tr style="background:#f8fafc;"><th class="ps-3 py-3"><?php echo get_label('Subject'); ?></th><th class="py-3">CA1</th><th class="py-3">CA2</th><th class="py-3">Exam</th><th class="py-3">Total</th><th class="py-3">Grade</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach($manual_results as $res):
                                $gc = 'grade-c';
                                if($res['total'] >= 70) $gc = 'grade-a';
                                elseif($res['total'] >= 60) $gc = 'grade-b';
                                elseif($res['total'] < 40) $gc = 'grade-f';
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td class="ps-3 py-3">
                                        <div class="fw-700" style="font-size:0.82rem;"><?php echo htmlspecialchars($res['subject_name']); ?></div>
                                        <div class="text-muted" style="font-size:0.6rem;"><?php echo htmlspecialchars($res['subject_code']); ?></div>
                                    </td>
                                    <td class="fw-600"><?php echo $res['ca1']; ?></td>
                                    <td class="fw-600"><?php echo $res['ca2']; ?></td>
                                    <td class="fw-600"><?php echo $res['exam']; ?></td>
                                    <td><span class="fw-900"><?php echo $res['total']; ?></span></td>
                                    <td><div class="grade-pill <?php echo $gc; ?>"><?php echo $res['grade']; ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="stu-card">
                    <div class="stu-empty py-4">
                        <div class="stu-empty-icon"><i class="fas fa-pen-ruler"></i></div>
                        <h6 class="fw-bold text-muted">No Manual Scores</h6>
                        <p class="text-muted small">CA and exam scores will appear here when published by your teacher.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function showTab(tab) {
    document.getElementById('tab-cbt').style.display = tab === 'cbt' ? '' : 'none';
    document.getElementById('tab-manual').style.display = tab === 'manual' ? '' : 'none';
    document.querySelectorAll('.tab-pill').forEach(p => p.classList.remove('active'));
    event.target.closest('.tab-pill').classList.add('active');
}
</script>
</body>
</html>
