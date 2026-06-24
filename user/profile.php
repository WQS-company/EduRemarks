<?php
// user/profile.php
require_once '../includes/auth_check.php';

if ($role !== 'staff') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

$pageTitle = "My Profile";
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
        .profile-pic-container { position: relative; width: 150px; height: 150px; margin: 0 auto; }
        .profile-pic-preview { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid var(--white); box-shadow: var(--shadow-soft); }
        .edit-pic-btn { position: absolute; bottom: 5px; right: 5px; background: var(--accent-gold); color: var(--dark-text); border: none; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .edit-pic-btn:hover { transform: scale(1.1); background: #e5a900; }
        .settings-card { border-radius: 20px; }
        .nav-pills .nav-link { border-radius: 50px; padding: 10px 25px; font-weight: 600; color: var(--muted-text); }
        .nav-pills .nav-link.active { background: var(--primary-blue); color: var(--white); }
    </style>
    <script src="../js/security_ui.js"></script>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0">Personal Profile</h3>
                    <p class="text-muted small mb-0">Manage your identity and security settings within the institution.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="glass-card p-4 text-center settings-card shadow-sm">
                        <form id="profilePicForm" enctype="multipart/form-data">
                            <div class="profile-pic-container mb-4">
                                <img src="<?php echo $profile_picture; ?>" id="personalPicPreview" class="profile-pic-preview">
                                <label for="personalPicInput" class="edit-pic-btn">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" id="personalPicInput" name="profile_picture" accept="image/*" style="display:none;">
                            </div>
                        </form>
                        <h5 class="fw-bold mb-1"><?php echo $user_full_name; ?></h5>
                        <p class="text-muted small mb-4"><?php echo ucfirst($role); ?> — Educator</p>
                        
                        <div class="list-group list-group-flush text-start small border-top pt-3">
                            <div class="list-group-item bg-transparent border-0 px-0 d-flex justify-content-between">
                                <span class="text-muted">Institution ID</span>
                                <span class="fw-semibold"><?php echo $active_school['unique_id'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="list-group-item bg-transparent border-0 px-0 d-flex justify-content-between">
                                <span class="text-muted">Member Since</span>
                                <span class="fw-semibold"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="glass-card p-4 settings-card shadow-sm">
                        <ul class="nav nav-pills mb-4" id="settingsTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="pill" data-bs-target="#personal-content">
                                    <i class="fas fa-user-circle me-2"></i>Personal Info
                                </button>
                            </li>
                            <li class="nav-item ms-2">
                                <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security-content">
                                    <i class="fas fa-shield-alt me-2"></i>Security
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="settingsTabsContent">
                            <!-- Personal Info Tab -->
                            <div class="tab-pane fade show active" id="personal-content">
                                <form id="personalInfoForm">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Full Name</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user input-icon-box"></i></span>
                                                <input type="text" class="form-control" name="full_name" value="<?php echo $user_full_name; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Phone Number</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-phone input-icon-box"></i></span>
                                                <input type="text" class="form-control" name="phone" value="<?php echo $user_phone; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold">Email Address (Read Only)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-envelope input-icon-box"></i></span>
                                                <input type="email" class="form-control bg-light" value="<?php echo $user_email; ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-12 pt-3 border-top mt-4">
                                            <button type="submit" class="btn btn-primary rounded-pill px-5">Save Personal Info</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security-content">
                                <form id="securityForm">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Current Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-lock input-icon-box"></i></span>
                                                <input type="password" class="form-control" name="current_password" id="curr_pass_staff" required>
                                                <span class="input-group-text password-toggle" data-target="curr_pass_staff"><i class="fas fa-eye-slash"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">New Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-key input-icon-box"></i></span>
                                                <input type="password" class="form-control" name="new_password" id="new_pass_staff" required minlength="6">
                                                <span class="input-group-text password-toggle" data-target="new_pass_staff"><i class="fas fa-eye-slash"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Confirm New Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-shield-check input-icon-box"></i></span>
                                                <input type="password" class="form-control" name="confirm_password" id="conf_pass_staff" required minlength="6">
                                                <span class="input-group-text password-toggle" data-target="conf_pass_staff"><i class="fas fa-eye-slash"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-12 pt-3 border-top mt-4">
                                            <button type="submit" class="btn btn-primary rounded-pill px-5">Update Security</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>

    <div id="successOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.85); backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center; flex-direction:column;">
        <div class="reveal reveal-scale shadow-2xl" style="background:white; border-radius:40px; padding:50px; text-align:center; max-width:420px; width:90%; border:1px solid rgba(255,255,255,0.1);">
            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
            <h4 class="fw-bold mb-2 text-dark">Identity Updated</h4>
            <p class="text-muted small mb-4">Your staff credentials and profile information have been securely updated.</p>
            <button onclick="location.href='dashboard.php'" class="btn btn-primary rounded-pill px-5 py-3 fw-bold w-100 shadow-sm" style="background:var(--primary-blue); border:none; letter-spacing: 0.5px;">
                Return to Dashboard
            </button>
        </div>
    </div>

    
    <script>
        const picInput = document.getElementById('personalPicInput');
        const picPreview = document.getElementById('personalPicPreview');

        picInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                picPreview.src = event.target.result;
            };
            reader.readAsDataURL(file);

            Spinner.show('Uploading Photo...');
            const fd = new FormData();
            fd.append('profile_picture', file);
            fd.append('full_name', '<?php echo $user_full_name; ?>');
            
            fetch('../ajax/update_user_profile.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                Spinner.hide();
                if (d.success) {
                    document.getElementById('successOverlay').style.display = 'flex';
                    setTimeout(() => location.reload(), 2000);
                } else Notif.show(d.message, 'error');
            });
        });

        document.getElementById('personalInfoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            Spinner.show('Saving Info...');
            fetch('../ajax/update_user_profile.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json()).then(d => {
                Spinner.hide();
                if (d.success) {
                    document.getElementById('successOverlay').style.display = 'flex';
                    setTimeout(() => location.reload(), 2000);
                } else Notif.show(d.message, 'error');
            });
        });

        document.getElementById('securityForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            if (fd.get('new_password') !== fd.get('confirm_password')) {
                return Notif.show('Passwords do not match!', 'error');
            }
            Spinner.show('Updating Password...');
            fetch('../ajax/change_password.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json()).then(d => {
                Spinner.hide();
                if (d.success) {
                    Notif.show(d.message);
                    this.reset();
                } else Notif.show(d.message, 'error');
            });
        });
    </script>
</body>
</html>
