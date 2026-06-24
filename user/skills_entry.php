<?php
// user/skills_entry.php - Professional Skills Entry Hub
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$student_id = intval($_GET['student_id'] ?? 0);
$class_id = intval($_GET['class_id'] ?? 0);

if (!$student_id || !$class_id) {
    header('Location: report_management.php?class_id=' . $class_id);
    exit();
}

// Fetch Student details
$stu_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
$stu_stmt->execute([$student_id, $school_id]);
$student = $stu_stmt->fetch();

if (!$student) {
    header('Location: report_management.php?class_id=' . $class_id);
    exit();
}

// Fetch Class details
$cls_stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND school_id = ?");
$cls_stmt->execute([$class_id, $school_id]);
$class_name = $cls_stmt->fetchColumn();

// Fetch Session and Term Context
$current_session_id = intval($_GET['session_id'] ?? $active_school['current_session_id'] ?? 0);
$current_term_id = intval($_GET['term_id'] ?? 0);

if (!$current_term_id) {
    // Fallback to active term if not specified
    $stmt = $pdo->prepare("SELECT id FROM academic_terms WHERE session_id = ? AND school_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$current_session_id, $school_id]);
    $current_term_id = $stmt->fetchColumn() ?: 0;
}

// Fetch Names for UI
$session_name = "Session";
$term_name = "Term";
$sn_stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
$sn_stmt->execute([$current_session_id]);
$session_name = $sn_stmt->fetchColumn();

$tn_stmt = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
$tn_stmt->execute([$current_term_id]);
$term_name = $tn_stmt->fetchColumn();

// Fetch all students in class for pagination and stats
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
$current_index = 0;
$next_student_id = null;

foreach($all_students as $index => $s) {
    if($s['id'] == $student_id) {
        $current_index = $index + 1;
        if(isset($all_students[$index + 1])) {
            $next_student_id = $all_students[$index + 1]['id'];
        }
    }
}
$completion_percentage = ($total_students > 0) ? round(($current_index / $total_students) * 100) : 0;

// Fetch Orchestration Settings
$is_locked = false;
$lock_reason = "";
if ($role === 'staff') {
    $orch_stmt = $pdo->prepare("SELECT global_status FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
    $orch_stmt->execute([$school_id, $current_session_id, $current_term_id]);
    $global_status = $orch_stmt->fetchColumn();

    if ($global_status === 'closed') {
        $is_locked = true;
        $lock_reason = "Academic Audit Window is currently CLOSED globally.";
    }

    $sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id = ? AND school_id = ?");
    $sd_stmt->execute([$user_id, $school_id]);
    $staff_id = $sd_stmt->fetchColumn();

    if ($staff_id) {
        $win_stmt = $pdo->prepare("SELECT window_status FROM staff_entry_windows WHERE staff_id = ? AND session_id = ? AND term_id = ?");
        $win_stmt->execute([$staff_id, $current_session_id, $current_term_id]);
        if ($win_stmt->fetchColumn() === 'closed') {
            $is_locked = true;
            $lock_reason = "Your individual entry window is LOCKED.";
        }
    }
}

// Fetch saved traits
$saved_stmt = $pdo->prepare("SELECT trait_name, trait_type, rating FROM student_traits WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
$saved_stmt->execute([$student_id, $class_id, $current_session_id, $current_term_id]);
$saved_db = $saved_stmt->fetchAll();
$saved_traits = [];
foreach($saved_db as $r) {
    $saved_traits[strtoupper(trim($r['trait_name']))] = $r['rating'];
}

$affectiveTraits = ['PUNCTUALITY', 'ATTENDANCE', 'RELIABILITY', 'NEATNESS', 'POLITENESS', 'HONESTY', 'RELATIONSHIP WITH STUDENTS', 'SELF CONTROL', 'ATTENTIVENESS', 'PERSEVERANCE'];
$psychomotorTraits = ['HANDWRITING', 'GAMES', 'SPORTS', 'DRAWING & PAINTING', 'CRAFTS', 'MUSICAL SKILLS'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills Entry | <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body, html { height: 100%; }
        .sa-main-content { padding: 20px !important; background: #f8fafc; font-family: 'Inter', sans-serif; min-height: 100vh; }
        
        /* Top Header */
        .se-top-header { background: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .se-title h4 { margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .se-title p { margin: 0; font-size: 0.65rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-top: 4px; letter-spacing: 0.5px; }
        .btn-close-se { background: transparent; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.75rem; font-weight: 700; color: #475569; padding: 6px 14px; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; }
        .btn-close-se:hover { background: #f1f5f9; color: #0f172a; }

        /* Student Banner */
        .se-student-banner { background: #0f172a; border-radius: 12px; padding: 15px 25px; display: flex; align-items: center; justify-content: space-between; color: #fff; margin-bottom: 25px; position: relative; overflow: hidden; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1); }
        .se-student-banner::after { content: ''; position: absolute; bottom: 0; left: 0; height: 4px; background: #818cf8; width: <?php echo $completion_percentage; ?>%; transition: width 0.5s ease; }
        .ss-info { display: flex; align-items: center; gap: 18px; z-index: 2; position: relative; }
        .ss-avatar { width: 52px; height: 52px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.15); overflow: hidden; background: #1e293b; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; color: #e2e8f0; }
        .ss-info h5 { margin: 0; font-weight: 700; font-size: 1.1rem; letter-spacing: -0.2px; }
        .ss-info p { margin: 0; color: #94a3b8; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; margin-top: 3px; letter-spacing: 0.5px; }
        .ss-stats { text-align: right; z-index: 2; position: relative; }
        .ss-stats .fraction { font-size: 1.15rem; font-weight: 800; letter-spacing: 1px; }
        .ss-stats .perc { font-size: 0.65rem; color: #94a3b8; font-weight: 800; letter-spacing: 0.5px; margin-top: 2px; }

        /* Layout Grid */
        .se-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
        @media (max-width: 991px) { .se-grid { grid-template-columns: 1fr; } }

        /* Card Styles */
        .se-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 20px; }
        .se-card-header { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .se-card-header h6 { margin: 0; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.8px; display: flex; align-items: center; gap: 10px; color: #334155; }
        .se-card-header .badge { background: #f1f5f9; color: #475569; font-weight: 800; border-radius: 50px; padding: 4px 10px; font-size: 0.65rem; border: 1px solid #e2e8f0; }
        .se-card.affective .se-card-header { background: #fffafb; border-bottom-color: #fce7f3; }
        .se-card.psychomotor .se-card-header { background: #f8fafc; border-bottom-color: #e2e8f0; }
        
        .se-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #f8fafc; }
        .se-row:last-child { border-bottom: none; }
        .se-row:hover { background: #fafbfc; }
        .se-row-label { font-size: 0.65rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; flex-grow: 1; }

        /* Scale Pills */
        .scale-pill-group { display: flex; gap: 8px; }
        .scale-pill { width: 30px; height: 30px; border-radius: 8px; background: #fff; border: 1.5px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: #94a3b8; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); user-select: none; }
        .scale-pill:hover { border-color: #cbd5e1; color: #475569; transform: translateY(-1px); }

        /* Active States */
        .scale-pill.active[data-val="1"] { background: #ef4444; border-color: #ef4444; color: #fff; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); }
        .scale-pill.active[data-val="2"] { background: #f97316; border-color: #f97316; color: #fff; box-shadow: 0 4px 10px rgba(249, 115, 22, 0.2); }
        .scale-pill.active[data-val="3"] { background: #eab308; border-color: #eab308; color: #fff; box-shadow: 0 4px 10px rgba(234, 179, 8, 0.2); }
        .scale-pill.active[data-val="4"] { background: #22c55e; border-color: #22c55e; color: #fff; box-shadow: 0 4px 10px rgba(34, 197, 94, 0.2); }
        .scale-pill.active[data-val="5"] { background: #3b82f6; border-color: #3b82f6; color: #fff; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); }

        /* Rating Scale Content */
        .se-card.rating-scale { background: #0f172a; border-color: #1e293b; color: #cbd5e1; border-radius: 12px; }
        .se-card.rating-scale .se-card-header { background: #0f172a; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .se-card.rating-scale .se-card-header h6 { color: #f8fafc; }
        .rs-row { display: flex; align-items: center; gap: 14px; padding: 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.7rem; font-weight: 600; color: #94a3b8; }
        .rs-row:last-child { border-bottom: none; }
        .rs-row strong { color: #f8fafc; }
        .rs-pill { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; color: #fff; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.5); }
        .rs-pill.c1 { background: #ef4444; }
        .rs-pill.c2 { background: #f97316; }
        .rs-pill.c3 { background: #eab308; }
        .rs-pill.c4 { background: #22c55e; }
        .rs-pill.c5 { background: #3b82f6; }

        /* Bottom Action Card (Flows naturally) */
        .se-action-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 17px; margin-top: 5px; margin-bottom: 20px; }
        
        /* Desktop / Default Layout */
        .se-action-top { display: flex; justify-content: space-between; align-items: center; }
        .se-action-bottom { display: flex; align-items: center; gap: 12px; overflow-x: auto; padding-bottom: 5px; border-top: 1px solid #f1f5f9; padding-top: 17px; }
        .se-action-bottom::-webkit-scrollbar { display: none; }
        .se-action-group { display: flex; align-items: center; gap: 10px; }

        .btn-se { padding: 9px 18px; border-radius: 8px; font-weight: 700; font-size: 0.75rem; display: flex; align-items: center; gap: 8px; justify-content: center; border: 1.5px solid #e2e8f0; background: #fff; color: #334155; transition: 0.2s; cursor: pointer; text-decoration: none; word-break: keep-all; white-space: nowrap; }
        .btn-se:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
        .btn-se-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn-se-primary:hover { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }

        .stu-pag-pill { background: #fff; border: 1.5px solid #e2e8f0; border-radius: 50px; padding: 7px 16px; font-size: 0.7rem; font-weight: 700; color: #64748b; white-space: nowrap; cursor: pointer; transition: 0.2s; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .stu-pag-pill:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
        .stu-pag-pill.active { background: #0f172a; color: #fff !important; border-color: #0f172a; }
        .stu-pag-pill .num { opacity: 0.7; }
        
        /* Mobile Stack */
        @media (max-width: 768px) {
            .sa-main-content { padding: 12px !important; }
            .se-top-header { padding: 10px 15px; flex-direction: column; align-items: flex-start; gap: 12px; border-radius: 10px; }
            .se-title h4 { font-size: 1rem; }
            .btn-close-se { font-size: 0.65rem; padding: 5px 10px; align-self: flex-end; }
            
            .se-student-banner { padding: 12px 15px; border-radius: 10px; margin-bottom: 15px; }
            .ss-avatar { width: 40px; height: 40px; font-size: 1rem; }
            .ss-info h5 { font-size: 0.95rem; }
            .ss-info p { font-size: 0.6rem; }
            .ss-stats .fraction { font-size: 1rem; }
            
            .se-card { border-radius: 12px; }
            .se-card-header { padding: 10px 15px; }
            .se-row { padding: 10px 15px; flex-direction: column; align-items: flex-start; gap: 10px; }
            .se-row-label { font-size: 0.6rem; }
            .scale-pill-group { width: 100%; justify-content: space-between; }
            .scale-pill { width: 28px; height: 28px; font-size: 0.7rem; }

            .se-action-card { padding: 12px; gap: 12px; }
            .se-action-top { flex-direction: column; align-items: stretch; gap: 12px; }
            .assessment-btn-wrapper { width: 100%; }
            .assessment-btn-wrapper .btn-se { width: 100%; }
            .se-action-group { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; width: 100%; }
            .se-action-group button:last-child { grid-column: span 2; }
            
            .btn-se { padding: 8px 12px; font-size: 0.7rem; }
            .stu-pag-pill { padding: 4px 10px; font-size: 0.62rem; }
            
            .rs-row { padding: 10px 15px; font-size: 0.65rem; gap: 10px; }
            .rs-pill { width: 20px; height: 20px; font-size: 0.6rem; }
        }

        @media (max-width: 480px) {
            .se-grid { gap: 15px; }
            .scale-pill { width: 26px; height: 26px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>
    <?php include '../includes/spinner.php'; ?>

    <main class="sa-main-content">
        <!-- Top Nav -->
        <div class="se-top-header">
            <div class="se-title">
                <h4><i class="fas fa-star text-primary"></i> Skills Entry</h4>
                <p><?php echo htmlspecialchars($class_name); ?> — <?php echo htmlspecialchars($term_name); ?> <?php echo get_label('Term'); ?> (<?php echo htmlspecialchars($session_name); ?>)</p>
            </div>
            <a href="report_management.php?class_id=<?php echo $class_id; ?>&session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>" class="btn-close-se">
                <i class="fas fa-times"></i> Score Entry
            </a>
        </div>

        <?php if ($is_locked): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 p-4 mb-4 d-flex align-items-center gap-3">
            <i class="fas fa-lock fa-2x opacity-50"></i>
            <div>
                <div class="fw-900 uppercase tracking-1 small">Skill Entry Locked</div>
                <div class="small opacity-75"><?php echo $lock_reason; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Student Banner -->
        <div class="se-student-banner">
            <div class="ss-info">
                <div class="ss-avatar">
                    <?php if($student['image_path']): ?>
                        <img src="../<?php echo $student['image_path']; ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h5><?php echo htmlspecialchars($student['full_name']); ?></h5>
                    <p><?php echo ucfirst($student['gender'] ?? 'Student'); ?> - Student <?php echo $current_index; ?> of <?php echo $total_students; ?></p>
                </div>
            </div>
            <div class="ss-stats">
                <div class="fraction"><?php echo $current_index; ?> / <?php echo $total_students; ?></div>
                <div class="perc"><?php echo $completion_percentage; ?>% DONE</div>
            </div>
        </div>

        <div class="se-grid">
            <!-- Left Col (Affective) -->
            <div>
                <div class="se-card affective">
                    <div class="se-card-header">
                        <h6><i class="fas fa-heart text-danger"></i> Affective Traits</h6>
                        <span class="badge"><?php echo count($affectiveTraits); ?></span>
                    </div>
                    <div class="se-card-body">
                        <?php foreach($affectiveTraits as $trait): 
                            $val = $saved_traits[strtoupper($trait)] ?? 0;
                        ?>
                            <div class="se-row trait-instance" data-name="<?php echo htmlspecialchars($trait); ?>" data-type="affective">
                                <div class="se-row-label"><?php echo htmlspecialchars($trait); ?></div>
                                <div class="scale-pill-group">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <div class="scale-pill <?php echo ($val == $i) ? 'active' : ''; ?>" data-val="<?php echo $i; ?>"><?php echo $i; ?></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Col (Psychomotor + Legend) -->
            <div>
                <div class="se-card psychomotor">
                    <div class="se-card-header">
                        <h6><i class="fas fa-running text-warning"></i> Psychomotor Skills</h6>
                        <span class="badge"><?php echo count($psychomotorTraits); ?></span>
                    </div>
                    <div class="se-card-body">
                        <?php foreach($psychomotorTraits as $trait): 
                            $val = $saved_traits[strtoupper($trait)] ?? 0;
                        ?>
                            <div class="se-row trait-instance" data-name="<?php echo htmlspecialchars($trait); ?>" data-type="psychomotor">
                                <div class="se-row-label"><?php echo htmlspecialchars($trait); ?></div>
                                <div class="scale-pill-group">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <div class="scale-pill <?php echo ($val == $i) ? 'active' : ''; ?>" data-val="<?php echo $i; ?>"><?php echo $i; ?></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Rating Scale -->
                <div class="se-card rating-scale">
                    <div class="se-card-header">
                        <h6><i class="fas fa-chart-bar"></i> Rating Scale</h6>
                    </div>
                    <div class="se-card-body">
                        <div class="rs-row">
                            <div class="rs-pill c1">1</div> <strong>1</strong> - No Observable Trait
                        </div>
                        <div class="rs-row">
                            <div class="rs-pill c2">2</div> <strong>2</strong> - Poor Level of Observable Trait
                        </div>
                        <div class="rs-row">
                            <div class="rs-pill c3">3</div> <strong>3</strong> - Fair But Acceptable
                        </div>
                        <div class="rs-row">
                            <div class="rs-pill c4">4</div> <strong>4</strong> - Good Level of Observable Trait
                        </div>
                        <div class="rs-row">
                            <div class="rs-pill c5">5</div> <strong>5</strong> - Excellence Degree of Observable Trait
                        </div>
                    </div>
                </div>
        </div>

        <!-- Action Card (Flows naturally, not fixed, no overlap) -->
        <div class="se-action-card">
            <div class="se-action-top">
                <div class="assessment-btn-wrapper">
                    <a href="assessment_entry.php?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>&session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>" class="btn-se">
                        <i class="fas fa-arrow-left"></i> Assessment
                    </a>
                </div>
                <div class="se-action-group">
                    <?php if (!$is_locked): ?>
                        <?php if($next_student_id): ?>
                            <button onclick="window.location.href='skills_entry.php?student_id=<?php echo $next_student_id; ?>&class_id=<?php echo $class_id; ?>&session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>'" class="btn-se">
                                <i class="fas fa-times"></i> Skip
                            </button>
                        <?php endif; ?>
                        <button class="btn-se" onclick="saveData(false)">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button class="btn-se btn-se-primary" onclick="saveData(true)">
                            Save & Next <i class="fas fa-arrow-right"></i>
                        </button>
                    <?php else: ?>
                        <button onclick="window.location.href='report_management.php?class_id=<?php echo $class_id; ?>&session_id=<?php echo $current_session_id; ?>&term_id=<?php echo $current_term_id; ?>'" class="btn-se btn-outline-danger">
                            <i class="fas fa-times me-2"></i> EXIT SKILLS AUDIT
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="se-action-bottom">
                <?php 
                    $start = max(0, $current_index - 3);
                    $end = min($total_students, $current_index + 2);
                    for($i = $start; $i < $end; $i++) {
                        $s = $all_students[$i];
                        $is_active = ($s['id'] == $student_id) ? 'active' : '';
                        $name_short = explode(' ', $s['full_name'])[0];
                        echo '<a href="skills_entry.php?student_id='.$s['id'].'&class_id='.$class_id.'&session_id='.$current_session_id.'&term_id='.$current_term_id.'" class="stu-pag-pill '.$is_active.'"><span class="num">'.($i+1).'.</span> '.$name_short.'</a>';
                    }
                ?>
            </div>
        </div>

    </main>

    <!-- Hidden Form for submission -->
    <form id="saveTraitsForm" action="../ajax/save_student_traits.php" method="POST" style="display:none;">
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
        <input type="hidden" name="session_id" value="<?php echo $current_session_id; ?>">
        <input type="hidden" name="term_id" value="<?php echo $current_term_id; ?>">
    </form>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <?php include '../includes/notifications.php'; ?>
    <script>
        const STUDENT_ID = <?php echo $student_id; ?>;
        const NEXT_STUDENT_ID = <?php echo $next_student_id ?: 'null'; ?>;
        const CLASS_ID = <?php echo $class_id; ?>;
        const IS_LOCKED = <?php echo $is_locked ? 'true' : 'false'; ?>;

        $(document).ready(function() {
            // Pill Click Logic
            $('.scale-pill').on('click', function() {
                if (IS_LOCKED) {
                    Notif.show('Audit window is locked.', 'warning');
                    return;
                }
                // Remove active from siblings within the same group
                $(this).siblings().removeClass('active');
                // Toggle active on clicked
                $(this).addClass('active');
            });
        });

        function saveData(isNext) {
            let traits = [];
            $('.trait-instance').each(function() {
                let name = $(this).data('name');
                let type = $(this).data('type');
                let active_pill = $(this).find('.scale-pill.active');
                let rating = active_pill.length ? active_pill.data('val') : 0;
                
                traits.push({name: name, type: type, rating: rating});
            });

            Spinner.show('Synchronizing State...');
            
            $.post('../ajax/save_student_traits.php', {
                student_id: STUDENT_ID,
                class_id: CLASS_ID,
                session_id: <?php echo $current_session_id; ?>,
                term_id: <?php echo $current_term_id; ?>,
                traits: traits
            }, function(res) {
                Spinner.hide();
                if(res.success) {
                    if(isNext) {
                        if(NEXT_STUDENT_ID) {
                            window.location.href = `skills_entry.php?student_id=${NEXT_STUDENT_ID}&class_id=${CLASS_ID}&session_id=${SESSION_ID}&term_id=${TERM_ID}`;
                        } else {
                            Notif.show('Finished all students in class!', 'success');
                            setTimeout(() => { window.location.href = `report_management.php?class_id=${CLASS_ID}&session_id=${SESSION_ID}&term_id=${TERM_ID}`; }, 1500);
                        }
                    } else {
                        Notif.show('Scores saved successfully', 'success');
                    }
                } else {
                    Notif.show(res.message, 'error');
                }
            }, 'json');
        }
    </script>
</body>
</html>
