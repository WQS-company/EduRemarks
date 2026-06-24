<?php
// includes/staff_header.php - World Class Staff Header
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/functions.php';

$user_name = $user_full_name ?? 'Staff User';
$profile_pic = $profile_picture ?? '../img/default_picture.png';

// Fetch Notifications
$notif_res = get_support_notifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
$unread_count = $notif_res['count'];
$recent_notifs = $notif_res['notifications'];

// Fetch Active Context Names
$current_session_name = "Session";
$current_term_name = get_label('Term');
if (isset($active_school['current_session_id'])) {
    $sess_stmt = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
    $sess_stmt->execute([$active_school['current_session_id']]);
    $current_session_name = $sess_stmt->fetchColumn() ?: "Active Session";

    $term_stmt = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
    $term_stmt->execute([$active_school['current_term_id']]);
    $current_term_name = $term_stmt->fetchColumn();
    $current_term_name = $current_term_name ? get_label($current_term_name) : 'Active '.get_label('Term');
}
?>
<style>
    :root { 
        --sa-blue: #1a4da1; 
        --sa-bg: #f8fafc;
        --brand-gold: #F4B400;
        --sa-sidebar-width: 200px;
    }
    body { background: var(--sa-bg); font-family: 'Inter', sans-serif; letter-spacing: -0.2px; }
    .sa-main-content { margin-left: var(--sa-sidebar-width); padding: 30px; transition: 0.3s; min-height: calc(100vh - 75px); position: relative; }
    
    .fw-800 { font-weight: 800; }
    .extra-small { font-size: 0.65rem; }
    .uppercase { text-transform: uppercase; }
    
    .glass-card { 
        border-radius: 20px; 
        background: #fff; 
        box-shadow: 0 10px 25px rgba(31, 60, 136, 0.05); 
        border: 1px solid rgba(0,0,0,0.03);
    }

    @media (max-width: 991px) {
        :root { --sa-sidebar-width: 0px; }
        .sa-main-content { padding: 15px; padding-bottom: 100px; }
    }

    .btn-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: 0.2s; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); color: white; }
    .btn-icon:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
    @media (max-width: 480px) {
        .responsive-greeting { font-size: 0.85rem !important; }
        .staff-node-badge { display: none !important; }
    }
    .wave { animation: wave 2.5s infinite; transform-origin: 70% 70%; display: inline-block; }
    @keyframes wave {
        0%, 60%, 100% { transform: rotate(0deg); }
        10%, 30% { transform: rotate(14deg); }
        20% { transform: rotate(-8deg); }
        40% { transform: rotate(-4deg); }
        50% { transform: rotate(10deg); }
    }
</style>

<header class="sa-header d-flex justify-content-between align-items-center px-3 px-md-4 shadow-sm" style="background: #1a4da1; height: 75px; color: white; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; margin-left: var(--sa-sidebar-width); transition: margin-left 0.3s ease;">
    <div class="d-flex align-items-center">
        <button id="saSidebarToggle" class="btn text-white p-0 me-3 d-lg-none" style="font-size: 1.5rem;">
            <i class="fas fa-bars-staggered"></i>
        </button>

        <div class="d-flex align-items-center gap-2">
            <h6 class="fw-800 mb-0 text-white responsive-greeting" style="font-size: 1rem;">Hi, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?> <span class="wave">👋</span></h6>
            <div class="ms-3 ps-3 border-start border-white border-opacity-25 d-none d-md-block">
                <div class="extra-small fw-800 text-white uppercase tracking-1 opacity-75">Academic Context</div>
                <div class="fw-bold text-white small" style="font-size: 0.75rem;">
                    <i class="fas fa-calendar-alt me-1 text-warning"></i> <?php echo htmlspecialchars($current_term_name); ?> — <?php echo htmlspecialchars($current_session_name); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <!-- Notification Bell -->
        <div class="dropdown">
            <button class="btn btn-icon position-relative" data-bs-toggle="dropdown">
                <i class="fas fa-bell"></i>
                <?php if($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-primary" style="font-size: 0.6rem; padding: 4px 6px;">
                        <?php echo $unread_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-0 mt-3 overflow-hidden" style="width: 300px;">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800 text-dark" style="font-size: 0.8rem;">EduRemarks Support</h6>
                    <span class="badge bg-primary rounded-pill"><?php echo $unread_count; ?> New</span>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if(empty($recent_notifs)): ?>
                        <div class="p-4 text-center opacity-50">
                            <i class="fas fa-comment-dots fa-2x mb-2 text-muted"></i>
                            <p class="small mb-0 text-dark">No new messages from EduRemarks.</p>
                        </div>
                    <?php else: foreach($recent_notifs as $n): ?>
                        <div class="dropdown-item p-3 border-bottom d-flex gap-2 align-items-start whitespace-normal cursor-pointer" onclick="document.getElementById('chatToggle').click()">
                             <img src="../img/logo.png" class="rounded-circle border p-1 bg-white" style="width: 32px; height: 32px; min-width: 32px; object-fit: contain;">
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-800 small text-dark">EduRemarks</span>
                                    <span class="tiny-text opacity-50 text-dark"><?php echo time_ago($n['created_at']); ?></span>
                                </div>
                                <p class="small mb-0 text-muted line-clamp-2" style="font-size: 0.75rem; white-space: normal;"><?php echo htmlspecialchars($n['message']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="p-2 border-top text-center bg-light">
                    <button class="btn btn-link tiny-text fw-800 text-blue text-decoration-none uppercase" onclick="document.getElementById('chatToggle').click()">Open Support Chat</button>
                </div>
            </div>
        </div>
        
        <!-- Profile Dropdown -->
        <div class="dropdown">
            <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 p-1 pe-3 rounded-pill border border-white border-opacity-10 dropdown-toggle no-caret" data-bs-toggle="dropdown" style="cursor: pointer;">
                <img src="<?php echo $profile_pic; ?>" class="rounded-circle border border-2 border-white" style="width: 36px; height: 36px; min-width: 36px; object-fit: cover;">
                <div class="d-none d-sm-block">
                    <div class="fw-bold" style="line-height: 1.2; font-size: 0.75rem;"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                    <div class="extra-small opacity-75" style="font-size: 0.6rem;">Active Node</div>
                </div>
            </div>
            
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2" style="border-radius: 15px; font-size: 0.85rem; min-width: 220px;">
                <li class="px-3 py-3 border-bottom mb-2 bg-light rounded-4">
                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="extra-small text-muted"><?php echo htmlspecialchars($user_email); ?></div>
                </li>
                <li><a class="dropdown-item py-2 rounded-3" href="profile.php"><i class="fas fa-user-circle me-2 opacity-50"></i> View Profile</a></li>
                <li><hr class="dropdown-divider opacity-10"></li>
                <li><a class="dropdown-item py-2 text-danger fw-bold rounded-3" href="../logout.php"><i class="fas fa-power-off me-2"></i> End Session</a></li>
            </ul>
        </div>
    </div>
</header>

<div style="height: 75px;"></div>
