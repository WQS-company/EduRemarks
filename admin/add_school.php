<?php
// admin/add_school.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Institution | EduRemarks</title>
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

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0">Add New Institution</h3>
                    <p class="text-muted small mb-0">Expand your portfolio by registering a new school.</p>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="glass-card p-5">
                        <div class="text-center mb-5">
                            <div class="icon-box mx-auto mb-3" style="width: 70px; height: 70px; background: rgba(212, 175, 55, 0.1); color: var(--accent-gold);">
                                <i class="fas fa-plus-circle" style="font-size: 2rem;"></i>
                            </div>
                            <h4>Institutional Setup</h4>
                            <p class="text-muted">Enter the basic details to initialize your new school portal.</p>
                        </div>

                        <form id="addSchoolForm">
                            <div class="row g-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Official School Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="fas fa-university text-muted"></i></span>
                                        <input type="text" class="form-control border-start-0" name="school_name" required placeholder="e.g. EduRemarks International School">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">School Category</label>
                                    <select class="form-select" name="school_type" required>
                                        <option value="" selected disabled>Choose Level...</option>
                                        <option value="Nursery & Primary">Nursery & Primary</option>
                                        <option value="Secondary / High School">Secondary / High School</option>
                                        <option value="K-12 Combined">K-12 (Combined)</option>
                                        <option value="Tertiary / Vocational">Tertiary / Vocational</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Operational Region</label>
                                    <input type="text" class="form-control" name="region" placeholder="e.g. Lagos, Nigeria">
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold">Physical Business Address</label>
                                    <textarea class="form-control" name="school_address" rows="3" required placeholder="Full street address..."></textarea>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-warning border-0 small py-2">
                                        <i class="fas fa-lightbulb me-2"></i> After registration, a <strong>Unique School ID</strong> will be generated for staff to join.
                                    </div>
                                </div>

                                <div class="col-12 text-center mt-4">
                                    <button type="submit" class="btn btn-gold px-5 py-3 w-100 fw-bold shadow-sm">
                                        Initialize Institution Portal <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>



    <script>
        const addForm = document.getElementById('addSchoolForm');
        addForm.onsubmit = (e) => {
            e.preventDefault();
             Spinner.show('Configuring New Environment...');
            fetch('../ajax/add_school.php', { method: 'POST', body: new FormData(addForm) })
            .then(r => r.json()).then(d => {
                if (d.success) {
                    Notif.show(d.message);
                    setTimeout(() => {
                        // Switch to the newly created school if possible or just reload
                        location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    Notif.show(d.message, 'error');
                    Spinner.hide();
                }
            });
        };

</body>
</html>
