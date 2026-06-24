<?php include 'preloader.php'; ?>
<?php
// includes/sa_header.php - World Class Super Admin Header
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/functions.php';

// Safe fallback for admin identity
$admin_full_name = $admin_name ?? $user_full_name ?? $_SESSION['user_full_name'] ?? 'Super Admin';
$admin_first_name = explode(' ', trim($admin_full_name))[0];
$profile_picture = $profile_picture ?? $_SESSION['profile_picture'] ?? '../img/default_picture.png';

// Fetch Notifications
$notif_res = get_support_notifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
$unread_count = $notif_res['count'];
$recent_notifs = $notif_res['notifications'];
?>
<header class="sa-header d-flex justify-content-between align-items-center px-4 shadow-sm" style="background: white; height: 75px; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; border-bottom: 1px solid #f1f5f9; margin-left: var(--sa-sidebar-width, 220px); transition: margin-left 0.4s ease;">
    <div class="d-flex align-items-center">
        <!-- Sidebar Toggle for Mobile -->
        <button class="btn btn-icon me-3 d-lg-none" id="saSidebarToggle">
            <i class="fas fa-bars-staggered"></i>
        </button>
        
        <div class="d-flex align-items-center gap-2">
            <h5 class="fw-800 mb-0 text-dark" style="font-size: 1.1rem;">Hi, <?php echo htmlspecialchars($admin_first_name); ?> <span class="wave">👋</span></h5>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="d-flex align-items-center gap-2">
        <!-- Notification Bell -->
        <div class="dropdown">
            <button class="btn btn-icon btn-notification position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if($unread_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-white" style="font-size: 0.6rem; padding: 4px 6px;">
                        <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-0 mt-2 overflow-hidden" style="width: 320px;">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800" style="font-size: 0.85rem;">Intelligence Hub</h6>
                    <span class="badge bg-primary rounded-pill extra-small"><?php echo $unread_count; ?> New</span>
                </div>
                <div class="notif-list" style="max-height: 350px; overflow-y: auto;">
                    <?php if(empty($recent_notifs)): ?>
                        <div class="p-4 text-center opacity-50">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="small mb-0">System synchronization complete. No pending alerts.</p>
                        </div>
                    <?php else: foreach($recent_notifs as $n): ?>
                        <a href="support_view.php?id=<?php echo $n['ticket_id']; ?>" class="dropdown-item p-3 border-bottom d-flex gap-3 align-items-start whitespace-normal">
                            <img src="<?php echo !empty($n['profile_picture']) ? '../'.$n['profile_picture'] : '../img/default_picture.png'; ?>" class="rounded-circle" style="width: 35px; height: 35px; min-width: 35px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-800 small text-blue"><?php echo htmlspecialchars($n['sender_name']); ?></span>
                                    <span class="tiny-text opacity-50"><?php echo time_ago($n['created_at']); ?></span>
                                </div>
                                <p class="small mb-0 text-muted line-clamp-2" style="font-size: 0.75rem; white-space: normal;"><?php echo htmlspecialchars($n['message']); ?></p>
                            </div>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
                <div class="p-2 border-top text-center bg-light">
                    <a href="requests.php" class="tiny-text fw-800 text-blue text-decoration-none uppercase">Open Support Center <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Vertical Separator -->
        <div class="vr mx-2 opacity-10 d-none d-md-block" style="height: 25px;"></div>

        <!-- Profile Dropdown -->
        <div class="dropdown">
            <button class="btn p-0 border-0 profile-node position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo $profile_picture; ?>" class="rounded-circle border border-2 border-white shadow-sm" style="width: 44px; height: 44px; object-fit: cover; background: #f1f5f9;">
                <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle" style="width: 12px; height: 12px;"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2 mt-2" style="min-width: 220px;">
                <li class="px-3 py-3 border-bottom mb-2 bg-light bg-opacity-50 rounded-top-4">
                    <h6 class="mb-0 fw-800 text-blue" style="font-size: 0.85rem;"><?php echo htmlspecialchars($admin_full_name); ?></h6>
                    <span class="tiny-text text-muted fw-800 uppercase">Institutional Super Admin</span>
                </li>
                <li><a class="dropdown-item rounded-3 small fw-600 py-2" href="profile.php"><i class="fas fa-user-shield me-2 opacity-50"></i> View Profile Node</a></li>
                <li><a class="dropdown-item rounded-3 small fw-600 py-2" href="billing.php"><i class="fas fa-wallet me-2 opacity-50"></i> Financial Ledger</a></li>
                <li><hr class="dropdown-divider opacity-5"></li>
                <li><a class="dropdown-item rounded-3 small fw-700 py-2 text-danger" href="../logout.php"><i class="fas fa-power-off me-2 opacity-75"></i> Logout System</a></li>
            </ul>
        </div>
    </div>
</header>
<div style="height: 75px;"></div>

<style>
    :root { --sa-sidebar-width: 220px; }
    @media (max-width: 991px) {
        :root { --sa-sidebar-width: 0px; }
        .sa-header { padding-left: 1rem !important; }
    }
    .btn-icon { width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center; transition: 0.2s; background: #f8fafc; border: 1px solid #f1f5f9; color: #64748b; font-size: 1.1rem; }
    .btn-icon:hover { background: #f1f5f9; color: #1e40af; border-color: #e2e8f0; transform: translateY(-2px); }
    .btn-notification:hover i { animation: ring 0.5s ease; }
    
    @media (max-width: 480px) {
        .sa-header { height: 62px !important; padding: 0 15px !important; }
        .sa-header h5 { font-size: 0.95rem !important; }
        .sa-header .btn-icon { width: 34px !important; height: 34px !important; font-size: 0.9rem !important; border-radius: 10px !important; }
        .sa-header .profile-node img { width: 34px !important; height: 34px !important; }
    }

    @keyframes ring {
        0%, 100% { transform: rotate(0); }
        25% { transform: rotate(15deg); }
        50% { transform: rotate(-15deg); }
        75% { transform: rotate(10deg); }
    }

    .dropdown-item { transition: 0.2s; }
    .dropdown-item:hover { background-color: #f8fafc; color: #1e40af; transform: translateX(5px); }
    .dropdown-item:active { background-color: #1e40af; }
    .whitespace-normal { white-space: normal !important; }
    
    .wave { animation: wave 2.5s infinite; transform-origin: 70% 70%; display: inline-block; }
    @keyframes wave {
        0%, 60%, 100% { transform: rotate(0deg); }
        10%, 30% { transform: rotate(14deg); }
        20% { transform: rotate(-8deg); }
        40% { transform: rotate(-4deg); }
        50% { transform: rotate(10deg); }
    }
</style>
