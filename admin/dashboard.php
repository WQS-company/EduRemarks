<?php
// admin/dashboard.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

// Stats for Active School
$stats = [
    'pending_staff' => 0,
    'active_staff' => 0,
    'total_students' => 0
];

if ($active_school_id) {
    // Count Staff
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_details WHERE school_id = ? AND status = 'pending'");
    $stmt->execute([$active_school_id]);
    $stats['pending_staff'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_details WHERE school_id = ? AND status = 'active'");
    $stmt->execute([$active_school_id]);
    $stats['active_staff'] = $stmt->fetchColumn();

    // Count Students
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
    $stmt->execute([$active_school_id]);
    $stats['total_students'] = $stmt->fetchColumn();

    // Fetch Platform Campaigns
    $campaign_stmt = $pdo->prepare("SELECT * FROM platform_campaigns WHERE target_school_ids IS NULL OR FIND_IN_SET(?, target_school_ids) ORDER BY created_at DESC LIMIT 3");
    $campaign_stmt->execute([$active_school_id]);
    $campaigns = $campaign_stmt->fetchAll();

    // FETCH GIFT NOTIFICATIONS
    $gift_stmt = $pdo->prepare("SELECT * FROM platform_notifications WHERE school_id = ? AND is_read = 0 AND type = 'gift' ORDER BY created_at DESC LIMIT 1");
    $gift_stmt->execute([$active_school_id]);
    $pending_gift = $gift_stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">

    <?php include '../includes/spinner.php'; ?>

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
            
            <!-- INSTITUTIONAL CREDIT WALLET -->
            <?php if($active_school): ?>
            <div class="row mb-5 reveal-fade-up">
                <div class="col-12 col-xl-8">
                    <div class="glass-card p-4 border-0 shadow-premium bg-white overflow-hidden position-relative" style="border-radius: 20px;">
                        <!-- Decal SVG / Icon -->
                        <div class="position-absolute top-0 end-0 p-4 opacity-05">
                            <i class="fas fa-wallet fa-9x text-dark" style="transform: rotate(-15deg) translate(30px, -30px);"></i>
                        </div>
                        
                        <div class="d-flex flex-mobile-column justify-content-between align-items-center position-relative">
                            <div class="d-flex align-items-center gap-4">
                                <div class="wallet-icon-box bg-opacity-05 text-dark rounded-4 d-flex align-items-center justify-content-center border-fine" style="width: 70px; height: 70px;">
                                    <i class="fas <?php echo ($active_school['billing_mode'] === 'subscription' && $active_school['subscription_active']) ? 'fa-calendar-check text-success' : 'fa-credit-card'; ?> h3 mb-0 opacity-75"></i>
                                </div>
                                <div>
                                    <?php if($active_school['billing_mode'] === 'subscription' && $active_school['subscription_active']): ?>
                                        <div class="tiny-text fw-800 text-success uppercase tracking-2 mb-1">Active Subscription</div>
                                        <div class="d-flex align-items-baseline gap-2">
                                            <div class="h2 fw-900 text-dark mb-0 font-poppins" style="letter-spacing: -1px;"><?php echo htmlspecialchars($active_school['subscription_type'] ?? 'Institutional Plan'); ?></div>
                                        </div>
                                        <div class="extra-small text-muted fw-bold mt-2">
                                            Valid until: <span class="text-dark"><?php echo date('M d, Y', strtotime($active_school['subscription_end'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="tiny-text fw-800 text-primary uppercase tracking-2 mb-1">Credits Balance</div>
                                        <div class="d-flex align-items-baseline gap-2">
                                            <div class="h1 fw-900 text-dark mb-0 font-poppins" style="letter-spacing: -2px;"><?php echo number_format($active_school['credits']); ?></div>
                                            <div class="h6 fw-800 text-muted opacity-50 mb-0">TOTAL</div>
                                        </div>
                                        <div class="extra-small text-success fw-bold mt-2 d-flex align-items-center gap-2">
                                            <div class="pulse-green"></div> 
                                            Institutional Node Active
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end w-mobile-100 mt-mobile-4">
                                <?php if($active_school['billing_mode'] === 'subscription' && $active_school['subscription_active']): ?>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="btn btn-success disabled rounded-pill px-4 py-2 fw-800 shadow-sm opacity-75" style="font-size: 0.85rem;">
                                            <i class="fas fa-check-circle me-2"></i> UNLIMITED ACCESS
                                        </div>
                                        <a href="print_agreement.php?id=<?php echo $active_school_id; ?>" target="_blank" class="btn btn-outline-success rounded-pill px-4 py-2 fw-800 shadow-sm" style="font-size: 0.85rem;">
                                            <i class="fas fa-print me-2"></i> PRINT AGREEMENT
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <a href="pricing.php" class="btn btn-primary rounded-pill px-4 py-2 fw-800 shadow-lg hover-scale transition-all w-mobile-100 text-nowrap" style="background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); border: none; font-size: 0.85rem;">
                                        <i class="fas fa-plus-circle me-2"></i> TOP UP BALANCE
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Secondary Info Card -->
                <div class="col-12 col-xl-4 mt-4 mt-xl-0">
                    <div class="glass-card p-4 border-0 shadow-premium h-100 d-flex align-items-center" style="border-radius: 20px; background: #fff;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box-sm bg-opacity-05 text-primary rounded-circle border-fine" style="width: 50px; height: 50px; min-width: 50px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-fingerprint h4 mb-0 opacity-75"></i>
                            </div>
                            <div>
                                <h6 class="fw-800 mb-0 text-dark uppercase tracking-1" style="font-size: 0.75rem;">Institutional Identity</h6>
                                <p class="small text-muted mb-0 font-poppins fw-600"><?php echo $active_school['unique_id']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card border-0 shadow-sm p-4 h-100" style="border-radius: 20px;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon-box-sm bg-primary bg-opacity-10 text-primary rounded-3">
                                <i class="fas fa-university"></i>
                            </div>
                            <span class="badge bg-soft-primary text-primary tiny-text rounded-pill">Active</span>
                        </div>
                        <div class="h3 fw-900 text-dark mb-1"><?php echo count($user_schools); ?></div>
                        <div class="text-muted extra-small fw-600 uppercase tracking-1">Total Schools</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card border-0 shadow-sm p-4 h-100" style="border-radius: 20px;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon-box-sm bg-success bg-opacity-10 text-success rounded-3">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                        <div class="h3 fw-900 text-dark mb-1"><?php echo $stats['total_students']; ?></div>
                        <div class="text-muted extra-small fw-600 uppercase tracking-1">Total Students</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card border-0 shadow-sm p-4 h-100" style="border-radius: 20px;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon-box-sm bg-warning bg-opacity-10 text-warning rounded-3">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="h3 fw-900 text-dark mb-1"><?php echo $stats['active_staff']; ?></div>
                        <div class="text-muted extra-small fw-600 uppercase tracking-1">Active <?php echo get_label('Staff'); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card border-0 shadow-sm p-4 h-100" style="border-radius: 20px;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="icon-box-sm bg-danger bg-opacity-10 text-danger rounded-3">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <?php if($stats['pending_staff'] > 0): ?>
                            <span class="pulse-red-glow"></span>
                            <?php endif; ?>
                        </div>
                        <div class="h3 fw-900 text-dark mb-1"><?php echo $stats['pending_staff']; ?></div>
                        <div class="text-muted extra-small fw-600 uppercase tracking-1">Pending Review</div>
                    </div>
                </div>
            </div>


            <!-- GIFT RECOGNITION OVERLAY -->
            <?php if ($pending_gift): ?>
            <div class="row mb-4" id="giftCelebrationContainer">
                <div class="col-12">
                    <div class="glass-card p-4 overflow-hidden border-0 shadow-lg position-relative" style="background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); border-radius: 24px;">
                        <!-- Abstract Celebration Graphics -->
                        <div class="position-absolute top-0 end-0 p-4 opacity-15" style="transform: rotate(15deg) scale(1.5);">
                            <i class="fas fa-gift fa-6x text-white"></i>
                        </div>
                        <div class="d-flex flex-mobile-column align-items-center gap-3 gap-md-4 position-relative">
                            <div class="icon-box bg-white text-primary rounded-circle flex-shrink-0 shadow-lg" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-magic h3 mb-0"></i>
                            </div>
                            <div class="flex-grow-1 text-mobile-center">
                                <h5 class="fw-900 mb-1 text-white uppercase tracking-1">Institutional Reward Unlocked</h5>
                                <div class="text-white fw-500 opacity-90"><?php echo htmlspecialchars($pending_gift['message']); ?></div>
                            </div>
                            <div class="text-end w-mobile-100">
                                <button class="btn btn-white rounded-pill px-5 py-2 fw-800 text-primary hover-scale w-mobile-100 shadow-sm" onclick="dismissGiftNotification(<?php echo $pending_gift['id']; ?>)">
                                    CLAIM GIFT <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Campaign / Message Feed -->
            <?php if (!empty($campaigns)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="glass-card p-4 bg-gradient-brand text-white border-0 shadow-lg position-relative overflow-hidden" style="border-radius: 20px;">
                        <div class="d-flex flex-mobile-column align-items-center gap-3 gap-md-4 reveal-fade-up position-relative">
                            <div class="icon-box bg-white bg-opacity-20 rounded-circle flex-shrink-0" style="width: 60px; height: 60px; min-width: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-rocket h4 mb-0"></i>
                            </div>
                            <div class="flex-grow-1 text-mobile-center">
                                <div class="extra-small fw-800 uppercase tracking-2 opacity-75 mb-1">System Intelligence Dispatch</div>
                                <h6 class="fw-700 mb-0"><?php echo htmlspecialchars($campaigns[0]['message']); ?></h6>
                            </div>
                            <div class="text-end d-none d-md-block">
                                <span class="badge bg-white bg-opacity-10 py-2 px-3 rounded-pill tiny-text border border-white border-opacity-10 fw-800 uppercase">Live Broadcast</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="glass-card p-4 h-100 d-flex flex-column" style="border-radius: 24px;">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h5 class="fw-900 mb-0 text-dark uppercase tracking-1">Institutional Health</h5>
                            <i class="fas fa-chart-pie text-muted opacity-50"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center p-4 rounded-4 bg-light bg-opacity-30 border border-white mb-3 transition-all hover-scale shadow-hover-sm">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-box-sm bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 40px; height: 40px;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="fw-800 small text-dark"><?php echo get_label('Staff'); ?> Roster</div>
                                        <div class="extra-small text-muted">Currently Active</div>
                                    </div>
                                </div>
                                <span class="h5 fw-900 text-primary mb-0"><?php echo $stats['active_staff']; ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center p-4 rounded-4 bg-light bg-opacity-30 border border-white mb-3 transition-all hover-scale shadow-hover-sm">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-box-sm bg-warning bg-opacity-10 text-warning rounded-circle" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                    <div>
                                        <div class="fw-800 small text-grey">Pending Validation</div>
                                        <div class="extra-small text-muted">Awaiting Review</div>
                                    </div>
                                </div>
                                <span class="h5 fw-900 text-warning mb-0"><?php echo $stats['pending_staff']; ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center p-4 rounded-4 bg-light bg-opacity-30 border border-white transition-all hover-scale shadow-hover-sm">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon-box-sm bg-info bg-opacity-10 text-info rounded-circle" style="width: 40px; height: 40px;">
                                        <i class="fas fa-id-badge"></i>
                                    </div>
                                    <div>
                                        <div class="fw-800 small text-dark">ID Card Node</div>
                                        <div class="extra-small text-muted">Drafts & Issued</div>
                                    </div>
                                </div>
                                <span class="h5 fw-900 text-info mb-0"><?php echo count($user_schools); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="glass-card p-4 h-100 d-flex flex-column" style="border-radius: 24px; background: linear-gradient(135deg, #ffffff 0%, #f9faff 100%);">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h5 class="fw-900 mb-0 text-dark uppercase tracking-1">Platform Guidance</h5>
                            <i class="fas fa-lightbulb text-premium-gold shadow-glow"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="p-4 bg-white rounded-4 border border-opacity-10 mb-4 text-start position-relative overflow-hidden shadow-sm" style="border-left: 5px solid #F4B400 !important;">
                                <!-- Rocket Icon Fixed with Opacity and Z-Index -->
                                <div class="position-absolute top-0 end-0 p-3 opacity-05" style="z-index: 0; pointer-events: none;">
                                    <i class="fas fa-rocket fa-6x" style="transform: rotate(15deg) translate(20px, -10px);"></i>
                                </div>
                                <div class="d-flex align-items-center gap-3 mb-3 position-relative" style="z-index: 1;">
                                    <div class="icon-box bg-premium-gold bg-opacity-10 text-premium-gold rounded-circle flex-shrink-0" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-magic small mb-0"></i>
                                    </div>
                                    <h6 class="fw-800 mb-0 text-dark">Institutional Efficiency</h6>
                                </div>
                                <div class="position-relative" style="z-index: 1;">
                                    <p class="small text-muted mb-0" style="line-height: 1.7; font-weight: 500;">
                                        Your credits unlock high-performance result analysis, strategic SMS dispatch, and world-class digital ID orchestration. 
                                        <span class="text-primary fw-700">Maximize your institution's digital footprint</span> with EduRemarks proprietary algorithms.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <a href="pricing.php" class="btn btn-primary rounded-pill w-100 fw-900 py-3 shadow-lg hover-scale uppercase tracking-1 mt-auto" style="background: var(--primary-blue); border: none;">
                            EXPLORE ALL TIER FEATURES <i class="fas fa-chevron-right ms-2 small"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>



    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function dismissGiftNotification(id) {
            $.post('../ajax/dismiss_notification.php', { id }, function(res) {
                if(res.success) {
                    $('#giftCelebrationContainer').fadeOut(500, function() { $(this).remove(); });
                }
            }, 'json');
        }
    </script>
</body>
</html>
