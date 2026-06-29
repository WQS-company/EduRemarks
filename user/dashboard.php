<?php
// user/dashboard.php
require_once '../includes/auth_check.php';

if ($role !== 'staff') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

// Get Active School Status
$access_status = $active_school ? $active_school['status'] : 'none';

// Fetch assigned classes for this staff member
$my_classes = [];
if ($active_school && $access_status === 'active') {
    $sd = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
    $sd->execute([$user_id, $active_school['id']]);
    $sd_row = $sd->fetch();
    if ($sd_row) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.code, c.section,
                   GROUP_CONCAT(sub.name ORDER BY sub.name SEPARATOR '|') AS subject_names,
                   GROUP_CONCAT(sub.code ORDER BY sub.name SEPARATOR '|') AS subject_codes,
                   COUNT(DISTINCT sc.student_id) AS student_count
            FROM staff_class_subjects scs
            JOIN classes c ON c.id = scs.class_id
            JOIN subjects sub ON sub.id = scs.subject_id
            LEFT JOIN student_classes sc ON sc.class_id = c.id AND sc.school_id = c.school_id
            WHERE scs.staff_detail_id=? AND scs.school_id=?
            GROUP BY c.id, c.name, c.code, c.section
            ORDER BY c.name
        ");
        $stmt->execute([$sd_row['id'], $active_school['id']]);
        $my_classes = $stmt->fetchAll();

        // Fetch Detailed Schedule for Timetable
        $stmt_sched = $pdo->prepare("
            SELECT scs.*, c.name as class_name, c.section, sub.name as subject_name, sub.code as subject_code, sub.period
            FROM staff_class_subjects scs
            JOIN classes c ON c.id = scs.class_id
            JOIN subjects sub ON sub.id = scs.subject_id
            WHERE scs.staff_detail_id=? AND scs.school_id=?
            ORDER BY sub.period ASC
        ");
        $stmt_sched->execute([$sd_row['id'], $active_school['id']]);
        $my_schedule = $stmt_sched->fetchAll();

        // Fetch Platform Campaigns
        $campaign_stmt = $pdo->prepare("SELECT * FROM platform_campaigns WHERE target_school_ids IS NULL OR FIND_IN_SET(?, target_school_ids) ORDER BY created_at DESC LIMIT 1");
        $campaign_stmt->execute([$active_school['id']]);
        $campaign = $campaign_stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | <?php echo get_setting('hero_title', 'EduRemarks'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .hover-scale:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(31, 60, 136, 0.1); }
        .schedule-widget { 
            background: linear-gradient(135deg, #1e3c88 0%, #2d6cdf 100%); 
            color: white; 
            border-radius: 24px; 
            overflow: hidden; 
            position: relative; 
            box-shadow: 0 20px 40px rgba(31, 60, 136, 0.15);
        }
        .live-clock { font-size: 2.2rem; font-weight: 800; letter-spacing: -1px; }
        .sched-card { 
            background: rgba(255,255,255,0.08); 
            backdrop-filter: blur(10px); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 16px; 
            padding: 15px; 
            margin-bottom: 12px; 
        }
        .sched-card.active { border-left: 4px solid var(--brand-gold); background: rgba(255,255,255,0.15); }
    </style>
</head>
<body>

    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <?php if ($access_status === 'active'): ?>
            <!-- Quick Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="glass-card p-3 h-100 hover-scale">
                        <div class="extra-small opacity-50 fw-bold uppercase mb-1">Environment ID</div>
                        <div class="fw-800 text-primary h5 mb-0"><?php echo $active_school ? $active_school['unique_id'] : 'N/A'; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="glass-card p-3 h-100 hover-scale">
                        <div class="extra-small opacity-50 fw-bold uppercase mb-1">Assigned <?php echo get_label('Classes'); ?></div>
                        <div class="fw-800 text-primary h5 mb-0"><?php echo count($my_classes); ?> <span class="extra-small opacity-50 fw-600">Active</span></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="glass-card p-3 h-100 hover-scale">
                        <div class="extra-small opacity-50 fw-bold uppercase mb-1">Active <?php echo get_label('Term'); ?>/Session</div>
                        <div class="fw-800 text-dark" style="font-size: 0.9rem;">
                            <span class="text-primary"><?php echo htmlspecialchars($current_term_name ?? 'Active '.get_label('Term')); ?></span>
                            <div class="extra-small opacity-75 mt-1"><?php echo htmlspecialchars($current_session_name ?? 'Current Session'); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="glass-card p-3 h-100 hover-scale d-flex align-items-center gap-3">
                         <div class="bg-success bg-opacity-10 text-success rounded-circle p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                             <i class="fas fa-check-circle"></i>
                         </div>
                         <div>
                            <div class="extra-small opacity-50 fw-bold uppercase">System Access</div>
                            <div class="fw-800 text-success" style="font-size: 0.9rem;">Authenticated Node</div>
                         </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Feed -->
            <?php if (!empty($campaign)): ?>
            <div class="glass-card p-3 mb-4 border-start border-4 border-primary" style="background: #f0f7ff;">
               <div class="d-flex align-items-center gap-3">
                  <div class="text-primary"><i class="fas fa-bullhorn fa-lg"></i></div>
                  <div class="small fw-600 text-dark"><?php echo htmlspecialchars($campaign['message']); ?></div>
               </div>
            </div>
            <?php endif; ?>

            <!-- Live Schedule Widget -->
            <div class="schedule-widget p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-lg-5 mb-4 mb-lg-0 border-lg-end border-white border-opacity-10 pe-lg-4">
                        <div class="extra-small opacity-75 fw-bold uppercase tracking-1 mb-1" id="realTimeDate">Friday, 24 JAN, 2024</div>
                        <div class="live-clock mb-3" id="realTimeClock">00:00:00</div>
                        <div class="badge bg-white bg-opacity-10 p-2 px-3 rounded-pill extra-small d-inline-flex align-items-center gap-2">
                             <span class="p-1 bg-danger rounded-circle blink" style="width: 6px; height: 6px;"></span>
                             Operational System Clock
                        </div>
                    </div>
                    <div class="col-lg-7 ps-lg-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-800 mb-0 small uppercase opacity-90"><i class="fas fa-calendar-day me-2"></i>Upcoming <?php echo (get_label('Subject') === 'Course') ? 'Lectures' : 'Class Sessions'; ?></h6>
                        </div>
                        <div class="timetable-scroll" style="max-height: 200px; overflow-y: auto;">
                            <?php if (empty($my_schedule)): ?>
                                <div class="text-center py-4 opacity-50">
                                    <i class="fas fa-calendar-times mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="extra-small fw-600">No scheduled sessions today</div>
                                </div>
                            <?php else: 
                                $now = time();
                                foreach ($my_schedule as $item): 
                                    $sched_time = strtotime($item['period']);
                                    $isActive = ($sched_time <= $now && $now < ($sched_time + 3600));
                            ?>
                                <div class="sched-card <?php echo $isActive ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-800 small mb-1"><?php echo htmlspecialchars($item['subject_name']); ?></div>
                                            <div class="extra-small opacity-75">
                                                <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($item['class_name']); ?> 
                                                <span class="mx-2">&bull;</span>
                                                <i class="fas fa-clock me-1"></i><?php echo date("g:i A", $sched_time); ?>
                                            </div>
                                        </div>
                                        <a href="class_view.php?class_id=<?php echo $item['class_id']; ?>" class="btn btn-sm btn-light py-1 px-3 rounded-pill fw-800 extra-small">ENTER</a>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Classes Grid -->
            <div id="myClassesSection" class="pb-5">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h5 class="fw-800 text-primary mb-1">Assigned <?php echo get_label('Classes'); ?></h5>
                        <p class="text-muted extra-small fw-600 mb-0">Management nodes for academic orchestration</p>
                    </div>
                </div>

                <div class="row g-4">
                    <?php if (empty($my_classes)): ?>
                        <div class="col-12">
                            <div class="glass-card p-5 text-center">
                                <i class="fas fa-chalkboard-teacher fa-3x text-muted opacity-25 mb-3"></i>
                                <h6 class="fw-800 opacity-50">No <?php echo get_label('Classes'); ?> Assigned</h6>
                            </div>
                        </div>
                    <?php else: foreach ($my_classes as $cls): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="glass-card p-4 h-100 hover-scale border-0 shadow-sm overflow-hidden" style="border-top: 4px solid var(--sa-blue) !important;">
                                <div class="d-flex justify-content-between mb-4">
                                     <div class="bg-primary bg-opacity-10 text-primary rounded-4 p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                         <i class="fas fa-university"></i>
                                     </div>
                                     <span class="badge bg-soft-primary text-primary px-3 rounded-pill d-flex align-items-center" style="font-size: 0.65rem; background: #EEF2FB; font-weight: 800;"><?php echo $cls['student_count']; ?> <?php echo get_label('Pupils'); ?></span>
                                </div>
                                <h5 class="fw-800 text-dark mb-1"><?php echo htmlspecialchars($cls['name']); ?></h5>
                                <p class="extra-small text-muted fw-bold uppercase mb-4"><?php echo htmlspecialchars($cls['code']); ?><?php echo $cls['section'] ? ' &bull; '.get_label('Section').' '.$cls['section'] : ''; ?></p>
                                
                                <div class="d-flex flex-wrap gap-1 mb-4">
                                    <?php $names = explode('|',$cls['subject_names']); foreach($names as $sname): ?>
                                        <span class="badge bg-light text-muted border py-2 px-3 fw-800" style="font-size: 0.6rem; border-radius: 10px;"><?php echo htmlspecialchars($sname); ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <a href="class_view.php?class_id=<?php echo $cls['id']; ?>" class="btn btn-primary w-100 rounded-pill py-2 fw-800" style="font-size: 0.75rem;">ORCHESTRATE ACADEMICS</a>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        <?php elseif ($access_status === 'pending'): ?>
            <div class="glass-card p-5 text-center mt-5">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2.5rem;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <h4 class="fw-800">Node Authentication Pending</h4>
                <p class="text-muted small mx-auto" style="max-width: 500px;">Your staff record is awaiting digital validation from <strong><?php echo $active_school['school_name']; ?></strong> management console.</p>
                <div class="alert alert-warning border-0 d-inline-block small fw-600 px-4 mt-2">
                    System access is restricted during validation phase.
                </div>
            </div>
        <?php else: ?>
            <div class="glass-card p-5 text-center mt-5">
                <i class="fas fa-network-wired fa-3x text-muted opacity-25 mb-4"></i>
                <h4 class="fw-800 text-primary">No Institution Link Detected</h4>
                <p class="text-muted small">Please established a secure link to your institution using its Unique ID.</p>
                <button class="btn btn-primary rounded-pill px-5 fw-800 mt-3" data-bs-toggle="modal" data-bs-target="#joinSchoolModal">ESTABLISH NEW LINK</button>
            </div>
        <?php endif; ?>
        <?php include '../includes/dashboard_footer.php'; ?>
    </main>

    <script>
        // Real-time Clock
        function updateClock() {
            const now = new Date();
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
            
            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            
            let hours = now.getHours();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; 
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const dtEl = document.getElementById('realTimeDate');
            if (dtEl) dtEl.textContent = `${dayName}, ${day} ${monthName}, ${year}`;
            
            const clEl = document.getElementById('realTimeClock');
            if (clEl) clEl.textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
