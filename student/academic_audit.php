<?php
// student/academic_audit.php - Professional Academic History & Timeline
require_once 'auth.php';

// Helper for GP calculation
function calculateGP($total, $is_higher_ed) {
    if (!$is_higher_ed) return 0; // Secondary uses Average
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
    WHERE r.student_id = ?
    ORDER BY r.session_id DESC, r.term_id DESC, s.name ASC
");
$stmt->execute([$student_id]);
$all_results = $stmt->fetchAll();

// Group results by Session -> Term
$history = [];
foreach ($all_results as $r) {
    $session_key = $r['session_id'];
    $term_key = $r['term_id'];
    
    if (!isset($history[$session_key])) {
        $history[$session_key] = [
            'name' => $r['session_name'],
            'terms' => []
        ];
    }
    
    if (!isset($history[$session_key]['terms'][$term_key])) {
        $history[$session_key]['terms'][$term_key] = [
            'name' => $r['term_name'],
            'class_name' => $r['class_name'],
            'results' => []
        ];
    }
    
    $history[$session_key]['terms'][$term_key]['results'][] = $r;
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

// Calculate cumulative stats
$cumulative_credits = 0;
$cumulative_points = 0;

// Reverse history to calculate CGPA chronologically (from oldest to newest)
$chronological_history = array_reverse($history, true);
foreach ($chronological_history as $sess_id => &$sess) {
    // Sort terms ascendingly for CGPA calculation
    ksort($sess['terms']);
    foreach ($sess['terms'] as $term_id => &$term) {
        $term_credits = 0;
        $term_points = 0;
        
        foreach ($term['results'] as $res) {
            $gp = calculateGP($res['total'], $is_higher_ed);
            $p = $gp * ($res['credit_units'] ?: 0);
            
            $term_credits += ($res['credit_units'] ?: 0);
            $term_points += $p;
        }
        
        $term['gpa'] = $term_credits > 0 ? round($term_points / $term_credits, 2) : 0;
        
        $cumulative_credits += $term_credits;
        $cumulative_points += $term_points;
        $term['cgpa'] = $cumulative_credits > 0 ? round($cumulative_points / $cumulative_credits, 2) : 0;
    }
}
unset($sess, $term); // Clean up references

// Return to descending order for display (Newest first)
$display_history = array_reverse($chronological_history, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_label('Academic Audit'); ?> | <?php echo htmlspecialchars($student['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="includes/student.css?v=<?php echo time(); ?>">
    <style>
        .audit-timeline { position: relative; padding-left: 2.5rem; }
        .audit-timeline::before { content: ''; position: absolute; left: 0.75rem; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        
        .timeline-item { position: relative; margin-bottom: 2.5rem; }
        .timeline-marker { position: absolute; left: -2.3rem; top: 0.2rem; width: 1.2rem; height: 1.2rem; border-radius: 50%; background: #fff; border: 3px solid #3b82f6; z-index: 1; }
        .timeline-item.current .timeline-marker { background: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
        
        .audit-card { background: var(--stu-card); border: 1px solid var(--stu-border); border-radius: 20px; padding: 24px; transition: 0.3s; }
        .audit-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        
        .stats-stripe { display: flex; gap: 20px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9; }
        .stat-node { display: flex; flex-direction: column; }
        .stat-label { font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 1rem; font-weight: 900; color: var(--stu-accent); }
        
        .course-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
        .course-row:last-child { border-bottom: none; }
        .course-total { font-weight: 800; color: var(--stu-text); }
        .grade-badge { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 900; font-size: 0.8rem; }
        
        .grade-A { background: #ecfdf5; color: #10b981; }
        .grade-B { background: #eff6ff; color: #3b82f6; }
        .grade-C { background: #fefce8; color: #ca8a04; }
        .grade-D, .grade-E { background: #fff7ed; color: #f97316; }
        .grade-F { background: #fef2f2; color: #ef4444; }

        @media (max-width: 768px) {
            .course-row { grid-template-columns: 2fr 1fr 1fr; }
            .course-credits { display: none; }
            .stats-stripe { flex-wrap: wrap; gap: 10px 20px; }
        }
    </style>
</head>
<body>
<div class="stu-layout">
    <?php include 'includes/nav.php'; ?>

    <main class="stu-main">
        <div class="stu-hero mb-4">
            <h2 class="fw-900 mb-1"><?php echo get_label('Academic Audit'); ?></h2>
            <p class="opacity-60 small fw-600 mb-0">Institutional Record of Academic Progression</p>
        </div>

        <?php if (empty($display_history)): ?>
            <div class="text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-history text-muted fa-2x"></i>
                </div>
                <h5 class="fw-bold">No Records Found</h5>
                <p class="text-muted small">Your academic history will appear here once results are uploaded.</p>
            </div>
        <?php else: ?>
            <div class="audit-timeline">
                <?php 
                $is_first = true;
                foreach ($display_history as $sess_id => $sess): 
                    foreach (array_reverse($sess['terms'], true) as $term_id => $term):
                ?>
                    <div class="timeline-item <?php echo $is_first ? 'current' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="audit-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill mb-2 px-3 fw-800" style="font-size: 0.65rem;">
                                        <?php echo htmlspecialchars($sess['name']); ?>
                                    </div>
                                    <h5 class="fw-900 mb-0"><?php echo get_label($term['name']); ?></h5>
                                    <div class="small text-muted fw-bold mt-1"><?php echo get_label('Class'); ?>: <?php echo htmlspecialchars($term['class_name']); ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="extra-small fw-800 text-muted uppercase tracking-1">Status</div>
                                    <div class="badge bg-success bg-opacity-10 text-success rounded-pill fw-900">VERIFIED</div>
                                </div>
                            </div>

                            <div class="course-list mt-4">
                                <div class="course-row text-muted extra-small fw-800 uppercase tracking-1 pb-2 border-bottom">
                                    <span><?php echo get_label('Subject'); ?></span>
                                    <span class="text-center"><?php echo $is_higher_ed ? 'Units' : 'C.A'; ?></span>
                                    <span class="text-center"><?php echo $is_higher_ed ? 'Grade' : 'Exam'; ?></span>
                                    <span class="text-center">Total</span>
                                </div>
                                <?php foreach ($term['results'] as $res): ?>
                                    <div class="course-row">
                                        <div class="d-flex flex-column">
                                            <span class="fw-800 small text-dark"><?php echo htmlspecialchars($res['subject_name']); ?></span>
                                            <span class="extra-small text-muted fw-bold"><?php echo htmlspecialchars($res['subject_code']); ?></span>
                                        </div>
                                        <div class="text-center small fw-700">
                                            <?php echo $is_higher_ed ? $res['credit_units'] : ($res['ca1'] + $res['ca2']); ?>
                                        </div>
                                        <div class="text-center d-flex justify-content-center">
                                            <?php if ($is_higher_ed): ?>
                                                <div class="grade-badge grade-<?php echo substr($res['grade'], 0, 1); ?>">
                                                    <?php echo $res['grade']; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="small fw-700"><?php echo $res['exam']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-center course-total">
                                            <?php echo $res['total']; ?>%
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="stats-stripe">
                                <?php if ($is_higher_ed): ?>
                                    <div class="stat-node">
                                        <span class="stat-label">Semester GPA</span>
                                        <span class="stat-value"><?php echo number_format($term['gpa'], 2); ?></span>
                                    </div>
                                    <div class="stat-node">
                                        <span class="stat-label">Cumulative GPA</span>
                                        <span class="stat-value text-dark"><?php echo number_format($term['cgpa'], 2); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="stat-node">
                                        <span class="stat-label">Term Average</span>
                                        <?php 
                                            $term_scores = array_column($term['results'], 'total');
                                            $term_avg = count($term_scores) > 0 ? array_sum($term_scores) / count($term_scores) : 0;
                                        ?>
                                        <span class="stat-value"><?php echo round($term_avg, 1); ?>%</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="stat-node ms-auto text-end">
                                    <span class="stat-label">Audit Verified</span>
                                    <span class="stat-value" style="font-size: 0.7rem;">
                                        <i class="fas fa-check-circle text-success me-1"></i> 
                                        Academic Registry
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                    $is_first = false;
                    endforeach; 
                endforeach; 
                ?>
            </div>
        <?php endif; ?>
        
        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
