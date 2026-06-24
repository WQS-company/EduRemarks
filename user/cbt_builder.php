<?php
// user/cbt_builder.php — Staff Question Builder
require_once '../includes/auth_check.php';

if ($role !== 'staff') { header('Location: ../dashboard.php'); exit(); }

$school_id = $_SESSION['school_id'] ?? null;
$exam_id   = $_GET['exam_id'] ?? null;

if (!$exam_id) { header('Location: cbt_exams.php'); exit(); }

// --- CBT GOVERNANCE VERIFICATION ---
// Fetch staff detail first
$sd_stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$sd_stmt->execute([$user_id, $school_id]);
$staff = $sd_stmt->fetch();
if (!$staff) { header('Location: dashboard.php'); exit(); }
$staff_id = $staff['id'];

$active_sid = $active_school['current_session_id'];
$active_tid = $active_school['current_term_id'];

$orch_stmt = $pdo->prepare("SELECT cbt_status FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
$orch_stmt->execute([$school_id, $active_sid, $active_tid]);
$global_cbt_open = $orch_stmt->fetchColumn() !== 0;

$window_stmt = $pdo->prepare("SELECT cbt_status FROM staff_entry_windows WHERE staff_id = ? AND school_id = ? AND session_id = ? AND term_id = ?");
$window_stmt->execute([$staff_id, $school_id, $active_sid, $active_tid]);
$staff_cbt_window = $window_stmt->fetchColumn();

$is_cbt_locked = !$global_cbt_open;
if ($staff_cbt_window === 'open') $is_cbt_locked = false;
if ($staff_cbt_window === 'closed') $is_cbt_locked = true;

if ($is_cbt_locked) {
    header('Location: cbt_exams.php');
    exit();
}
// --- END GOVERNANCE ---

// Fetch exam details to verify ownership
$stmt = $pdo->prepare("
    SELECT e.*, c.name as class_name, s.name as subject_name 
    FROM cbt_exams e
    JOIN classes c ON c.id = e.class_id
    JOIN subjects s ON s.id = e.subject_id
    WHERE e.id=? AND e.school_id=?
");
$stmt->execute([$exam_id, $school_id]);
$exam = $stmt->fetch();

if (!$exam) { header('Location: cbt_exams.php'); exit(); }

// Fetch existing questions
$stmt = $pdo->prepare("SELECT * FROM cbt_questions WHERE exam_id=? ORDER BY id ASC");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Builder | <?php echo htmlspecialchars($exam['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .q-card { border-left: 4px solid #1F3C88; transition: 0.2s; position: relative; }
        .q-card:hover { border-left-width: 8px; background: #fdfdfd; }
        .q-number { background: #1F3C88; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem; }
        .q-type-badge { font-size: 0.65rem; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; font-weight: 700; background: #eef2fb; color: #1F3C88; }
        .option-item { padding: 8px 12px; border: 1px solid #edf2f7; border-radius: 8px; margin-bottom: 5px; font-size: 0.9rem; display: flex; align-items: center; }
        .option-item.correct { background: #e8f5e9; border-color: #2e7d32; color: #1b5e20; }
        .option-letter { font-weight: 800; margin-right: 10px; width: 20px; }
        .builder-sticky { position: sticky; top: 20px; }
        #questionImagePreview { max-height: 200px; display: none; margin-top: 10px; border-radius: 8px; }
        
        .confirm-modal-icon { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; background: #FFEBEE; color: #C62828; }
        .confirm-modal-icon.success { background: #E8F5E9; color: #2E7D32; }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="cbt_exams.php" class="text-decoration-none">CBT Management</a></li>
                    <li class="breadcrumb-item active">Question Builder</li>
                </ol>
            </nav>

            <div class="row g-4">
                <!-- Left: Question List -->
                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0">Questions (<?php echo count($questions); ?>)</h4>
                        <div class="text-muted small"><?php echo $exam['class_name']; ?> &bull; <?php echo $exam['subject_name']; ?></div>
                    </div>

                    <?php if (empty($questions)): ?>
                        <div class="glass-card p-5 text-center">
                            <i class="fas fa-question-circle text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5>No Questions Added</h5>
                            <p class="small text-muted">Use the builder on the right to start adding questions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $index => $q): 
                            $options = json_decode($q['options'], true);
                        ?>
                            <div class="glass-card p-4 q-card mb-3" id="q-<?php echo $q['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="q-number"><?php echo $index + 1; ?></div>
                                        <span class="q-type-badge"><?php echo $q['type']; ?></span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editQuestion(<?php echo htmlspecialchars(json_encode($q)); ?>)"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteQuestion(<?php echo $q['id']; ?>)"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                                <div class="fw-bold mb-3"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                                
                                <?php if ($q['image_path']): ?>
                                    <div class="mb-3">
                                        <img src="../<?php echo $q['image_path']; ?>" class="img-fluid rounded border" style="max-height: 200px;">
                                    </div>
                                <?php endif; ?>

                                <?php if ($q['type'] === 'objective' && $options): ?>
                                    <div class="options-list">
                                        <?php foreach ($options as $key => $val): ?>
                                            <div class="option-item <?php echo ($key === $q['correct_answer']) ? 'correct' : ''; ?>">
                                                <span class="option-letter"><?php echo $key; ?>.</span> <?php echo htmlspecialchars($val); ?>
                                                <?php if ($key === $q['correct_answer']): ?>
                                                    <i class="fas fa-check-circle ms-auto text-success"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($q['type'] === 'tf'): ?>
                                    <div class="option-item <?php echo ($q['correct_answer'] === 'True') ? 'correct' : ''; ?>">
                                        <span class="option-letter">A.</span> True
                                    </div>
                                    <div class="option-item <?php echo ($q['correct_answer'] === 'False') ? 'correct' : ''; ?>">
                                        <span class="option-letter">B.</span> False
                                    </div>
                                <?php elseif ($q['type'] === 'essay'): ?>
                                    <div class="p-3 bg-light rounded small text-muted">
                                        <i class="fas fa-pen-nib me-2"></i>Essay response required. Keywords for grading can be set separately.
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3 text-end">
                                    <span class="small text-muted">Marks: <strong><?php echo $q['marks'] ?: $exam['marks_per_question']; ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Right: Builder Panel -->
                <div class="col-lg-5">
                    <div class="builder-sticky">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-4" id="builderTitle">Add New Question</h5>
                            <form id="questionForm">
                                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                                <input type="hidden" id="q_id" name="id">
                                
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Question Type</label>
                                    <select class="form-select" name="type" id="type" onchange="toggleTypeFields()">
                                        <option value="objective">Multiple Choice (Objective)</option>
                                        <option value="tf">True / False</option>
                                        <option value="essay">Essay / Long Answer</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Question Text *</label>
                                    <textarea class="form-control" name="question_text" id="question_text" rows="5" required placeholder="What is the square root of 64?"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Question Image (Optional)</label>
                                    <input type="file" class="form-control" name="question_image" id="question_image" accept="image/*" onchange="previewImage(this)">
                                    <img id="questionImagePreview" class="img-fluid border shadow-sm">
                                </div>

                                <!-- Objective Fields -->
                                <div id="objectiveFields">
                                    <label class="small fw-bold mb-2">Options & Correct Answer</label>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">A</span>
                                        <input type="text" class="form-control" name="opt_A" id="opt_A" placeholder="Option A">
                                        <div class="input-group-text"><input type="radio" name="correct_answer" value="A" checked></div>
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">B</span>
                                        <input type="text" class="form-control" name="opt_B" id="opt_B" placeholder="Option B">
                                        <div class="input-group-text"><input type="radio" name="correct_answer" value="B"></div>
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">C</span>
                                        <input type="text" class="form-control" name="opt_C" id="opt_C" placeholder="Option C">
                                        <div class="input-group-text"><input type="radio" name="correct_answer" value="C"></div>
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text">D</span>
                                        <input type="text" class="form-control" name="opt_D" id="opt_D" placeholder="Option D">
                                        <div class="input-group-text"><input type="radio" name="correct_answer" value="D"></div>
                                    </div>
                                </div>

                                <!-- T/F Fields -->
                                <div id="tfFields" style="display: none;">
                                    <label class="small fw-bold mb-2">Select Correct Answer</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="correct_tf" id="tf_true" value="True" checked>
                                            <label class="form-check-label" for="tf_true">True</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="correct_tf" id="tf_false" value="False">
                                            <label class="form-check-label" for="tf_false">False</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4 mt-3">
                                    <label class="small fw-bold mb-1">Custom Marks (Leave empty for default <?php echo $exam['marks_per_question']; ?>)</label>
                                    <input type="number" step="0.5" class="form-control" name="marks" id="marks" placeholder="Default: <?php echo $exam['marks_per_question']; ?>">
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary rounded-pill py-2" onclick="saveQuestion()">
                                        <i class="fas fa-save me-2"></i>Save Question
                                    </button>
                                    <button type="button" class="btn btn-light rounded-pill py-2" onclick="resetBuilder()">
                                        Cancel / New
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-body p-5 text-center">
                    <div class="confirm-modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Delete Question?</h4>
                    <p class="text-muted mb-4">Are you sure you want to remove this question from the examination bank? This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No, Keep it</button>
                        <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDeleteBtn">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-body p-5 text-center">
                    <div class="confirm-modal-icon success">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Deleted!</h4>
                    <p class="text-muted mb-4">The question has been removed successfully.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-5" onclick="location.reload()">Great!</button>
                </div>
            </div>
        </div>
    </div>

    
    <script>
        function toggleTypeFields() {
            const type = document.getElementById('type').value;
            document.getElementById('objectiveFields').style.display = (type === 'objective') ? 'block' : 'none';
            document.getElementById('tfFields').style.display = (type === 'tf') ? 'block' : 'none';
        }

        function previewImage(input) {
            const preview = document.getElementById('questionImagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        function saveQuestion() {
            const form = document.getElementById('questionForm');
            const formData = new FormData(form);
            
            // Validate required
            if (!formData.get('question_text')) {
                Notif.show('Please enter question text', 'error');
                return;
            }

            Spinner.show('Saving question...');
            fetch('../ajax/save_cbt_question.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) location.reload();
                else { Notif.show(d.message, 'error'); Spinner.hide(); }
            });
        }

        function editQuestion(q) {
            resetBuilder();
            document.getElementById('builderTitle').textContent = 'Edit Question';
            document.getElementById('q_id').value = q.id;
            document.getElementById('type').value = q.type;
            document.getElementById('question_text').value = q.question_text;
            document.getElementById('marks').value = q.marks;
            
            toggleTypeFields();
            
            if (q.type === 'objective') {
                const options = JSON.parse(q.options);
                document.getElementById('opt_A').value = options.A || '';
                document.getElementById('opt_B').value = options.B || '';
                document.getElementById('opt_C').value = options.C || '';
                document.getElementById('opt_D').value = options.D || '';
                document.querySelector(`input[name="correct_answer"][value="${q.correct_answer}"]`).checked = true;
            } else if (q.type === 'tf') {
                document.querySelector(`input[name="correct_tf"][value="${q.correct_answer}"]`).checked = true;
            }

            if (q.image_path) {
                const preview = document.getElementById('questionImagePreview');
                preview.src = '../' + q.image_path;
                preview.style.display = 'block';
            }
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        let deleteTargetId = null;
        function deleteQuestion(id) {
            deleteTargetId = id;
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!deleteTargetId) return;
            
            bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
            Spinner.show('Deleting...');
            
            fetch('../ajax/delete_cbt_question.php', {
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

        function resetBuilder() {
            document.getElementById('questionForm').reset();
            document.getElementById('q_id').value = '';
            document.getElementById('builderTitle').textContent = 'Add New Question';
            document.getElementById('questionImagePreview').style.display = 'none';
            toggleTypeFields();
        }
    </script>
</body>
</html>
