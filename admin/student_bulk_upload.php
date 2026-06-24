<?php
// admin/student_bulk_upload.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}
if (!$active_school) { header('Location: dashboard.php'); exit(); }

$school_id = $active_school['id'];

// Fetch classes for dropdown
$stmt2 = $pdo->prepare("SELECT id, name, code FROM classes WHERE school_id=? ORDER BY name");
$stmt2->execute([$school_id]);
$classes = $stmt2->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Student Upload | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }
        .glass-card { background: #fff; border-radius: 20px; border: 1px solid #eef2f6; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .upload-area { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 40px 20px; text-align: center; transition: 0.3s; cursor: pointer; background: #fcfdfe; }
        .upload-area:hover { border-color: #3b82f6; background: #f1f5f9; }
        .upload-icon { font-size: 3rem; color: #3b82f6; margin-bottom: 15px; }
        .step-pill { width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; margin-right: 12px; }
        .instruction-card { border-left: 4px solid #3b82f6; background: #eff6ff; padding: 20px; border-radius: 12px; }
        .btn-modern { font-weight: 700; padding: 12px 28px; border-radius: 50px; transition: 0.3s; }
        .btn-primary-modern { background: #2563eb; color: #fff; border: none; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2); }
        .btn-primary-modern:hover { background: #1d4ed8; transform: translateY(-2px); }
        
        @media (max-width: 768px) {
            .sa-main-content { padding: 15px !important; }
            .header-flex { flex-direction: column; align-items: flex-start !important; gap: 15px; }
        }
    </style>
</head>
<body class="bg-light">
<?php include '../includes/spinner.php'; ?>
<?php include '../includes/notifications.php'; ?>

<div class="dashboard-wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content p-3 p-md-4">
        <?php include '../includes/dashboard_top_nav.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 header-flex">
            <div>
                <a href="students.php" class="text-decoration-none small fw-bold text-muted mb-2 d-inline-block">
                    <i class="fas fa-arrow-left me-1"></i> Back to Students
                </a>
                <h3 class="fw-900 mb-0">Bulk Student Upload</h3>
                <p class="text-muted small">Onboard your students in seconds using our CSV tool.</p>
            </div>
            <a href="../ajax/get_student_template.php" class="btn btn-white border shadow-sm rounded-pill fw-bold">
                <i class="fas fa-download me-2 text-primary"></i> Download Template
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-card p-4">
                    <form id="bulkUploadForm">
                        <div class="mb-4">
                            <label class="form-label d-flex align-items-center fw-900 text-dark uppercase tracking-1" style="font-size:0.75rem;">
                                <span class="step-pill">1</span> Target Environment (Class)
                            </label>
                            <select class="form-select border-0 bg-light fw-bold p-3 rounded-4 shadow-none" name="class_id" id="class_id" required>
                                <option value="">— Select Target Class —</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-flex align-items-center fw-900 text-dark uppercase tracking-1" style="font-size:0.75rem;">
                                <span class="step-pill">2</span> Upload Manifest (CSV)
                            </label>
                            <input type="file" name="csv_file" id="csv_file" class="d-none" accept=".csv" required>
                            <div class="upload-area" id="dropArea" onclick="document.getElementById('csv_file').click()">
                                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <h5 class="fw-800" id="fileNameDisp">Drag & Drop CSV File</h5>
                                <p class="text-muted small mb-0">or click to browse from your device</p>
                            </div>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-modern btn-primary-modern py-3">
                                <i class="fas fa-rocket me-2"></i> INITIALIZE BULK UPLOAD
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results Display (Hidden initially) -->
                <div id="resultsCard" class="glass-card p-4 mt-4 d-none">
                    <h5 class="fw-900 mb-3"><i class="fas fa-poll me-2 text-primary"></i> Upload Summary</h5>
                    <div id="summaryText" class="alert alert-info rounded-4 fw-bold"></div>
                    <div id="errorLog" class="mt-3">
                         <h6 class="fw-800 text-danger mb-2">Errors Encountered:</h6>
                         <div class="list-group list-group-flush rounded-4 border overflow-hidden" id="errorList" style="max-height: 200px; overflow-y: auto;">
                         </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-card p-4 mb-4">
                    <h6 class="fw-900 mb-3 text-uppercase tracking-1" style="font-size:0.75rem;">Instructions</h6>
                    <div class="instruction-card">
                        <ul class="small fw-600 text-muted list-unstyled mb-0 d-flex flex-column gap-3">
                            <li class="d-flex align-items-start gap-2">
                                <i class="fas fa-check-circle text-primary mt-1"></i>
                                <span>Ensure your file is in <strong>CSV</strong> format.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="fas fa-check-circle text-primary mt-1"></i>
                                <span>Do not change the header names in the template.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="fas fa-check-circle text-primary mt-1"></i>
                                <span>Date of Birth should be in <strong>YYYY-MM-DD</strong> format.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="fas fa-check-circle text-primary mt-1"></i>
                                <span>Admission numbers must be unique within the school.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="glass-card p-4">
                    <h6 class="fw-900 mb-3 text-uppercase tracking-1" style="font-size:0.75rem;">Admission Logic</h6>
                    <div class="p-3 bg-light rounded-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-muted fw-800">Protocol:</span>
                            <span class="badge bg-primary rounded-pill"><?php echo strtoupper($active_school['adm_no_type']); ?></span>
                        </div>
                        <p class="extra-small text-muted mb-0 fw-600">
                            <?php if ($active_school['adm_no_type'] === 'manual'): ?>
                                You MUST provide admission numbers in the CSV file manually.
                            <?php else: ?>
                                If left blank in CSV, the system will auto-generate numbers based on your <strong><?php echo $active_school['adm_no_type']; ?></strong> protocol.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    const fileInput = document.getElementById('csv_file');
    const dropArea = document.getElementById('dropArea');
    const fileNameDisp = document.getElementById('fileNameDisp');

    fileInput.onchange = () => {
        if (fileInput.files.length) {
            fileNameDisp.innerText = fileInput.files[0].name;
            dropArea.style.borderColor = '#22c55e';
            dropArea.style.background = '#f0fdf4';
        }
    };

    dropArea.ondragover = (e) => { e.preventDefault(); dropArea.style.borderColor = '#3b82f6'; };
    dropArea.ondragleave = () => { dropArea.style.borderColor = '#cbd5e1'; };
    dropArea.ondrop = (e) => {
        e.preventDefault();
        fileInput.files = e.dataTransfer.files;
        fileInput.onchange();
    };

    document.getElementById('bulkUploadForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        Spinner.show('Processing Student Roster...');
        
        try {
            const resp = await fetch('../ajax/bulk_upload_students.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();
            Spinner.hide();

            if (data.success) {
                Notif.show(data.message, 'success');
                $('#resultsCard').removeClass('d-none');
                $('#summaryText').text(data.message);
                
                if (data.errors && data.errors.length > 0) {
                    $('#errorLog').show();
                    $('#errorList').empty();
                    data.errors.forEach(err => {
                        $('#errorList').append(`<div class="list-group-item small fw-600 text-danger bg-danger bg-opacity-10">${err}</div>`);
                    });
                } else {
                    $('#errorLog').hide();
                }
                
                // Scroll to results
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            } else {
                Notif.show(data.message, 'error');
            }
        } catch (err) {
            Spinner.hide();
            Notif.show('System Error: Could not connect to the cloud server.', 'error');
        }
    };
</script>
</body>
</html>
