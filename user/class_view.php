<?php
// user/class_view.php  — Staff portal: view one assigned class and its students
require_once '../includes/auth_check.php';

if ($role !== 'staff') { header('Location: ../dashboard.php'); exit(); }

$school_id = $_SESSION['school_id'] ?? null;
$class_id  = intval($_GET['class_id'] ?? 0);

if (!$class_id || !$school_id) { header('Location: dashboard.php'); exit(); }

// Verify this staff is active in the school and assigned to this class
$sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$sd->execute([$user_id, $school_id]);
$sd_row = $sd->fetch();
if (!$sd_row) { header('Location: dashboard.php'); exit(); }

$assigned = $pdo->prepare("SELECT id FROM staff_class_subjects WHERE staff_detail_id=? AND class_id=? AND school_id=? LIMIT 1");
$assigned->execute([$sd_row['id'], $class_id, $school_id]);
if (!$assigned->fetch()) { header('Location: dashboard.php'); exit(); }

// Fetch class details
$cls_stmt = $pdo->prepare("SELECT * FROM classes WHERE id=? AND school_id=?");
$cls_stmt->execute([$class_id, $school_id]);
$cls = $cls_stmt->fetch();
if (!$cls) { header('Location: dashboard.php'); exit(); }

// Fetch subjects assigned to this staff for this class
$sub_stmt = $pdo->prepare("
    SELECT sub.name, sub.code, sub.period, sub.is_course
    FROM staff_class_subjects scs
    JOIN subjects sub ON sub.id = scs.subject_id
    WHERE scs.staff_detail_id=? AND scs.class_id=? AND scs.school_id=?
");
$sub_stmt->execute([$sd_row['id'], $class_id, $school_id]);
$assigned_subjects = $sub_stmt->fetchAll();

// Fetch students in this class
$stu_stmt = $pdo->prepare("
    SELECT s.id, s.full_name, s.admission_no, s.gender, s.dob, s.guardian_name, s.guardian_phone, s.image_path
    FROM students s
    JOIN student_classes sc ON sc.student_id = s.id
    WHERE sc.class_id=? AND sc.school_id=?
    ORDER BY s.full_name
");
$stu_stmt->execute([$class_id, $school_id]);
$students_in_class = $stu_stmt->fetchAll();

$is_course = !empty($assigned_subjects) && $assigned_subjects[0]['is_course'];
$label_pl  = $is_course ? 'Courses' : 'Subjects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cls['name']); ?> | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @media print {
            .sidebar,.dash-top-nav,.dash-footer,.no-print { display:none !important; }
            .main-content { margin:0 !important; padding:20px !important; }
        }
        .student-avatar { width:34px; height:34px; border-radius:8px; background:linear-gradient(135deg,#1F3C88,#2D6CDF); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }
        .subject-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; background:#EEF2FB; color:#1F3C88; font-weight:600; font-size:.8rem; border:1px solid #d0daff; margin:3px; }
        .class-header { background:linear-gradient(135deg,#1F3C88 0%,#2D6CDF 100%); border-radius:16px; padding:28px 32px; color:#fff; margin-bottom:24px; }
        .table th { font-size:.72rem; text-transform:uppercase; letter-spacing:.8px; color:#94a3b8; font-weight:600; }
        #searchInput { max-width:260px; }

        @media (max-width: 576px) {
            .class-header { padding: 20px !important; }
            .class-header h2 { font-size: 1.25rem !important; }
            .btn-action-mobile { 
                flex: 1; 
                padding: 8px 5px !important; 
                font-size: 0.65rem !important; 
                white-space: nowrap !important;
                display: flex;
                align-items: center;
                justify-content: center;
                letter-spacing: -0.2px;
            }
            .mobile-btn-group {
                display: flex !important;
                flex-direction: row !important;
                width: 100%;
                gap: 8px !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <!-- Class Header -->
        <div class="class-header">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <a href="dashboard.php" class="btn btn-sm no-print" style="background:rgba(255,255,255,0.15); color:#fff; border:none; border-radius:8px;"><i class="fas fa-arrow-left me-1"></i>Back</a>
                        <span style="background:rgba(244,180,0,0.2); color:#F4B400; padding:4px 12px; border-radius:20px; font-size:.75rem; font-weight:700;"><?php echo strtoupper(get_label('Class')); ?> ROSTER</span>
                    </div>
                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($cls['name']); ?></h2>
                    <p class="mb-0" style="opacity:.8;"><?php if (get_label('Subject') !== 'Course'): ?>Code: <strong><?php echo htmlspecialchars($cls['code']); ?></strong><?php endif; ?>
                        <?php if ($cls['section']): ?><?php echo (get_label('Subject') !== 'Course') ? ' &bull; ' : ''; ?><?php echo get_label('Section'); ?>: <strong><?php echo htmlspecialchars($cls['section']); ?></strong><?php endif; ?>
                        &bull; <strong><?php echo count($students_in_class); ?></strong> <?php echo get_label('Pupils'); ?>
                    </p>
                </div>
                <div class="no-print mobile-btn-group d-flex gap-2">
                    <a href="report_management.php?class_id=<?php echo $class_id; ?>" class="btn btn-warning btn-sm rounded-pill px-3 fw-800 shadow-sm btn-action-mobile">
                        <i class="fas fa-file-invoice me-1"></i> <?php echo get_label('Report Sheets'); ?>
                    </a>
                    <button class="btn btn-sm btn-action-mobile" style="background:rgba(255,255,255,0.15); color:#fff; border:none; border-radius:20px;" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Roster
                    </button>
                </div>
            </div>

            <?php if (!empty($assigned_subjects)): ?>
            <div class="mt-3">
                <small style="opacity:.7; display:block; margin-bottom:6px;">Your <?php echo $label_pl; ?>:</small>
                <?php foreach ($assigned_subjects as $sub): ?>
                <span class="subject-pill" style="background:rgba(255,255,255,0.15); color:#fff; border-color:rgba(255,255,255,0.2);">
                    <i class="fas fa-book-open" style="font-size:.7rem;"></i>
                    <?php echo htmlspecialchars($sub['name']); ?> <small style="opacity:.7;">(<?php echo $sub['code']; ?>)</small>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Students Table -->
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
                <p class="mb-0 fw-600"><?php echo rtrim(get_label('Pupils'), 's'); ?> Roster — <?php echo htmlspecialchars($cls['name']); ?></p>
                <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search <?php echo strtolower(get_label('Pupils')); ?>..." oninput="filterStudents()">
            </div>

            <?php if (empty($students_in_class)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-graduate text-muted mb-3" style="font-size:2.5rem;"></i>
                <h5>No <?php echo get_label('Pupils'); ?> Allocated</h5>
                <p class="text-muted">The admin has not allocated any <?php echo strtolower(get_label('Pupils')); ?> to this <?php echo strtolower(get_label('Class')); ?> yet.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle" id="rosterTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo rtrim(get_label('Pupils'), 's'); ?> Name</th>
                            <th>Admission No</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Guardian</th>
                            <th>Guardian Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students_in_class as $i => $stu): ?>
                    <tr>
                        <td class="text-muted"><?php echo $i + 1; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="student-avatar no-print overflow-hidden">
                                    <?php if (!empty($stu['image_path'])): ?>
                                        <img src="../<?php echo $stu['image_path']; ?>" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <img src="../img/default_picture.png" style="width:100%;height:100%;object-fit:cover;">
                                    <?php endif; ?>
                                </div>
                                <span class="fw-600"><?php echo htmlspecialchars($stu['full_name']); ?></span>
                            </div>
                        </td>
                        <td><span class="badge bg-secondary-subtle text-secondary"><?php echo htmlspecialchars($stu['admission_no']); ?></span></td>
                        <td><?php echo htmlspecialchars($stu['gender']); ?></td>
                        <td><?php echo $stu['dob'] ? date('M d, Y', strtotime($stu['dob'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($stu['guardian_name'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($stu['guardian_phone'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php include '../includes/dashboard_footer.php'; ?>
        </div>
    </main>
<script>
function filterStudents() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#rosterTable tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

</script>
</body>
</html>
