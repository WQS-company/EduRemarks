<?php
// admin/add_staff.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

$pageTitle = "Register New Staff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .registration-card { max-width: 600px; margin: 40px auto; border-radius: 24px; }
        .success-card { max-width: 500px; margin: 60px auto; border-radius: 24px; display: none; }
        .credential-box { background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 16px; transition: 0.3s; }
        .form-floating > .form-control:focus ~ label::after { background-color: transparent !important; }
    </style>
</head>
<body class="bg-light">

    <?php include '../includes/spinner.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <!-- Registration Form Area -->
            <div id="registrationArea">
                <div class="d-flex align-items-center mb-4">
                    <a href="staff.php" class="btn btn-light rounded-circle me-3" title="Back to Staff List">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                            <h3 class="fw-bold mb-0">Register <?php echo get_label('Staff'); ?> Member</h3>
                            <p class="text-muted small mb-0">Manually enroll a new educator or administrator into your institution.</p>
                        </div>
                    </div>

                    <div class="glass-card registration-card shadow-lg p-5">
                        <div class="text-center mb-5">
                            <div class="icon-box mx-auto bg-primary text-white mb-3" style="width: 70px; height: 70px; font-size: 1.8rem;">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h4 class="fw-bold">New <?php echo get_label('Staff'); ?> Enrollment</h4>
                        <p class="text-muted px-4">Fill in the details below. System will automatically generate secure login credentials.</p>
                    </div>

                    <form id="addStaffPageForm">
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-uppercase tracking-wider">Full Legal Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-4"><i class="fas fa-user text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 rounded-end-pill py-3" name="full_name" placeholder="Johnathan Doe" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-uppercase tracking-wider">Official Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-4"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" class="form-control border-start-0 rounded-end-pill py-3" name="email" placeholder="staff.member@yourschool.com" required>
                            </div>
                            <div class="form-text mt-2 ps-3">This will be used as their login identity.</div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold small text-uppercase tracking-wider">Phone Number (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-4"><i class="fas fa-phone text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 rounded-end-pill py-3" name="phone" placeholder="+234 ...">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold fs-5 shadow-sm" id="submitBtn">
                            <i class="fas fa-check-circle me-2"></i>Finalize Registration
                        </button>
                    </form>
                </div>
            </div>

            <!-- Success / Credentials Area -->
            <div id="successArea" class="success-card glass-card shadow-lg p-5 text-center">
                <div class="icon-box mx-auto bg-success text-white mb-4" style="width: 80px; height: 80px; font-size: 2.2rem;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="fw-bold mb-3">Registration Successful!</h3>
                <p class="text-muted mb-5">Professional staff profile created. Please secure these temporary credentials and share them with the staff member.</p>

                <div class="credential-box p-4 mb-5 text-start">
                    <div class="mb-4">
                        <label class="small text-muted text-uppercase fw-bold mb-1 d-block"><?php echo get_label('Staff'); ?> Username (Email)</label>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-5 text-dark" id="disp_email"></span>
                            <button class="btn btn-sm btn-light" onclick="copyText('disp_email')"><i class="far fa-copy"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="small text-muted text-uppercase fw-bold mb-1 d-block">Temporary Password</label>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-5 text-primary tracking-widest" id="disp_password"></span>
                            <button class="btn btn-sm btn-light" onclick="copyText('disp_password')"><i class="far fa-copy"></i></button>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-3">
                    <a href="staff.php" class="btn btn-gold rounded-pill py-3 fw-bold">Return to Dashboard</a>
                        <button class="btn btn-outline-secondary rounded-pill py-2" onclick="location.reload()">Register Another <?php echo get_label('Staff'); ?></button>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <script>
        document.getElementById('addStaffPageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const originalText = btn.innerHTML;
            
            Spinner.show('Processing Registration...');
            btn.disabled = true;

            const formData = new FormData(this);
            fetch('../ajax/add_staff.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                Spinner.hide();
                if (data.success) {
                    if (data.credentials) {
                        document.getElementById('disp_email').textContent = data.credentials.email;
                        document.getElementById('disp_password').textContent = data.credentials.password;
                        document.getElementById('registrationArea').style.display = 'none';
                        document.getElementById('successArea').style.display = 'block';
                    } else {
                        Notif.show('Staff added successfully');
                        setTimeout(() => window.location.href = 'staff.php', 1500);
                    }
                } else {
                    Notif.show(data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                Spinner.hide();
                Notif.show('A system error occurred.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        function copyText(elementId) {
            const text = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(text).then(() => {
                Notif.show('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>
