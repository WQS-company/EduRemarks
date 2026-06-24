<?php
// admission_form.php - Public Admission Submission Portal
require_once 'includes/config.php';

$school_unique_id = $_GET['sid'] ?? '';

if (!$school_unique_id) {
    die("Institutional Node Not Specified. Access Denied.");
}

// Fetch School and Admission Config
$stmt = $pdo->prepare("
    SELECT s.*, af.is_active, af.title, af.description, af.requirements, af.target_session_id, af.max_slots, 
           af.theme_color, af.require_email, af.require_dob, af.require_address, af.declaration_text, af.success_message,
           af.contact_email, af.contact_phone,
           ash.name as session_name, s.feature_access
    FROM schools s
    JOIN admission_forms af ON af.school_id = s.id
    LEFT JOIN academic_sessions ash ON ash.id = af.target_session_id
    WHERE s.unique_id = ?
");
$stmt->execute([$school_unique_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Institutional Archive Not Found.");
}

$closed_mode = false;
$reason = "";

if (strpos($data['feature_access'], 'ADMISSION_PORTAL') === false) {
    $closed_mode = true;
    $reason = "This institution's admission portal is temporarily offline due to periodic maintenance. Please check back later.";
} elseif (!$data['is_active']) {
    $closed_mode = true;
    $reason = "The admission cycle for <strong>" . htmlspecialchars($data['school_name']) . "</strong> is currently deactivated.";
} else {
    // Check Slots
    if ($data['max_slots'] > 0) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM admission_applications WHERE school_id = ? AND session_id = ?");
        $count_stmt->execute([$data['id'], $data['target_session_id']]);
        $applied_count = $count_stmt->fetchColumn();
        
        if ($applied_count >= $data['max_slots']) {
            $closed_mode = true;
            $reason = "Admission Capacity Reached. All available slots for this intake cycle have been commissioned.";
        }
    }
}

// Fetch classes for this school
$cls_stmt = $pdo->prepare("SELECT id, name FROM classes WHERE school_id = ? ORDER BY name ASC");
$cls_stmt->execute([$data['id']]);
$classes = $cls_stmt->fetchAll();

$school_logo = (!empty($data['logo_path'])) ? $data['logo_path'] : 'img/logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($data['title']); ?> | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $data['theme_color'] ?: '#1F3C88'; ?>;
            --accent-color: #F4B400;
            --bg-gradient: linear-gradient(135deg, #f8faff 0%, #eef2fb 100%);
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #1e293b;
        }
        .form-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(31, 60, 136, 0.1);
            overflow: hidden;
        }
        .portal-banner {
            background: var(--primary-color);
            padding: 50px;
            color: white;
            text-align: center;
            position: relative;
        }
        .school-logo-lg {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            padding: 10px;
            margin-bottom: 20px;
            object-fit: contain;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .section-title {
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.75rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
        }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            font-weight: 500;
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(31, 60, 136, 0.1);
            border-color: var(--primary-color);
        }
        .btn-submit {
            background: var(--primary-color);
            color: white;
            border-radius: 15px;
            padding: 15px 40px;
            font-weight: 800;
            border: none;
            transition: 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(31, 60, 136, 0.2);
            color: white;
        }
        .guidelines-box {
            background: #f8faff;
            border-radius: 20px;
            padding: 25px;
            border: 1px dashed #cbd5e1;
        }
        .help-pill { 
            background: rgba(255,255,255,0.1); 
            border-radius: 50px; 
            padding: 5px 15px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            color: white;
            text-decoration: none;
        }
        .help-pill:hover { background: rgba(255,255,255,0.2); color: white; }
        .declaration-area { background: #fffbeb; border-radius: 15px; padding: 20px; border: 1px solid #fef3c7; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="form-container">
            <?php if ($closed_mode): ?>
                <div class="p-5 text-center">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" class="school-logo-lg">
                    <h2 class="fw-900 mb-3">Gateway Restricted</h2>
                    <p class="text-muted"><?php echo $reason ?: 'The admission cycle is currently closed.'; ?></p>
                    <hr class="my-4 opacity-50">
                    <div class="small fw-700 opacity-75">EDUCATIONAL INFRASTRUCTURE BY EDUREMARKS</div>
                </div>
            <?php else: ?>
                <div class="portal-banner">
                    <img src="<?php echo htmlspecialchars($school_logo); ?>" class="school-logo-lg">
                    <h1 class="fw-900 mb-2"><?php echo htmlspecialchars($data['title']); ?></h1>
                    <p class="opacity-75 mb-3"><?php echo htmlspecialchars($data['school_name']); ?> — <?php echo htmlspecialchars($data['session_name'] ?? 'Intake Cycle'); ?></p>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <?php if(!empty($data['contact_phone'])): ?>
                            <a href="tel:<?php echo $data['contact_phone']; ?>" class="help-pill"><i class="fas fa-phone-alt"></i> Help: <?php echo $data['contact_phone']; ?></a>
                        <?php endif; ?>
                        <?php if(!empty($data['contact_email'])): ?>
                            <a href="mailto:<?php echo $data['contact_email']; ?>" class="help-pill"><i class="fas fa-envelope"></i> Support</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-4 p-md-5">
                    <?php if (!empty($data['description'])): ?>
                        <div class="mb-5">
                            <h5 class="fw-800">Institutional Greeting</h5>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($data['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($data['requirements'])): ?>
                        <div class="guidelines-box mb-5">
                            <h6 class="fw-800 text-primary mb-3"><i class="fas fa-clipboard-list me-2"></i> GUIDELINES & REQUIREMENTS</h6>
                            <div class="small text-muted"><?php echo nl2br(htmlspecialchars($data['requirements'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <form id="publicAdmissionForm">
                        <input type="hidden" name="school_id" value="<?php echo $data['id']; ?>">
                        <input type="hidden" name="session_id" value="<?php echo $data['target_session_id']; ?>">
                        
                        <div class="section-title">Step 1: Applicant Biology</div>
                        <div class="row g-3 mb-5">
                            <div class="col-md-12">
                                <label class="small fw-800 mb-1">Full Name of Student (Legal) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="full_name" required placeholder="Surname first, then other names">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-800 mb-1">Gender <span class="text-danger">*</span></label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <?php if($data['require_dob']): ?>
                                <div class="col-md-6">
                                    <label class="small fw-800 mb-1">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="dob" required>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-12">
                                <label class="small fw-800 mb-1">Target Class for Admission <span class="text-danger">*</span></label>
                                <select class="form-select" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="section-title">Step 2: Guardian Communications</div>
                        <div class="row g-3 mb-5">
                            <div class="col-md-12">
                                <label class="small fw-800 mb-1">Parent / Guardian Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="parent_name" required placeholder="Title and Name">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-800 mb-1">Primary Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="parent_phone" required placeholder="For SMS updates">
                            </div>
                            <?php if($data['require_email']): ?>
                                <div class="col-md-6">
                                    <label class="small fw-800 mb-1">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="parent_email" required placeholder="example@domain.com">
                                </div>
                            <?php endif; ?>
                            <?php if($data['require_address']): ?>
                                <div class="col-md-12">
                                    <label class="small fw-800 mb-1">Residential Address <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="address" rows="3" required placeholder="Permanent home address"></textarea>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($data['declaration_text'])): ?>
                            <div class="declaration-area mb-5">
                                <div class="form-check p-0 ps-5">
                                    <input class="form-check-input" type="checkbox" id="declarationCheck" required style="width: 1.5em; height: 1.5em;">
                                    <label class="form-check-label small fw-800 ms-2" for="declarationCheck">Honorable Declaration</label>
                                    <div class="extra-small text-muted mt-2 lh-base"><?php echo nl2br(htmlspecialchars($data['declaration_text'])); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="text-center pt-3">
                            <button type="submit" class="btn btn-submit btn-lg px-5 shadow">
                                <i class="fas fa-paper-plane me-2"></i> TRANSMIT APPLICATION
                            </button>
                            <p class="extra-small text-muted mt-3">By submitting, you agree to the institutional processing of your data for educational assessment.</p>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Professional Submission Overlay -->
            <div id="process-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; flex-direction: column;">
                <div class="loader-visual" style="position: relative; width: 80px; height: 80px; margin-bottom: 25px;">
                    <div class="spinner-ring" style="position: absolute; width: 100%; height: 100%; border: 4px solid rgba(255, 255, 255, 0.1); border-top: 4px solid var(--primary-color); border-radius: 50%; animation: auth-spin 1s linear infinite;"></div>
                    <div class="spinner-core" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%; box-shadow: 0 0 15px var(--primary-color);"></div>
                </div>
                <div class="loader-message text-center">
                    <h5 class="text-white fw-900 mb-2 uppercase tracking-2" style="font-size: 0.9rem; letter-spacing: 3px;">TRANSMITTING...</h5>
                    <p class="text-white opacity-50 tiny-text uppercase tracking-1" style="font-size: 0.65rem;">Synchronizing with Registry</p>
                </div>
            </div>

            <style>
            @keyframes auth-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            </style>

            <div class="bg-light p-4 text-center border-top">
                <div class="d-flex justify-content-center gap-3 mb-2">
                    <span class="badge bg-white text-dark border extra-small fw-800 rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-shield-alt text-success me-1"></i> VERIFIED INSTITUTION</span>
                    <span class="badge bg-white text-dark border extra-small fw-800 rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-sync text-primary fa-spin me-1" style="animation-duration: 3s;"></i> REAL-TIME SYNC</span>
                </div>
                <div class="extra-small fw-800 text-muted uppercase tracking-2">Powered by EduRemarks Global Ed-Tech Infrastructure</div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg text-center p-5" style="border-radius: 30px;">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                </div>
                <h2 class="fw-900 mb-2">Transmission Successful!</h2>
                <p class="text-muted mb-4"><?php echo !empty($data['success_message']) ? nl2br(htmlspecialchars($data['success_message'])) : 'Your application dossier has been synchronized with the institutional registry. The management will review and contact you via your provided phone number.'; ?></p>
                <button type="button" class="btn btn-primary rounded-pill px-5 fw-800 py-3" onclick="location.reload()">OKAY, GREAT!</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
            $('#publicAdmissionForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const overlay = $('#process-overlay');
                const data = $(this).serialize();
                
                overlay.css('display', 'flex');
                btn.prop('disabled', true);
                
                $.post('ajax/admission_handler.php', {
                    action: 'submit_application',
                    data: data
                }, function(res) {
                    overlay.hide();
                    if(res.success) {
                        new bootstrap.Modal('#successModal').show();
                        $('#publicAdmissionForm')[0].reset();
                    } else {
                        alert(res.message);
                    }
                    btn.prop('disabled', false);
                }, 'json');
            });
    </script>
</body>
</html>
