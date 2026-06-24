<?php
// admin/staff.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

// Get Staff for Active School
$pending_staff = [];
$active_staff = [];
if ($active_school_id) {
    $stmt = $pdo->prepare("
        SELECT sd.id as detail_id, sd.can_manage_students, sd.can_manage_academics, sd.can_manage_cbt, sd.can_edit_history, u.full_name, u.email, u.phone, sd.status, sd.created_at 
        FROM staff_details sd 
        JOIN users u ON sd.user_id = u.id 
        WHERE sd.school_id = ?
    ");
    $stmt->execute([$active_school_id]);
    $all_staff = $stmt->fetchAll();
    
    foreach ($all_staff as $s) {
        if ($s['status'] === 'pending') $pending_staff[] = $s;
        else $active_staff[] = $s;
    }
}

// Fetch classes and subjects for assignment modal
$classes_for_modal = [];
$subjects_for_modal = [];
if ($active_school_id) {
    $stmt = $pdo->prepare("SELECT id,name,code,section FROM classes WHERE school_id=? ORDER BY name");
    $stmt->execute([$active_school_id]);
    $classes_for_modal = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id,name,code,is_course FROM subjects WHERE school_id=? ORDER BY name");
    $stmt->execute([$active_school_id]);
    $subjects_for_modal = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_label('Staff'); ?> Management | EduRemarks</title>
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
                    <h3 class="fw-bold mb-0"><?php echo get_label('Staff'); ?> Management</h3>
                    <p class="text-muted small mb-0">Review join requests and manage your active workforce.</p>
                </div>
                <a href="add_staff.php" class="btn btn-gold btn-sm rounded-pill px-3 py-1" style="font-size: 0.8rem;">
                    <i class="fas fa-plus me-1"></i>Add <?php echo get_label('Staff'); ?>
                </a>
            </div>

            <div class="glass-card p-4 mb-4">
                <nav>
                    <div class="nav nav-tabs border-0" id="nav-tab" role="tablist">
                        <button class="nav-link active border-0 fw-bold px-4" id="nav-pending-tab" data-bs-toggle="tab" data-bs-target="#nav-pending" type="button" role="tab">
                            Pending Requests <span class="badge bg-warning ms-2"><?php echo count($pending_staff); ?></span>
                        </button>
                        <button class="nav-link border-0 fw-bold px-4" id="nav-active-tab" data-bs-toggle="tab" data-bs-target="#nav-active" type="button" role="tab">
                            Active <?php echo get_label('Staff'); ?> <span class="badge bg-primary ms-2"><?php echo count($active_staff); ?></span>
                        </button>
                    </div>
                </nav>
                <div class="tab-content pt-4" id="nav-tabContent">
                    <div class="tab-pane fade show active" id="nav-pending" role="tabpanel">
                        <?php if (empty($pending_staff)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-clock text-muted mb-3" style="font-size: 2rem;"></i>
                                <p class="text-muted">No pending staff join requests.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Email Address</th>
                                            <th>Date Requested</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_staff as $ps): ?>
                                        <tr>
                                            <td data-label="Full Name" class="fw-bold"><?php echo $ps['full_name']; ?></td>
                                            <td data-label="Email"><?php echo $ps['email']; ?></td>
                                            <td data-label="Requested"><?php echo date('M d, Y', strtotime($ps['created_at'])); ?></td>
                                            <td data-label="Actions" class="text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-sm btn-success px-3" onclick="manageStaff(<?php echo $ps['detail_id']; ?>, 'approve')">Approve</button>
                                                    <button class="btn btn-sm btn-outline-danger px-3" onclick="manageStaff(<?php echo $ps['detail_id']; ?>, 'reject')">Reject</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="nav-active" role="tabpanel">
                        <?php if (empty($active_staff)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users-slash text-muted mb-3" style="font-size: 2rem;"></i>
                                <p class="text-muted">You haven't approved any staff members yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Email Address</th>
                                            <th>Status</th>
                                            <th>Permissions <i class="fas fa-info-circle text-muted" title="Manage <?php echo get_label('Pupils'); ?> allows editing records. Manage Academics allows <?php echo strtolower(get_label('Class')); ?>/<?php echo strtolower(get_label('Subject')); ?> settings."></i></th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($active_staff as $as): ?>
                                        <tr>
                                            <td data-label="Staff" class="fw-bold"><?php echo htmlspecialchars($as['full_name']); ?></td>
                                            <td data-label="Email"><?php echo htmlspecialchars($as['email']); ?></td>
                                            <td data-label="Status">
                                                <?php if($as['status'] === 'active'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Permissions">
                                                <div class="d-flex flex-column gap-2 text-start">
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" role="switch" 
                                                               id="perm-stu-<?php echo $as['detail_id']; ?>" 
                                                               <?php echo $as['can_manage_students'] ? 'checked' : ''; ?>
                                                               onchange="togglePermission(<?php echo $as['detail_id']; ?>, 'can_manage_students', this.checked, 'stu')"
                                                               title="Allow this <?php echo strtolower(get_label('Staff')); ?> to add/edit <?php echo strtolower(get_label('Pupils')); ?>">
                                                        <label class="form-check-label small" for="perm-stu-<?php echo $as['detail_id']; ?>">
                                                            Manage <?php echo get_label('Pupils'); ?>
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox" role="switch" 
                                                               id="perm-acad-<?php echo $as['detail_id']; ?>" 
                                                               <?php echo $as['can_manage_academics'] ? 'checked' : ''; ?>
                                                               onchange="togglePermission(<?php echo $as['detail_id']; ?>, 'can_manage_academics', this.checked, 'acad')"
                                                               title="Allow this <?php echo strtolower(get_label('Staff')); ?> to manage <?php echo strtolower(get_label('Classes')); ?>, <?php echo strtolower(get_label('Subjects')); ?>, and <?php echo strtolower(get_label('Terms')); ?>">
                                                        <label class="form-check-label small" for="perm-acad-<?php echo $as['detail_id']; ?>">
                                                            Manage Academics
                                                        </label>
                                                    </div>
                                                    <?php if (strpos($active_school['feature_access'] ?? '', 'CBT_EXAMS') !== false): ?>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input border-warning" type="checkbox" role="switch" 
                                                               id="perm-cbt-<?php echo $as['detail_id']; ?>" 
                                                               <?php echo $as['can_manage_cbt'] ? 'checked' : ''; ?>
                                                               onchange="togglePermission(<?php echo $as['detail_id']; ?>, 'can_manage_cbt', this.checked, 'cbt')"
                                                               title="Allow this <?php echo strtolower(get_label('Staff')); ?> to utilize the Elite Question Builder feature.">
                                                        <label class="form-check-label small text-warning fw-bold" for="perm-cbt-<?php echo $as['detail_id']; ?>">
                                                            <i class="fas fa-bolt"></i> Elite Builder Access
                                                        </label>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input border-info" type="checkbox" role="switch" 
                                                               id="perm-hist-<?php echo $as['detail_id']; ?>" 
                                                               <?php echo $as['can_edit_history'] ? 'checked' : ''; ?>
                                                               onchange="togglePermission(<?php echo $as['detail_id']; ?>, 'can_edit_history', this.checked, 'hist')"
                                                               title="Allow this <?php echo strtolower(get_label('Staff')); ?> to update historical academic results.">
                                                        <label class="form-check-label small text-info fw-bold" for="perm-hist-<?php echo $as['detail_id']; ?>">
                                                            <i class="fas fa-history"></i> Edit Result History
                                                        </label>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Actions" class="text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <a href="edit_staff.php?id=<?php echo $as['detail_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Profile">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-primary" title="Assign <?php echo get_label('Class'); ?> & <?php echo get_label('Subject'); ?>"
                                                        onclick="openAssign(<?php echo $as['detail_id']; ?>, '<?php echo addslashes($as['full_name']); ?>')">
                                                        <i class="fas fa-graduation-cap"></i>
                                                    </button>
                                                    
                                                    <?php if($as['status'] === 'active'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" title="Suspend Access" onclick="manageStaff(<?php echo $as['detail_id']; ?>, 'suspend')">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success" title="Activate Access" onclick="manageStaff(<?php echo $as['detail_id']; ?>, 'activate')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="btn btn-sm btn-outline-danger" title="Remove Permanently" onclick="manageStaff(<?php echo $as['detail_id']; ?>, 'delete')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <!-- Assign Class Modal -->
    <div class="modal fade" id="assignClassModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content glass-card">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title"><i class="fas fa-graduation-cap me-2"></i>Assign <?php echo get_label('Class'); ?> &amp; <?php echo get_label('Subjects'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="assign_staff_detail_id">
                    <p class="text-muted small mb-3">Assigning to: <strong id="assign_staff_name"></strong></p>

                    <?php if (empty($classes_for_modal)): ?>
                        <div class="alert alert-warning border-0"><i class="fas fa-exclamation-triangle me-2"></i>No <?php echo strtolower(get_label('Classes')); ?> created. <a href="academics.php">Create <?php echo strtolower(get_label('classes')); ?> first.</a></div>
                    <?php elseif (empty($subjects_for_modal)): ?>
                        <div class="alert alert-info border-0"><i class="fas fa-info-circle me-2"></i>No <?php echo strtolower(get_label('Subjects')); ?> created yet. <a href="academics.php">Add them first.</a></div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Allocate <?php echo get_label('Classes'); ?> &amp; <?php echo get_label('Subjects'); ?> <span class="text-danger">*</span></label>
                        <div class="accordion accordion-flush bg-white border border-light-subtle rounded-3 shadow-sm" id="classesAccordion" style="max-height: 420px; overflow-y: auto;">
                            <?php foreach ($classes_for_modal as $c): ?>
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header" id="heading<?php echo $c['id']; ?>">
                                    <button class="accordion-button collapsed bg-light text-dark fw-bold py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $c['id']; ?>">
                                        <div class="form-check me-2" onclick="event.stopPropagation();">
                                            <input class="form-check-input class-checkbox" type="checkbox" value="<?php echo $c['id']; ?>" id="chk_class_<?php echo $c['id']; ?>" onchange="toggleClassAccordion(<?php echo $c['id']; ?>, this.checked)">
                                        </div>
                                        <?php echo htmlspecialchars($c['name']); ?> <small class="text-muted ms-1 fw-normal">(<?php echo htmlspecialchars($c['code']); ?>)</small>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $c['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#classesAccordion">
                                    <div class="accordion-body bg-white py-3">
                                        <label class="form-label fw-bold small text-muted text-uppercase mb-2">Select Accessible <?php echo get_label('Subjects'); ?></label>
                                        <div class="subject-list-<?php echo $c['id']; ?> row g-2">
                                            <?php foreach ($subjects_for_modal as $sub): ?>
                                            <div class="col-md-6 col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input subject-checkbox" type="checkbox" value="<?php echo $sub['id']; ?>" id="sub_chk_<?php echo $c['id']; ?>_<?php echo $sub['id']; ?>" onchange="checkParentClass(<?php echo $c['id']; ?>)">
                                                    <label class="form-check-label small" for="sub_chk_<?php echo $c['id']; ?>_<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?> <span class="text-muted opacity-75">(<?php echo $sub['code']; ?>)</span></label>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow" onclick="doAssign()"><i class="fas fa-save me-2"></i>Save Allocations</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Credentials Modal -->

    <!-- Delete Confirm Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-body p-5 text-center">
                    <div class="confirm-modal-icon mb-4" style="width:70px;height:70px;background:#FFEBEE;color:#C62828;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:2rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Delete Staff Member?</h4>
                    <p class="text-muted mb-4">Are you sure you want to remove "<span id="del_staff_name" class="fw-bold text-dark"></span>"? This action cannot be undone and will affect all associated data.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">No, Keep it</button>
                        <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDelBtn">Yes, Delete</button>
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
                    <div class="confirm-modal-icon success mb-4" style="width:70px;height:70px;background:#E8F5E9;color:#2E7D32;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:2rem;">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Success!</h4>
                    <p class="text-muted mb-4">The record has been deleted successfully.</p>
                    <button type="button" class="btn btn-primary rounded-pill px-5" onclick="location.reload()">Great!</button>
                </div>
            </div>
        </div>
    </div>


    <script>
        function openAssign(staffDetailId, name) {
            document.getElementById('assign_staff_detail_id').value = staffDetailId;
            document.getElementById('assign_staff_name').textContent = name;
            
            // clear all checkboxes and close accordions
            document.querySelectorAll('.class-checkbox, .subject-checkbox').forEach(c => c.checked = false);
            document.querySelectorAll('.accordion-collapse').forEach(el => el.classList.remove('show'));
            
            fetch(`../ajax/get_staff_class_subjects.php?staff_detail_id=${staffDetailId}`)
            .then(r=>r.json()).then(d => {
                if(d.success && d.assignments) {
                    for (const [classId, subjectIds] of Object.entries(d.assignments)) {
                        const classChk = document.getElementById('chk_class_' + classId);
                        if(classChk) classChk.checked = true;
                        
                        subjectIds.forEach(sid => {
                            const subChk = document.getElementById(`sub_chk_${classId}_${sid}`);
                            if(subChk) subChk.checked = true;
                        });
                    }
                }
            });
            new bootstrap.Modal(document.getElementById('assignClassModal')).show();
        }

        function toggleClassAccordion(classId, isChecked) {
            if(isChecked) {
                document.getElementById('collapse' + classId).classList.add('show');
            } else {
                document.querySelectorAll(`.subject-list-${classId} .subject-checkbox`).forEach(c => c.checked = false);
                document.getElementById('collapse' + classId).classList.remove('show');
            }
        }

        function checkParentClass(classId) {
            const anyChecked = Array.from(document.querySelectorAll(`.subject-list-${classId} .subject-checkbox`)).some(c => c.checked);
            const classChk = document.getElementById('chk_class_' + classId);
            if(anyChecked && classChk) classChk.checked = true;
        }

        function doAssign() {
            const sdId = document.getElementById('assign_staff_detail_id').value;
            const allocations = {};

            document.querySelectorAll('.class-checkbox:checked').forEach(cChk => {
                const classId = cChk.value;
                const subjectIds = Array.from(document.querySelectorAll(`.subject-list-${classId} .subject-checkbox:checked`)).map(s => s.value);
                
                if (subjectIds.length > 0) {
                    allocations[classId] = subjectIds;
                }
            });

            Spinner.show('Assigning...');
            fetch('../ajax/assign_staff_class.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ staff_detail_id: sdId, allocations: allocations })
            })
            .then(r=>r.json()).then(d => {
                Spinner.hide();
                if(d.success) { 
                    bootstrap.Modal.getInstance(document.getElementById('assignClassModal')).hide(); 
                    Notif.show(d.message); 
                } else {
                    Notif.show(d.message, 'error');
                }
            }).catch(e => {
                Spinner.hide();
                Notif.show('Failed to save allocations', 'error');
            });
        }

        let deleteTargetId = null;
        function confirmDeleteStaff(detailId, name) {
            deleteTargetId = detailId;
            document.getElementById('del_staff_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        document.getElementById('confirmDelBtn').addEventListener('click', function(){
            bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
            manageStaff(deleteTargetId, 'delete');
        });

        function manageStaff(detailId, action) {
            const verbMap = { approve:'Approving', reject:'Rejecting', suspend:'Suspending', activate:'Activating', delete:'Deleting' };
            Spinner.show(`${verbMap[action] || 'Processing'}...`);
            
            // Get the global CSRF token defined in footer.php
            const csrfToken = typeof EDUREMARKS_CSRF_TOKEN !== 'undefined' ? EDUREMARKS_CSRF_TOKEN : '';

            fetch('../ajax/manage_staff.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken 
                },
                body: `staff_detail_id=${detailId}&action=${action}&csrf_token=${csrfToken}`
            }).then(r => r.json()).then(d => {
                if (d.success) { 
                    if (action === 'delete') {
                        new bootstrap.Modal(document.getElementById('successModal')).show();
                    } else {
                        Notif.show(d.message); 
                        setTimeout(() => location.reload(), 1500); 
                    }
                }
                else { Notif.show(d.message, 'error'); Spinner.hide(); }
            }).catch(e => {
                Notif.show('Request failed: ' + e.message, 'error');
                Spinner.hide();
            });
        }

        // Toggle Staff Permissions
        function togglePermission(detailId, permission, enabled, typePrefix) {
            const csrfToken = typeof EDUREMARKS_CSRF_TOKEN !== 'undefined' ? EDUREMARKS_CSRF_TOKEN : '';
            fetch('../ajax/toggle_staff_permission.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: `staff_detail_id=${detailId}&permission=${permission}&enabled=${enabled ? 1 : 0}&csrf_token=${csrfToken}`
            }).then(r => r.json()).then(d => {
                const labelTitle = permission === 'can_manage_students' ? 'Manage Students' : 'Manage Academics';
                const label = document.querySelector(`label[for="perm-${typePrefix}-${detailId}"]`);
                if (d.success) {
                    Notif.show(d.message, 'success');
                    if (label) label.innerHTML = `${labelTitle}: ${enabled ? '<span class="text-success fw-bold">ON</span>' : '<span class="text-muted">OFF</span>'}`;
                } else {
                    Notif.show(d.message, 'error');
                    document.getElementById(`perm-${typePrefix}-${detailId}`).checked = !enabled;
                }
            }).catch(() => {
                Notif.show('Network error', 'error');
                document.getElementById(`perm-${typePrefix}-${detailId}`).checked = !enabled;
            });
        }

    </script>
</body>
</html>
