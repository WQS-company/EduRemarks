<?php
// super_admin/feedback.php - Institutional Intelligence Hub
require_once 'auth_check.php';

// Fetch all feedback with school names
$stmt = $pdo->query("
    SELECT f.*, s.school_name, s.unique_id as school_uid 
    FROM platform_feedback f 
    LEFT JOIN schools s ON f.school_id = s.id 
    ORDER BY f.created_at DESC
");
$feedbacks = $stmt->fetchAll();

// Aggregated Stats
$avg_rating = $pdo->query("SELECT AVG(rating) FROM platform_feedback")->fetchColumn() ?? 0;
$total_feedbacks = count($feedbacks);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Insights | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
        
        .rating-chip {
            padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 0.7rem;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .bg-rating-high { background: #dcfce7; color: #166534; }
        .bg-rating-mid { background: #fef9c3; color: #854d0e; }
        .bg-rating-low { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h4 class="fw-800 mb-0">Platform Intelligence Dashboard</h4>
            <p class="text-muted small">Voice of the institutions and student nodes</p>
        </div>
        <div class="d-flex gap-4">
            <div class="text-center">
                <div class="small fw-bold text-muted uppercase tracking-1">Average Satisfaction</div>
                <div class="h3 fw-900 text-premium-gold mb-0"><?php echo round($avg_rating, 1); ?> <i class="fas fa-star tiny-text"></i></div>
            </div>
            <div class="text-center">
                <div class="small fw-bold text-muted uppercase tracking-1">Insight Points</div>
                <div class="h3 fw-900 text-blue mb-0"><?php echo number_format($total_feedbacks); ?></div>
            </div>
        </div>
    </div>

    <!-- Feedback Feed -->
    <div class="row g-4">
        <?php if(empty($feedbacks)): ?>
        <div class="col-12">
            <div class="glass-card p-5 text-center opacity-50">
                <i class="fas fa-box-open fa-3x mb-3"></i>
                <h5>No user insights captured yet.</h5>
                <p class="small">Feedabck triggers are active across reporting and CBT nodes.</p>
            </div>
        </div>
        <?php else: foreach($feedbacks as $f): 
            $rating_class = ($f['rating'] >= 4) ? 'bg-rating-high' : (($f['rating'] >= 3) ? 'bg-rating-mid' : 'bg-rating-low');
        ?>
        <div class="col-xl-6">
            <div class="glass-card p-4 border-0 shadow-sm h-100 position-relative reveal-fade-up">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-node bg-light border p-2 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user-tie text-blue"></i>
                        </div>
                        <div>
                            <h6 class="fw-800 mb-0"><?php echo htmlspecialchars($f['full_name']); ?></h6>
                            <div class="tiny-text opacity-75 fw-600"><?php echo htmlspecialchars($f['email']); ?></div>
                        </div>
                    </div>
                    <div class="rating-chip <?php echo $rating_class; ?>">
                        <?php echo $f['rating']; ?> <i class="fas fa-star"></i>
                    </div>
                </div>

                <div class="mb-3 px-2 py-1 bg-light rounded-3 d-inline-block small fw-700 text-blue">
                    <i class="fas fa-bolt me-1 opacity-50"></i> <?php echo htmlspecialchars($f['activity_type']); ?>
                </div>

                <div class="p-3 bg-light bg-opacity-50 rounded-4 mb-3 italic-style">
                    <i class="fas fa-quote-left opacity-10 h3 position-absolute" style="top: 100px; right: 30px;"></i>
                    <p class="small mb-0 opacity-80" style="min-height: 40px; font-style: italic;">"<?php echo nl2br(htmlspecialchars($f['comments'] ?: 'The user provided a rating without textual insight.')); ?>"</p>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-3">
                    <div class="tiny-text fw-bold text-muted">
                        <i class="fas fa-university me-1"></i> <?php echo htmlspecialchars($f['school_name'] ?? 'Orphan Node'); ?> 
                        <span class="mx-1">&bull;</span> <?php echo $f['school_uid'] ?? 'N/A'; ?>
                    </div>
                    <div class="tiny-text opacity-50"><?php echo date('M d, Y - h:i A', strtotime($f['created_at'])); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
