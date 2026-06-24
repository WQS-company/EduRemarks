<?php
// admin/edit_staff.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

$detail_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$staff = null;

if ($detail_id) {
    $stmt = $pdo->prepare("
        SELECT sd.id as detail_id, u.full_name, u.email, u.phone, sd.status 
        FROM staff_details sd 
        JOIN users u ON sd.user_id = u.id 
        WHERE sd.id = ? AND sd.school_id = ?
    ");
    $stmt->execute([$detail_id, $active_school_id]);
    $staff = $stmt->fetch();
}

if (!$staff) {
    header('Location: staff.php');
    exit();
}

// Handle Form Submission
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);

    try {
        $pdo->beginTransaction();

        // Update User table (name and phone)
        $stmt = $pdo->prepare("UPDATE users u JOIN staff_details sd ON u.id = sd.user_id SET u.full_name = ?, u.phone = ? WHERE sd.id = ?");
        $stmt->execute([$full_name, $phone, $detail_id]);

        $pdo->commit();
        $success_msg = "Staff profile updated successfully.";
        // Refresh local data
        $staff['full_name'] = $full_name;
        $staff['phone'] = $phone;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">

    <?php include '../includes/spinner.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="container-fluid py-4">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="d-flex align-items-center mb-4">
                            <a href="staff.php" class="btn btn-outline-primary rounded-circle me-3" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div>
                                <h3 class="fw-bold mb-0">Edit Staff Profile</h3>
                                <p class="text-muted small mb-0">Update information for <?php echo htmlspecialchars($staff['full_name']); ?></p>
                            </div>
                        </div>

                        <?php if($success_msg): ?>
                            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                                <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($error_msg): ?>
                            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
                            </div>
                        <?php endif; ?>

                        <div class="glass-card p-4 p-md-5 border-0 shadow-lg" style="border-radius: 30px; background: rgba(255, 255, 255, 0.9);">
                            <form method="POST">
                                <div class="row g-4">
                                    <div class="col-md-12 mb-2">
                                        <label class="form-label fw-bold text-dark small text-uppercase" style="letter-spacing: 1px;">Full Name</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text border-0 bg-light"><i class="fas fa-user text-primary opacity-50"></i></span>
                                            <input type="text" class="form-control border-0 bg-light py-2" name="full_name" value="<?php echo htmlspecialchars($staff['full_name']); ?>" required style="border-radius: 0 10px 10px 0;">
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <label class="form-label fw-bold text-dark small text-uppercase" style="letter-spacing: 1px;">Email Address</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text border-0 bg-light"><i class="fas fa-envelope text-primary opacity-50"></i></span>
                                            <input type="email" class="form-control border-0 bg-light py-2" value="<?php echo htmlspecialchars($staff['email']); ?>" readonly style="border-radius: 0 10px 10px 0; font-style: italic;">
                                        </div>
                                        <div class="extra-small text-muted mt-1 px-1">Identity emails are immutable.</div>
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <label class="form-label fw-bold text-dark small text-uppercase" style="letter-spacing: 1px;">Contact Phone</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text border-0 bg-light"><i class="fas fa-phone text-primary opacity-50"></i></span>
                                            <input type="text" class="form-control border-0 bg-light py-2" name="phone" value="<?php echo htmlspecialchars($staff['phone']); ?>" style="border-radius: 0 10px 10px 0;">
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold text-dark small text-uppercase" style="letter-spacing: 1px;">Access Status</label>
                                        <div class="d-flex align-items-center">
                                            <div class="badge <?php echo $staff['status'] === 'active' ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning'; ?> p-2 px-3 rounded-pill" style="font-size: 0.75rem; border: 1px solid currentColor;">
                                                <i class="fas <?php echo $staff['status'] === 'active' ? 'fa-check-double' : 'fa-hourglass-half'; ?> me-2"></i>
                                                <?php echo strtoupper($staff['status']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 pt-4 border-top mt-4 d-flex justify-content-between align-items-center">
                                        <a href="staff.php" class="btn btn-link text-muted btn-sm text-decoration-none fw-bold"><i class="fas fa-times me-2"></i>Discard</a>
                                        <button type="submit" class="btn btn-gold btn-sm rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-sync-alt me-2"></i>Update Profile
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if($success_msg): ?>
    <script>
        setTimeout(() => {
            window.location.href = 'staff.php';
        }, 2000);
    </script>
    <?php endif; ?>
</body>
</html>
