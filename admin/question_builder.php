<?php
// admin/question_builder.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}

if (!$active_school) {
    header('Location: dashboard.php');
    exit();
}

// 1. Core Feature Access Guard: The school must have the CBT_EXAMS feature activated by Super Admin
if (strpos($active_school['feature_access'] ?? '', 'CBT_EXAMS') === false) {
    header('Location: ../dashboard.php');
    exit();
}

// 2. Individual Permission Guard: Staff must be specifically granted 'can_manage_cbt' by the School Owner
if ($role === 'staff' && empty($staff_permissions['can_manage_cbt'])) {
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $active_school['id'];

require_once '../includes/functions.php';

// Fetch School Credits
$stmt_credits = $pdo->prepare("SELECT credits FROM schools WHERE id = ?");
$stmt_credits->execute([$school_id]);
$school_credits = $stmt_credits->fetchColumn();

// Fetch Platform Pricing for Answer Booklets
$credit_rate = getCreditRate('credit_answer_sheet', $pdo);

// Fetch Classes with Student Counts
$stmt_classes = $pdo->prepare("
    SELECT c.id, c.name, COUNT(sc.student_id) as student_count 
    FROM classes c
    LEFT JOIN student_classes sc ON c.id = sc.class_id AND sc.school_id = c.school_id
    WHERE c.school_id = ? 
    GROUP BY c.id
    ORDER BY c.name
");
$stmt_classes->execute([$school_id]);
$classes = $stmt_classes->fetchAll();

// Fetch Subjects
$stmt_subjects = $pdo->prepare("SELECT id, name FROM subjects WHERE school_id = ? ORDER BY name");
$stmt_subjects->execute([$school_id]);
$subjects = $stmt_subjects->fetchAll();

// Fetch Sessions
$stmt_sessions = $pdo->prepare("SELECT id, name FROM academic_sessions WHERE school_id = ? ORDER BY name DESC");
$stmt_sessions->execute([$school_id]);
$sessions = $stmt_sessions->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Builder | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; }
        .glass-card { background: #fff; border-radius: 20px; border: 1px solid #eef2f6; box-shadow: 0 4px 25px rgba(0,0,0,0.02); transition: 0.3s; }
        .question-block { border-left: 4px solid #3b82f6 !important; background: #fdfdfd; margin-bottom: 20px; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:translateY(0);} }
        .block-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 15px; }
        .type-pill { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; padding: 4px 10px; border-radius: 50px; background: #eff6ff; color: #3b82f6; }
        .guide-box { background: #0f172a; color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; }
        .btn-modern { font-weight: 800; border-radius: 12px; padding: 10px 20px; transition: 0.3s; }
        .btn-modern:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-label { font-weight: 700; color: #475569; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .sticky-controls { position: sticky; top: 10px; z-index: 100; }
        
        /* Mobile overrides */
        @media (max-width: 576px) {
            .btn-modern { width: 100%; margin-bottom: 10px; font-size: 0.7rem !important; white-space: nowrap; padding-left: 10px; padding-right: 10px; }
            .header-info { text-align: center; }
            .type-pill { font-size: 0.55rem; }
        }

        .q-image-preview { max-width: 100%; max-height: 200px; border-radius: 12px; margin-top: 10px; display: none; object-fit: contain; border: 1px solid #eef2f6; }
        .image-upload-btn { font-size: 0.7rem; font-weight: 700; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; transition: 0.2s; }
        .image-upload-btn:hover { color: #3b82f6; }
        .remove-img-btn { font-size: 0.65rem; color: #ef4444; cursor: pointer; display: none; margin-top: 5px; font-weight: 700; }
    </style>
</head>
<body class="bg-light">

    <?php include '../includes/spinner.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="header-info">
                    <h3 class="fw-900 mb-0">Exam Question Builder</h3>
                    <p class="text-muted small fw-600 mb-0">Construct professional examination papers for your institution.</p>
                </div>
                <div class="d-flex gap-2 w-mobile-100">
                    <button class="btn btn-outline-dark btn-modern" onclick="showGuide()"><i class="fas fa-info-circle me-1"></i> Build Guide</button>
                    <button class="btn btn-primary btn-modern shadow-lg" style="font-size: 0.85rem;" onclick="triggerGenerate()"><i class="fas fa-print me-1"></i> Generate Paper</button>
                </div>
            </div>

            <!-- Dashboard Bottom Tabs for Mobile -->
            <?php 
                if ($role === 'staff') {
                    // Handled by staff_sidebar.php inclusion if global, but ensuring it shows here
                    // Actually, bottom navs are usually in the sidebar files
                }
            ?>

            <div class="row g-4">
                <div class="col-lg-4 order-lg-2">
                    <div class="sticky-controls">
                        <div class="glass-card p-4 mb-4">
                            <h6 class="fw-900 text-dark mb-3">Institutional Metadata</h6>
                            <div class="mb-3">
                                <label class="form-label"><?php echo get_label('Subject'); ?> Context</label>
                                <select class="form-select border-0 bg-light fw-bold" id="examSubject">
                                    <option value="">-- Choose <?php echo get_label('Subject'); ?> --</option>
                                    <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Active Session</label>
                                <select class="form-select border-0 bg-light fw-bold" id="examSession">
                                    <option value="">-- Choose Session --</option>
                                    <?php foreach ($sessions as $sess): ?>
                                    <option value="<?php echo htmlspecialchars($sess['name']); ?>"><?php echo htmlspecialchars($sess['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?php echo get_label('Term'); ?> Designation</label>
                                <select class="form-select border-0 bg-light fw-bold" id="examTerm">
                                    <option value="1st Term">1st <?php echo get_label('Term'); ?></option>
                                    <option value="2nd Term">2nd <?php echo get_label('Term'); ?></option>
                                    <?php if(get_label('Term') === 'Term'): ?>
                                    <option value="3rd Term">3rd <?php echo get_label('Term'); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assessment Type</label>
                                <select class="form-select border-0 bg-light fw-bold" id="examType">
                                    <option value="test">Mock Test / Continuous Assessment</option>
                                    <option value="exam">Terminal Examination</option>
                                </select>
                            </div>
                        </div>

                        <div class="glass-card p-4">
                            <h6 class="fw-900 text-dark mb-3">General Instructions</h6>
                            <textarea class="form-control border-0 bg-light fw-bold" id="examInstructions" rows="4" placeholder="Enter instructions (e.g., Answer all questions in section A...)"></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 order-lg-1">
                    <div id="questionsContainer">
                        <!-- Questions will be dynamically added here -->
                        <div class="glass-card p-5 text-center mb-4" id="emptyState">
                            <i class="fas fa-layer-group fa-3x text-light mb-3"></i>
                            <h5 class="fw-900">Your Builder is Empty</h5>
                            <p class="text-muted small">Choose a question type from below to start building your examination paper.</p>
                        </div>
                    </div>

                    <div class="glass-card p-4 mt-4 bg-white border-dashed text-center">
                        <div class="row g-2 justify-content-center">
                            <div class="col-md-3 col-6">
                                <button class="btn btn-outline-primary w-100 btn-sm py-2 px-1 fw-800 rounded-pill" onclick="addQuestion('objective')">
                                    <i class="fas fa-check-circle d-block mb-1"></i> OBJECTIVE
                                </button>
                            </div>
                            <div class="col-md-3 col-6">
                                <button class="btn btn-outline-primary w-100 btn-sm py-2 px-1 fw-800 rounded-pill" onclick="addQuestion('essay')">
                                    <i class="fas fa-paragraph d-block mb-1"></i> ESSAY
                                </button>
                            </div>
                            <div class="col-md-3 col-6">
                                <button class="btn btn-outline-primary w-100 btn-sm py-2 px-1 fw-800 rounded-pill" onclick="addQuestion('tf')">
                                    <i class="fas fa-toggle-on d-block mb-1"></i> TRUE/FALSE
                                </button>
                            </div>
                            <div class="col-md-3 col-6">
                                <button class="btn btn-outline-primary w-100 btn-sm py-2 px-1 fw-800 rounded-pill" onclick="addQuestion('fill_in_the_blank')">
                                    <i class="fas fa-underline d-block mb-1"></i> FILL BLANKS
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Generator Modal -->
    <div class="modal fade" id="generateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0 shadow-lg" style="border-radius: 30px;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <h5 class="modal-title fw-900 text-dark"><i class="fas fa-print text-primary me-2"></i>Generation Hub</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-4">
                        <label class="form-label">Generation Mode</label>
                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="gen_mode" id="modePlain" value="plain" checked>
                                <label class="btn btn-outline-primary w-100 fw-bold py-3 rounded-4" for="modePlain">
                                    <i class="fas fa-file-alt d-block mb-1"></i> Plain Questions
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="gen_mode" id="modeBooklet" value="booklet">
                                <label class="btn btn-outline-primary w-100 fw-bold py-3 rounded-4" for="modeBooklet">
                                    <i class="fas fa-book-open d-block mb-1"></i> Student Booklet
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="plainOptions">
                        <div class="mb-4">
                            <label class="form-label">Number of Copies <span class="text-muted">(Pages)</span></label>
                            <input type="number" class="form-control bg-light border-0 fw-bold py-3 px-4 rounded-4" id="plainCopies" value="1" min="1" max="100">
                            <div class="form-text mt-2 extra-small text-muted"><i class="fas fa-info-circle me-1"></i> Specify how many duplicates to generate in this session.</div>
                        </div>
                    </div>

                    <div id="bookletOptions" style="display:none;">
                        <div class="mb-4">
                            <label class="form-label">Target <?php echo get_label('Class'); ?></label>
                            <select class="form-select bg-light border-0 fw-bold py-3 px-4 rounded-4 mb-3" id="targetClass" onchange="toggleBookletCopies(this.value); computeCredits();">
                                <option value="" data-count="0">-- No Specific <?php echo get_label('Class'); ?> (Blank Booklets) --</option>
                                <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-count="<?php echo $c['student_count']; ?>"><?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['student_count']; ?> <?php echo get_label('Pupils'); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div id="bookletCopiesWrapper" style="display: block;">
                                <label class="form-label">Number of Blank Booklets</label>
                                <input type="number" class="form-control bg-light border-0 fw-bold py-3 px-4 rounded-4" id="bookletCopies" value="1" min="1" max="1000" oninput="computeCredits()">
                                <div class="form-text mt-2 extra-small text-muted"><i class="fas fa-info-circle me-1"></i> Specify how many blank booklets to generate.</div>
                            </div>

                            <div class="form-text mt-2 extra-small text-muted"><i class="fas fa-info-circle me-1"></i> Booklet mode generates individual sheets with student metadata when a class is selected.</div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Numbering Orchestration</label>
                        <select class="form-select bg-light border-0 fw-900 py-3 px-4 rounded-4" id="numFormat">
                            <option value="1">Numeric System (1, 2, 3...)</option>
                            <option value="A">Alphanumeric (A, B, C...)</option>
                            <option value="i">Roman Classical (i, ii, iii...)</option>
                        </select>
                    </div>

                    <!-- Dynamic Credit Projection UI -->
                    <?php if ($role !== 'super_admin'): ?>
                    <div class="mt-4 p-3 rounded-4 bg-light border" id="creditProjectionBox">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold small text-muted text-uppercase tracking-1"><i class="fas fa-bolt text-warning me-1"></i> Projected Cost</span>
                            <span class="fw-900 text-dark" id="projectedCost">0.00 Credits</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                            <span class="fw-bold small text-muted text-uppercase tracking-1"><i class="fas fa-wallet text-success me-1"></i> Your Balance</span>
                            <span class="fw-900 text-dark"><?php echo number_format($school_credits, 2); ?> Credits</span>
                        </div>
                        <div id="creditWarning" class="text-danger small fw-bold" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-1"></i> Insufficient credits for this operation.
                        </div>
                        <div id="creditSafe" class="text-success small fw-bold" style="display: none;">
                            <i class="fas fa-check-circle me-1"></i> Sufficient credits available.
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer border-0 p-4">
                    <button class="btn btn-primary fw-900 rounded-pill w-100 py-3 shadow-sm" onclick="finalizeGeneration()">
                        <i class="fas fa-sync-alt me-2"></i> INITIALIZE ENGINE
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Build Guide Modal -->
    <div class="modal fade" id="guideModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0 shadow-lg overflow-hidden" style="border-radius: 24px;">
                <div class="guide-box mb-0 rounded-0">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center p-3 me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-brain h4 mb-0"></i>
                        </div>
                        <div>
                            <h4 class="fw-900 mb-0">Professional Builder Guide</h4>
                            <p class="mb-0 extra-small opacity-75">Operational procedures for elite exam construction.</p>
                        </div>
                    </div>
                    
                    <div class="guide-scroll pe-2" style="max-height: 400px; overflow-y: auto;">
                        <div class="mb-4">
                            <h6 class="fw-bold text-premium-gold"><i class="fas fa-pen-alt me-2"></i>Construction Workflow</h6>
                            <p class="extra-small opacity-80 mb-2">Build your sequence by adding questions using the action deck below. You can combine multiple types (e.g. 10 Objectives, then 5 Essays) in any order.</p>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="fw-bold text-premium-gold"><i class="fas fa-layer-group me-2"></i>Generation Modes</h6>
                            <ul class="extra-small opacity-80 list-unstyled ps-0">
                                <li class="mb-2"><i class="fas fa-file-alt me-1 text-primary"></i> <strong>Plain:</strong> A master copy for mass reproduction or general viewing.</li>
                                <li class="mb-2"><i class="fas fa-book-open me-1 text-primary"></i> <strong>Booklet:</strong> Individualized papers containing pre-filled student names and admission numbers for a personalized exam environment.</li>
                            </ul>
                        </div>

                        <div class="mb-0">
                            <h6 class="fw-bold text-premium-gold"><i class="fas fa-sort-numeric-down me-2"></i>Orchestration</h6>
                            <p class="extra-small opacity-80">Choose your preferred numbering logic. The system will automatically handle the rendering across all pages of your generated document.</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-top border-secondary border-opacity-25 w-100">
                        <button class="btn btn-outline-light rounded-pill px-5 fw-900 w-100 py-3" data-bs-dismiss="modal">I UNDERSTAND</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CURRENT_BALANCE = <?php echo floatval($school_credits ?? 0); ?>;
        const CREDIT_RATE = <?php echo floatval($credit_rate ?? 1.0); ?>;
        const IS_SUPER_ADMIN = <?php echo ($role === 'super_admin') ? 'true' : 'false'; ?>;

        let questionId = 0;

        function addQuestion(type) {
            document.getElementById('emptyState').style.display = 'none';
            questionId++;
            const rid = questionId;
            let typeLabel = '';
            let innerHtml = '';

            switch(type) {
                case 'objective':
                    typeLabel = 'Objective / MCQ';
                    innerHtml = `
                        <div class="mb-3">
                            <label class="form-label small">Question Stem</label>
                            <textarea class="form-control border-0 bg-light fw-bold" name="q_text_${rid}" rows="2" placeholder="e.g. What is the powerhouse of the cell?"></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><input type="text" class="form-control form-control-sm border-0 bg-light" name="q_opt_a_${rid}" placeholder="Option A"></div>
                            <div class="col-6"><input type="text" class="form-control form-control-sm border-0 bg-light" name="q_opt_b_${rid}" placeholder="Option B"></div>
                            <div class="col-6"><input type="text" class="form-control form-control-sm border-0 bg-light" name="q_opt_c_${rid}" placeholder="Option C"></div>
                            <div class="col-6"><input type="text" class="form-control form-control-sm border-0 bg-light" name="q_opt_d_${rid}" placeholder="Option D"></div>
                        </div>
                    `;
                    break;
                case 'essay':
                    typeLabel = 'Essay / Theory';
                    innerHtml = `
                        <div class="mb-3">
                            <label class="form-label small">Question Prompt</label>
                            <textarea class="form-control border-0 bg-light fw-bold" name="q_text_${rid}" rows="3" placeholder="e.g. Explain the process of photosynthesis in detail."></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Allocate Answer Space</label>
                                <select class="form-select border-0 bg-light fw-bold" name="q_space_${rid}" onchange="toggleCustomSpace(${rid}, this.value)">
                                    <option value="0.5">Half Page (Standard)</option>
                                    <option value="1">1 Full Page</option>
                                    <option value="1.5">1.5 Pages</option>
                                    <option value="2">2 Full Pages</option>
                                    <option value="0.25">Quarter Page</option>
                                    <option value="custom">-- Custom Pages --</option>
                                </select>
                            </div>
                            <div class="col-6" id="custom_space_wrapper_${rid}" style="display:none;">
                                <label class="form-label small">Number of Pages</label>
                                <input type="number" step="0.5" min="0.5" class="form-control border-0 bg-light fw-bold" name="q_custom_space_${rid}" placeholder="e.g. 5">
                            </div>
                        </div>
                    `;
                    break;
                case 'tf':
                    typeLabel = 'True or False';
                    innerHtml = `
                        <div class="mb-3">
                            <label class="form-label small">Statement</label>
                            <input type="text" class="form-control border-0 bg-light fw-bold" name="q_text_${rid}" placeholder="e.g. Water boils at 100 degrees Celsius at sea level.">
                        </div>
                    `;
                    break;
                case 'fill_in_the_blank':
                    typeLabel = 'Fill in the Blank';
                    innerHtml = `
                        <div class="mb-3">
                            <label class="form-label small">Incomplete Statement</label>
                            <textarea class="form-control border-0 bg-light fw-bold" name="q_text_${rid}" rows="2" placeholder="e.g. The _________ is the largest planet in our solar system."></textarea>
                        </div>
                    `;
                    break;
            }

            const html = `
                <div class="glass-card p-4 question-block" id="qblock-${rid}" data-type="${type}">
                    <div class="block-header">
                        <span class="type-pill">${typeLabel}</span>
                        <div class="d-flex align-items-center gap-2">
                            <label for="img_input_${rid}" class="image-upload-btn mb-0">
                                <i class="fas fa-image"></i> Add Image
                            </label>
                            <input type="file" id="img_input_${rid}" style="display:none;" accept="image/*" onchange="handleImageUpload(this, ${rid})">
                            <button class="btn btn-sm btn-outline-danger border-0 rounded-circle" onclick="removeQuestion(${rid})"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    ${innerHtml}
                    <div class="mt-2">
                        <img src="" id="img_preview_${rid}" class="q-image-preview">
                        <div id="remove_img_${rid}" class="remove-img-btn" onclick="removeImage(${rid})"><i class="fas fa-trash-alt me-1"></i> Remove Image</div>
                    </div>
                </div>
            `;
            document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', html);
        }

        function handleImageUpload(input, rid) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(`img_preview_${rid}`);
                    const removeBtn = document.getElementById(`remove_img_${rid}`);
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    removeBtn.style.display = 'inline-block';
                    // We store the base64 in a data attribute
                    document.getElementById(`qblock-${rid}`).setAttribute('data-image', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage(rid) {
            const preview = document.getElementById(`img_preview_${rid}`);
            const removeBtn = document.getElementById(`remove_img_${rid}`);
            const input = document.getElementById(`img_input_${rid}`);
            const block = document.getElementById(`qblock-${rid}`);
            
            preview.src = "";
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
            input.value = "";
            block.removeAttribute('data-image');
        }

        function toggleCustomSpace(rid, value) {
            const wrapper = document.getElementById(`custom_space_wrapper_${rid}`);
            if (value === 'custom') {
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
            }
        }

        function removeQuestion(rid) {
            document.getElementById(`qblock-${rid}`).remove();
            if (document.querySelectorAll('.question-block').length === 0) {
                document.getElementById('emptyState').style.display = 'block';
            }
        }

        function showGuide() {
            new bootstrap.Modal(document.getElementById('guideModal')).show();
        }

        function triggerGenerate() {
            if (document.querySelectorAll('.question-block').length === 0) {
                Notif.show('Add at least one question to the builder.', 'warning');
                return;
            }
            new bootstrap.Modal(document.getElementById('generateModal')).show();
        }

        $('input[name="gen_mode"]').on('change', function() {
            if (this.value === 'booklet') {
                $('#bookletOptions').slideDown();
                $('#plainOptions').slideUp();
            } else {
                $('#bookletOptions').slideUp();
                $('#plainOptions').slideDown();
            }
            computeCredits();
        });

        function toggleBookletCopies(val) {
            if (val === '') {
                document.getElementById('bookletCopiesWrapper').style.display = 'block';
            } else {
                document.getElementById('bookletCopiesWrapper').style.display = 'none';
            }
            computeCredits();
        }

        function computeCredits() {
            if (IS_SUPER_ADMIN) return;

            const mode = $('input[name="gen_mode"]:checked').val();
            let cost = 0;

            if (mode === 'booklet') {
                const targetClass = $('#targetClass').val();
                let bookletsToGenerate = 0;

                if (targetClass) {
                    // Get student count from data attribute
                    bookletsToGenerate = parseInt($('#targetClass option:selected').attr('data-count')) || 0;
                } else {
                    // Get blank booklet copies
                    bookletsToGenerate = parseInt($('#bookletCopies').val()) || 1;
                }

                cost = bookletsToGenerate * CREDIT_RATE;
            }

            // Plain mode is completely free
            
            $('#projectedCost').text(cost.toFixed(2) + ' Credits');

            if (cost > CURRENT_BALANCE) {
                $('#creditWarning').show();
                $('#creditSafe').hide();
                $('#projectedCost').removeClass('text-dark').addClass('text-danger');
            } else {
                $('#creditWarning').hide();
                $('#creditSafe').show();
                $('#projectedCost').removeClass('text-danger').addClass('text-dark');
            }
        }

        // Run compute credits immediately when modal is about to show
        document.getElementById('generateModal').addEventListener('show.bs.modal', function () {
            computeCredits();
        });

        function finalizeGeneration() {
            const mode = $('input[name="gen_mode"]:checked').val();
            const targetClass = $('#targetClass').val();
            const plainCopies = $('#plainCopies').val();
            const bookletCopies = $('#bookletCopies').val();
            const numFormat = $('#numFormat').val();
            const subject = $('#examSubject').val();
            const session = $('#examSession').val();
            const term = $('#examTerm').val();
            const type = $('#examType').val();
            const instructions = $('#examInstructions').val();

            // Collect all questions
            const questions = [];
            $('.question-block').each(function() {
                const rid = this.id.split('-')[1];
                const type = $(this).data('type');
                const qText = $(this).find(`[name="q_text_${rid}"]`).val();
                const qImage = $(this).attr('data-image') || null;
                
                let qData = { type: type, text: qText, image: qImage };
                
                if (type === 'objective') {
                    qData.options = {
                        A: $(this).find(`[name="q_opt_a_${rid}"]`).val(),
                        B: $(this).find(`[name="q_opt_b_${rid}"]`).val(),
                        C: $(this).find(`[name="q_opt_c_${rid}"]`).val(),
                        D: $(this).find(`[name="q_opt_d_${rid}"]`).val()
                    };
                }

                if (type === 'essay') {
                    const space = $(this).find(`[name="q_space_${rid}"]`).val();
                    if (space === 'custom') {
                        qData.space = $(this).find(`[name="q_custom_space_${rid}"]`).val() || 0.5;
                    } else {
                        qData.space = space;
                    }
                }

                questions.push(qData);
            });

            const questionsJson = JSON.stringify(questions);

            // SAVE DRAFT FIRST
            Spinner.show('Archiving draft and generating paper...');
            fetch('../ajax/save_question_draft.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subject_id: subject,
                    session: session,
                    term: term,
                    exam_type: type,
                    instructions: instructions,
                    questions: questionsJson,
                    num_format: numFormat
                })
            })
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                // We proceed to generation even if draft fails, but notify
                if(!d.success) console.warn("Draft save failed: ", d.message);

                // Post to a specialized generator script that opens in a new tab
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'generate_paper.php';
                form.target = '_blank';

                const fields = {
                    mode: mode,
                    target_class: targetClass,
                    plain_copies: plainCopies,
                    booklet_copies: bookletCopies,
                    num_format: numFormat,
                    subject_id: subject,
                    session: session,
                    term: term,
                    exam_type: type,
                    instructions: instructions,
                    questions: questionsJson
                };

                for (const key in fields) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                bootstrap.Modal.getInstance(document.getElementById('generateModal')).hide();
                
                if(d.success) Notif.show("Draft saved & Generating...", "success");
            });
        }

        // Initialize with one question
        addQuestion('objective');
    </script>
    <?php 
        if ($role === 'super_admin') {
            include '../includes/sa_bottom_nav.php'; 
        } elseif ($role === 'owner') {
            include '../includes/admin_bottom_nav.php';
        } else {
            include '../includes/staff_bottom_nav.php';
        }
    ?>
</body>
</html>
