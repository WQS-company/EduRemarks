<?php
// includes/admin_bottom_nav.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Bottom Nav for Mobile */
.admin-bottom-nav {
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
    .admin-bottom-nav { display: flex; }
    .main-content, .sa-main-content { padding-bottom: 80px !important; margin-left: 0 !important; }
}
</style>

<!-- Bottom Nav for Mobile -->
<div class="admin-bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i> Home
    </a>
    <a href="students.php" class="bottom-nav-item <?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
        <i class="fas fa-user-graduate"></i> Students
    </a>
    <a href="staff.php" class="bottom-nav-item <?php echo ($current_page == 'staff.php') ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i> <?php echo get_label('Staff'); ?>
    </a>
    <a href="support.php" class="bottom-nav-item <?php echo ($current_page == 'support.php') ? 'active' : ''; ?>">
        <i class="fas fa-headset"></i> Support
    </a>
    <a href="#" class="bottom-nav-item" data-bs-toggle="offcanvas" data-bs-target="#adminMoreOffcanvas">
        <i class="fas fa-ellipsis-h"></i> More
    </a>
</div>

<!-- Admin More Offcanvas -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="adminMoreOffcanvas" style="border-radius: 20px 20px 0 0; height: auto;">
  <div class="offcanvas-header pb-0 border-0">
    <h6 class="offcanvas-title fw-800 text-primary">Institution Hub</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="row g-3 text-center pb-4">
        <div class="col-4">
            <a href="profile.php" class="text-decoration-none">
                <div class="bg-primary bg-opacity-10 text-primary rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-school"></i></div>
                <div class="extra-small fw-700 text-dark">Profile</div>
            </a>
        </div>
        <div class="col-4">
            <a href="academics.php" class="text-decoration-none">
                <div class="bg-success bg-opacity-10 text-success rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-book-open"></i></div>
                <div class="extra-small fw-700 text-dark">Academics</div>
            </a>
        </div>
        <div class="col-4">
            <a href="question_builder.php" class="text-decoration-none">
                <div class="bg-info bg-opacity-10 text-info rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-brain"></i></div>
                <div class="extra-small fw-700 text-dark">Builder</div>
            </a>
        </div>
        <div class="col-4">
            <a href="pricing.php" class="text-decoration-none">
                <div class="bg-warning bg-opacity-10 text-warning rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-bolt"></i></div>
                <div class="extra-small fw-700 text-dark">Top Up</div>
            </a>
        </div>
        <?php if (hasFeature('COURSE_CURRICULUM')): ?>
        <div class="col-4">
            <a href="curriculum.php" class="text-decoration-none">
                <div class="bg-premium-gold bg-opacity-10 text-premium-gold rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-scroll"></i></div>
                <div class="extra-small fw-700 text-dark">Curriculum</div>
            </a>
        </div>
        <?php endif; ?>
        <div class="col-4">
             <a href="../logout.php" class="text-decoration-none">
                <div class="bg-danger bg-opacity-10 text-danger rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-power-off"></i></div>
                <div class="extra-small fw-700 text-dark">Logout</div>
            </a>
        </div>
    </div>
  </div>
</div>
