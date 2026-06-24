<?php
// admin/admission_portal.php - Admission Application Management Center
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

// Global Feature Access Guard
if (strpos($active_school['feature_access'], 'ADMISSION_PORTAL') === false && $role !== 'super_admin') {
    $_SESSION['sys_error'] = "The Admission Portal node is currently deactivated for your institution. Please contact support.";
    header('Location: ../dashboard.php');
    exit();
}

$school_id = $active_school_id;

// Fetch Admission Form Config
$config_stmt = $pdo->prepare("SELECT * FROM admission_forms WHERE school_id = ?");
$config_stmt->execute([$school_id]);
$config = $config_stmt->fetch();

if (!$config) {
    // Initialize default config if not exists
    $pdo->prepare("INSERT INTO admission_forms (school_id, title) VALUES (?, ?)")
        ->execute([$school_id, 'Public Admission Form']);
    $config_stmt->execute([$school_id]);
    $config = $config_stmt->fetch();
}

$status_filter = $_GET['status'] ?? 'all';
$sql = "SELECT a.*, c.name as class_name, s.name as session_name 
        FROM admission_applications a 
        LEFT JOIN classes c ON c.id = a.class_id 
        LEFT JOIN academic_sessions s ON s.id = a.session_id
        WHERE a.school_id = ?";

if ($status_filter !== 'all') {
    $sql .= " AND a.status = ?";
}
$sql .= " ORDER BY a.application_date DESC";

$app_stmt = $pdo->prepare($sql);
if ($status_filter !== 'all') {
    $app_stmt->execute([$school_id, $status_filter]);
} else {
    $app_stmt->execute([$school_id]);
}
$applications = $app_stmt->fetchAll();

// Stats
$stats = [
    'total' => 0,
    'pending' => 0,
    'accepted' => 0,
    'rejected' => 0
];
$st_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM admission_applications WHERE school_id = ? GROUP BY status");
$st_stmt->execute([$school_id]);
while($row = $st_stmt->fetch()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}

// Fetch Classes for allocation selection
$cls_stmt = $pdo->prepare("SELECT id, name FROM classes WHERE school_id = ? ORDER BY name ASC");
$cls_stmt->execute([$school_id]);
$classes = $cls_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Portal | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .portal-header {
            background: linear-gradient(135deg, #1F3C88 0%, #0D1B3E 100%);
            border-radius: 24px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .portal-header::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .stat-card {
            border-radius: 20px;
            border: none;
            transition: 0.3s;
            overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .status-pill {
            font-size: 0.7rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .status-pending { background: #FFF3E0; color: #E65100; }
        .status-accepted { background: #E8F5E9; color: #2E7D32; }
        .status-rejected { background: #FFEBEE; color: #C62828; }
        
        .app-row { transition: 0.2s; cursor: pointer; border-radius: 12px; }
        .app-row:hover { background: #f8f9fa; }
        
        .admission-link-box {
            background: rgba(255,255,255,0.1);
            border: 1px dashed rgba(255,255,255,0.3);
            border-radius: 12px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
            gap: 10px;
        }
        .btn-nowrap { white-space: nowrap; flex-shrink: 0; }
        
        @media (max-width: 768px) {
            .portal-header { padding: 20px; text-align: center; }
            .admission-link-box { flex-direction: column; text-align: center; }
            .admission-link-box span { margin-bottom: 10px; width: 100%; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; }
            
            /* Hide table on mobile, show cards */
            .desktop-table { display: none; }
            .mobile-apps-list { display: block; }
            
            .stat-card .card-body { padding: 1.25rem !important; text-align: center; }
            .stat-card .h3 { font-size: 1.5rem; }
        }
        
        @media (min-width: 769px) {
            .mobile-apps-list { display: none; }
            .desktop-table { display: block; }
        }

        .mobile-app-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            position: relative;
        }
        .batch-action-bar {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(150%);
            background: #1F3C88;
            padding: 12px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1040;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            transition: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            color: white;
            width: 90%;
            max-width: 400px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .batch-action-bar.active { 
            transform: translateX(-50%) translateY(0); 
            opacity: 1; 
            visibility: visible; 
            pointer-events: auto; 
        }
        .batch-action-bar.hidden-by-modal { opacity: 0; pointer-events: none; transform: translateX(-50%) translateY(150%); visibility: hidden; }
        .batch-check { width: 18px; height: 18px; cursor: pointer; }
        .batch-card-check { position: absolute; top: 12px; right: 12px; z-index: 5; }

        @media (min-width: 993px) {
            .modal-xl { 
                width: calc(100% - 300px) !important;
                max-width: none !important;
                margin-left: 280px !important;
                margin-right: 20px !important;
            }
            .modal-dialog-centered {
                display: flex;
                align-items: center;
                justify-content: flex-start !important;
            }
            .modal {
                z-index: 1300 !important;
            }
            .modal-backdrop {
                z-index: 1250 !important;
            }
        }

        @media (max-width: 576px) {
            .modal-content { border-radius: 16px !important; }
            .modal-body { padding: 12px !important; }
            .form-select, .form-control { padding: 8px !important; font-size: 0.75rem !important; }
            .fw-900 { font-size: 0.9rem !important; }
            .h5 { font-size: 1rem !important; }
            .btn-lg { padding: 10px 15px !important; font-size: 0.85rem !important; }
            .ivory-dossier .bg-light { padding: 10px !important; }
            .extra-small { font-size: 0.6rem !important; }
            .batch-action-bar { bottom: 15px; padding: 10px 15px; gap: 10px; }
            .batch-action-bar .small { font-size: 0.65rem !important; }
        }

        /* Ultra Tiny Screen Responsiveness (e.g. 280px - 320px) */
        @media (max-width: 320px) {
            .p-4 { padding: 10px !important; }
            .portal-header { padding: 15px; border-radius: 15px; }
            .stat-card .card-body { padding: 1rem !important; }
            .batch-action-bar { width: 95%; padding: 8px 12px; }
            .batch-action-bar button { padding: 5px 10px !important; font-size: 0.6rem !important; }
        }
    </style>
</head>
<body>
    <?php include '../includes/spinner.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
            
            <div class="p-4">
                <div class="portal-header shadow-lg">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <div class="badge bg-white bg-opacity-10 text-white mb-2 px-3 py-2 rounded-pill small fw-800">
                                <i class="fas fa-id-badge me-2"></i> ADMISSION GOVERNANCE
                            </div>
                            <h2 class="fw-900 mb-2">Applicants Processing Hub</h2>
                            <p class="opacity-75 mb-0">Manage student intakes, customize your public portal, and synchronize admissions with your primary institutional database.</p>
                            
                            <?php 
                                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                                $host = $_SERVER['HTTP_HOST'];
                                $public_url = $protocol . "://" . $host . "/admission_form.php?sid=" . $active_school['unique_id'];
                            ?>
                            <div class="admission-link-box">
                                <span class="small opacity-75 text-truncate me-2" id="publicLink"><?php echo $public_url; ?></span>
                                <div class="d-flex gap-2 w-mobile-100 justify-content-center">
                                    <button class="btn btn-sm btn-white px-3 fw-800 rounded-pill btn-nowrap" onclick="copyLink()">
                                        <i class="fas fa-copy me-1"></i> COPY
                                    </button>
                                    <a href="../admission_form.php?sid=<?php echo $active_school['unique_id']; ?>" target="_blank" class="btn btn-sm btn-outline-light px-3 fw-800 rounded-pill btn-nowrap">
                                        <i class="fas fa-external-link-alt me-1"></i> VIEW
                                    </a>
                                </div>
                            </div>
                            <div class="mt-2 extra-small opacity-50 fw-bold">
                                <i class="fas fa-coins me-1 text-warning"></i> Operational Rate: <?php echo getCreditRate('credit_admission_applicant', $pdo); ?> Credits / Applicant
                            </div>
                        </div>
                        <div class="col-md-5 text-md-end mt-4 mt-md-0">
                            <?php if (($config['max_slots'] ?? 0) > 0): 
                                $percent = min(100, round(($stats['total'] / $config['max_slots']) * 100));
                                $color = $percent > 90 ? 'danger' : ($percent > 70 ? 'warning' : 'success');
                            ?>
                                <div class="bg-white bg-opacity-10 p-3 rounded-4 mb-3 text-start border border-white border-opacity-10">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="extra-small fw-800 uppercase tracking-2">Intake Capacity</span>
                                        <span class="small fw-900"><?php echo $stats['total']; ?> / <?php echo $config['max_slots']; ?> Slots</span>
                                    </div>
                                    <div class="progress bg-white bg-opacity-20" style="height: 6px; border-radius: 10px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percent; ?>%; border-radius: 10px;"></div>
                                    </div>
                                    <div class="extra-small opacity-50 mt-2 text-end fw-bold"><?php echo $percent; ?>% Synchronized</div>
                                </div>
                            <?php endif; ?>
                            <a href="admission_config.php" class="btn btn-lg btn-warning rounded-pill px-4 fw-900 shadow-sm transition btn-nowrap w-mobile-100">
                                <i class="fas fa-cog me-2"></i> CONFIGURE PORTAL
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card shadow-sm border-0 border-start border-primary border-5">
                            <div class="card-body p-4">
                                <div class="small fw-800 text-muted uppercase mb-1">Total Applicants</div>
                                <div class="h3 fw-900 mb-0"><?php echo $stats['total']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card shadow-sm border-0 border-start border-warning border-5">
                            <div class="card-body p-4">
                                <div class="small fw-800 text-muted uppercase mb-1">Pending Review</div>
                                <div class="h3 fw-900 mb-0"><?php echo $stats['pending']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card shadow-sm border-0 border-start border-success border-5">
                            <div class="card-body p-4">
                                <div class="small fw-800 text-muted uppercase mb-1">Successful ADM</div>
                                <div class="h3 fw-900 mb-0"><?php echo $stats['accepted']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card shadow-sm border-0 border-start border-danger border-5">
                            <div class="card-body p-4">
                                <div class="small fw-800 text-muted uppercase mb-1">Rejected List</div>
                                <div class="h3 fw-900 mb-0"><?php echo $stats['rejected']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass-card">
                    <div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h5 class="fw-900 mb-0"><i class="fas fa-list-ul me-2 text-primary"></i> Intake Applications</h5>
                        <div class="d-flex gap-2 bg-light p-1 rounded-pill">
                            <a href="?status=all" class="btn btn-sm rounded-pill px-3 <?php echo $status_filter == 'all' ? 'btn-primary shadow-sm' : 'text-muted'; ?> fw-800">ALL</a>
                            <a href="?status=pending" class="btn btn-sm rounded-pill px-3 <?php echo $status_filter == 'pending' ? 'btn-warning shadow-sm' : 'text-muted'; ?> fw-800">PENDING</a>
                            <a href="?status=accepted" class="btn btn-sm rounded-pill px-3 <?php echo $status_filter == 'accepted' ? 'btn-success shadow-sm' : 'text-muted'; ?> fw-800">ACCEPTED</a>
                            <a href="?status=rejected" class="btn btn-sm rounded-pill px-3 <?php echo $status_filter == 'rejected' ? 'btn-danger shadow-sm' : 'text-muted'; ?> fw-800">REJECTED</a>
                        </div>
                    </div>
                    <div class="p-0">
                        <!-- Desktop Table -->
                        <div class="table-responsive desktop-table">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3" style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th class="py-3 extra-small fw-800 uppercase tracking-2 opacity-50">Applicant Name</th>
                                        <th class="py-3 extra-small fw-800 uppercase tracking-2 opacity-50"><?php echo get_label('Class'); ?> applied</th>
                                        <th class="py-3 extra-small fw-800 uppercase tracking-2 opacity-50">Guardian Details</th>
                                        <th class="py-3 extra-small fw-800 uppercase tracking-2 opacity-50">Applied Date</th>
                                        <th class="py-3 extra-small fw-800 uppercase tracking-2 opacity-50">Status</th>
                                        <th class="pe-4 py-3 extra-small fw-800 uppercase tracking-2 opacity-50 text-end">Management</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($applications)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 opacity-50">
                                                <i class="fas fa-folder-open fa-3x mb-3 text-muted"></i>
                                                <p class="fw-800">No applications found in this registry.</p>
                                            </td>
                                        </tr>
                                    <?php else: foreach($applications as $app): ?>
                                        <tr class="app-row">
                                            <td class="ps-4">
                                                <input type="checkbox" class="form-check-input app-checkbox" data-id="<?php echo $app['id']; ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-900" style="width: 40px; height: 40px;">
                                                        <?php echo strtoupper(substr($app['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-800 small"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                                        <div class="tiny-text text-muted"><?php echo $app['gender']; ?> &bull; <?php echo $app['dob']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-700 small"><?php echo htmlspecialchars($app['class_name'] ?? 'N/A'); ?></div>
                                                <div class="tiny-text opacity-50"><?php echo htmlspecialchars($app['session_name'] ?? 'Target Session'); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-700 small"><i class="fas fa-user-circle me-1 opacity-50"></i> <?php echo htmlspecialchars($app['parent_name']); ?></div>
                                                <div class="tiny-text opacity-75"><i class="fas fa-phone-alt me-1 opacity-50"></i> <?php echo htmlspecialchars($app['parent_phone']); ?></div>
                                            </td>
                                            <td class="small opacity-75">
                                                <?php echo date('M d, Y', strtotime($app['application_date'])); ?>
                                            </td>
                                            <td>
                                                <span class="status-pill status-<?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-800" onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                                    <i class="fas fa-eye me-1"></i> PROCESS
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile List View -->
                        <div class="mobile-apps-list p-3">
                            <?php if(empty($applications)): ?>
                                <div class="text-center py-5 opacity-50">
                                    <i class="fas fa-folder-open fa-2x mb-3 text-muted"></i>
                                    <p class="small fw-800">No applications found.</p>
                                </div>
                            <?php else: foreach($applications as $app): ?>
                                <div class="mobile-app-card" onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                    <input type="checkbox" class="form-check-input app-checkbox batch-card-check" data-id="<?php echo $app['id']; ?>" onclick="event.stopPropagation(); syncCheckboxes();">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-900" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                <?php echo strtoupper(substr($app['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-800 small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                                <div class="tiny-text text-muted"><?php echo htmlspecialchars($app['class_name'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                        <span class="status-pill status-<?php echo $app['status']; ?>" style="font-size: 0.6rem;"><?php echo $app['status']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-2">
                                        <div class="tiny-text fw-700 text-muted">
                                            <i class="fas fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($app['application_date'])); ?>
                                        </div>
                                        <button class="btn btn-xs btn-primary rounded-pill px-3 fw-800 extra-small py-1">
                                            PROCESS <i class="fas fa-chevron-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <!-- Applicant Dossier Modal -->
    <div class="modal fade" id="appModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-900 mb-0"><i class="fas fa-id-card me-2 text-primary"></i> Applicant Dossier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-1">
                    <div class="row g-3 align-items-stretch">
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-4 h-100">
                                <h6 class="extra-small fw-800 uppercase tracking-2 text-primary mb-3">Student Data</h6>
                                <div class="mb-2"><span class="small text-muted">Name:</span> <div class="fw-800" id="v_name"></div></div>
                                <div class="mb-2"><span class="small text-muted">Gender:</span> <span class="fw-800" id="v_gender"></span></div>
                                <div class="mb-2"><span class="small text-muted"><?php echo get_label('Class'); ?>:</span> <div class="fw-800 text-primary" id="v_class"></div></div>
                                <div class="mb-0"><span class="small text-muted">Address:</span> <div class="tiny-text fw-700" id="v_address"></div></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded-4 h-100">
                                <h6 class="extra-small fw-800 uppercase tracking-2 text-primary mb-3">Contact Nodes</h6>
                                <div class="mb-2"><span class="small text-muted">Guardian:</span> <div class="fw-800" id="v_pname"></div></div>
                                <div class="mb-2"><span class="small text-muted">Phone:</span> <div class="fw-800" id="v_pphone"></div></div>
                                <div class="mb-0"><span class="small text-muted">Applied:</span> <div class="small fw-700" id="v_date"></div></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded-4 h-100">
                                <h6 class="extra-small fw-800 uppercase tracking-2 text-danger mb-3">Administrative Hub</h6>
                                <form id="processForm">
                                    <input type="hidden" name="app_id" id="app_id">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-2">
                                                <label class="small fw-800 mb-1">Status</label>
                                                <select class="form-select rounded-3 p-2 fw-700 font-small" name="status" id="v_status">
                                                    <option value="pending">PENDING</option>
                                                    <option value="accepted">ACCEPT</option>
                                                    <option value="rejected">REJECT</option>
                                                </select>
                                            </div>
                                            <div class="mb-0" id="singleClassArea" style="display: none;">
                                                <label class="small fw-800 mb-1">Final <?php echo get_label('Class'); ?></label>
                                                <select class="form-select rounded-3 p-2 fw-700 font-small" name="target_class_id" id="v_target_class">
                                                    <?php foreach($classes as $c): ?>
                                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-2">
                                                <label class="small fw-800 mb-1">Internal Notes</label>
                                                <textarea class="form-control rounded-3 p-2 font-small" name="comment" id="v_comment" rows="1" placeholder="Remarks..."></textarea>
                                            </div>
                                            <div class="form-check form-switch p-0 ps-5">
                                                <input class="form-check-input" type="checkbox" name="send_sms" id="sendSms" checked style="width: 1.8em; height: 0.9em;">
                                                <label class="form-check-label extra-small fw-800 ms-1" for="sendSms">SMS Notify</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-grid mt-3">
                                        <button type="button" class="btn btn-primary btn-md rounded-pill fw-900" onclick="processDecision()">
                                            <i class="fas fa-sync-alt me-1"></i> UPDATE RECORD
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="batch-action-bar" id="batchBar">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark rounded-pill" id="selectedCount">0</span>
            <span class="small fw-800">SELECTED</span>
        </div>
        <div class="vr bg-white opacity-25"></div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light rounded-pill px-4 fw-800 py-2" onclick="openBatchModal()">
                <i class="fas fa-layer-group me-1"></i> BATCH PROCESS
            </button>
            <button class="btn btn-sm btn-white rounded-circle" style="width: 36px; height: 36px; padding: 0;" onclick="clearSelection()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Batch Process Modal -->
    <div class="modal fade" id="batchModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-900 mb-0"><i class="fas fa-layer-group me-2 text-primary"></i> Mass Registry Synchronization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert bg-primary bg-opacity-10 border-0 rounded-4 p-3 mb-4">
                        <div class="d-flex gap-3 align-items-center">
                            <i class="fas fa-info-circle text-primary fa-lg"></i>
                            <div class="small fw-700">Mass processing <span id="b_count" class="text-primary">0</span> institutional intake dossiers.</div>
                        </div>
                    </div>
                    
                    <form id="batchForm">
                        <input type="hidden" name="app_ids" id="b_ids">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="small fw-800 mb-1 d-block text-truncate">Protocol</label>
                                <select class="form-select rounded-3 p-3 fw-700" name="status" id="b_status" required>
                                    <option value="accepted">ACCEPT & ENROLL</option>
                                    <option value="rejected">REJECT ALL</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="batchClassArea">
                                <label class="small fw-800 mb-1 d-block text-truncate">Destination</label>
                                <select class="form-select rounded-3 p-3 fw-700" name="target_class_id">
                                    <option value="applied">AS APPLIED</option>
                                    <?php foreach($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>">FORCE: <?php echo $c['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-800 mb-1">Administrative Notes</label>
                                <textarea class="form-control rounded-4 p-3" name="comment" rows="1" placeholder="Internal remarks..."></textarea>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded-4 border">
                                    <div class="form-check form-switch p-0 ps-5 mb-2">
                                        <input class="form-check-input" type="checkbox" name="send_sms" id="b_sendSms" checked style="width: 2.2em; height: 1.1em;">
                                        <label class="form-check-label extra-small fw-800 ms-2" for="b_sendSms">Send Notification</label>
                                    </div>
                                    <div id="smsMessageArea">
                                        <textarea class="form-control rounded-3 extra-small" name="custom_sms" id="b_sms" rows="1" placeholder="Broadcast content..."></textarea>
                                        <div class="mt-1 d-flex justify-content-between align-items-center">
                                            <div class="tiny-text opacity-75 fw-bold text-primary">
                                                <i class="fas fa-coins me-1"></i> <span id="smsCost">0.00</span>
                                            </div>
                                            <div class="extra-small opacity-50" id="charCount">0 ch</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid shadow-sm mt-4">
                            <button type="button" class="btn btn-primary btn-lg rounded-pill fw-900 py-3" onclick="processBatch()">
                                <i class="fas fa-bolt me-2"></i> EXECUTE BATCH SYNCHRONIZATION
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(function() {
            syncCheckboxes();
            
            $('#selectAll').on('change', function() {
                $('.app-checkbox').prop('checked', $(this).is(':checked'));
                syncCheckboxes();
            });

            $('.app-checkbox').on('change', function() {
                syncCheckboxes();
            });

            $('#b_sendSms').on('change', function() {
                $('#smsMessageArea').toggle($(this).is(':checked'));
            });

            $('#b_sms').on('input', function() {
                const len = $(this).val().length;
                $('#charCount').text(len + ' characters');
                updateSmsCost();
            });

            $('#v_status').on('change', function() {
                $('#singleClassArea').toggle($(this).val() === 'accepted');
            });

            $('#b_status').on('change', function() {
                $('#batchClassArea').toggle($(this).val() === 'accepted');
            });
        });

        function syncCheckboxes() {
            const count = $('.app-checkbox:checked').length;
            $('#selectedCount').text(count);
            if(count > 0) {
                $('#batchBar').addClass('active');
            } else {
                $('#batchBar').removeClass('active');
            }
        }

        function clearSelection() {
            $('.app-checkbox').prop('checked', false);
            $('#selectAll').prop('checked', false);
            syncCheckboxes();
        }

        function openBatchModal() {
            const ids = $('.app-checkbox:checked').map(function() { return $(this).data('id'); }).get();
            $('#b_ids').val(ids.join(','));
            $('#b_count').text(ids.length);
            updateSmsCost();
            $('#batchBar').addClass('hidden-by-modal');
            const bModal = new bootstrap.Modal('#batchModal');
            bModal.show();
            document.getElementById('batchModal').addEventListener('hidden.bs.modal', function() {
                $('#batchBar').removeClass('hidden-by-modal');
            });
        }

        function updateSmsCost() {
            const count = $('.app-checkbox:checked').length;
            const msgLen = $('#b_sms').val().length || 100; // default length
            const pages = Math.ceil(msgLen / 160);
            const rate = <?php echo getCreditRate('credit_per_sms', $pdo); ?>;
            $('#smsCost').text((count * pages * rate).toFixed(2));
        }

        function copyLink() {
            const link = document.getElementById('publicLink').textContent;
            navigator.clipboard.writeText(link).then(() => {
                Notif.show('Admission link commissioned to clipboard!', 'success');
            });
        }

        function viewApplication(app) {
            $('#app_id').val(app.id);
            $('#v_name').text(app.full_name);
            $('#v_gender').text(app.gender);
            $('#v_dob').text(app.dob);
            $('#v_class').text(app.class_name);
            $('#v_address').text(app.address || 'N/A');
            $('#v_pname').text(app.parent_name);
            $('#v_pphone').text(app.parent_phone);
            $('#v_pemail').text(app.parent_email || 'N/A');
            $('#v_date').text(app.application_date);
            $('#v_status').val(app.status);
            $('#v_comment').val(app.admin_comment);
            
            $('#batchBar').addClass('hidden-by-modal');
            const aModal = new bootstrap.Modal('#appModal');
            aModal.show();
            document.getElementById('appModal').addEventListener('hidden.bs.modal', function() {
                $('#batchBar').removeClass('hidden-by-modal');
            });
        }

        function processDecision() {
            const formData = $('#processForm').serialize();
            Spinner.show('Synchronizing Registry...');
            
            $.post('../ajax/admission_handler.php', {
                action: 'process_decision',
                data: formData
            }, function(res) {
                Spinner.hide();
                if(res.success) {
                    Notif.show('Application status updated successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Notif.show(res.message, 'error');
                }
            }, 'json');
        }

        function processBatch() {
            const formData = $('#batchForm').serialize();
            Spinner.show('Executing Batch Protocol...');
            
            $.post('../ajax/admission_handler.php', {
                action: 'batch_process',
                data: formData
            }, function(res) {
                Spinner.hide();
                if(res.success) {
                    Notif.show('Batch synchronization completed successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Notif.show(res.message, 'error');
                }
            }, 'json');
        }
    </script>
</body>
</html>
