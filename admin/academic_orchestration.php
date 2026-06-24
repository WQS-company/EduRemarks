<?php
// admin/academic_orchestration.php - Institutional Academic Command Center
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

$school_id = $active_school_id;

// Fetch current session and terms for filters
$sessions_stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = ? ORDER BY id DESC");
$sessions_stmt->execute([$school_id]);
$sessions = $sessions_stmt->fetchAll();

// Get Selected or Current Context
$current_session_id = intval($_GET['session_id'] ?? $active_school['current_session_id']);
$current_term_id = intval($_GET['term_id'] ?? $active_school['current_term_id']);

// Fetch terms for the selected session
$terms_stmt = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id = ? AND school_id = ?");
$terms_stmt->execute([$current_session_id, $school_id]);
$terms = $terms_stmt->fetchAll();

// Fetch current orchestration settings for selected period
$orch_stmt = $pdo->prepare("SELECT * FROM academic_orchestration WHERE school_id = ? AND session_id = ? AND term_id = ?");
$orch_stmt->execute([$school_id, $current_session_id, $current_term_id]);
$orch = $orch_stmt->fetch();

if (!$orch) {
    // Initialize if not exists
    $pdo->prepare("INSERT IGNORE INTO academic_orchestration (school_id, session_id, term_id) VALUES (?, ?, ?)")
        ->execute([$school_id, $current_session_id, $current_term_id]);
    $orch_stmt->execute([$school_id, $current_session_id, $current_term_id]);
    $orch = $orch_stmt->fetch();
}

// Fetch all staff members linked to this school with window AND cbt status
$staff_stmt = $pdo->prepare("
    SELECT u.id as user_id, u.full_name, sd.id as staff_id, sd.status as staff_status,
           sev.window_status, sev.cbt_status as staff_cbt_status
    FROM staff_details sd
    JOIN users u ON u.id = sd.user_id
    LEFT JOIN staff_entry_windows sev ON sev.staff_id = sd.id AND sev.session_id = ? AND sev.term_id = ?
    WHERE sd.school_id = ? AND sd.status = 'active'
");
$staff_stmt->execute([$current_session_id, $current_term_id, $school_id]);
$staff_list = $staff_stmt->fetchAll();

// Tertiary Check
$type = strtolower($active_school['school_type'] ?? '');
$is_higher_ed = (
    strpos($type, 'tertiary') !== false || 
    strpos($type, 'vocational') !== false || 
    strpos($type, 'polytechnic') !== false || 
    strpos($type, 'university') !== false || 
    strpos($type, 'college') !== false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Orchestration | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .orch-card { border-radius: 24px; border: none; box-shadow: 0 15px 35px rgba(31, 60, 136, 0.05); overflow: hidden; }
        .orch-header { background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); color: white; padding: 30px; }
        .status-pill { padding: 8px 20px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; cursor: pointer; transition: 0.3s; border: 2px solid transparent; }
        .status-pill.active { background: #e6fffa; color: #047857; border-color: #34d399; }
        .status-pill.inactive { background: #fee2e2; color: #991b1b; border-color: #f87171; }
        .staff-row { transition: 0.2s; border-radius: 12px; margin-bottom: 8px; border: 1px solid #f1f5f9; }
        .staff-row:hover { background: #f8fafc; border-color: #cbd5e1; }
        .form-check-input:checked { background-color: #1F3C88; border-color: #1F3C88; }
        .premium-switch { transform: scale(1.2); cursor: pointer; }
    </style>
</head>
<body class="bg-light">

    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/notifications.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="container-fluid py-4">
                <!-- Period Filter Node -->
                <div class="glass-card p-4 mb-4 border-0 shadow-sm">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="extra-small fw-800 uppercase tracking-1 text-muted mb-2">Academic Session</label>
                            <select name="session_id" class="form-select border-0 bg-light rounded-pill fw-700" onchange="this.form.submit()">
                                <?php foreach($sessions as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $current_session_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="extra-small fw-800 uppercase tracking-1 text-muted mb-2"><?php echo get_label('Term'); ?> Context</label>
                            <select name="term_id" class="form-select border-0 bg-light rounded-pill fw-700" onchange="this.form.submit()">
                                <?php foreach($terms as $t): ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $current_term_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(get_label($t['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-primary mb-0 py-2 border-0 rounded-pill text-center extra-small fw-800">
                                <i class="fas fa-history me-2"></i> ARCHIVE MODE ACTIVE
                            </div>
                        </div>
                    </form>
                </div>

                <div class="row g-4">
                    <!-- Master Control Column -->
                    <div class="col-lg-4">
                        <div class="orch-card bg-white h-100">
                            <div class="orch-header">
                                <div class="tiny-text opacity-75 fw-800 uppercase tracking-2 mb-2">Command Center</div>
                                <h4 class="fw-900 mb-0"><i class="fas fa-microchip me-2"></i> <?php echo get_label('Academic Audit'); ?> Lock</h4>
                                <p class="extra-small mt-2 mb-0 opacity-75">Control global entry windows for CA and Exams</p>
                            </div>
                            <div class="p-4">
                                <!-- Global Toggle -->
                                <div class="d-flex justify-content-between align-items-center p-3 mb-4 rounded-4 bg-light">
                                    <div>
                                        <h6 class="fw-800 mb-0">Global Entry Switch</h6>
                                        <p class="extra-small text-muted mb-0">Completely lock/unlock all result entry</p>
                                    </div>
                                    <div class="form-check form-switch p-0">
                                        <input class="form-check-input premium-switch ms-0" type="checkbox" id="globalStatus" <?php echo $orch['global_status'] === 'open' ? 'checked' : ''; ?> onchange="updateGlobalSetting('global_status', this.checked ? 'open' : 'closed')">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="extra-small fw-800 uppercase tracking-2 mb-2 text-primary" style="font-size: 0.7rem;">Entry Window Deadline</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0"><i class="fas fa-clock text-primary"></i></span>
                                        <input type="date" class="form-control border-0 bg-light fw-700" id="entryDeadline" value="<?php echo $orch['entry_deadline'] ?? ''; ?>" onchange="updateGlobalSetting('entry_deadline', this.value)">
                                    </div>
                                    <p class="extra-small text-muted mt-2 mb-0">Staff will be notified of this date on their entry dashboard.</p>
                                </div>

                                <h6 class="fw-800 uppercase tracking-2 mb-3 text-primary" style="font-size: 0.7rem;">Assessment Phase Controls</h6>
                                
                                <div class="list-group list-group-flush gap-2">
                                    <div class="list-group-item border-0 p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                                        <div class="fw-700 small"><i class="fas fa-file-signature me-2 text-primary"></i> <?php echo $is_higher_ed ? 'C.A' : 'C.A 1'; ?> Entry (<?php echo $is_higher_ed ? '40%' : '20%'; ?>)</div>
                                        <div class="form-check form-switch p-0">
                                            <input class="form-check-input ms-0" type="checkbox" <?php echo $orch['ca1_status'] ? 'checked' : ''; ?> onchange="updateGlobalSetting('ca1_status', this.checked ? 1 : 0)">
                                        </div>
                                    </div>
                                    <?php if (!$is_higher_ed): ?>
                                    <div class="list-group-item border-0 p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                                        <div class="fw-700 small"><i class="fas fa-file-signature me-2 text-success"></i> C.A 2 Entry (20%)</div>
                                        <div class="form-check form-switch p-0">
                                            <input class="form-check-input ms-0" type="checkbox" <?php echo $orch['ca2_status'] ? 'checked' : ''; ?> onchange="updateGlobalSetting('ca2_status', this.checked ? 1 : 0)">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="list-group-item border-0 p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                                        <div class="fw-700 small"><i class="fas fa-graduation-cap me-2 text-danger"></i> Exams Entry (60%)</div>
                                        <div class="form-check form-switch p-0">
                                            <input class="form-check-input ms-0" type="checkbox" <?php echo $orch['exam_status'] ? 'checked' : ''; ?> onchange="updateGlobalSetting('exam_status', this.checked ? 1 : 0)">
                                        </div>
                                    </div>
                                    <div class="list-group-item border-0 p-3 bg-light rounded-4 d-flex justify-content-between align-items-center">
                                        <div class="fw-700 small"><i class="fas fa-desktop me-2 text-info"></i> CBT Access (Auto)</div>
                                        <div class="form-check form-switch p-0">
                                            <input class="form-check-input ms-0" type="checkbox" <?php echo $orch['cbt_status'] ?? 1 ? 'checked' : ''; ?> onchange="updateGlobalSetting('cbt_status', this.checked ? 1 : 0)">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-4">
                                    <div class="extra-small fw-800 text-warning uppercase mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Security Note</div>
                                    <p class="extra-small text-muted mb-0">Closing these windows prevents staff from creating or editing scores. This is crucial for maintaining result integrity before printing.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Staff-Specific Orchestration -->
                    <div class="col-lg-8">
                        <div class="orch-card bg-white">
                            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="fw-900 mb-1">Staff-Level Entry Management</h5>
                                    <p class="extra-small text-muted mb-0">Manually override entry windows for individual educator nodes</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-success fw-800 rounded-pill px-3" onclick="bulkUpdateStaff('open')">OPEN ALL</button>
                                    <button class="btn btn-sm btn-outline-danger fw-800 rounded-pill px-3" onclick="bulkUpdateStaff('closed')">CLOSE ALL</button>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="table-responsive">
                                    <table class="table table-borderless align-middle">
                                        <thead>
                                            <tr>
                                                <th class="extra-small fw-800 uppercase tracking-2 opacity-50">Staff Node</th>
                                                <th class="extra-small fw-800 uppercase tracking-2 opacity-50 text-center">Authentication Status</th>
                                                <th class="extra-small fw-800 uppercase tracking-2 opacity-50 text-center">Score Entry</th>
                                                <th class="extra-small fw-800 uppercase tracking-2 opacity-50 text-end">CBT Access</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($staff_list as $s): ?>
                                                <tr class="staff-row">
                                                    <td>
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center fw-900 text-primary" style="width: 40px; height: 40px; font-size: 0.8rem;">
                                                                <?php echo strtoupper(substr($s['full_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-800 small"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                                                <div class="extra-small text-muted">Educator Node ID: #<?php echo $s['staff_id']; ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Authenticated</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <button class="status-pill <?php echo ($s['window_status'] ?: 'open') === 'open' ? 'active' : 'inactive'; ?>" 
                                                                onclick="toggleStaffControl(<?php echo $s['staff_id']; ?>, 'window_status', this)">
                                                            <?php echo strtoupper($s['window_status'] ?: 'open'); ?>
                                                        </button>
                                                    </td>
                                                    <td class="text-end">
                                                        <button class="status-pill <?php echo ($s['staff_cbt_status'] ?: 'open') === 'open' ? 'active' : 'inactive'; ?>" 
                                                                style="--sa-pill-bg: #E1F5FE; --sa-pill-color: #039BE5;"
                                                                onclick="toggleStaffControl(<?php echo $s['staff_id']; ?>, 'cbt_status', this)">
                                                            <?php echo strtoupper($s['staff_cbt_status'] ?: 'open'); ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        const SCHOOL_ID = <?php echo intval($school_id); ?>;
        const SESSION_ID = <?php echo intval($current_session_id); ?>;
        const TERM_ID = <?php echo intval($current_term_id); ?>;

        function updateGlobalSetting(field, value) {
            $.post('../ajax/save_academic_orchestration.php', {
                action: 'update_global',
                field: field,
                value: value,
                session_id: SESSION_ID,
                term_id: TERM_ID
            }, function(res) {
                if(res.success) Notif.show('Audit window updated successfully', 'success');
                else Notif.show(res.message, 'error');
            }, 'json');
        }

        function toggleStaffControl(staffId, field, btn) {
            const current = $(btn).hasClass('active') ? 'open' : 'closed';
            const next = current === 'open' ? 'closed' : 'open';
            
            Spinner.show('Updating Node...');
            $.post('../ajax/save_academic_orchestration.php', {
                action: 'update_staff',
                staff_id: staffId,
                field: field,
                status: next,
                session_id: SESSION_ID,
                term_id: TERM_ID
            }, function(res) {
                Spinner.hide();
                if(res.success) {
                    if(next === 'open') {
                        $(btn).removeClass('inactive').addClass('active').text('OPEN');
                    } else {
                        $(btn).removeClass('active').addClass('inactive').text('CLOSED');
                    }
                    Notif.show('Institutional node synchronized', 'success');
                } else Notif.show(res.message, 'error');
            }, 'json');
        }

        function bulkUpdateStaff(status) {
            Spinner.show('Mass Synchronizing...');
            $.post('../ajax/save_academic_orchestration.php', {
                action: 'bulk_staff',
                status: status,
                session_id: SESSION_ID,
                term_id: TERM_ID
            }, function(res) {
                Spinner.hide();
                if(res.success) {
                    location.reload();
                } else Notif.show(res.message, 'error');
            }, 'json');
        }
    </script>
</body>
</html>
