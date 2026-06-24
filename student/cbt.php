<?php
// student/cbt.php — Student CBT Portal Intake
require_once '../config/db.php';

$token = $_GET['token'] ?? '';

// 1. Initial Exam Validation
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

if ($exam) {
    require_once '../includes/functions.php';
    require_once '../includes/config.php';
    
    // Set context for get_label()
    $sch_ctx_stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
    $sch_ctx_stmt->execute([$exam['school_id']]);
    $active_school = $sch_ctx_stmt->fetch();
}

if (!$exam) {
    die("
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>
        <style>
            body { background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Inter', sans-serif; }
            .intake-card { max-width: 500px; background: white; border-radius: 24px; padding: 50px 30px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.3); }
            .error-circle { width: 80px; height: 80px; border-radius: 50%; background: #FFEBEE; color: #C62828; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; }
        </style>
    </head>
    <body>
        <div class='intake-card w-100'>
            <div class='error-circle'><i class='fas fa-exclamation-triangle'></i></div>
            <h4 class='fw-bold mb-3'>Invalid Link</h4>
            <p class='text-muted small mb-4'>This link is either broken, expired, or was moved. Please contact your administrator for a new one.</p>
            <a href='../index.php' class='btn btn-outline-primary rounded-pill px-4 btn-sm'>Return Home</a>
        </div>
    </body>
    </html>
    ");
}

$school_logo_url = $exam['logo_path'] ? '../' . $exam['logo_path'] : '../img/logo.png';
$term = ($exam['assessment_type'] === 'test') ? 'Test' : 'Examination';
$term_lower = strtolower($term);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Portal | <?php echo get_setting('hero_title', 'EduRemarks'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php 
    // Define platform_favicon for CBT landing page
    $path_prefix = '../';
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;
    ?>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Inter', sans-serif; }
        .intake-card { max-width: 500px; background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.3); border: none; }
        .instructions-box { background: #f8f9fa; border-left: 4px solid #F4B400; padding: 15px; border-radius: 12px; font-size: 0.9rem; }
        .dynamic-feedback { padding: 40px 20px; text-align: center; display: none; }
        .fb-circle { width: 60px; height: 60px; border-radius: 50%; background: #EEF2FB; color: #1F3C88; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 1.5rem; }
        .fb-circle.error { background: #FFEBEE; color: #C62828; }
        .fb-circle.success { background: #E8F5E9; color: #2E7D32; }
        #authArea, #feedbackArea, #welcomeArea { transition: opacity 0.3s ease, transform 0.3s ease; }
        .fade-in-up { animation: fadeInUp 0.4s ease forwards; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    
    <div class="intake-card w-100 bounce-in">
        <!-- Header -->
        <div class="p-4 text-center border-bottom bg-light">
            <?php if ($exam['logo_path']): ?>
                <img src="../<?php echo $exam['logo_path']; ?>" alt="Logo" class="mb-3" style="height: 60px;">
            <?php else: ?>
                <img src="../img/logo.png" alt="EduRemarks" class="mb-3" style="height: 60px;">
            <?php endif; ?>
            <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($exam['school_name']); ?></h5>
            <div class="badge bg-primary rounded-pill px-3">CBT PORTAL</div>
        </div>

        <div class="p-4">
            <!-- Global Info (Always visible) -->
            <div class="text-center mb-4">
                <h4 class="fw-bold"><?php echo htmlspecialchars($exam['title']); ?></h4>
                <div class="text-muted small">
                    <i class="fas fa-layer-group me-1"></i><?php echo get_label('Class'); ?>: <?php echo $exam['class_name']; ?> &bull; 
                    <i class="fas fa-book me-1"></i><?php echo get_label('Subject'); ?>: <?php echo $exam['subject_name']; ?>
                </div>
            </div>

            <!-- Login Area -->
            <div id="authArea">
                <div class="mb-4">
                    <label class="small fw-bold mb-1">Entrance Access (Admission Number) *</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-user-graduate"></i></span>
                        <input type="text" id="admission_no" class="form-control form-control-lg" placeholder="Enter Registration No." required>
                    </div>
                    <div class="form-text mt-2 small text-center"><i class="fas fa-info-circle me-1"></i> Please log in to view your eligibility and instructions.</div>
                </div>
                <button class="btn btn-primary btn-lg w-100 rounded-pill shadow" onclick="verifyStudent()">
                    Check Eligibility <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>

            <!-- Dynamic Feedback Area (Error/Mismatch) -->
            <div id="feedbackArea" class="dynamic-feedback">
                <div class="fb-circle error">
                    <i class="fas fa-exclamation-circle" id="fbIcon"></i>
                </div>
                <h5 class="fw-bold mb-3" id="fbTitle">Access Denied</h5>
                <p class="text-muted small mb-4" id="fbMessage">Message goes here.</p>
                <button class="btn btn-light rounded-pill px-4 btn-sm" onclick="resetPortal()">Try Again</button>
            </div>

            <!-- Welcome Area (Instructions shown only after successful auth) -->
            <div id="welcomeArea" style="display: none;" class="text-center">
                <div class="mb-3 text-success">
                    <i class="fas fa-check-circle" style="font-size: 3.5rem;"></i>
                </div>
                <h4 class="fw-bold mb-1">Welcome, <span id="studentName">Student Name</span>!</h4>
                <p class="text-muted small mb-4">You are verified for this <?php echo $term_lower; ?>.</p>
                
                <div class="instructions-box mb-4 text-start shadow-sm border-0">
                    <div class="fw-bold mb-2 text-primary"><i class="fas fa-info-circle me-1"></i><?php echo strtoupper($term); ?> INSTRUCTIONS:</div>
                    <div class="mb-3" style="max-height: 200px; overflow-y: auto;"><?php echo nl2br(htmlspecialchars($exam['instructions'])); ?></div>
                    <div class="small fw-bold p-2 bg-white rounded-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span><i class="fas fa-clock me-2 text-muted"></i>Duration:</span>
                            <span><?php echo $exam['duration_mins']; ?> Minutes</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span><i class="fas fa-calendar-times me-2 text-muted"></i>Deadline:</span>
                            <span class="text-danger"><?php echo date('h:i A', strtotime($exam['end_time'])); ?></span>
                        </div>
                    </div>
                </div>

                <button class="btn btn-warning btn-lg w-100 rounded-pill fw-bold shadow border-0" onclick="startExam()">
                    <i class="fas fa-play me-2"></i>START <?php echo strtoupper($term); ?> NOW
                </button>
            </div>
        </div>

        <div class="p-3 bg-light text-center small text-muted border-top">
            Powered by EduRemarks CBT &bull; Secure Assessment Environment
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const examToken = '<?php echo $token; ?>';
        const assessmentTerm = '<?php echo $term; ?>';
        let studentId = null;

        function verifyStudent() {
            const adm = document.getElementById('admission_no').value.trim();
            if (!adm) return;

            Spinner.show('Verifying details...');
            fetch('../ajax/cbt_student_auth.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `admission_no=${encodeURIComponent(adm)}&token=${examToken}`
            })
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                const authArea = document.getElementById('authArea');
                const welcomeArea = document.getElementById('welcomeArea');
                const feedbackArea = document.getElementById('feedbackArea');

                if (d.success) {
                    document.getElementById('studentName').textContent = d.student_name;
                    studentId = d.student_id;
                    authArea.style.display = 'none';
                    feedbackArea.style.display = 'none';
                    welcomeArea.style.display = 'block';
                } else {
                    const fbIcon = document.getElementById('fbIcon');
                    const fbTitle = document.getElementById('fbTitle');
                    const fbCircle = fbIcon.parentElement;
                    
                    // Reset classes
                    fbCircle.className = 'fb-circle error';
                    fbIcon.className = 'fas';

                    // Tailor the state based on error type
                    switch(d.type) {
                        case 'CLASS_MISMATCH':
                            fbIcon.classList.add('fa-user-shield');
                            fbTitle.textContent = "Class Mismatch";
                            break;
                        case 'TIME_EXPIRED':
                            fbIcon.classList.add('fa-hourglass-end');
                            fbTitle.textContent = assessmentTerm + " Closed";
                            break;
                        case 'NOT_STARTED':
                            fbIcon.classList.add('fa-clock');
                            fbTitle.textContent = "Not Started";
                            fbCircle.className = 'fb-circle text-primary'; // Blue for info
                            break;
                        case 'ALREADY_TAKEN':
                            fbIcon.classList.add('fa-check-double');
                            fbTitle.textContent = "Attempt Recorded";
                            fbCircle.className = 'fb-circle success';
                            break;
                        case 'INACTIVE':
                            fbIcon.classList.add('fa-lock');
                            fbTitle.textContent = "Restricted Access";
                            break;
                        default:
                            fbIcon.classList.add('fa-exclamation-circle');
                            fbTitle.textContent = "Access Denied";
                    }

                    document.getElementById('fbMessage').textContent = d.message;
                    authArea.style.display = 'none';
                    welcomeArea.style.display = 'none';
                    feedbackArea.style.display = 'block';
                }
            });
        }

        function resetPortal() {
            document.getElementById('authArea').style.display = 'block';
            document.getElementById('welcomeArea').style.display = 'none';
            document.getElementById('feedbackArea').style.display = 'none';
            document.getElementById('admission_no').value = '';
        }

        function startExam() {
            window.location.href = `exam.php?token=${examToken}&uid=${studentId}`;
        }
    </script>
</body>
</html>
