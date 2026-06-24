<?php
// user/lessons.php — Staff Lesson Plan & Note Management
require_once '../includes/auth_check.php';

if ($role !== 'staff') { header('Location: ../dashboard.php'); exit(); }

$school_id = $_SESSION['school_id'] ?? null;

// Fetch staff detail
$sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$sd->execute([$user_id, $school_id]);
$staff = $sd->fetch();
if (!$staff) { header('Location: dashboard.php'); exit(); }
$staff_id = $staff['id'];

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

// Group subjects by class for easier dropdown handling
$class_subjects = [];
foreach ($my_assignments as $row) {
    if (!isset($class_subjects[$row['class_id']])) {
        $class_subjects[$row['class_id']] = [
            'name' => $row['class_name'],
            'subjects' => []
        ];
    }
    $class_subjects[$row['class_id']]['subjects'][] = [
        'id' => $row['subject_id'],
        'name' => $row['subject_name']
    ];
}

// Fetch existing lesson plans
$stmt = $pdo->prepare("
    SELECT lp.*, c.name as class_name, sub.name as subject_name 
    FROM lesson_plans lp
    JOIN classes c ON c.id = lp.class_id
    JOIN subjects sub ON sub.id = lp.subject_id
    WHERE lp.staff_detail_id=? AND lp.school_id=?
    ORDER BY lp.date_planned DESC
");
$stmt->execute([$staff_id, $school_id]);
$lessons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Management | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .lesson-card { transition: 0.3s; cursor: pointer; border-left: 4px solid #1F3C88; position: relative; }
        .lesson-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .lesson-card .card-delete-btn { position: absolute; bottom: 15px; right: 15px; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #FFEBEE; color: #C62828; border: none; opacity: 0; transition: 0.2s; }
        .lesson-card:hover .card-delete-btn { opacity: 1; }
        .lesson-card .card-delete-btn:hover { background: #C62828; color: #fff; }
        .status-badge { font-size: 0.7rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; }
        .status-draft { background: #EEF2FB; color: #1F3C88; }
        .status-published { background: #E8F5E9; color: #2E7D32; }
        #editor { height: 300px; background: white; border-radius: 0 0 12px 12px; }
        .ql-toolbar { border-radius: 12px 12px 0 0; background: #f8f9fa; }
        .confirm-modal-icon { width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; background: #FFEBEE; color: #C62828; }
        .confirm-modal-icon.success { background: #E8F5E9; color: #2E7D32; }
        
        @media (max-width: 576px) {
            .main-content { padding: 15px !important; }
            .modal-body { padding: 15px !important; }
            #editor { height: 200px; }
            .modal-header, .modal-footer { padding: 15px !important; }
            .btn-primary.rounded-pill { width: 100%; margin-top: 10px; }
            .d-flex.justify-content-between.align-items-center.mb-4 { flex-direction: column; align-items: flex-start !important; }
        }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-0">Lesson Management</h4>
                    <p class="text-muted small mb-0">Create and manage your professional lesson plans and notes.</p>
                </div>
                <button class="btn btn-primary rounded-pill px-4" onclick="openLessonModal()">
                    <i class="fas fa-plus me-2"></i>New Lesson Plan
                </button>
            </div>

            <div class="row g-4">
                <?php if (empty($lessons)): ?>
                    <div class="col-12">
                        <div class="glass-card p-5 text-center">
                            <div class="mb-3 text-muted" style="font-size: 3rem;"><i class="fas fa-chalkboard"></i></div>
                            <h5 class="fw-bold">No Lesson Plans Yet</h5>
                            <p class="text-muted">Start creating your first lesson plan by clicking the button above.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($lessons as $l): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="glass-card p-3 lesson-card" onclick="editLesson(<?php echo $l['id']; ?>)">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="status-badge status-<?php echo $l['status']; ?>">
                                        <?php echo strtoupper($l['status']); ?>
                                    </span>
                                    <div class="dropdown no-print">
                                        <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editLesson(<?php echo $l['id']; ?>)"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteLesson(<?php echo $l['id']; ?>, '<?php echo addslashes($l['topic']); ?>')"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <h6 class="fw-bold text-truncate mb-1"><?php echo htmlspecialchars($l['topic']); ?></h6>
                                <p class="small text-muted mb-2"><?php echo htmlspecialchars($l['sub_topic'] ?: 'No sub-topic'); ?></p>
                                <div class="d-flex align-items-center gap-2 small text-muted border-top pt-2">
                                    <span><i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($l['class_name']); ?></span>
                                    <span>&bull;</span>
                                    <span><i class="fas fa-book me-1"></i><?php echo htmlspecialchars($l['subject_name']); ?></span>
                                </div>
                                <div class="small text-muted mt-1">
                                    <i class="fas fa-calendar-alt me-1"></i><?php echo date('M d, Y', strtotime($l['date_planned'])); ?>
                                </div>
                                <button class="card-delete-btn" onclick="event.stopPropagation(); deleteLesson(<?php echo $l['id']; ?>, '<?php echo addslashes($l['topic']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                <h4 class="fw-bold mb-2">Delete Lesson Plan?</h4>
                <p class="text-muted mb-4">Are you sure you want to remove "<span id="deleteItemName" class="fw-bold text-dark"></span>"? This action cannot be undone.</p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDeleteBtn">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-body p-5 text-center">
                <div class="confirm-modal-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <h4 class="fw-bold mb-2">Success!</h4>
                <p class="text-muted mb-4">The lesson plan has been deleted successfully.</p>
                <button type="button" class="btn btn-primary rounded-pill px-5" onclick="location.reload()">Great!</button>
            </div>
        </div>
    </div>
</div>

    <!-- Lesson Modal -->
    <div class="modal fade" id="lessonModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold" id="modalTitle">Create Lesson Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="lessonForm">
                        <input type="hidden" id="lesson_id">
                        
                        <!-- Header Info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="small fw-bold mb-1">Class *</label>
                                <select class="form-select" id="class_id" required onchange="updateSubjects()">
                                    <option value="">Select Class</option>
                                    <?php foreach ($class_subjects as $cid => $data): ?>
                                        <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($data['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold mb-1">Subject *</label>
                                <select class="form-select" id="subject_id" required>
                                    <option value="">Select Subject</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold mb-1">Date Planned *</label>
                                <input type="date" class="form-control" id="date_planned" required>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold mb-1">Duration (e.g. 40 mins)</label>
                                <input type="text" class="form-control" id="duration" placeholder="e.g. 40 mins">
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Left Column: Structured Plan -->
                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">Lesson Fundamentals</h6>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Topic *</label>
                                    <input type="text" class="form-control" id="topic" required placeholder="Main lesson topic">
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Sub-topic</label>
                                    <input type="text" class="form-control" id="sub_topic" placeholder="Specific sub-topic">
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Learning Objectives</label>
                                    <textarea class="form-control" id="learning_objectives" rows="3" placeholder="What should students know by the end?"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Instructional Materials</label>
                                    <textarea class="form-control" id="instructional_materials" rows="2" placeholder="Textbooks, charts, videos, etc."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Introduction</label>
                                    <textarea class="form-control" id="introduction" rows="2" placeholder="Hook the students..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Presentation Steps (Procedure)</label>
                                    <textarea class="form-control" id="presentation_steps" rows="4" placeholder="Step-by-step procedure..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Evaluation Questions</label>
                                    <textarea class="form-control" id="evaluation_questions" rows="3" placeholder="Questions to test understanding..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold mb-1">Conclusion</label>
                                    <textarea class="form-control" id="conclusion" rows="2" placeholder="Summary or closing..."></textarea>
                                </div>
                            </div>

                            <!-- Right Column: Rich Lesson Note -->
                            <div class="col-lg-6">
                                <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">Lesson Note (Rich Text)</h6>
                                <div id="editor-container">
                                    <div id="editor"></div>
                                </div>
                                <div class="mt-3">
                                    <label class="small fw-bold mb-1">Status</label>
                                    <select class="form-select" id="status">
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveLesson()">
                        <i class="fas fa-save me-2"></i>Save Lesson
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        const classSubjects = <?php echo json_encode($class_subjects); ?>;
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Write your comprehensive lesson note here...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    ['image', 'link', 'clean']
                ]
            }
        });

        function updateSubjects() {
            const classId = document.getElementById('class_id').value;
            const subSel = document.getElementById('subject_id');
            subSel.innerHTML = '<option value="">Select Subject</option>';
            
            if (classId && classSubjects[classId]) {
                classSubjects[classId].subjects.forEach(s => {
                    subSel.innerHTML += `<option value="${s.id}">${s.name}</option>`;
                });
            }
        }

        function openLessonModal() {
            document.getElementById('lessonForm').reset();
            document.getElementById('lesson_id').value = '';
            document.getElementById('modalTitle').textContent = 'Create Lesson Plan';
            quill.root.innerHTML = '';
            new bootstrap.Modal(document.getElementById('lessonModal')).show();
        }

        function saveLesson() {
            const data = {
                id: document.getElementById('lesson_id').value,
                class_id: document.getElementById('class_id').value,
                subject_id: document.getElementById('subject_id').value,
                topic: document.getElementById('topic').value,
                sub_topic: document.getElementById('sub_topic').value,
                date_planned: document.getElementById('date_planned').value,
                duration: document.getElementById('duration').value,
                learning_objectives: document.getElementById('learning_objectives').value,
                instructional_materials: document.getElementById('instructional_materials').value,
                introduction: document.getElementById('introduction').value,
                presentation_steps: document.getElementById('presentation_steps').value,
                evaluation_questions: document.getElementById('evaluation_questions').value,
                conclusion: document.getElementById('conclusion').value,
                lesson_note: quill.root.innerHTML,
                status: document.getElementById('status').value
            };

            if (!data.class_id || !data.subject_id || !data.topic || !data.date_planned) {
                Notif.show('Please fill required fields', 'error');
                return;
            }

            Spinner.show('Saving lesson...');
            fetch('../ajax/save_lesson_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    location.reload();
                } else {
                    Notif.show(d.message, 'error');
                    Spinner.hide();
                }
            });
        }

        function editLesson(id) {
            Spinner.show('Loading lesson...');
            fetch(`../ajax/get_lesson_plan.php?id=${id}`)
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    const p = d.plan;
                    document.getElementById('lesson_id').value = p.id;
                    document.getElementById('class_id').value = p.class_id;
                    updateSubjects();
                    document.getElementById('subject_id').value = p.subject_id;
                    document.getElementById('topic').value = p.topic;
                    document.getElementById('sub_topic').value = p.sub_topic;
                    document.getElementById('date_planned').value = p.date_planned;
                    document.getElementById('duration').value = p.duration;
                    document.getElementById('learning_objectives').value = p.learning_objectives;
                    document.getElementById('instructional_materials').value = p.instructional_materials;
                    document.getElementById('introduction').value = p.introduction;
                    document.getElementById('presentation_steps').value = p.presentation_steps;
                    document.getElementById('evaluation_questions').value = p.evaluation_questions;
                    document.getElementById('conclusion').value = p.conclusion;
                    document.getElementById('status').value = p.status;
                    quill.root.innerHTML = p.lesson_note || '';
                    
                    document.getElementById('modalTitle').textContent = 'Edit Lesson Plan';
                    new bootstrap.Modal(document.getElementById('lessonModal')).show();
                } else {
                    Notif.show(d.message, 'error');
                }
            });
        }

let deleteTargetId = null;
function deleteLesson(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteItemName').textContent = name || 'this lesson plan';
    new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteTargetId) return;
    
    // Close confirm modal
    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
    
    Spinner.show('Deleting...');
    fetch('../ajax/delete_lesson_plan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
    </script>
</body>
</html>
