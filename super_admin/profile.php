<?php
// super_admin/profile.php - Super Admin Profile Management
require_once 'auth_check.php';

// Safe stats for profile overview
try {
    $total_schools = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM platform_payments WHERE status='success'")->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_schools = 0; $total_revenue = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | EduRemarks Orchestrator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1a4da1; --sa-bg: #f8fafc; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 220px; padding: 30px; transition: 0.3s; }
        .glass-card { border-radius: 20px; background: #fff; box-shadow: 0 10px 25px rgba(31, 60, 136, 0.05); border: none; }
        
        .profile-header-gradient {
            background: linear-gradient(135deg, #1a4da1 0%, #1e40af 100%);
            height: 180px;
            border-radius: 20px 20px 0 0;
            position: relative;
        }
        .profile-avatar-wrapper {
            position: absolute;
            bottom: -50px;
            left: 40px;
            z-index: 5;
        }
        .profile-avatar-wrapper img {
            width: 120px;
            height: 120px;
            border: 5px solid white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            object-fit: cover;
            border-radius: 30px;
            background: #fff;
        }
        .avatar-edit-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 35px;
            height: 35px;
            background: #F4B400;
            color: white;
            border: 3px solid white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
        }
        .avatar-edit-btn:hover { transform: scale(1.1); background: #e0a400; }
        
        .form-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
        .form-control { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 0.9rem; }
        .form-control:focus { border-color: var(--sa-blue); box-shadow: 0 0 0 4px rgba(26, 77, 161, 0.1); }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
            .profile-avatar-wrapper { left: 50%; transform: translateX(-50%); }
            .profile-header-gradient { height: 140px; }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="glass-card mb-4 overflow-hidden shadow-lg border-0">
                <div class="profile-header-gradient">
                    <div class="p-4 text-white opacity-50 small fw-bold uppercase tracking-1">Platform Orchestrator Node</div>
                </div>
                <div class="p-4 pt-5 mt-3 position-relative">
                    <div class="profile-avatar-wrapper">
                        <img src="<?php echo $profile_picture; ?>" id="avatarPreview">
                        <label for="avatarUpload" class="avatar-edit-btn">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="avatarUpload" hidden accept="image/*">
                    </div>
                    
                    <div class="ms-md-5 ps-md-5 pt-2 text-center text-md-start">
                        <h3 class="fw-900 text-blue mb-1"><?php echo htmlspecialchars($admin_full_name); ?></h3>
                        <p class="text-muted fw-600 mb-0">Root Authority &bull; System Super Admin</p>
                    </div>
                </div>
                
                <div class="p-4 p-md-5 border-top bg-light bg-opacity-50">
                    <form id="profileUpdateForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Identity Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin_full_name); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Secure Email Access</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Link</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Institutional Access Code</label>
                                <input type="text" class="form-control bg-white" value="#SA-<?php echo $_SESSION['user_id']; ?>" disabled>
                            </div>
                            
                            <hr class="my-4 opacity-5">
                            
                            <div class="col-md-12">
                                <h6 class="fw-800 text-blue mb-3 uppercase small tracking-1">Security Credentials (Leave blank to keep current)</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Secret Key (Password)</label>
                                <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Secret Key</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                            </div>
                            
                            <div class="col-12 text-end mt-5">
                                <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-800 shadow-sm transition hover-scale" id="updateBtn">
                                    SYNCHRONIZE PROFILE <i class="fas fa-sync-alt ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- System Stats Section -->
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="glass-card p-4 text-center">
                        <div class="h3 fw-900 text-blue mb-1">₦<?php echo number_format($total_revenue / 1000, 1); ?>K</div>
                        <div class="tiny-text fw-800 text-muted uppercase">Global Revenue</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 text-center">
                        <div class="h3 fw-900 text-premium-gold mb-1"><?php echo number_format($total_schools); ?></div>
                        <div class="tiny-text fw-800 text-muted uppercase">Active Institutions</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4 text-center">
                        <div class="h3 fw-900 text-success mb-1">Root</div>
                        <div class="tiny-text fw-800 text-muted uppercase">Permission Level</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/spinner.php'; ?>
<?php include '../includes/notifications.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Avatar Preview
    $('#avatarUpload').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#avatarPreview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });

    // Form Submit
    $('#profileUpdateForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#updateBtn');
        const formData = new FormData(this);
        const avatarFile = $('#avatarUpload')[0].files[0];
        if (avatarFile) formData.append('profile_picture', avatarFile);

        Spinner.show('Synchronizing profile nodes...');
        
        $.ajax({
            url: '../ajax/update_sa_profile.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                Spinner.hide();
                if (res.success) {
                    Notif.show(res.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Notif.show(res.message, 'error');
                }
            },
            error: function() {
                Spinner.hide();
                Notif.show('Communication failure with central node.', 'error');
            }
        });
    });
});
</script>
</body>
</html>
