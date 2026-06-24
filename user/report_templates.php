<?php
// user/report_templates.php - Template Orchestration Hub
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $_SESSION['school_id'];
$class_id = intval($_GET['class_id'] ?? 0);
$session_id = intval($_GET['session_id'] ?? 0);
$term_id = intval($_GET['term_id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);
$show_pos = intval($_GET['show_pos'] ?? 1);

if (!$class_id || !$session_id || !$term_id) {
    header('Location: report_management.php?class_id=' . $class_id);
    exit();
}

// Fetch class details
$cls_stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND school_id = ?");
$cls_stmt->execute([$class_id, $school_id]);
$class_name = $cls_stmt->fetchColumn() ?: 'Active Class';

$query_params = "class_id=$class_id&session_id=$session_id&term_id=$term_id&student_id=$student_id&show_pos=$show_pos";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Templates | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .template-card {
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            background: #fff;
            height: 100%;
            cursor: pointer;
            position: relative;
        }
        .template-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 30px 60px rgba(31, 60, 136, 0.18);
            border-color: var(--primary-blue);
        }
        .template-card.active {
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 4px rgba(244, 180, 0, 0.1);
        }
        .template-preview {
            height: 240px;
            background: linear-gradient(135deg, #f8faff 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            transition: 0.4s;
        }
        .template-preview i {
            font-size: 5rem;
            color: #cbd5e1;
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.05));
        }
        .template-card:hover .template-preview i {
            color: var(--primary-blue);
            transform: scale(1.2) rotate(5deg);
        }
        .template-info {
            padding: 24px;
        }
        .template-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 2;
        }
        .btn-select {
            width: 100%;
            border-radius: 12px;
            padding: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.75rem;
            transition: 0.3s;
        }
        .header-visual {
            background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%);
            color: white;
            padding: 60px 0;
            border-radius: 0 0 40px 40px;
            margin-bottom: -40px;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="header-visual text-center rounded-4 overflow-hidden mb-4">
            <div class="container py-4">
                <h2 class="fw-900 mb-2 uppercase tracking-2">Report Orchestration Templates</h2>
                <p class="opacity-75 fw-500 mb-0">Select a premium academic blueprint for <strong><?php echo htmlspecialchars($class_name); ?></strong></p>
            </div>
        </div>

        <div class="container-fluid py-2">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="report_management.php?class_id=<?php echo $class_id; ?>" class="btn btn-dark rounded-pill px-4 fw-800 shadow-sm btn-sm">
                    <i class="fas fa-arrow-left me-2"></i> BACK TO HUB
                </a>
                <div class="extra-small text-muted fw-800 uppercase tracking-2">System Version 4.8.2</div>
            </div>

            <div class="row g-4">
                <!-- Template 0: Classic -->
                <div class="col-md-6 col-lg-3">
                    <div class="template-card" onclick="location.href='generate_reports_pdf.php?<?php echo $query_params; ?>&template_id=0'">
                        <span class="badge bg-primary template-badge py-2 px-3 rounded-pill tiny-text">DEFAULT</span>
                        <div class="template-preview">
                            <i class="fas fa-file-invoice"></i>
                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-10 text-center tiny-text fw-bold">Academic Classic</div>
                        </div>
                        <div class="template-info">
                            <h6 class="fw-800 mb-2">Academic Classic</h6>
                            <p class="extra-small text-muted mb-4" style="line-height: 1.6;">The standard, high-readability layout optimized for mass institutional distribution. Reliable and clear.</p>
                            <button class="btn btn-dark btn-select mt-auto">GENERATE CLASSIC</button>
                        </div>
                    </div>
                </div>

                <!-- Template 1: Executive Premium (QR Code) -->
                <div class="col-md-6 col-lg-3">
                    <div class="template-card border-fine" onclick="location.href='generate_reports_pdf.php?<?php echo $query_params; ?>&template_id=1'">
                        <span class="badge bg-premium-gold template-badge py-2 px-3 rounded-pill tiny-text text-dark">PREMIUM</span>
                        <div class="template-preview" style="background: #1F3C88;">
                            <i class="fas fa-qrcode text-white opacity-25"></i>
                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-20 text-center tiny-text fw-bold text-white">Executive Hub (QR Code)</div>
                        </div>
                        <div class="template-info">
                            <h6 class="fw-800 mb-2">Executive Premium</h6>
                            <p class="extra-small text-muted mb-4" style="line-height: 1.6;">Advanced security with embedded verification QR code. Sleek dark accents and professional institutional branding.</p>
                            <button class="btn btn-primary btn-select mt-auto" style="background: var(--primary-blue);">GENERATE EXECUTIVE</button>
                        </div>
                    </div>
                </div>

                <!-- Template 2: Dynamic Matrix (Barcode) -->
                <div class="col-md-6 col-lg-3">
                    <div class="template-card" onclick="location.href='generate_reports_pdf.php?<?php echo $query_params; ?>&template_id=2'">
                        <span class="badge bg-success template-badge py-2 px-3 rounded-pill tiny-text">MODERN</span>
                        <div class="template-preview">
                            <i class="fas fa-barcode"></i>
                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-10 text-center tiny-text fw-bold">Dynamic Matrix (Barcode)</div>
                        </div>
                        <div class="template-info">
                            <h6 class="fw-800 mb-2">Dynamic Matrix</h6>
                            <p class="extra-small text-muted mb-4" style="line-height: 1.6;">Grid-optimized layout with archival barcode. Designed for data-heavy assessments and rapid digital sorting.</p>
                            <button class="btn btn-success btn-select mt-auto">GENERATE MATRIX</button>
                        </div>
                    </div>
                </div>

                <!-- Template 3: Minimalist Node -->
                <div class="col-md-6 col-lg-4">
                    <div class="template-card" onclick="location.href='generate_reports_pdf.php?<?php echo $query_params; ?>&template_id=3'">
                        <span class="badge bg-info template-badge py-2 px-3 rounded-pill tiny-text">LITE</span>
                        <div class="template-preview">
                            <i class="fas fa-layer-group"></i>
                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-10 text-center tiny-text fw-bold">Minimalist Node</div>
                        </div>
                        <div class="template-info">
                            <h6 class="fw-800 mb-2">Minimalist Node</h6>
                            <p class="extra-small text-muted mb-4" style="line-height: 1.6;">A modern, whitespace-optimized layout focusing on essential outcomes. Compact and high-fidelity typography.</p>
                            <button class="btn btn-info btn-select text-white mt-auto">GENERATE MINIMALIST</button>
                        </div>
                    </div>
                </div>

                <!-- Template 4: Spectrum Elite (Rainbow Style) -->
                <div class="col-md-6 col-lg-4">
                    <div class="template-card" onclick="location.href='generate_reports_pdf.php?<?php echo $query_params; ?>&template_id=4'">
                        <span class="badge bg-danger template-badge py-2 px-3 rounded-pill tiny-text">DYNAMIC</span>
                        <div class="template-preview" style="background: linear-gradient(45deg, #FF0000, #FF7F00, #FFFF00, #00FF00, #0000FF, #4B0082, #8F00FF); background-size: 400% 400%; animation: gradientBG 10s ease infinite;">
                            <i class="fas fa-palette text-white"></i>
                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-20 text-center tiny-text fw-bold text-white">Spectrum Elite (Rainbow)</div>
                        </div>
                        <div class="template-info">
                            <h6 class="fw-800 mb-2">Spectrum Elite</h6>
                            <p class="extra-small text-muted mb-4" style="line-height: 1.6;">A vibrant, multi-tone design representing a full spectrum of academic achievement. High-energy and inspirational.</p>
                            <button class="btn btn-danger btn-select mt-auto">GENERATE SPECTRUM</button>
                        </div>
                    </div>
                </div>

                <!-- Template 5: Institutional Laurel (Heritage Style) -->
                <div class="col-md-6 col-lg-4">
                    <div class="template-card" onclick="location.href='generate_reports_pdf.php?<?php echo $query_params; ?>&template_id=5'">
                        <span class="badge bg-secondary template-badge py-2 px-3 rounded-pill tiny-text">HERITAGE</span>
                        <div class="template-preview" style="background: #fdfaf3;">
                            <i class="fas fa-scroll text-dark opacity-25"></i>
                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-10 text-center tiny-text fw-bold">Institutional Laurel</div>
                        </div>
                        <div class="template-info">
                            <h6 class="fw-800 mb-2">Institutional Laurel</h6>
                            <p class="extra-small text-muted mb-4" style="line-height: 1.6;">Classical academic elegance with laurel motifs and wax-seal signatures. Perfect for prestigious traditional institutions.</p>
                            <button class="btn btn-secondary btn-select mt-auto">GENERATE HERITAGE</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include '../includes/dashboard_footer.php'; ?>
    </main>

    <?php include '../includes/staff_bottom_nav.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
