<?php
// admin/departments.php
require_once '../includes/auth_check.php';
if ($role !== 'owner' && $role !== 'staff' && $role !== 'super_admin') {
    header('Location: ../dashboard.php');
    exit();
}
if (!$active_school) { header('Location: dashboard.php'); exit(); }

$label = get_label('Section');
$label_pl = get_label('Sections');

// Fetch Departments (Sections)
$stmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM subjects WHERE department_id = s.id) as course_count
    FROM school_sections s 
    WHERE s.school_id = ? 
    ORDER BY s.section_name ASC
");
$stmt->execute([$active_school['id']]);
$departments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $label_pl; ?> Management | <?php echo htmlspecialchars($active_school['school_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .builder-row { background:#f8faff; border:1px solid #e3eaff; border-radius:15px; padding:20px; margin-bottom:15px; transition:all .3s ease; }
        .builder-row:hover { transform: translateY(-2px); box-shadow:0 8px 24px rgba(31,60,136,.08); }
        .btn-add-row { border:2px dashed #2D6CDF; color:#2D6CDF; background:transparent; border-radius:12px; width:100%; padding:14px; font-weight:700; transition:.2s; }
        .btn-add-row:hover { background:#f0f4ff; border-style: solid; }
        .dept-card { border-radius:20px; border:1px solid #eef2fb; background:#fff; overflow:hidden; transition:all .3s ease; }
        .dept-card:hover { border-color: #2D6CDF; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .dept-icon { width: 48px; height: 48px; border-radius: 12px; background: #EEF2FB; color: #1F3C88; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .stats-badge { font-size: 0.7rem; font-weight: 700; padding: 4px 12px; border-radius: 50px; background: #f1f5f9; color: #475569; }
        .dept-code { font-family: 'Monaco', 'Consolas', monospace; font-weight: 700; color: #2D6CDF; font-size: 0.85rem; padding: 2px 8px; background: #f0f7ff; border-radius: 6px; }
    </style>
</head>
<body class="bg-light">
<?php include '../includes/spinner.php'; ?>

<div class="dashboard-wrapper">
    <?php include '../includes/admin_sidebar.php'; ?>
    <main class="main-content">
        <?php include '../includes/dashboard_top_nav.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0"><?php echo $label_pl; ?> Management</h3>
                <p class="text-muted small mb-0">Organize <?php echo strtolower(get_label('Subjects')); ?> and <?php echo strtolower(get_label('Classes')); ?> under professional institutional <?php echo strtolower($label_pl); ?>.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="academics.php" class="btn btn-light rounded-pill px-4 border">
                    <i class="fas fa-book-open me-2"></i>View <?php echo get_label('Subjects'); ?>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left: Creation Area -->
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-800 text-uppercase tracking-wider text-primary mb-0 d-flex justify-content-between align-items-center w-100" id="builderCardTitle" style="font-size:0.75rem;">Create New <?php echo $label_pl; ?></h6>
                    </div>
                    
                    <div id="deptBuilderRows">
                        <!-- Initial row added via JS -->
                    </div>

                    <button class="btn-add-row mt-2" onclick="addDeptRow()">
                        <i class="fas fa-plus-circle me-2"></i>Add Another <?php echo $label; ?>
                    </button>

                    <div class="mt-4 pt-3 border-top text-end">
                        <button class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-sm" onclick="saveDepartments()">
                            <i class="fas fa-save me-2"></i>SAVE ALL <?php echo strtoupper($label_pl); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Inventory -->
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-800 text-uppercase tracking-wider text-muted mb-0" style="font-size:0.75rem;">Registered <?php echo $label_pl; ?> (<?php echo count($departments); ?>)</h6>
                    </div>

                    <?php if (empty($departments)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3 text-muted opacity-25">
                                <i class="fas fa-<?php echo ($label === 'Department') ? 'building-columns' : 'layer-group'; ?> fa-4x"></i>
                            </div>
                            <p class="text-muted fw-500">No <?php echo strtolower($label_pl); ?> configured yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($departments as $dept): ?>
                                <div class="dept-card p-3 d-flex align-items-center gap-3" id="dept-item-<?php echo $dept['id']; ?>">
                                    <div class="dept-icon">
                                        <i class="fas fa-<?php echo ($label === 'Department') ? 'university' : 'layer-group'; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="fw-700 text-dark text-truncate"><?php echo htmlspecialchars($dept['section_name']); ?></span>
                                            <?php if (!empty($dept['section_code'])): ?>
                                                <span class="dept-code"><?php echo htmlspecialchars($dept['section_code']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="stats-badge"><i class="fas fa-book-open me-1"></i><?php echo $dept['course_count']; ?> <?php echo get_label('Subjects'); ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1" title="Edit <?php echo $label; ?>" onclick="editDept(<?php echo $dept['id']; ?>, '<?php echo addslashes($dept['section_name']); ?>', '<?php echo addslashes($dept['section_code'] ?: ''); ?>')">
                                            <i class="fas fa-pen-to-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" title="Delete <?php echo $label; ?>" onclick="deleteDept(<?php echo $dept['id']; ?>, '<?php echo addslashes($dept['section_name']); ?>')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 28px;">
            <div class="modal-body p-5 text-center">
                <div style="width:70px;height:70px;background:#FFEBEE;color:#C62828;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 24px;">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
                <h4 class="fw-900 mb-2">Delete <?php echo $label; ?>?</h4>
                <p class="text-muted mb-4">Are you sure you want to remove "<span id="deleteItemName" class="fw-bold text-dark"></span>"? This will un-link all associated <?php echo strtolower(get_label('Subjects')); ?>.</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-danger btn-lg rounded-pill fw-bold py-3" id="confirmDeleteBtn">YES, DELETE <?php echo strtoupper($label); ?></button>
                    <button type="button" class="btn btn-light btn-lg rounded-pill fw-bold py-3 border" data-bs-dismiss="modal">CANCEL</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let deptRowId = 0;

function addDeptRow(id = '', name = '', code = '') {
    const rid = ++deptRowId;
    const html = `
        <div class="builder-row animate-fade" id="drow-${rid}">
            <div class="row g-3 align-items-center">
                <input type="hidden" name="dept_id" value="${id}">
                <div class="col-md-7">
                    <label class="form-label extra-small fw-800 text-muted text-uppercase tracking-wider"><?php echo $label; ?> Name</label>
                    <input type="text" class="form-control" name="dept_name" value="${name}" placeholder="e.g. <?php echo ($label === 'Department') ? 'Computer Science & Engineering' : 'Primary Section'; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label extra-small fw-800 text-muted text-uppercase tracking-wider">Short Code</label>
                    <input type="text" class="form-control" name="dept_code" value="${code}" placeholder="e.g. <?php echo ($label === 'Department') ? 'CSE' : 'PRI'; ?>" maxlength="10">
                </div>
                <div class="col-md-2 text-end pt-4">
                    <button class="btn btn-outline-danger border-0 rounded-circle" onclick="removeDeptRow(${rid})">
                        <i class="fas fa-circle-minus fa-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    document.getElementById('deptBuilderRows').insertAdjacentHTML('beforeend', html);
}

function removeDeptRow(rid) {
    const el = document.getElementById('drow-' + rid);
    if (el) el.remove();
    
    // Auto add back a blank row if none remain
    const rows = document.querySelectorAll('#deptBuilderRows .builder-row');
    if (rows.length === 0) {
        addDeptRow();
    }
}

function saveDepartments() {
    const rows = document.querySelectorAll('#deptBuilderRows .builder-row');
    const departments = [];
    
    rows.forEach(r => {
        const name = r.querySelector('[name="dept_name"]').value.trim();
        const code = r.querySelector('[name="dept_code"]').value.trim();
        const id   = r.querySelector('[name="dept_id"]').value;
        if (name) departments.push({ id, name, code });
    });

    if (departments.length === 0) {
        return Notif.show('Add at least one <?php echo strtolower($label); ?>', 'warning');
    }

    Spinner.show('Safeguarding institutional structure...');
    fetch('../ajax/save_departments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ departments })
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success) {
            Notif.show(d.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            Notif.show(d.message, 'error');
        }
    });
}

function editDept(id, name, code) {
    document.getElementById('builderCardTitle').innerHTML = `Edit <?php echo $label; ?> <button class="btn btn-sm btn-link text-decoration-none text-muted fw-bold ms-2 p-0" onclick="resetBuilder()"><i class="fas fa-times me-1"></i>Cancel</button>`;
    document.getElementById('deptBuilderRows').innerHTML = '';
    addDeptRow(id, name, code);
    document.querySelector('.btn-add-row').style.display = 'none';
    document.getElementById('deptBuilderRows').scrollIntoView({ behavior: 'smooth' });
}

function resetBuilder() {
    document.getElementById('builderCardTitle').textContent = 'Create New <?php echo $label_pl; ?>';
    document.getElementById('deptBuilderRows').innerHTML = '';
    document.querySelector('.btn-add-row').style.display = 'block';
    addDeptRow();
}

let deleteTargetId = null;
function deleteDept(id, name) {
    deleteTargetId = id;
    document.getElementById('deleteItemName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteTargetId) return;
    Spinner.show('Removing <?php echo strtolower($label); ?>...');
    const fd = new FormData();
    fd.append('id', deleteTargetId);

    fetch('../ajax/delete_department.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        Spinner.hide();
        if (d.success) {
            location.reload();
        } else {
            Notif.show(d.message, 'error');
        }
    });
});

// Initialize with one empty row if none exist
addDeptRow();
</script>
</body>
</html>
