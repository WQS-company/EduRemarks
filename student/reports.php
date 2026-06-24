<?php
// student/reports.php
require_once 'auth.php';

// Fetch all sessions where the student has results
$sessions_stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.name
    FROM academic_sessions s
    JOIN student_results r ON r.session_id = s.id
    WHERE r.student_id = ?
    ORDER BY s.created_at DESC
");
$sessions_stmt->execute([$student_id]);
$sessions = $sessions_stmt->fetchAll();

$reportsBySession = [];
foreach ($sessions as $session) {
    $terms_stmt = $pdo->prepare("
        SELECT DISTINCT t.id, t.name
        FROM academic_terms t
        JOIN student_results r ON r.term_id = t.id
        WHERE r.student_id = ? AND r.session_id = ?
        ORDER BY t.created_at ASC
    ");
    $terms_stmt->execute([$student_id, $session['id']]);
    $terms_data = $terms_stmt->fetchAll();

    // Get subject count and avg for each term
    foreach ($terms_data as &$term) {
        $st = $pdo->prepare("SELECT COUNT(*) as cnt, AVG(total) as avg_score, class_id FROM student_results WHERE student_id = ? AND session_id = ? AND term_id = ? GROUP BY class_id LIMIT 1");
        $st->execute([$student_id, $session['id'], $term['id']]);
        $info = $st->fetch();
        $term['subjects'] = $info['cnt'] ?? 0;
        $term['avg'] = $info ? round($info['avg_score'], 1) : 0;
        $term['class_id'] = $info['class_id'] ?? 0;
    }
    unset($term);

    $reportsBySession[$session['id']] = [
        'name' => $session['name'],
        'terms' => $terms_data
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | <?php echo htmlspecialchars($student['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="includes/student.css?v=<?php echo time(); ?>">
    <style>
        .report-item {
            background: #fff;
            border: 1px solid var(--stu-border);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
        }
        .report-item:hover { border-color: var(--stu-primary); box-shadow: 0 15px 35px rgba(31, 60, 136, 0.08); transform: translateY(-3px); }
        .report-icon {
            width: 56px; height: 56px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            background: var(--stu-accent-light) !important;
            color: var(--stu-accent) !important;
        }
        .report-info { flex: 1; min-width: 0; }
        .report-actions { display: flex; gap: 10px; flex-shrink: 0; }
        .report-btn {
            padding: 10px 20px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .report-btn-view { background: var(--stu-primary); color: #fff; border: none; }
        .report-btn-view:hover { background: #0f172a; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .session-divider {
            font-weight: 800; color: var(--stu-primary);
            text-transform: uppercase; letter-spacing: 1.5px;
            font-size: 0.75rem; margin: 40px 0 20px;
            display: flex; align-items: center; gap: 12px;
        }
        .session-divider::after { content: ''; flex: 1; height: 2px; background: var(--stu-border); }
        .avg-badge {
            display: inline-flex; align-items: center;
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.68rem; font-weight: 800;
        }
        @media (max-width: 576px) {
            .report-item { flex-wrap: wrap; padding: 14px; gap: 10px; }
            .report-actions { width: 100%; }
            .report-btn { flex: 1; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="stu-layout">
    <?php include 'includes/nav.php'; ?>

    <main class="stu-main">
        <div class="mb-4">
            <h3 class="fw-900 mb-1" style="letter-spacing:-0.5px;">My <?php echo get_label('Report Sheets'); ?></h3>
            <p class="text-muted small fw-600">View and download your official academic performance reports</p>
        </div>

        <?php if (empty($reportsBySession)): ?>
            <div class="stu-card">
                <div class="stu-empty">
                    <div class="stu-empty-icon"><i class="fas fa-file-invoice"></i></div>
                    <h5 class="fw-bold text-muted mb-2">No Reports Available</h5>
                    <p class="text-muted small"><?php echo get_label('Report Sheets'); ?> will appear here once your teachers publish assessment results.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($reportsBySession as $sid => $data): ?>
                <div class="session-divider">
                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($data['name']); ?>
                </div>
                <?php foreach ($data['terms'] as $term):
                    $avg_color = $term['avg'] >= 70 ? '#10b981' : ($term['avg'] >= 50 ? '#f59e0b' : '#ef4444');
                ?>
                <div class="report-item">
                    <div class="report-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="report-info">
                        <div class="fw-800" style="font-size:0.9rem;"><?php echo htmlspecialchars(get_label($term['name'])); ?> Report</div>
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                            <span class="text-muted" style="font-size:0.68rem; font-weight:600;">
                                <i class="fas fa-book-open me-1 opacity-50"></i><?php echo $term['subjects']; ?> <?php echo strtolower(get_label('Subjects')); ?>
                            </span>
                            <span class="avg-badge" style="background: <?php echo $avg_color; ?>15; color: <?php echo $avg_color; ?>;">
                                <i class="fas fa-chart-line me-1"></i><?php echo $term['avg']; ?>%
                            </span>
                        </div>
                    </div>
                    <div class="report-actions">
                        <a href="view_report.php?session_id=<?php echo $sid; ?>&term_id=<?php echo $term['id']; ?>&class_id=<?php echo $term['class_id']; ?>" class="report-btn report-btn-view">
                            <i class="fas fa-eye"></i> View & Print
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
