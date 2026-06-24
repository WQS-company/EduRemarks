<?php
// student/cbt_finished.php — Standard-Compliant Submission Confirmation
require_once '../config/db.php';

$exam_id = $_GET['exam'] ?? '';
$token   = $_GET['token'] ?? '';

// Fetch exam details for context
$exam = null;
if ($exam_id) {
    $stmt = $pdo->prepare("
        SELECT e.title, e.assessment_type, c.name as class_name, s.name as subject_name, sch.school_name, sch.logo_path
        FROM cbt_exams e
        JOIN classes c ON c.id = e.class_id
        JOIN subjects s ON s.id = e.subject_id
        JOIN schools sch ON sch.id = e.school_id
        WHERE e.id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
}

$term = ($exam && $exam['assessment_type'] === 'test') ? 'Test' : 'Examination';
$term_lower = strtolower($term);
$school_logo = ($exam && $exam['logo_path']) ? '../' . $exam['logo_path'] : '../img/logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Successful | EduRemarks CBT</title>
    <?php 
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : '../' . $sidebar_logo_raw;
    ?>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1F3C88; --gold: #F4B400; }
        body { 
            background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Inter', sans-serif; padding: 20px; color: #333;
        }
        .success-card {
            max-width: 500px; width: 100%; background: white; border-radius: 28px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3); overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        
        .card-header-accent {
            height: 6px; background: linear-gradient(90deg, var(--primary), var(--gold), var(--primary));
        }

        .icon-box {
            width: 100px; height: 100px; border-radius: 50%; background: #E8F5E9; color: #2E7D32;
            display: flex; align-items: center; justify-content: center; font-size: 3rem;
            margin: 40px auto 25px; border: 8px solid #f0fdf4;
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring { 0% { box-shadow: 0 0 0 0 rgba(46, 125, 50, 0.2); } 70% { box-shadow: 0 0 0 20px rgba(46, 125, 50, 0); } 100% { box-shadow: 0 0 0 0 rgba(46, 125, 50, 0); } }

        .exam-info-badge {
            background: #f1f5f9; color: #475569; font-size: 0.75rem; font-weight: 700;
            padding: 6px 16px; border-radius: 50px; display: inline-block; margin-bottom: 20px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .redirect-timer {
            font-size: 0.8rem; color: #94a3b8; margin-top: 25px;
            font-weight: 500;
        }
        .timer-val { color: var(--primary); font-weight: 800; font-family: monospace; }
        
        .btn-portal-back {
            background: var(--primary); color: white; border: none; padding: 16px 30px;
            border-radius: 50px; font-weight: 700; font-size: 0.95rem; width: 100%;
            transition: all 0.3s ease; box-shadow: 0 10px 20px rgba(31, 60, 136, 0.2);
        }
        .btn-portal-back:hover { background: #152A6E; transform: translateY(-2px); box-shadow: 0 15px 30px rgba(31, 60, 136, 0.3); color: white; }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="card-header-accent"></div>
        <div class="p-4 p-md-5">
            <div class="icon-box">
                <i class="fas fa-check-double"></i>
            </div>
            
            <h3 class="fw-800 mb-2">Submission Successful</h3>
            <p class="text-muted small mb-4 px-3">
                Your <?php echo $term_lower; ?> responses for <strong><?php echo htmlspecialchars($exam['title'] ?? 'this session'); ?></strong> have been securely transmitted to the institutional servers.
            </p>

            <?php if ($exam): ?>
            <div class="exam-info-badge">
                <i class="fas fa-university me-1 text-primary"></i> <?php echo htmlspecialchars($exam['school_name']); ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-info border-0 py-3 rounded-4 shadow-sm mb-4" style="background: rgba(31, 60, 136, 0.04);">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-user-shield me-2 text-primary"></i>
                    <span class="small fw-700 text-primary">Session Cleared & Secure</span>
                </div>
            </div>

            <div class="d-grid gap-2">
                <a href="cbt.php?token=<?php echo urlencode($token); ?>" class="btn btn-portal-back">
                    READY FOR NEXT STUDENT <i class="fas fa-user-plus ms-2"></i>
                </a>
            </div>

            <div class="redirect-timer">
                Automatic redirect in <span class="timer-val" id="timerCount">10</span> seconds...
            </div>
        </div>
        <div class="py-3 bg-light border-top">
            <img src="../img/logo.png" style="height: 20px; opacity: 0.5;" alt="EduRemarks">
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <?php include '../includes/feedback_modal.php'; ?>
    <script>
        let counter = 15; // Increased for feedback
        const target = 'cbt.php?token=<?php echo urlencode($token); ?>';
        const timerEl = document.getElementById('timerCount');
        let redirectPaused = false;

        const interval = setInterval(() => {
            if(redirectPaused) return;
            counter--;
            timerEl.textContent = counter;
            if (counter <= 0) {
                clearInterval(interval);
                window.location.href = target;
            }
        }, 1000);

        // Show feedback after 1 second
        setTimeout(() => {
            EduRemarks.showFeedback('CBT Session', '<?php echo addslashes($exam['title'] ?? "CBT Assessment"); ?>');
        }, 1200);

        // Pause redirect when modal opens
        $(document).on('show.bs.modal', '#feedbackModal', function() {
            redirectPaused = true;
            timerEl.parentElement.style.opacity = '0.5';
        });
    </script>
</body>
</html>
