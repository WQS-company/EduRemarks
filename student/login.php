<?php
// student/login.php
session_start();
require_once '../config/db.php';
require_once '../includes/config.php';



if (isset($_SESSION['student_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($admission_no && $password) {
        $stmt = $pdo->prepare("SELECT s.*, sch.school_name, sch.logo_path, sch.feature_access FROM students s JOIN schools sch ON sch.id = s.school_id WHERE s.admission_no = ? AND s.portal_active = 1");
        $stmt->execute([$admission_no]);
        $student = $stmt->fetch();

        if ($student) {
            $features = explode(',', $student['feature_access'] ?? '');
            if (!in_array('STUDENT_PORTAL', $features)) {
                $error = "The student portal is currently not activated for your institution.";
            } elseif (password_verify($password, $student['student_password'])) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['student_school_id'] = $student['school_id'];
                $_SESSION['student_class'] = $student['student_class'];
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid admission number or password.";
            }
        } else {
            $error = "Invalid admission number or student record is inactive.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login | EduRemarks</title>
    <?php 
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : '../' . $sidebar_logo_raw;
    ?>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1F3C88;
            --secondary-blue: #2D6CDF;
            --accent-gold: #F4B400;
            --dark-text: #1E1E2F;
            --primary-gradient: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            --gold-gradient: linear-gradient(135deg, var(--accent-gold) 0%, #e5a900 100%);
            --glass: rgba(255, 255, 255, 0.98);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
            color: #1E1E2F;
        }
        .bg-pattern {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.3;
            z-index: -1;
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            padding: 30px 20px 0;
            text-align: center;
        }
        .school-logo {
            max-height: 40px;
            display: inline-block;
            margin-bottom: 5px;
        }
        .login-body {
            padding: 30px 25px;
        }
        .form-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group-custom .field-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            transition: 0.3s;
        }
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            transition: 0.3s;
            z-index: 10;
        }
        .password-toggle:hover {
            color: #3b82f6;
        }
        .form-control {
            padding: 12px 40px 12px 48px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        .form-control:focus {
            background: #fff;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(31, 60, 136, 0.1);
        }
        .form-control:focus ~ .field-icon {
            color: var(--primary-blue);
        }
        .btn-login {
            background: var(--gold-gradient);
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 700;
            color: var(--dark-text);
            width: 100%;
            letter-spacing: 1px;
            box-shadow: 0 10px 15px -3px rgba(244, 180, 0, 0.2);
            transition: 0.3s;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 20px -5px rgba(244, 180, 0, 0.3);
            color: #000;
        }
        .portal-meta {
            text-align: center;
            margin-top: 25px;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .error-toast {
            background: #fef2f2;
            color: #991b1b;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <div class="login-card">
        <div class="login-header">
            <div class="mb-3">
                <a href="../index.php">
                    <img src="../<?php echo get_setting('platform_logo', 'img/logo.png'); ?>" alt="EduRemarks" class="school-logo">
                </a>
            </div>
            <h3 class="fw-800 mb-1" style="letter-spacing: -0.5px; color: var(--primary-blue); font-size: 1.5rem;">Student Portal</h3>
            <p class="text-muted small mb-0 fw-500 uppercase tracking-2 opacity-75">Performance & Academic Hub</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-toast">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Admission Number</label>
                    <div class="input-group-custom">
                        <input type="text" name="admission_no" class="form-control" placeholder="e.g. ADM/2024/001" required>
                        <i class="fas fa-id-card field-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Secret Web Password</label>
                    <div class="input-group-custom">
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="••••••••" required>
                        <i class="fas fa-lock field-icon"></i>
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye-slash" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login uppercase">
                    Access My Dashboard <i class="fas fa-chevron-right ms-2 small"></i>
                </button>
            </form>
            
            <div class="portal-meta">
                <p class="mb-1">Secured by EduRemarks Intelligence</p>
                <a href="../index.php" class="text-primary text-decoration-none fw-700">Institution Home</a>
            </div>
        </div>
    </div>

    <!-- Professional Submission Overlay -->
    <div id="process-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; flex-direction: column;">
        <div class="loader-visual" style="position: relative; width: 80px; height: 80px; margin-bottom: 25px;">
            <div class="spinner-ring" style="position: absolute; width: 100%; height: 100%; border: 4px solid rgba(244, 180, 0, 0.1); border-top: 4px solid var(--accent-gold); border-radius: 50%; animation: auth-spin 1s linear infinite;"></div>
            <div class="spinner-core" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 12px; height: 12px; background: var(--accent-gold); border-radius: 50%; box-shadow: 0 0 15px var(--accent-gold);"></div>
        </div>
        <div class="loader-message text-center">
            <h5 class="text-white fw-900 mb-2 uppercase tracking-2" style="font-size: 0.9rem; letter-spacing: 3px;">AUTHENTICATING...</h5>
            <p class="text-white opacity-50 tiny-text uppercase tracking-1" style="font-size: 0.65rem;">Syncing Node Permissions</p>
        </div>
    </div>

    <style>
    @keyframes auth-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
    
    <script>
        function togglePassword() {
            const passField = document.getElementById('passwordField');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passField.type === 'password') {
                passField.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passField.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('process-overlay').style.display = 'flex';
        });
    </script>
</body>
</html>
