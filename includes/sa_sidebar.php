<?php
// includes/sa_sidebar.php - Refined Super Admin Sidebar
$current_page = basename($_SERVER['PHP_SELF']);
$logo_path = function_exists('get_setting') ? get_setting('sidebar_logo', get_setting('platform_logo', 'img/logo.png')) : 'img/logo.png';
?>
<aside class="sa-sidebar d-flex flex-column" style="background: #1a4da1; width: 220px; height: 100vh; position: fixed; left: 0; top: 0; z-index: 1060; transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
    <!-- Sidebar Logo Section -->
    <div class="sidebar-logo-container d-flex align-items-center px-4" style="height: 75px; background: rgba(0,0,0,0.1); border-bottom: 1px solid rgba(255,255,255,0.05);">
        <div class="bg-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
            <img src="../<?php echo $logo_path; ?>" alt="EduRemarks" style="width: 22px; height: 22px;">
        </div>
        <h6 class="fw-900 mb-0 text-white tracking-tight" style="font-size: 0.95rem; letter-spacing: -0.5px;">EDUREMARKS</h6>
    </div>

    <nav class="flex-grow-1 py-4 overflow-auto">
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link sa-nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-grid-2 me-3"></i> <span>Dashboard Hub</span>
                </a>
            </li>
            <li class="nav-item border-top border-white border-opacity-10 my-2"></li>
            <li class="nav-item">
                <a href="schools.php" class="nav-link sa-nav-link <?php echo ($current_page == 'schools.php') ? 'active' : ''; ?>">
                    <i class="fas fa-school me-3"></i> <span>Institutions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="billing.php" class="nav-link sa-nav-link <?php echo ($current_page == 'billing.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar me-3"></i> <span>Financial Hub</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="pricing.php" class="nav-link sa-nav-link <?php echo ($current_page == 'pricing.php' || $current_page == 'credits.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tags me-3"></i> <span>Pricing Hub</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="content.php" class="nav-link sa-nav-link <?php echo ($current_page == 'content.php') ? 'active' : ''; ?>">
                    <i class="fas fa-globe me-3"></i> <span>Landing Page</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="blog.php" class="nav-link sa-nav-link <?php echo ($current_page == 'blog.php') ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper me-3"></i> <span>Blog Center</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="requests.php" class="nav-link sa-nav-link <?php echo ($current_page == 'requests.php') ? 'active' : ''; ?>">
                    <i class="fas fa-life-ring me-3"></i> <span>Support Hub</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sms_campaigns.php" class="nav-link sa-nav-link <?php echo ($current_page == 'sms_campaigns.php') ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane me-3"></i> <span>Global Broadcaster</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../documentation.php" target="_blank" class="nav-link sa-nav-link text-warning fw-bold">
                    <i class="fas fa-book-reader me-3"></i> <span>Master Guide</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="print_documentation.php" target="_blank" class="nav-link sa-nav-link text-white fw-bold bg-success bg-opacity-25 mt-2 rounded-3 border-start border-4 border-success mx-2 overflow-hidden">
                    <i class="fas fa-file-pdf me-3 text-success"></i> <span>Print All Docs</span>
                </a>
            </li>
            <li class="nav-item border-top border-white border-opacity-10 my-2"></li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link sa-nav-link text-warning">
                    <i class="fas fa-sign-out-alt me-3"></i> <span>Terminate</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sa-sidebar-overlay" id="saSidebarOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1035;"></div>

<style>
.sa-nav-link {
    color: rgba(255,255,255,0.7) !important;
    padding: 10px 20px !important;
    font-size: 0.82rem !important;
    font-weight: 500 !important;
    transition: 0.3s;
    display: flex;
    align-items: center;
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
    .sa-main-content { padding-bottom: 80px !important; }
}
</style>

<script>
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

<?php include dirname(__FILE__) . '/sa_bottom_nav.php'; ?>
