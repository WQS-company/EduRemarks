<?php
// includes/sa_bottom_nav.php - Consistent Mobile Experience for Super Admin
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Bottom Nav for Mobile -->
<div class="sa-bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i> Dashboard
    </a>
    <a href="schools.php" class="bottom-nav-item <?php echo ($current_page == 'schools.php') ? 'active' : ''; ?>">
        <i class="fas fa-school"></i> Schools
    </a>
    <a href="billing.php" class="bottom-nav-item <?php echo ($current_page == 'billing.php') ? 'active' : ''; ?>">
        <i class="fas fa-wallet"></i> Billing
    </a>
    <a href="requests.php" class="bottom-nav-item <?php echo ($current_page == 'requests.php') ? 'active' : ''; ?>">
        <i class="fas fa-headset"></i> Support
    </a>
    <a href="#" class="bottom-nav-item" data-bs-toggle="offcanvas" data-bs-target="#mobileMoreOffcanvas">
        <i class="fas fa-ellipsis-h"></i> More
    </a>
</div>

<!-- Simple Mobile More Offcanvas -->
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="mobileMoreOffcanvas" style="border-radius: 20px 20px 0 0; height: auto;">
  <div class="offcanvas-header pb-0 px-4 pt-4">
    <h6 class="offcanvas-title fw-800 text-blue">System Operations</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-4">
    <div class="row g-4 text-center pb-2">
        <div class="col-4">
            <a href="content.php" class="text-decoration-none">
                <div class="bg-primary bg-opacity-10 text-primary rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;"><i class="fas fa-globe"></i></div>
                <div class="extra-small fw-700 text-dark">Landing</div>
            </a>
        </div>
        <div class="col-4">
            <a href="blog.php" class="text-decoration-none">
                <div class="bg-success bg-opacity-10 text-success rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;"><i class="fas fa-newspaper"></i></div>
                <div class="extra-small fw-700 text-dark">Blog</div>
            </a>
        </div>
        <div class="col-4">
            <a href="sms_campaigns.php" class="text-decoration-none">
                <div class="bg-info bg-opacity-10 text-info rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;"><i class="fas fa-paper-plane"></i></div>
                <div class="extra-small fw-700 text-dark">SMS</div>
            </a>
        </div>
        <div class="col-4">
            <a href="pricing.php" class="text-decoration-none">
                <div class="bg-warning bg-opacity-10 text-warning rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;"><i class="fas fa-tags"></i></div>
                <div class="extra-small fw-700 text-dark">Pricing</div>
            </a>
        </div>
        <div class="col-4">
             <a href="../logout.php" class="text-decoration-none">
                <div class="bg-danger bg-opacity-10 text-danger rounded-4 p-3 mb-2 mx-auto d-flex align-items-center justify-content-center" style="width: 55px; height: 55px;"><i class="fas fa-power-off"></i></div>
                <div class="extra-small fw-700 text-dark">Logout</div>
            </a>
        </div>
    </div>
  </div>
</div>
