<?php
// includes/dashboard_top_nav.php
require_once dirname(__FILE__) . '/functions.php';

// Fetch Notifications for User
$notif_res = get_support_notifications($pdo, $_SESSION['user_id'], $_SESSION['role']);
$unread_count = $notif_res['count'];
$recent_notifs = $notif_res['notifications'];

$full_name_raw = $user_full_name ?? $_SESSION['user_full_name'] ?? 'User';
$user_first_name = explode(' ', trim($full_name_raw))[0];

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
    @media (max-width: 480px) {
        .responsive-greeting { font-size: 0.8rem !important; }
    }
    @media (max-width: 320px) {
        .responsive-greeting { font-size: 0.7rem !important; }
        .responsive-greeting span.wave { display: none; }
    }
    .text-truncate-greeting {
        max-width: 120px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
        vertical-align: bottom;
    }
</style>
<script>
    // System-wide school switching logic
    if (typeof switchSchool === 'undefined') {
        function switchSchool(schoolId) {
            if (typeof Spinner !== 'undefined') Spinner.show('Switching Environment...');
            const path = '<?php echo $path_prefix ?? ""; ?>';
            fetch(path + 'ajax/switch_school.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'school_id=' + schoolId
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else { 
                    if (typeof Notif !== 'undefined') Notif.show(d.message, 'error');
                    if (typeof Spinner !== 'undefined') Spinner.hide(); 
                }
            }).catch(e => {
                if (typeof Spinner !== 'undefined') Spinner.hide();
            });
        }
    }
</script>
<header class="dash-top-nav d-flex align-items-center px-3 py-2 bg-white border-bottom shadow-sm">
    <div class="d-flex align-items-center me-auto">
        <button class="btn btn-icon d-lg-none me-3" onclick="const sb=document.querySelector('.sidebar'); sb.classList.toggle('active'); document.body.classList.toggle('sidebar-open', sb.classList.contains('active'))">
            <i class="fas fa-bars-staggered"></i>
        </button>

        <div class="d-flex align-items-center gap-2">
            <h6 class="fw-800 mb-0 text-dark responsive-greeting" style="font-size: 0.95rem;">
                Hi, <span class="text-truncate-greeting"><?php echo htmlspecialchars($user_first_name); ?></span> <span class="wave">👋</span>
            </h6>
        </div>

        <?php if ($active_school): ?>
        <div class="ms-4 ps-4 border-start d-none d-md-flex flex-column justify-content-center">
            <div class="extra-small fw-800 text-muted uppercase tracking-1 opacity-75">Active Node Context</div>
            <div class="fw-bold text-primary small" style="font-size: 0.75rem;">
                <i class="fas fa-calendar-check me-1"></i> <?php echo htmlspecialchars($current_term_name); ?> — <?php echo htmlspecialchars($current_session_name); ?>
            </div>
        </div>
        <?php endif; ?>


    </div>

    <div class="d-flex align-items-center gap-2">
        <!-- Notification Bell -->
        <div class="dropdown">
            <button class="btn btn-icon btn-notification position-relative" data-bs-toggle="dropdown">
                <i class="fas fa-bell"></i>
                <?php if($unread_count > 0): ?>
                    <span class="position-absolute top-10 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-white" style="font-size: 0.6rem; padding: 4px 6px;">
                        <?php echo $unread_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-0 mt-3 overflow-hidden" style="width: 300px;">
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-800" style="font-size: 0.8rem;">EduRemarks Support</h6>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill"><?php echo $unread_count; ?> New</span>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if(empty($recent_notifs)): ?>
                        <div class="p-4 text-center opacity-50">
                            <i class="fas fa-comment-slash fa-2x mb-3 text-muted"></i>
                            <p class="small mb-0">No new support transmissions.</p>
                        </div>
                    <?php else: foreach($recent_notifs as $n): ?>
                        <div class="dropdown-item p-3 border-bottom d-flex gap-2 align-items-start whitespace-normal cursor-pointer" onclick="document.getElementById('chatToggle').click()">
                            <img src="<?php echo $path_prefix ?? ''; ?>img/logo.png" class="rounded-circle border p-1 bg-white" style="width: 32px; height: 32px; min-width: 32px; object-fit: contain;">
                            <div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-800 small">EduRemarks</span>
                                    <span class="tiny-text opacity-50"><?php echo time_ago($n['created_at']); ?></span>
                                </div>
                                <p class="small mb-0 text-muted line-clamp-2" style="font-size: 0.75rem; white-space: normal;"><?php echo htmlspecialchars($n['message']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="p-2 border-top text-center bg-light">
                    <button class="btn btn-link tiny-text fw-800 text-blue text-decoration-none uppercase" onclick="document.getElementById('chatToggle').click()">Open Chat Node</button>
                </div>
            </div>
        </div>
    <div class="user-profile d-flex align-items-center dropdown ms-3">
        <div class="avatar-container dropdown-toggle no-caret cursor-pointer p-0" 
             data-bs-toggle="dropdown" 
             aria-expanded="false"
             style="border-radius: 50%; padding: 3px !important; border: 2px solid rgba(31, 60, 136, 0.1);">
            <div style="position: relative;">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?php echo $profile_picture; ?>" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover; border: 2px solid white;">
                <?php else: ?>
                    <img src="../img/default_picture.png" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover; border: 2px solid white;">
                <?php endif; ?>
                <div style="position: absolute; bottom: 1px; right: 1px; width: 10px; height: 10px; background: #28a745; border: 2px solid white; border-radius: 50%;"></div>
            </div>
        </div>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-3 p-2 animate-fade-in" style="border-radius: 20px; min-width: 240px;">
            <li class="px-3 py-3 border-bottom mb-2 bg-light rounded-4 mx-2">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm overflow-hidden" style="width: 48px; height: 48px;">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?php echo $profile_picture; ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <img src="../img/default_picture.png" style="width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="overflow-hidden">
                        <div class="fw-bold text-primary text-truncate"><?php echo $user_full_name; ?></div>
                        <div class="extra-small text-muted text-truncate"><?php echo $user_email; ?></div>
                        <div class="badge bg-soft-primary text-primary extra-small mt-1 px-2">
                            <i class="fas fa-shield-alt me-1"></i> <?php echo ($role === 'owner') ? 'Admin' : 'Staff'; ?>
                        </div>
                    </div>
                </div>
            </li>
            <li>
                <a class="dropdown-item py-2 px-3 rounded-3" href="<?php echo $role === 'owner' ? $path_prefix.'admin/settings.php' : $path_prefix.'user/profile.php'; ?>">
                    <i class="fas fa-user-circle me-2 text-muted"></i> Account Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item py-2 px-3 rounded-3" href="<?php echo $role === 'owner' ? $path_prefix.'admin/settings.php' : $path_prefix.'user/profile.php'; ?>">
                    <i class="fas fa-cog me-2 text-muted"></i> Preferences
                </a>
            </li>
            <li><hr class="dropdown-divider opacity-50"></li>
            <li>
                <a class="dropdown-item py-2 px-3 text-danger rounded-3 fw-bold" href="<?php echo $path_prefix; ?>logout.php">
                    <i class="fas fa-power-off me-2"></i> End Session
                </a>
            </li>
        </ul>
    </div>
</header>
