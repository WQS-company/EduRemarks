<?php
// user/cbt_exams.php — Staff CBT Management
require_once '../includes/auth_check.php';

if ($role !== 'staff') { header('Location: ../dashboard.php'); exit(); }

$school_id = $_SESSION['school_id'] ?? null;

// Fetch staff detail
$sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$sd->execute([$user_id, $school_id]);
$staff = $sd->fetch();
if (!$staff) { header('Location: dashboard.php'); exit(); }
$staff_id = $staff['id'];

// --- CBT GOVERNANCE VERIFICATION ---
// Get current session/term for orchestration check
$active_sid = $active_school['current_session_id'];
$active_tid = $active_school['current_term_id'];

// 1. Check Global Orchestration for CBT
$orch_stmt = $pdo->prepare("SELECT cbt_status FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
$orch_stmt->execute([$school_id, $active_sid, $active_tid]);
$global_cbt_open = $orch_stmt->fetchColumn() !== 0; // Default to true if not exists (1), but check if specifically 0

// 2. Check Staff-Specific Window for CBT
$window_stmt = $pdo->prepare("SELECT cbt_status FROM staff_entry_windows WHERE staff_id = ? AND school_id = ? AND session_id = ? AND term_id = ?");
$window_stmt->execute([$staff_id, $school_id, $active_sid, $active_tid]);
$staff_cbt_window = $window_stmt->fetchColumn();

// Final Access Logic: Global must be open AND Staff window must not be specifically 'closed'
// Or if Global is closed, Staff window must be specifically 'open' (Override)
$is_cbt_locked = !$global_cbt_open;
if ($staff_cbt_window === 'open') $is_cbt_locked = false;
if ($staff_cbt_window === 'closed') $is_cbt_locked = true;

if ($is_cbt_locked) {
    // Professional Lock UI
    include '../includes/staff_header.php';
    include '../includes/staff_sidebar.php';
    echo '<main class="sa-main-content">
            <div class="glass-card p-5 text-center mt-5 mx-auto" style="max-width: 600px; border-top: 5px solid #dc3545;">
                <div class="mb-4">
                    <i class="fas fa-lock text-danger" style="font-size: 4rem; opacity: 0.2;"></i>
                </div>
                <h3 class="fw-900 mb-3">CBT Access Restricted</h3>
                <p class="text-muted mb-4">The Computer Based Testing module has been locked for your node by the institutional administration. This typically occurs during audit phases or scheduled maintenance.</p>
                <div class="p-3 bg-light rounded-4 mb-4 text-start">
                    <div class="extra-small fw-800 uppercase tracking-2 opacity-50 mb-2">Institutional Context</div>
                    <div class="d-flex justify-content-between small fw-bold">
                        <span>Academic Period:</span>
                        <span class="text-primary">' . htmlspecialchars($active_school['session_name']) . '</span>
                    </div>
                </div>
                <a href="dashboard.php" class="btn btn-primary rounded-pill px-5 fw-800">Return to Command Center</a>
            </div>
          </main>';
    include '../includes/dashboard_footer.php';
    exit();
}
// --- END GOVERNANCE ---

// Fetch classes and subjects for this staff
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id as class_id, c.name as class_name, sub.id as subject_id, sub.name as subject_name
    FROM staff_class_subjects scs
    JOIN classes c ON c.id = scs.class_id
    JOIN subjects sub ON sub.id = scs.subject_id
    WHERE scs.staff_detail_id=? AND scs.school_id=?
");
$stmt->execute([$staff_id, $school_id]);
$my_assignments = $stmt->fetchAll();

// Group subjects by class
$class_subjects = [];
foreach ($my_assignments as $row) {
    if (!isset($class_subjects[$row['class_id']])) {
        $class_subjects[$row['class_id']] = ['name' => $row['class_name'], 'subjects' => []];
    }
    $class_subjects[$row['class_id']]['subjects'][] = ['id' => $row['subject_id'], 'name' => $row['subject_name']];
}

// Fetch existing CBT exams
$stmt = $pdo->prepare("
    SELECT e.*, c.name as class_name, s.name as subject_name,
           (SELECT COUNT(*) FROM cbt_questions WHERE exam_id = e.id) as question_count,
           (SELECT COUNT(*) FROM cbt_student_attempts WHERE exam_id = e.id) as attempt_count
    FROM cbt_exams e
    JOIN classes c ON c.id = e.class_id
    JOIN subjects s ON s.id = e.subject_id
    WHERE e.staff_id=? AND e.school_id=?
    ORDER BY e.created_at DESC
");
$stmt->execute([$staff_id, $school_id]);
$exams = $stmt->fetchAll();

// Generate a random token for new exams
$new_token = bin2hex(random_bytes(16));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Management | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .exam-card { transition: 0.3s; border-left: 4px solid #1F3C88; }
        .exam-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .badge-status { font-size: 0.7rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; text-transform: uppercase; }
        .status-draft { background: #EEF2FB; color: #1F3C88; }
        .status-active { background: #E8F5E9; color: #2E7D32; }
        .status-closed { background: #FFEBEE; color: #C62828; }
        .link-box { background: #f8f9fa; border: 1px dashed #ddd; border-radius: 8px; padding: 10px; font-size: 0.85rem; display: flex; align-items: center; justify-content: space-between; }
        .action-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; border: none; background: transparent; }
        .icon-edit { color: #1F3C88; background: #EEF2FB; }
        .icon-edit:hover { background: #1F3C88; color: white; }
        .icon-delete { color: #C62828; background: #FFEBEE; }
        .icon-delete:hover { background: #C62828; color: white; }
        
        .confirm-modal-icon { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; background: #FFEBEE; color: #C62828; }
        .confirm-modal-icon.success { background: #E8F5E9; color: #2E7D32; }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-0">CBT Test & Exam Management</h4>
                    <p class="text-muted small mb-0">Create world-class secured computer-based assessments.</p>
                </div>
                <button class="btn btn-primary rounded-pill px-4" onclick="openExamModal()">
                    <i class="fas fa-plus me-2"></i>Create New CBT
                </button>
            </div>

            <div class="row g-4">
                <?php if (empty($exams)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="glass-card p-5">
                            <i class="fas fa-laptop-code text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5>No CBT Exams Created Yet</h5>
                            <p class="text-muted">Set up your first professional assessment to get started.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($exams as $e): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="glass-card p-4 exam-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge-status status-<?php echo $e['status']; ?>"><?php echo $e['status']; ?></span>
                                    <div class="d-flex gap-2">
                                        <button class="action-icon icon-edit" onclick="editExam(<?php echo htmlspecialchars(json_encode($e)); ?>)" title="Update Settings">
                                            <i class="fas fa-edit small text-primary"></i>
                                        </button>
                                        <button class="action-icon icon-delete" onclick="confirmDelete(<?php echo $e['id']; ?>)" title="Delete Exam">
                                            <i class="fas fa-trash small text-danger"></i>
                                        </button>
                                        <div class="dropdown ms-1">
                                            <button class="action-icon btn-link text-muted p-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                            <ul class="dropdown-menu shadow border-0">
                                                <li><a class="dropdown-item" href="cbt_builder.php?exam_id=<?php echo $e['id']; ?>"><i class="fas fa-tools me-2"></i>Manage Questions</a></li>
                                                <li><a class="dropdown-item" href="cbt_results.php?exam_id=<?php echo $e['id']; ?>"><i class="fas fa-chart-bar me-2"></i>View Results</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(<?php echo $e['id']; ?>)"><i class="fas fa-trash me-2"></i>Delete Now</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($e['title']); ?></h5>
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($e['class_name']); ?> &bull; 
                                    <i class="fas fa-book me-1"></i><?php echo htmlspecialchars($e['subject_name']); ?>
                                    <?php if ($e['assessment_type'] === 'test'): ?>
                                        <span class="badge bg-soft-warning text-warning ms-1" style="font-size: 0.65rem;"><?php echo $e['test_category'] === '1st_ca' ? '1st C.A' : '2nd C.A'; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-primary text-primary ms-1" style="font-size: 0.65rem;">EXAM</span>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-4">
                                        <div class="p-2 bg-light rounded text-center">
                                            <div class="small text-muted">Bank</div>
                                            <div class="fw-bold"><?php echo $e['question_count']; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-light rounded text-center">
                                            <div class="small text-muted">Display</div>
                                            <div class="fw-bold"><?php echo $e['total_questions'] ?: 'All'; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-2 bg-light rounded text-center">
                                            <div class="small text-muted">Minutes</div>
                                            <div class="fw-bold"><?php echo $e['duration_mins']; ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="small text-muted mb-2"><i class="fas fa-clock me-1"></i><?php echo date('M d, h:i A', strtotime($e['start_time'])); ?></div>
                                
                                <div class="mt-3">
                                    <div class="small fw-bold mb-1"><?php echo get_label('Pupils'); ?> Access Link:</div>
                                    <div class="link-box">
                                       <span class="text-truncate me-2" id="link-<?php echo $e['id']; ?>">
<?php echo (isset($_SERVER['HTTPS']) ? "https" : "http") . "://".$_SERVER['HTTP_HOST']."/student/cbt.php?token=".$e['token']; ?>
</span>
                                        <button class="btn btn-sm btn-white p-1" onclick="copyLink(<?php echo $e['id']; ?>)" title="Copy Link">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 mt-3">
                                    <a href="cbt_builder.php?exam_id=<?php echo $e['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                                        Build Questions <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                    <?php if ($e['attempt_count'] > 0): ?>
                                    <a href="cbt_results.php?exam_id=<?php echo $e['id']; ?>" class="btn btn-outline-success btn-sm rounded-pill">
                                        <i class="fas fa-chart-bar me-1"></i>View Results (<?php echo $e['attempt_count']; ?>)
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>

    <!-- Exam Modal -->
    <div class="modal fade" id="examModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 p-4 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold" id="modalTitle">Create CBT Exam</h5>
                        <p class="text-muted small mb-0" id="modalSubTitle">Configure your examination settings carefully.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Validation Alert -->
                    <div class="alert alert-danger d-none rounded-3 py-2 px-3" id="formAlert" style="font-size:0.85rem;">
                        <i class="fas fa-exclamation-circle me-2"></i><span id="formAlertText"></span>
                    </div>

                    <form id="examForm">
                        <input type="hidden" id="exam_id" name="id">
                        <input type="hidden" id="token" name="token" value="<?php echo $new_token; ?>">
                        
                        <!-- Section 1: Basic Info -->
                        <div class="mb-4">
                            <div class="small fw-bold text-primary text-uppercase mb-3" style="letter-spacing:1px;">
                                <i class="fas fa-info-circle me-1"></i> Basic Information
                            </div>
                            <div class="row g-3">
                                <div class="col-12" id="titleGroup">
                                    <label class="small fw-bold mb-1" id="titleLabel">Exam Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" id="title" required placeholder="e.g. End of First Term Mathematics Exam" minlength="5">
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold mb-1">Target <?php echo get_label('Class'); ?> <span class="text-danger">*</span></label>
                                    <select class="form-select" name="class_id" id="class_id" required onchange="updateSubjects()">
                                        <option value="">Select <?php echo get_label('Class'); ?></option>
                                        <?php foreach ($class_subjects as $cid => $data): ?>
                                            <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($data['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold mb-1"><?php echo get_label('Subject'); ?> <span class="text-danger">*</span></label>
                                    <select class="form-select" name="subject_id" id="subject_id" required>
                                        <option value="">Select <?php echo get_label('Subject'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold mb-1">Assessment Type <span class="text-danger">*</span></label>
                                    <select class="form-select" name="assessment_type" id="assessment_type" required onchange="toggleTestCategory()">
                                        <option value="test">Test (C.A)</option>
                                        <option value="exam">Main Examination</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="testCategoryGroup">
                                    <label class="small fw-bold mb-1">Test Category <span class="text-danger">*</span></label>
                                    <select class="form-select" name="test_category" id="test_category">
                                        <option value="1st_ca">First C.A</option>
                                        <option value="2nd_ca">Second C.A</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Schedule -->
                        <div class="mb-4">
                            <div class="small fw-bold text-primary text-uppercase mb-3" style="letter-spacing:1px;">
                                <i class="fas fa-calendar-alt me-1"></i> Schedule & Timing
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="small fw-bold mb-1">Start Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="start_time" id="start_time" required onchange="validateTimes()">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold mb-1">End Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="end_time" id="end_time" required onchange="validateTimes()">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold mb-1">Duration (Minutes) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="duration_mins" id="duration_mins" value="60" min="5" max="480" required onchange="validateTimes()">
                                    <div class="form-text small">5 – 480 minutes max</div>
                                </div>
                            </div>
                            <!-- Time Validation Feedback -->
                            <div id="timeValidation" class="mt-2 d-none">
                                <div class="p-3 rounded-3" id="timeValidationBox" style="font-size:0.82rem;">
                                    <i class="fas fa-clock me-1" id="timeValidationIcon"></i>
                                    <span id="timeValidationText"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Configuration -->
                        <div class="mb-4">
                            <div class="small fw-bold text-primary text-uppercase mb-3" style="letter-spacing:1px;">
                                <i class="fas fa-cog me-1"></i> <span id="configSectionTitle">Exam Configuration</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="small fw-bold mb-1">Questions to Display <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="total_questions" id="total_questions" value="10" min="1" max="500" required>
                                    <div class="form-text small"><?php echo get_label('Pupils'); ?> see this many questions</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold mb-1">Marks per Question</label>
                                    <input type="number" step="0.5" class="form-control" name="marks_per_question" id="marks_per_question" value="1.0" min="0.5" max="100">
                                </div>
                                <div class="col-md-4">
                                    <label class="small fw-bold mb-1">Question Order</label>
                                    <select class="form-select" name="order_type" id="order_type">
                                        <option value="random">🔀 Randomized</option>
                                        <option value="asc">⬆️ Ascending</option>
                                        <option value="desc">⬇️ Descending</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold mb-1" id="statusLabel">Exam Status</label>
                                    <select class="form-select" name="status" id="status">
                                        <option value="draft">📝 Draft (Private — not visible to <?php echo strtolower(get_label('Pupils')); ?>)</option>
                                        <option value="active">✅ Active (Visible to <?php echo strtolower(get_label('Pupils')); ?>)</option>
                                        <option value="closed">🔒 Closed (No longer accessible)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="small fw-bold mb-1">Instructions</label>
                                    <textarea class="form-control" name="instructions" id="instructions" rows="3" placeholder="Read all questions carefully before answering..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Exam Summary -->
                        <div class="p-3 bg-light rounded-3 border" id="examSummary" style="font-size:0.82rem;">
                            <div class="fw-bold mb-2 text-primary"><i class="fas fa-list-check me-1"></i> <span id="summaryTitle">Exam Summary</span></div>
                            <div class="row g-2">
                                <div class="col-6"><span class="text-muted">Total Questions:</span> <strong id="summaryQ">10</strong></div>
                                <div class="col-6"><span class="text-muted">Duration:</span> <strong id="summaryDuration">60 min</strong></div>
                                <div class="col-6"><span class="text-muted">Total Marks:</span> <strong id="summaryMarks">10</strong></div>
                                <div class="col-6"><span class="text-muted">Status:</span> <strong id="summaryStatus">Draft</strong></div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 pt-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" id="saveExamBtn" onclick="saveExam()">
                        <i class="fas fa-save me-2"></i>Save Exam
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-body p-5 text-center">
                    <div class="confirm-modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Delete CBT Exam?</h4>
                    <p class="text-muted mb-4">Are you sure you want to delete this exam? This action will permanently remove all associated questions and <?php echo strtolower(get_label('Pupils')); ?> records.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No, Keep it</button>
                        <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDeleteBtn">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Success Modal (Checkmark) -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-body p-5 text-center">
                    <div class="confirm-modal-icon success">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Success!</h4>
                    <p class="text-muted mb-4" id="successMessage">The examination has been deleted successfully.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-5" onclick="location.reload()">Great!</button>
                </div>
            </div>
        </div>
    </div>

    
    <script>
        const classSubjects = <?php echo json_encode($class_subjects); ?>;

        function updateSubjects() {
            const cid = document.getElementById('class_id').value;
            const subSel = document.getElementById('subject_id');
            subSel.innerHTML = '<option value="">Select <?php echo get_label("Subject"); ?></option>';
            if (cid && classSubjects[cid]) {
                classSubjects[cid].subjects.forEach(s => {
                    subSel.innerHTML += `<option value="${s.id}">${s.name}</option>`;
                });
            }
        }

        function openExamModal() {
            document.getElementById('examForm').reset();
            document.getElementById('exam_id').value = '';
            document.getElementById('modalTitle').textContent = 'Create CBT Exam';
            document.getElementById('formAlert').classList.add('d-none');
            document.getElementById('timeValidation').classList.add('d-none');
            toggleTestCategory();

            // Auto-set start time to next hour
            const now = new Date();
            now.setMinutes(0, 0, 0);
            now.setHours(now.getHours() + 1);
            document.getElementById('start_time').value = toLocalDateTimeStr(now);
            // Auto-set end time to start + 2 hours
            const end = new Date(now.getTime() + 2 * 3600000);
            document.getElementById('end_time').value = toLocalDateTimeStr(end);
            validateTimes();
            updateSummary();

            new bootstrap.Modal(document.getElementById('examModal')).show();
        }

        function toLocalDateTimeStr(date) {
            return date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + 
                   String(date.getDate()).padStart(2,'0') + 'T' + String(date.getHours()).padStart(2,'0') + ':' + 
                   String(date.getMinutes()).padStart(2,'0');
        }

        function editExam(e) {
            document.getElementById('exam_id').value = e.id;
            document.getElementById('title').value = e.title;
            document.getElementById('class_id').value = e.class_id;
            updateSubjects();
            document.getElementById('subject_id').value = e.subject_id;
            document.getElementById('duration_mins').value = e.duration_mins;
            document.getElementById('start_time').value = e.start_time.replace(' ', 'T');
            document.getElementById('end_time').value = e.end_time.replace(' ', 'T');
            document.getElementById('order_type').value = e.order_type;
            document.getElementById('marks_per_question').value = e.marks_per_question;
            document.getElementById('instructions').value = e.instructions;
            document.getElementById('status').value = e.status;
            document.getElementById('token').value = e.token;
            document.getElementById('total_questions').value = e.total_questions;
            document.getElementById('assessment_type').value = e.assessment_type || 'test';
            document.getElementById('test_category').value = e.test_category || '1st_ca';
            document.getElementById('formAlert').classList.add('d-none');
            toggleTestCategory();
            
            document.getElementById('modalTitle').textContent = 'Edit Exam Settings';
            validateTimes();
            updateSummary();
            new bootstrap.Modal(document.getElementById('examModal')).show();
        }

        function saveExam() {
            const form = document.getElementById('examForm');
            const formData = new FormData(form);
            const alertBox = document.getElementById('formAlert');
            const alertText = document.getElementById('formAlertText');

            // Hide previous alerts
            alertBox.classList.add('d-none');

            function showError(msg) {
                alertText.textContent = msg;
                alertBox.classList.remove('d-none');
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // ─── Client-side Validation ───
            const type = formData.get('assessment_type');
            const title = formData.get('title')?.trim();
            if (type === 'exam' && (!title || title.length < 5)) { 
                showError('Exam title must be at least 5 characters.'); 
                return; 
            }

            if (!formData.get('class_id')) { showError('Please select a target <?php echo strtolower(get_label("Class")); ?>.'); return; }
            if (!formData.get('subject_id')) { showError('Please select a <?php echo strtolower(get_label("Subject")); ?>.'); return; }

            const duration = parseInt(formData.get('duration_mins'));
            if (isNaN(duration) || duration < 5 || duration > 480) { showError('Duration must be between 5 and 480 minutes.'); return; }

            const startTime = formData.get('start_time');
            const endTime = formData.get('end_time');
            if (!startTime || !endTime) { showError('Both start and end times are required.'); return; }

            const startDate = new Date(startTime);
            const endDate = new Date(endTime);
            const now = new Date();

            // Only check past time for new exams
            const isEditing = !!formData.get('id');
            if (!isEditing) {
                const fiveMinAgo = new Date(now.getTime() - 5 * 60000);
                if (startDate < fiveMinAgo) {
                    showError('Start time cannot be in the past. Please choose a future time.');
                    return;
                }
            }

            if (endDate <= startDate) {
                showError('End time must be after the start time.');
                return;
            }

            const windowMins = (endDate - startDate) / 60000;
            if (windowMins < duration) {
                showError(`The exam access window (${Math.round(windowMins)} min) is shorter than the exam duration (${duration} min). Students won't have enough time to complete it. Please extend the end time.`);
                return;
            }

            if (windowMins > 1440) {
                showError('Exam access window cannot exceed 24 hours.');
                return;
            }

            const qCount = parseInt(formData.get('total_questions'));
            if (isNaN(qCount) || qCount < 1) { showError('At least 1 question must be displayed to students.'); return; }

            // ─── All Valid — Submit ───
            Spinner.show('Saving exam configuration...');
            fetch('../ajax/save_cbt_exam.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    // Show feedback before reload
                    if(typeof EduRemarks !== 'undefined' && EduRemarks.showFeedback) {
                        EduRemarks.showFeedback('CBT Configuration', 'CBT Setup Matrix');
                        // Delay reload to let them see the modal
                        setTimeout(() => {
                           if(!$('#feedbackModal').hasClass('show')) location.reload();
                        }, 2000);
                        
                        // Listen for modal close to reload if they close it
                        $(document).on('hidden.bs.modal', '#feedbackModal', function () {
                            location.reload();
                        });
                    } else {
                        location.reload();
                    }
                }
                else { showError(d.message); Spinner.hide(); }
            })
            .catch(() => { showError('Network error. Please try again.'); Spinner.hide(); });
        }

        let deleteTargetId = null;
        function confirmDelete(id) {
            deleteTargetId = id;
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!deleteTargetId) return;
            
            // Close confirm modal
            bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
            
            Spinner.show('Deleting...');
            fetch('../ajax/delete_cbt_exam.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${deleteTargetId}`
            })
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                } else {
                    Notif.show(d.message, 'error');
                }
            });
        });

        function deleteExam(id) {
            // Deprecated in favor of confirmDelete
            confirmDelete(id);
        }

        function copyLink(id) {
            const linkText = document.getElementById(`link-${id}`).textContent.trim();
            navigator.clipboard.writeText(linkText).then(() => {
                Notif.show('Exam link copied to clipboard!', 'success');
            });
        }

        // ─── Live Time Validation ───
        function validateTimes() {
            const startVal = document.getElementById('start_time').value;
            const endVal = document.getElementById('end_time').value;
            const duration = parseInt(document.getElementById('duration_mins').value) || 0;
            const box = document.getElementById('timeValidation');
            const boxInner = document.getElementById('timeValidationBox');
            const icon = document.getElementById('timeValidationIcon');
            const text = document.getElementById('timeValidationText');

            if (!startVal || !endVal) { box.classList.add('d-none'); return; }

            const startDate = new Date(startVal);
            const endDate = new Date(endVal);
            box.classList.remove('d-none');

            if (endDate <= startDate) {
                boxInner.style.background = '#FFEBEE'; boxInner.style.color = '#C62828';
                icon.className = 'fas fa-times-circle me-1';
                text.textContent = '✗ End time must be after start time.';
                return;
            }

            const windowMins = Math.round((endDate - startDate) / 60000);

            if (duration > windowMins) {
                boxInner.style.background = '#FFF3E0'; boxInner.style.color = '#E65100';
                icon.className = 'fas fa-exclamation-triangle me-1';
                text.textContent = `⚠ Exam duration (${duration} min) exceeds the access window (${windowMins} min). Students won't have enough time.`;
                return;
            }

            // Check past time for new exams
            const isEditing = !!document.getElementById('exam_id').value;
            if (!isEditing && startDate < new Date()) {
                boxInner.style.background = '#FFF3E0'; boxInner.style.color = '#E65100';
                icon.className = 'fas fa-exclamation-triangle me-1';
                text.textContent = '⚠ Start time is in the past. Update to a future time.';
                return;
            }

            // All good
            boxInner.style.background = '#E8F5E9'; boxInner.style.color = '#2E7D32';
            icon.className = 'fas fa-check-circle me-1';
            const hrs = Math.floor(windowMins / 60);
            const mins = windowMins % 60;
            text.textContent = `✓ Valid schedule. Access window: ${hrs > 0 ? hrs + 'h ' : ''}${mins}min. Students get ${duration} min once started.`;
        }

        // ─── Assessment Type Toggle ───
        function toggleTestCategory() {
            const type = document.getElementById('assessment_type').value;
            const categoryGroup = document.getElementById('testCategoryGroup');
            const categorySelect = document.getElementById('test_category');
            const titleGroup = document.getElementById('titleGroup');
            const titleInput = document.getElementById('title');
            
            // Dynamic Label Updates
            const isEditing = !!document.getElementById('exam_id').value;
            const term = type === 'test' ? 'Test' : 'Exam';
            
            document.getElementById('modalTitle').textContent = (isEditing ? 'Edit ' : 'Create CBT ') + term;
            document.getElementById('modalSubTitle').textContent = `Configure your ${term.toLowerCase()} settings carefully.`;
            document.getElementById('configSectionTitle').textContent = term + ' Configuration';
            document.getElementById('statusLabel').textContent = term + ' Status';
            document.getElementById('summaryTitle').textContent = term + ' Summary';
            document.getElementById('saveExamBtn').innerHTML = `<i class="fas fa-save me-2"></i>Save ${term}`;
            
            if (type === 'test') {
                categoryGroup.style.display = 'block';
                categorySelect.required = true;
                titleGroup.style.display = 'none';
                titleInput.required = false;
            } else {
                categoryGroup.style.display = 'none';
                categorySelect.required = false;
                titleGroup.style.display = 'block';
                titleInput.required = true;
            }
            updateSummary();
        }

        // Live Summary Update ───
        function updateSummary() {
            const q = parseInt(document.getElementById('total_questions').value) || 0;
            const m = parseFloat(document.getElementById('marks_per_question').value) || 1;
            const d = parseInt(document.getElementById('duration_mins').value) || 0;
            const s = document.getElementById('status');
            const statusText = s.options[s.selectedIndex]?.text?.replace(/^[^\w]*/, '') || 'Draft';
            
            const type = document.getElementById('assessment_type').value;
            const cat = document.getElementById('test_category').value;
            const summaryType = type === 'exam' ? 'Exam' : (cat === '1st_ca' ? '1st C.A' : '2nd C.A');

            document.getElementById('summaryQ').textContent = q;
            document.getElementById('summaryDuration').textContent = d + ' min';
            document.getElementById('summaryMarks').textContent = (q * m);
            document.getElementById('summaryStatus').textContent = statusText + ' (' + summaryType + ')';
        }

        // Wire up real-time listeners
        ['total_questions','marks_per_question','duration_mins','status', 'assessment_type', 'test_category'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updateSummary);
            if (el) el.addEventListener('change', updateSummary);
        });
    </script>
</body>
</html>
