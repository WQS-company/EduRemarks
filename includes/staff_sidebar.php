<?php
// includes/staff_sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar sa-sidebar" style="background: #1a4da1; width: 200px; position: fixed; top: 0; left: 0; bottom: 0; z-index: 1200; color: white;">
    <div class="sidebar-header p-3 border-bottom border-white border-opacity-10">
        <!-- Logo and Brand -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <div class="bg-white rounded-circle p-1" style="width: 35px; height: 35px;">
                <img src="../img/logo.png" alt="EduRemarks" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="brand-text">
                <div class="fw-bold small tracking-tight uppercase" style="line-height: 1;"><?php echo get_label('Staff'); ?> Node</div>
                <div class="extra-small opacity-50">EduRemarks v2.1</div>
            </div>
        </div>

        <!-- School Switcher (Inside Sidebar) -->
        <div class="dropdown mt-2">
            <button class="btn btn-sm btn-light bg-white bg-opacity-10 text-white border-0 rounded-pill w-100 px-3 py-2 dropdown-toggle no-caret fw-600 tracking-tight text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" style="font-size: 0.75rem;">
                <span class="text-truncate me-2"><i class="fas fa-university me-2 opacity-75"></i> <?php echo $active_school ? htmlspecialchars($active_school['school_name']) : 'Select School'; ?></span>
                <i class="fas fa-chevron-down opacity-50" style="font-size: 0.6rem;"></i>
            </button>
            <ul class="dropdown-menu shadow-lg border-0 mt-2 w-100" style="border-radius: 12px; font-size: 0.85rem; z-index: 1100;">
                <li class="dropdown-header text-uppercase extra-small fw-bold">My Environments</li>
                <?php foreach ($user_schools as $school): ?>
                <li>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 <?php echo ($active_school && $school['id'] == $active_school['id']) ? 'active' : ''; ?>" href="#" onclick="switchSchool(<?php echo $school['id']; ?>)">
                        <span class="text-truncate me-2"><?php echo htmlspecialchars($school['school_name']); ?></span>
                        <?php if($school['status'] === 'pending') echo '<span class="badge bg-warning text-dark extra-small">P</span>'; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <nav class="mt-3">
        <a href="dashboard.php" class="sa-nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large me-2"></i> Dashboard
        </a>
        <a href="profile.php" class="sa-nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle me-2"></i> My Profile
        </a>
        <a href="lessons.php" class="sa-nav-link <?php echo $current_page == 'lessons.php' ? 'active' : ''; ?>">
            <i class="fas fa-chalkboard me-2"></i> Lesson Plans
        </a>
        <?php if (hasFeature('COURSE_CURRICULUM') && ($active_school['show_curriculum'] ?? 1)): ?>
        <a href="curriculum.php" class="sa-nav-link <?php echo $current_page == 'curriculum.php' ? 'active' : ''; ?>">
            <i class="fas fa-scroll me-2 text-warning"></i> Course Curriculum
        </a>
        <?php endif; ?>
        <?php if (hasFeature('CBT_EXAMS') && $staff_permissions['can_manage_cbt']): ?>
        <a href="question_builder.php" class="sa-nav-link <?php echo $current_page == 'question_builder.php' ? 'active' : ''; ?>">
            <i class="fas fa-brain me-2 text-warning"></i> Question Builder
        </a>
        <?php endif; ?>
        <?php if(defined('FEATURE_CBT_EXAMS') ? hasFeature('CBT_EXAMS') : true): ?>
        <a href="cbt_exams.php" class="sa-nav-link <?php echo $current_page == 'cbt_exams.php' ? 'active' : ''; ?>">
            <i class="fas fa-laptop-code me-2"></i> CBT / Exams
        </a>
        <?php endif; ?>
        <a href="students.php" class="sa-nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate me-2"></i> <?php echo get_label('Pupils'); ?> Records
        </a>
        <?php if (hasFeature('STUDENT_PORTAL') && $staff_permissions['can_manage_students']): ?>
        <a href="student_portal.php" class="sa-nav-link <?php echo $current_page == 'student_portal.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield me-2 text-warning"></i> <?php echo get_label('Pupils'); ?> Portal
        </a>
        <?php endif; ?>
        <?php if (hasFeature('ADMISSION_PORTAL')): ?>
        <a href="admission_portal.php" class="sa-nav-link <?php echo in_array($current_page, ['admission_portal.php', 'admission_config.php']) ? 'active' : ''; ?>">
            <i class="fas fa-id-badge me-2"></i> Admission Portal
        </a>
        <?php endif; ?>
        <?php if ($staff_permissions['can_manage_academics']): ?>
        <a href="academics.php" class="sa-nav-link <?php echo $current_page == 'academics.php' ? 'active' : ''; ?>">
            <i class="fas fa-book-reader me-2"></i> Academic Records
        </a>
        <?php endif; ?>
        <a href="support.php" class="sa-nav-link <?php echo $current_page == 'support.php' ? 'active' : ''; ?>">
            <i class="fas fa-headset me-2 text-info"></i> Support
        </a>
        <div class="px-3 mt-4 mb-2 extra-small fw-bold opacity-50 uppercase tracking-1">Institutions</div>
        <a href="#" class="sa-nav-link" data-bs-toggle="modal" data-bs-target="#joinSchoolModal">
            <i class="fas fa-search-plus me-2"></i> Join New
        </a>
        
        <a href="../documentation.php" target="_blank" class="sa-nav-link text-warning fw-bold">
            <i class="fas fa-book-reader me-2"></i> Platform Guide
        </a>
        <a href="print_documentation.php" target="_blank" class="sa-nav-link text-white fw-bold bg-success bg-opacity-25 mt-2 rounded-3 border-start border-4 border-success">
            <i class="fas fa-file-pdf me-2 text-success"></i> Print My Manual
        </a>
        <hr class="mx-3 border-white opacity-10">
        <a href="../logout.php" class="sa-nav-link text-danger">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sa-sidebar-overlay" id="saSidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1195;"></div>

<style>
.sa-nav-link {
    color: rgba(255,255,255,0.7) !important;
    padding: 12px 25px !important;
    font-size: 0.82rem !important;
    font-weight: 500 !important;
    transition: 0.3s;
    display: flex;
    align-items: center;
    text-decoration: none;
}
.sa-nav-link:hover {
    background: rgba(255,255,255,0.05);
    color: #fff !important;
}
.sa-nav-link.active {
    background: rgba(255,255,255,0.1) !important;
    color: #fff !important;
    border-left: 4px solid #F4B400;
}
.sa-nav-link i {
    width: 20px;
    text-align: center;
}

/* Bottom Nav for Mobile */
.sa-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
    z-index: 1040;
    padding: 10px 0;
    justify-content: space-around;
    border-top: 1px solid #f1f5f9;
}
.bottom-nav-item {
    text-align: center;
    color: #64748b;
    text-decoration: none;
    font-size: 0.65rem;
    font-weight: 600;
}
.bottom-nav-item i {
    display: block;
    font-size: 1.2rem;
    margin-bottom: 4px;
}
.bottom-nav-item.active {
    color: #1e40af;
}

@media (max-width: 991px) {
    .sa-sidebar { 
        transform: translateX(-100%); 
        transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
        top: 0 !important; 
        min-height: 100vh !important;
        box-shadow: 20px 0 50px rgba(0,0,0,0.1);
    }
    .sa-sidebar.active { transform: translateX(0); }
    .sa-sidebar-overlay.active { display: block !important; }
    .sa-bottom-nav { display: flex; }
    .main-content { padding-bottom: 80px !important; margin-left: 0 !important; }
}
</style>

<script>
function switchSchool(schoolId) {
    if (typeof Spinner !== 'undefined') Spinner.show('Switching Environment...');
    const path = window.location.pathname.indexOf('/user/') !== -1 || window.location.pathname.indexOf('/admin/') !== -1 ? '../' : '';
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

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sa-sidebar');
    const overlay = document.getElementById('saSidebarOverlay');
    const toggleBtn = document.getElementById('saSidebarToggle');

    if(toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    if(overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
    
    // Close sidebar on link click (for mobile)
    document.querySelectorAll('.sa-nav-link').forEach(link => {
        link.addEventListener('click', () => {
             if(window.innerWidth < 992) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
             }
        });
    });
});
</script>
</aside>

<?php 
// Include the staff mobile bottom nav
include __DIR__ . '/staff_bottom_nav.php'; 
?>

<?php 
// Include the global Join School Modal
include __DIR__ . '/staff_join_school.php'; 
?>
