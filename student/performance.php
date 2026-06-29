<?php
// student/performance.php
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

// Fetch school-specific sessions
$sessions = $pdo->prepare("SELECT id, name FROM academic_sessions WHERE school_id = ? ORDER BY created_at DESC");
$sessions->execute([$school_id]);
$sessions = $sessions->fetchAll();

// Default to school's active term/session from $active_school
$current_session_id = intval($_GET['session_id'] ?? $active_school['current_session_id'] ?? 0);

// Fetch terms for the selected session
$terms = [];
if ($current_session_id) {
    $t_stmt = $pdo->prepare("SELECT id, name FROM academic_terms WHERE session_id = ? AND school_id = ? ORDER BY created_at ASC");
    $t_stmt->execute([$current_session_id, $school_id]);
    $terms = $t_stmt->fetchAll();
}


$current_term_id = intval($_GET['term_id'] ?? $active_school['current_term_id'] ?? 0);
// If switching session, reset term if not in the new session's terms
if (isset($_GET['session_id']) && !isset($_GET['term_id'])) {
    $current_term_id = !empty($terms) ? $terms[0]['id'] : 0;
}

// Current names
$term_name = ''; $session_name = '';
if ($current_term_id) { $t = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?"); $t->execute([$current_term_id]); $term_name = $t->fetchColumn() ?: ''; }
if ($current_session_id) { $s = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?"); $s->execute([$current_session_id]); $session_name = $s->fetchColumn() ?: ''; }

// Fetch scores for selected term
$scores_stmt = $pdo->prepare("
    SELECT r.*, s.name as subject_name, s.code as subject_code
    FROM student_results r JOIN subjects s ON s.id = r.subject_id
    WHERE r.student_id = ? AND r.session_id = ? AND r.term_id = ?
    ORDER BY s.name
");
$scores_stmt->execute([$student_id, $current_session_id, $current_term_id]);
$scores = $scores_stmt->fetchAll();

// Metrics
$total_score = array_sum(array_column($scores, 'total'));
$avg_score = count($scores) ? round($total_score / count($scores), 1) : 0;

$best_subject = null;
$worst_subject = null;
if (!empty($scores)) {
    $sorted = $scores;
    usort($sorted, fn($a, $b) => $b['total'] <=> $a['total']);
    $best_subject = $sorted[0];
    $worst_subject = end($sorted);
}

// Skills/Traits for this term
$class_id_for_traits = $scores[0]['class_id'] ?? 0;
$traits = [];
if ($class_id_for_traits) {
    $tr_stmt = $pdo->prepare("SELECT * FROM student_traits WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
    $tr_stmt->execute([$student_id, $class_id_for_traits, $current_session_id, $current_term_id]);
    foreach ($tr_stmt->fetchAll() as $t) {
        $traits[$t['trait_type']][] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance | <?php echo htmlspecialchars($student['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="includes/student.css?v=<?php echo time(); ?>">
    <style>
        .subject-row { background: var(--stu-card); border: 1px solid var(--stu-border); border-radius: 16px; padding: 16px 20px; margin-bottom: 10px; transition: 0.2s; }
        .subject-row:hover { border-color: var(--stu-accent); box-shadow: 0 4px 16px rgba(59,130,246,0.06); }
        .progress-thin { height: 7px; border-radius: 10px; background: #f1f5f9; }
        .trait-card { background: var(--stu-card); border: 1px solid var(--stu-border); border-radius: 16px; padding: 20px; }
        .trait-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
        .trait-row:last-child { border-bottom: none; }
        .rating-dots { display: flex; gap: 4px; }
        .rating-dot { width: 10px; height: 10px; border-radius: 50%; background: #e2e8f0; }
        .rating-dot.filled { background: #3b82f6; }
    </style>
</head>
<body>
<div class="stu-layout">
    <?php include 'includes/nav.php'; ?>

    <main class="stu-main">
        <!-- Hero -->
        <div class="stu-hero mb-4">
            <div class="row align-items-center position-relative" style="z-index:1;">
                <div class="col-lg-7 mb-3 mb-lg-0">
                    <h2 class="fw-900 mb-1" style="letter-spacing:-1px; word-break: break-all;">Performance Hub</h2>
                    <p class="opacity-60 small fw-600 mb-3 text-truncate">Detailed view of your academic journey</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <?php 
                            $avg_color = '#ffffff';
                            if ($avg_score >= 80) $avg_color = '#10b981';
                            elseif ($avg_score >= 70) $avg_color = '#3b82f6';
                            elseif ($avg_score >= 60) $avg_color = '#f59e0b';
                            elseif ($avg_score < 40) $avg_color = '#ef4444';
                        ?>
                        <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 text-center" style="border-bottom: 3px solid <?php echo $avg_color; ?>;">
                            <div style="font-size:0.6rem;" class="opacity-50 text-uppercase fw-700 mb-1">Average</div>
                            <div class="fw-900 h5 mb-0" style="color: <?php echo $avg_color == '#ffffff' ? '#fff' : $avg_color; ?>;"><?php echo $avg_score; ?>%</div>
                        </div>
                        <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 text-center">
                            <div style="font-size:0.6rem;" class="opacity-50 text-uppercase fw-700 mb-1"><?php echo get_label('Subjects'); ?></div>
                            <div class="fw-900 h5 mb-0"><?php echo count($scores); ?></div>
                        </div>
                        <?php if ($best_subject): ?>
                        <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 text-center">
                            <div style="font-size:0.6rem;" class="opacity-50 text-uppercase fw-700 mb-1">Best <?php echo get_label('Subject'); ?></div>
                            <div class="fw-900" style="font-size:0.85rem;"><?php echo htmlspecialchars($best_subject['subject_name']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="bg-white bg-opacity-10 rounded-4 px-4 py-3 border border-white border-opacity-10 shadow-sm" style="backdrop-filter: blur(15px); min-width: 250px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                             <div class="extra-small opacity-75 fw-800 uppercase tracking-2" style="font-size: 0.62rem; color: #fff;">Academic Hub Context</div>
                             <div class="badge bg-warning text-dark rounded-pill fw-900 px-2 py-1" style="font-size: 0.55rem;">FILTER ACTIVE</div>
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
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Subject Scores -->
            <div class="col-lg-8">
                <div class="section-head"><i class="fas fa-book-open"></i> <?php echo get_label('Subject'); ?> Breakdown</div>
                <?php if (empty($scores)): ?>
                    <div class="stu-card">
                        <div class="stu-empty py-4">
                            <div class="stu-empty-icon"><i class="fas fa-search-minus"></i></div>
                            <h6 class="fw-bold text-muted">No data for this selection</h6>
                            <p class="text-muted small">Try selecting a different term or session.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($scores as $res):
                        $total = floatval($res['total']);
                        $color = '#64748b';
                        
                        if ($total >= 80) $color = '#10b981';
                        elseif ($total >= 70) $color = '#3b82f6';
                        elseif ($total >= 60) $color = '#f59e0b';
                        elseif ($total >= 50) $color = '#8b5cf6';
                        elseif ($total >= 40) $color = '#06b6d4';
                        else $color = '#ef4444';

                        $track_color = $color . '20'; // 12-15% opacity for professional tint
                    ?>
                    <div class="subject-row">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="subject-name fw-800" style="font-size: 0.95rem; color: var(--stu-primary);"><?php echo htmlspecialchars($res['subject_name']); ?></div>
                                <div class="text-muted fw-600" style="font-size:0.65rem; letter-spacing: 0.5px;"><?php echo htmlspecialchars($res['subject_code']); ?></div>
                            </div>
                            <div class="text-end">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-900" style="color:<?php echo $color; ?>; font-size:1.2rem;"><?php echo number_format($total, 2); ?>%</span>
                                    <span class="badge rounded-pill px-3 py-1" style="font-size:0.65rem; background:<?php echo $color; ?>15; color:<?php echo $color; ?>; border: 1px solid <?php echo $color; ?>20;"><?php echo $res['grade']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="progress-container position-relative" style="height: 12px; background: <?php echo $track_color; ?>; border-radius: 20px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                            <div class="progress-bar-fill" style="width:<?php echo $total; ?>%; height: 100%; background: linear-gradient(90deg, <?php echo $color; ?>dd, <?php echo $color; ?>); border-radius: 20px; transition: width 1s cubic-bezier(0.1, 0.5, 0.1, 1);"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2" style="font-size:0.75rem;">
                            <div class="d-flex gap-3">
                                <?php if ($is_higher_ed): ?>
                                    <span class="text-muted fw-600">CA: <span class="text-dark fw-800"><?php echo $res['ca1']; ?></span></span>
                                <?php else: ?>
                                    <span class="text-muted fw-600">CA1: <span class="text-dark fw-800"><?php echo $res['ca1']; ?></span></span>
                                    <span class="text-muted fw-600">CA2: <span class="text-dark fw-800"><?php echo $res['ca2']; ?></span></span>
                                <?php endif; ?>
                                <span class="text-muted fw-600">Exam: <span class="text-dark fw-800"><?php echo $res['exam']; ?></span></span>
                            </div>
                            <span class="text-muted extra-small fw-800 uppercase tracking-1" style="color: <?php echo $color; ?> !important; opacity: 0.7;"><?php echo $total >= 50 ? 'Successful Node' : 'Optimization Required'; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Skills + Legend -->
            <div class="col-lg-4">
                <?php if (!empty($traits)): ?>
                    <?php foreach (['affective' => 'Affective Traits', 'psychomotor' => 'Psychomotor Skills'] as $type => $label): ?>
                        <?php if (!empty($traits[$type])): ?>
                        <div class="trait-card mb-3">
                            <div class="section-head" style="font-size:0.95rem;">
                                <i class="fas <?php echo $type === 'affective' ? 'fa-heart' : 'fa-hand-sparkles'; ?>"></i> <?php echo $label; ?>
                            </div>
                            <?php foreach ($traits[$type] as $trait): ?>
                            <div class="trait-row">
                                <span class="fw-600" style="font-size:0.8rem;"><?php echo htmlspecialchars($trait['trait_name']); ?></span>
                                <div class="rating-dots">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="rating-dot <?php echo $i <= $trait['rating'] ? 'filled' : ''; ?>"></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Legend -->
                <div class="stu-card">
                    <div class="section-head" style="font-size:0.95rem;"><i class="fas fa-palette"></i> Assessment Key</div>
                    <div class="d-flex flex-column gap-2">
                        <?php
                        $legend = $is_higher_ed ? [
                            ['#10b981', 'Excellent (A: 70-100%)'],
                            ['#3b82f6', 'Very Good (B: 60-69%)'],
                            ['#f59e0b', 'Good (C: 50-59%)'],
                            ['#8b5cf6', 'Fair (D: 45-49%)'],
                            ['#06b6d4', 'Pass (E: 40-44%)'],
                            ['#ef4444', 'Fail (F: 0-39%)']
                        ] : [
                            ['#10b981', 'Excellent (A1: 75-100%)'],
                            ['#3b82f6', 'Very Good (B2: 70-74%)'],
                            ['#f59e0b', 'Good (B3-C4: 60-69%)'],
                            ['#8b5cf6', 'Credit (C5-C6: 50-59%)'],
                            ['#06b6d4', 'Pass (D7-E8: 40-49%)'],
                            ['#ef4444', 'Fail (F9: 0-39%)']
                        ];
                        foreach ($legend as $item): ?>
                        <div class="d-flex align-items-center gap-2" style="font-size:0.78rem;">
                            <span style="width:12px; height:12px; background:<?php echo $item[0]; ?>; border-radius:3px; flex-shrink:0;"></span>
                            <span class="fw-600"><?php echo $item[1]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
