<?php include __DIR__ . '/preloader.php'; ?>
<?php
// includes/admin_sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header p-3 border-bottom border-secondary border-opacity-25 mb-3">
        <div class="sidebar-logo-container mb-3 text-center">
            <?php if (isset($active_school) && !empty($active_school['logo_path'])): ?>
                <img src="../<?php echo htmlspecialchars($active_school['logo_path']); ?>" alt="School Logo" class="sidebar-school-logo" style="max-height: 50px; width: auto;">
            <?php else: ?>
                <img src="../<?php echo get_setting('sidebar_logo', 'img/logo.png'); ?>" alt="EduRemarks" class="sidebar-default-logo" style="max-height: 50px; width: auto;">
            <?php endif; ?>
        </div>

        <!-- School Switcher -->
        <div class="dropdown px-2">
            <button class="btn btn-sm w-100 px-3 py-2 dropdown-toggle no-caret fw-700 tracking-tight text-start d-flex justify-content-between align-items-center transition" 
                    type="button" 
                    data-bs-toggle="dropdown" 
                    style="font-size: 0.72rem; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(244, 180, 0, 0.4); border-radius: 12px; color: #fff;">
                <span class="text-truncate me-1"><i class="fas fa-university me-2" style="color: #F4B400;"></i> <?php echo $active_school ? htmlspecialchars($active_school['school_name']) : 'Select School'; ?></span>
                <i class="fas fa-chevron-down" style="font-size: 0.6rem; color: #F4B400; opacity: 0.7;"></i>
            </button>
            <ul class="dropdown-menu shadow-lg border-0 mt-2 w-100 animate-fade-in" style="border-radius: 12px; font-size: 0.82rem; z-index: 1250; background: #fff;">
                <li class="dropdown-header text-uppercase extra-small fw-bold text-primary opacity-75">Switch Environment</li>
                <?php foreach ($user_schools as $school): ?>
                <li>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 px-3 <?php echo ($active_school && $school['id'] == $active_school['id']) ? 'active bg-primary text-white rounded-3 mx-2 w-auto' : ''; ?>" href="#" onclick="switchSchool(<?php echo $school['id']; ?>)">
                        <span class="text-truncate me-2"><?php echo htmlspecialchars($school['school_name']); ?></span>
                        <?php if(isset($school['status']) && $school['status'] === 'pending') echo '<span class="badge bg-warning text-dark extra-small">P</span>'; ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <?php if ($role === 'owner'): ?>
                    <li><hr class="dropdown-divider opacity-50"></li>
                    <li><a class="dropdown-item fw-bold text-premium-gold py-2 px-3" href="add_school.php"><i class="fas fa-plus-circle me-2"></i>Add New School</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($active_school && $role !== 'super_admin'): ?>
        <!-- Mobile Credits Display -->
        <div class="d-lg-none px-2 mt-3">
            <div class="p-3 rounded-4 shadow-sm" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="extra-small fw-bold text-uppercase tracking-wider opacity-50"><i class="fas fa-bolt text-warning me-1"></i> Credits</span>
                    <?php if ($role === 'owner'): ?>
                    <a href="pricing.php" class="badge bg-primary text-white text-decoration-none rounded-pill px-2 py-1" style="font-size: 0.6rem;">TOP UP</a>
                    <?php endif; ?>
                </div>
                <div class="h4 fw-900 mb-0" style="letter-spacing: -0.5px;"><?php echo number_format($active_school['credits'] ?? 0); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    


    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <?php if (hasFeature('COURSE_CURRICULUM')): ?>
        <a href="curriculum.php" class="menu-item <?php echo $current_page == 'curriculum.php' ? 'active' : ''; ?>">
            <i class="fas fa-scroll text-premium-gold"></i> Course Curriculum
        </a>
        <?php endif; ?>
        <a href="profile.php" class="menu-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-school"></i> School Profile
        </a>
        <a href="departments.php" class="menu-item <?php echo $current_page == 'departments.php' ? 'active' : ''; ?>">
            <i class="fas fa-building-columns text-info"></i> <?php echo get_label('Section'); ?> Management
        </a>
        <?php if ($role === 'owner' || $role === 'super_admin'): ?>
        <a href="staff.php" class="menu-item <?php echo $current_page == 'staff.php' ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i> <?php echo get_label('Staff'); ?> Management
        </a>
        <?php endif; ?>
        <a href="students.php" class="menu-item <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i> Student Records
        </a>
        <a href="id_cards.php" class="menu-item <?php echo in_array($current_page, ['id_cards.php', 'id_cards_drafts.php']) ? 'active' : ''; ?>">
            <i class="fas fa-id-card"></i> ID Cards
            <span class="ms-auto badge rounded-pill" style="font-size:0.55rem; background: linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; padding:2px 7px;">NEW</span>
        </a>
        <?php if (hasFeature('ADMISSION_PORTAL') || $role === 'super_admin'): ?>
        <a href="admission_portal.php" class="menu-item <?php echo in_array($current_page, ['admission_portal.php', 'admission_config.php']) ? 'active' : ''; ?>">
            <i class="fas fa-id-badge"></i> Admission Portal
            <span class="ms-auto badge rounded-pill" style="font-size:0.55rem; background: linear-gradient(135deg,#f59e0b,#d97706); color:#fff; padding:2px 7px;">OPEN</span>
        </a>
        <?php endif; ?>
        <?php if (hasFeature('STUDENT_PORTAL') || $role === 'super_admin'): ?>
        <a href="student_portal.php" class="menu-item <?php echo $current_page == 'student_portal.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i> Student Portal
            <span class="ms-auto badge rounded-pill" style="font-size:0.55rem; background: linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; padding:2px 7px;">PRO</span>
        </a>
        <?php endif; ?>
        <?php if (hasFeature('CBT_EXAMS')): ?>
        <a href="question_builder.php" class="menu-item <?php echo $current_page == 'question_builder.php' ? 'active' : ''; ?>">
            <i class="fas fa-brain"></i> Question Builder
            <span class="ms-auto badge rounded-pill" style="font-size:0.55rem; background: linear-gradient(135deg,#10b981,#059669); color:#fff; padding:2px 7px;">HUB</span>
        </a>
        <?php endif; ?>
        <?php if (hasFeature('SMS_ALERTS')): ?>
        <a href="sms_campaigns.php" class="menu-item <?php echo $current_page == 'sms_campaigns.php' ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane"></i> SMS Campaigns
        </a>
        <?php endif; ?>
        <a href="academics.php" class="menu-item <?php echo $current_page == 'academics.php' ? 'active' : ''; ?>">
            <i class="fas fa-book-open"></i> Academics
        </a>

        <?php if ($role === 'owner' || $role === 'super_admin'): ?>
        <a href="academic_orchestration.php" class="menu-item <?php echo $current_page == 'academic_orchestration.php' ? 'active' : ''; ?>">
            <i class="fas fa-microchip"></i> <?php echo get_label('Academic Audit'); ?>
            <span class="ms-auto badge rounded-pill" style="font-size:0.55rem; background: #dc3545; color:#fff; padding:2px 7px;">LOCK</span>
        </a>
        <a href="add_school.php" class="menu-item <?php echo $current_page == 'add_school.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i> Add New School
        </a>
        <a href="settings.php" class="menu-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Account Settings
        </a>
        <a href="pricing.php" class="menu-item <?php echo $current_page == 'pricing.php' ? 'active' : ''; ?>">
            <i class="fas fa-bolt text-warning"></i> Academic Top Up
        </a>
        <a href="billing.php" class="menu-item <?php echo $current_page == 'billing.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i> Billing History
        </a>
        <?php endif; ?>
        <a href="support.php" class="menu-item <?php echo $current_page == 'support.php' ? 'active' : ''; ?>">
            <i class="fas fa-headset text-info"></i> Support Hub
        </a>
        <a href="../documentation.php" target="_blank" class="menu-item text-premium-gold">
            <i class="fas fa-book-reader"></i> Platform Guide
        </a>
        <a href="print_documentation.php" target="_blank" class="menu-item text-white fw-bold bg-success bg-opacity-25 mt-2 rounded-3 border-start border-4 border-success">
            <i class="fas fa-file-pdf text-success"></i> Print Documentation
        </a>
        <hr class="mx-3 border-secondary opacity-25">
        <a href="../logout.php" class="menu-item text-danger mt-auto">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
<div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('active'); document.body.classList.remove('sidebar-open')"></div>

<?php 
// Include the admin mobile bottom nav
include __DIR__ . '/admin_bottom_nav.php'; 
?>
