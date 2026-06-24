<?php
// student/includes/nav.php — Unified student portal navigation
$current_page = basename($_SERVER['PHP_SELF']);
// Use the student's registered school logo for branding in their portal
$school_logo_path = !empty($student['logo_path']) ? $student['logo_path'] : get_setting('sidebar_logo', 'img/logo.png');
$logo_src = (strpos($school_logo_path, 'http') === 0) ? $school_logo_path : '../' . $school_logo_path;

$sch_name_html = $student['school_name'];
$sch_name_display = htmlspecialchars_decode($student['school_name'], ENT_QUOTES);
$stu_name_html = htmlspecialchars($student['full_name']);
$stu_photo = $student['image_path'] ? '../' . $student['image_path'] : '../img/default_picture.png';
?>

<!-- ═══ DESKTOP SIDEBAR ═══ -->
<aside class="stu-sidebar" id="stuSidebar">
    <div class="stu-sidebar-header">
        <div class="bg-white rounded-circle p-1" style="width: 40px; height: 40px; display:flex; align-items:center; justify-content:center;">
            <img src="<?php echo $logo_src; ?>" style="width:100%; height:100%; object-fit:contain;" alt="Logo">
        </div>
        <div class="stu-sidebar-school">
            <div class="stu-sidebar-school-name"><?php echo htmlspecialchars($sch_name_display); ?></div>
            <div class="stu-sidebar-subtitle">Student Node</div>
        </div>
    </div>

    <div class="stu-sidebar-profile">
        <img src="<?php echo $stu_photo; ?>" class="stu-sidebar-avatar" alt="">
        <div>
            <div class="stu-sidebar-student-name"><?php echo $stu_name_html; ?></div>
            <div class="stu-sidebar-meta"><?php echo htmlspecialchars($student['admission_no']); ?></div>
        </div>
    </div>

    <nav class="stu-sidebar-nav">
        <a href="dashboard.php" class="stu-nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="performance.php" class="stu-nav-item <?php echo $current_page == 'performance.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Performance</span>
        </a>
        <a href="academic_audit.php" class="stu-nav-item <?php echo $current_page == 'academic_audit.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span><?php echo get_label('Academic Audit'); ?></span>
        </a>
        <a href="reports.php" class="stu-nav-item <?php echo in_array($current_page, ['reports.php', 'view_report.php']) ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span><?php echo get_label('Report Sheets'); ?></span>
        </a>
        <a href="assessments.php" class="stu-nav-item <?php echo $current_page == 'assessments.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Assessments</span>
        </a>
    </nav>

    <div class="stu-sidebar-footer">
        <a href="logout.php" class="stu-nav-item text-danger">
            <i class="fas fa-power-off"></i>
            <span>End Session</span>
        </a>
    </div>
</aside>
<div class="stu-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══ MOBILE TOP BAR ═══ -->
<header class="stu-topbar">
    <button class="stu-topbar-menu" onclick="openSidebar()">
        <i class="fas fa-bars-staggered"></i>
    </button>
    <div class="stu-topbar-brand">
        <span class="fw-800 tracking-tight text-white" style="font-size: 0.9rem;">Hi, <?php echo htmlspecialchars($student['full_name']); ?></span>
    </div>
    <div class="dropdown">
        <img src="<?php echo $stu_photo; ?>" class="stu-topbar-avatar border-2 border-white border-opacity-25" alt="" data-bs-toggle="dropdown">
    </div>
</header>

<!-- ═══ MOBILE BOTTOM TABS ═══ -->
<nav class="stu-bottom-tabs">
    <a href="dashboard.php" class="stu-tab <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i>
        <span>Home</span>
    </a>
    <a href="performance.php" class="stu-tab <?php echo $current_page == 'performance.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Stats</span>
    </a>
    <a href="academic_audit.php" class="stu-tab <?php echo $current_page == 'academic_audit.php' ? 'active' : ''; ?>">
        <i class="fas fa-history"></i>
        <span>Audit</span>
    </a>
    <a href="reports.php" class="stu-tab <?php echo in_array($current_page, ['reports.php', 'view_report.php']) ? 'active' : ''; ?>">
        <i class="fas fa-file-alt"></i>
        <span><?php echo (get_label('Subject') === 'Course') ? 'Results' : 'Reports'; ?></span>
    </a>
    <a href="assessments.php" class="stu-tab <?php echo $current_page == 'assessments.php' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-check"></i>
        <span>Tests</span>
    </a>
</nav>

<script>
function openSidebar() {
    document.getElementById('stuSidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('stuSidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
// Close on ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSidebar();
});
</script>
