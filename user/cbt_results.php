<?php
// user/cbt_results.php — Staff CBT Results & Analytics Dashboard
require_once '../includes/auth_check.php';

if ($role !== 'staff') { header('Location: ../dashboard.php'); exit(); }

$school_id = $_SESSION['school_id'] ?? null;
$exam_id   = $_GET['exam_id'] ?? null;

if (!$exam_id) { header('Location: cbt_exams.php'); exit(); }

// Fetch staff detail
$sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$sd->execute([$user_id, $school_id]);
$staff = $sd->fetch();
if (!$staff) { header('Location: dashboard.php'); exit(); }

// Fetch exam
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

// Fetch all attempts with student details
$stmt = $pdo->prepare("
    SELECT a.*, st.full_name, st.admission_no, st.student_class
    FROM cbt_student_attempts a
    JOIN students st ON st.id = a.student_id
    WHERE a.exam_id = ?
    ORDER BY a.total_score DESC, a.end_time ASC
");
$stmt->execute([$exam_id]);
$attempts = $stmt->fetchAll();

// Calculate analytics
$total_students = count($attempts);
$total_submitted = count(array_filter($attempts, fn($a) => in_array($a['status'], ['submitted', 'timed_out'])));
$avg_score = $total_submitted > 0 ? round(array_sum(array_map(fn($a) => $a['total_score'], $attempts)) / $total_submitted, 1) : 0;
$max_possible = ($exam['total_questions'] ?: 0) * $exam['marks_per_question'];
$highest = $total_submitted > 0 ? max(array_map(fn($a) => $a['total_score'], $attempts)) : 0;
$lowest  = $total_submitted > 0 ? min(array_map(fn($a) => $a['total_score'], $attempts)) : 0;
$pass_count = count(array_filter($attempts, fn($a) => $max_possible > 0 && ($a['total_score'] / $max_possible * 100) >= 50));
$pass_rate = $total_submitted > 0 ? round($pass_count / $total_submitted * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results: <?php echo htmlspecialchars($exam['title']); ?> | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stat-card { border-radius: 16px; padding: 24px; text-align: center; transition: 0.2s; border: 1px solid #e2e8f0; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 1.2rem; }
        .stat-value { font-size: 1.8rem; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-top: 4px; }
        .results-table { border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
        .results-table th { background: #f8fafc; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 16px !important; color: #64748b; border: none !important; }
        .results-table td { padding: 14px 16px !important; font-size: 0.88rem; vertical-align: middle; border-color: #f1f5f9 !important; }
        .score-pill { padding: 4px 14px; border-radius: 20px; font-weight: 700; font-size: 0.78rem; }
        .rank-badge { width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.75rem; }
        
        .report-sticky-bar { 
            position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); 
            background: #1F3C88; color: white; padding: 12px 24px; border-radius: 50px; 
            box-shadow: 0 10px 30px rgba(31, 60, 136, 0.4); z-index: 1000; 
            display: none; align-items: center; gap: 20px; transition: 0.3s;
        }
        .report-option-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 20px; padding: 5px 15px; font-size: 0.82rem; transition: 0.2s; }
        .report-option-btn:hover { background: rgba(255,255,255,0.2); color: white; }
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
                    <li class="breadcrumb-item active">Results & Analytics</li>
                </ol>
            </nav>

            <!-- Exam Header -->
            <div class="glass-card p-4 mb-4" style="border-left:4px solid #1F3C88;">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($exam['title']); ?></h4>
                        <div class="text-muted small">
                            <i class="fas fa-layer-group me-1"></i><?php echo $exam['class_name']; ?> &bull; 
                            <i class="fas fa-book me-1"></i><?php echo $exam['subject_name']; ?> &bull;
                            <i class="fas fa-clock me-1"></i><?php echo $exam['duration_mins']; ?> minutes
                        </div>
                    </div>
                    <span class="badge rounded-pill px-3 py-2 fw-bold" style="background:<?php echo $exam['status']==='active'?'#E8F5E9':'#FFEBEE';?>;color:<?php echo $exam['status']==='active'?'#2E7D32':'#C62828';?>;">
                        <?php echo ucfirst($exam['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon" style="background:#EEF2FB;color:#1F3C88;"><i class="fas fa-users"></i></div>
                        <div class="stat-value text-primary"><?php echo $total_students; ?></div>
                        <div class="stat-label">Participants</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon" style="background:#FFF3E0;color:#F57C00;"><i class="fas fa-chart-bar"></i></div>
                        <div class="stat-value" style="color:#F57C00;"><?php echo $avg_score; ?></div>
                        <div class="stat-label">Average Score</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-trophy"></i></div>
                        <div class="stat-value" style="color:#2E7D32;"><?php echo $highest; ?></div>
                        <div class="stat-label">Highest Score</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card bg-white">
                        <div class="stat-icon" style="background:#E8F5E9;color:#2E7D32;"><i class="fas fa-percentage"></i></div>
                        <div class="stat-value" style="color:#2E7D32;"><?php echo $pass_rate; ?>%</div>
                        <div class="stat-label">Pass Rate (≥50%)</div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="glass-card p-0">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-ol me-2 text-primary"></i>Student Results</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="toggleSelectAll()">
                            <i class="fas fa-check-double me-1"></i>Select All
                        </button>
                        <span class="badge bg-light text-dark border d-flex align-items-center"><?php echo $total_submitted; ?> Completed</span>
                    </div>
                </div>
                <?php if (empty($attempts)): ?>
                    <div class="p-5 text-center">
                        <i class="fas fa-inbox text-muted mb-3" style="font-size:2.5rem;"></i>
                        <h6 class="text-muted">No Students Have Attempted This Exam Yet</h6>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover results-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="masterSelect" onchange="selectAll(this)">
                                    </th>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Admission No</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $rank => $a): 
                                    $pct = $max_possible > 0 ? round($a['total_score'] / $max_possible * 100) : 0;
                                    $rank_bg = $rank === 0 ? '#F4B400' : ($rank === 1 ? '#9E9E9E' : ($rank === 2 ? '#BF8970' : '#e2e8f0'));
                                    $rank_color = $rank < 3 ? 'white' : '#64748b';
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input student-check" value="<?php echo $a['student_id']; ?>" onchange="updateSelectedCount()">
                                    </td>
                                    <td>
                                        <div class="rank-badge" style="background:<?php echo $rank_bg; ?>;color:<?php echo $rank_color; ?>;">
                                            <?php echo $rank + 1; ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($a['full_name']); ?></td>
                                    <td><code><?php echo $a['admission_no']; ?></code></td>
                                    <td class="fw-bold"><?php echo $a['total_score']; ?> / <?php echo $max_possible; ?></td>
                                    <td>
                                        <span class="score-pill" style="background:<?php echo $pct >= 80 ? '#E8F5E9' : ($pct >= 50 ? '#FFF3E0' : '#FFEBEE'); ?>;color:<?php echo $pct >= 80 ? '#2E7D32' : ($pct >= 50 ? '#F57C00' : '#C62828'); ?>;">
                                            <?php echo $pct; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($a['status'] === 'submitted'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success">Submitted</span>
                                        <?php elseif ($a['status'] === 'timed_out'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger">Timed Out</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?php echo $a['end_time'] ? date('M d, h:i A', strtotime($a['end_time'])) : '—'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>

    <!-- Report Action Bar -->
    <div class="report-sticky-bar" id="reportBar">
        <div class="small fw-bold border-end pe-3">
            <i class="fas fa-users-viewfinder me-2"></i>
            <span id="selectedCount">0</span> Students Selected
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="report-option-btn" onclick="openReportModal()">
                <i class="fas fa-file-invoice me-1"></i>Report Node
            </button>
            <div class="dropdown dropup" id="bulkActionsDropdown">
                <button class="report-option-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-magic me-1"></i>Actions
                </button>
                <ul class="dropdown-menu border-0 shadow-lg" style="background:#2C3E50; border-radius: 12px; font-size: 0.85rem; padding: 8px;">
                    <li><a class="dropdown-item py-2 fw-medium text-white" style="border-radius:6px; transition:0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'" href="#" onclick="openTimeModal()"><i class="fas fa-clock me-2 text-warning"></i>Grant Time Extension</a></li>
                    <li><a class="dropdown-item py-2 fw-medium text-danger" style="border-radius:6px; transition:0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'" href="#" onclick="bulkAction('retake')"><i class="fas fa-sync-alt me-2 text-danger"></i>Refresh Array (Retake)</a></li>
                </ul>
            </div>
            <button class="btn btn-sm btn-link text-white text-decoration-none p-1 ms-1" onclick="deselectAll()" title="Deselect All Candidates">
                <i class="fas fa-times-circle fs-5 opacity-75 hover-opacity-100 transition"></i>
            </button>
        </div>
    </div>

    <!-- Report Selection Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Customize Result Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small">Select the assessment components to include in the consolidated report.</p>
                    
                        <form id="reportForm" action="cbt_generate_report.php" method="POST" target="_blank">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">
                            <!-- Hidden fields for students and context -->
                        <input type="hidden" name="student_ids" id="modalStudentIds">
                        <input type="hidden" name="class_id" value="<?php echo $exam['class_id']; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo $exam['subject_id']; ?>">
                        
                        <div class="list-group list-group-flush border rounded-4 overflow-hidden mb-4">
                            <label class="list-group-item p-3">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3" name="include_1st_ca" value="1" checked>
                                    <div>
                                        <div class="fw-bold">1st C.A (Continuous Assessment)</div>
                                        <div class="text-muted small">First test scores</div>
                                    </div>
                                </div>
                            </label>
                            <label class="list-group-item p-3">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3" name="include_2nd_ca" value="1" checked>
                                    <div>
                                        <div class="fw-bold">2nd C.A (Continuous Assessment)</div>
                                        <div class="text-muted small">Second test scores</div>
                                    </div>
                                </div>
                            </label>
                            <label class="list-group-item p-3">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3" name="include_exam" value="1" checked>
                                    <div>
                                        <div class="fw-bold">Main Examination</div>
                                        <div class="text-muted small">Full terminal exam scores</div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <button type="submit" name="format" value="pdf" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">
                                    <i class="fas fa-file-pdf me-2"></i>Export PDF
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="submit" name="format" value="excel" class="btn btn-success w-100 rounded-pill py-3 fw-bold">
                                    <i class="fas fa-file-excel me-2"></i>Export Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Temporal Extension Modal -->
    <div class="modal fade" id="timeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form class="modal-content border-0 shadow-lg" style="border-radius:24px;" id="timeForm">
                <div class="modal-header border-0 p-4 pb-0">
                    <h6 class="fw-bold mb-0">Extend Temporal Allocation</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="icon-box-sm bg-soft-warning text-warning mx-auto mb-3"><i class="fas fa-clock h5 mb-0"></i></div>
                    <p class="text-muted small mb-4">Inject extra minutes for the selected candidate(s).</p>
                    
                    <input type="hidden" name="action" value="extend_time">
                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                    <input type="hidden" name="student_ids" id="timeModalStudentIds">
                    
                    <div class="input-group mb-4">
                        <input type="number" name="extra_mins" class="form-control text-center fw-bold" value="10" min="1" required>
                        <span class="input-group-text bg-light border-start-0">minutes</span>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 rounded-pill py-2 fw-bold text-white shadow-sm">
                        Synchronize Runtime
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <script>
        function selectAll(master) {
            document.querySelectorAll('.student-check').forEach(cb => {
                cb.checked = master.checked;
            });
            updateSelectedCount();
        }

        function toggleSelectAll() {
            const master = document.getElementById('masterSelect');
            master.checked = !master.checked;
            selectAll(master);
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.student-check:checked');
            const count = selected.length;
            const bar = document.getElementById('reportBar');
            
            document.getElementById('selectedCount').textContent = count;
            
            if (count > 0) {
                bar.style.display = 'flex';
                bar.classList.add('fade-in-up');
            } else {
                bar.style.display = 'none';
            }
        }

        function deselectAll() {
            document.getElementById('masterSelect').checked = false;
            selectAll(document.getElementById('masterSelect'));
        }

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.student-check:checked')).map(cb => cb.value).join(',');
        }

        function openReportModal() {
            document.getElementById('modalStudentIds').value = getSelectedIds();
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }

        function openTimeModal() {
            const ids = getSelectedIds();
            if(!ids) return;
            document.getElementById('timeModalStudentIds').value = ids;
            new bootstrap.Modal(document.getElementById('timeModal')).show();
        }

        function bulkAction(action) {
            const ids = getSelectedIds();
            if (!ids) return;

            if (action === 'retake') {
                if(!confirm("Security Warning: This will permanently purge the selected candidates' current answers and refresh their status to allow a full test array retake. Execute sequence?")) return;
            }

            Spinner.show('Orchestrating bulk candidate nodes...');
            $.post('../ajax/cbt_manage_attempts.php', {
                action: action,
                exam_id: <?php echo $exam_id; ?>,
                student_ids: ids
            }, function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    Spinner.hide();
                    Notif.show(res.message, 'error');
                }
            }, 'json');
        }

        $('#timeForm').on('submit', function(e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<i class="fas fa-sync fa-spin"></i> Processing...');
            Spinner.show('Extending temporal allocation...');
            $.post('../ajax/cbt_manage_attempts.php', $(this).serialize(), function(res) {
                if(res.success) {
                    location.reload();
                } else {
                    btn.prop('disabled', false).html('Synchronize Runtime');
                    Spinner.hide();
                    Notif.show(res.message, 'error');
                }
            }, 'json');
        });
    </script>
</body>
</html>
