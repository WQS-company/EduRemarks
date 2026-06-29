<?php
// admin/pricing.php - School Owner Top-up Portal
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    die("Unauthorized Access Node. Role detected: " . htmlspecialchars($role)); 
}

// Fetch available packages defined by Super Admin
$stmt = $pdo->query("SELECT * FROM pricing_packages ORDER BY price_naira ASC");
$packages = $stmt->fetchAll();

// Fetch school's current credits for display refinement
$stmt = $pdo->prepare("SELECT credits FROM schools WHERE id = ?");
$stmt->execute([$active_school_id]);
$current_credits = $stmt->fetchColumn();

// Paystack Public Key
$paystack = require_once '../config/paystack.php';
$public_key = $paystack['public_key'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Top-up | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .pricing-card { border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: white; overflow: hidden; position: relative; }
        .pricing-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); border-color: #1F3C88; }
        .pricing-card.featured { border: 2px solid #F4B400; }
        .pricing-card.featured::before { content: 'BEST VALUE'; position: absolute; top: 15px; right: -30px; background: #F4B400; color: #1F3C88; font-size: 0.65rem; font-weight: 900; padding: 5px 40px; transform: rotate(45deg); }
        .icon-shield { width: 60px; height: 60px; background: rgba(31, 60, 136, 0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; transition: 0.3s; }
        .pricing-card:hover .icon-shield { background: #1F3C88; color: white; }
        
        @media (max-width: 768px) {
            .pricing-card { padding: 30px !important; }
            .h1 { font-size: 2rem !important; }
        }
    </style>
</head>
<body class="bg-light">

    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <?php include '../includes/dashboard_top_nav.php'; ?>

        <div class="container-fluid p-3 p-md-4">
            <div class="row align-items-center mb-5 mt-2">
                <div class="col-8">
                    <h4 class="fw-900 mb-0">Resource Top-up</h4>
                    <p class="text-muted small mb-0">Fuel your academic node</p>
                </div>
                <div class="col-4 text-end">
                    <div class="small text-muted fw-bold opacity-50 uppercase tracking-1" style="font-size: 0.6rem;">Balance</div>
                    <div class="h4 fw-900 text-blue mb-0">
                        <?php echo number_format($current_credits); ?> <i class="fas fa-bolt text-warning" style="font-size: 0.8rem;"></i>
                    </div>
                </div>
            </div>

            <!-- Promotion / Advert Banner -->
            <div class="alert bg-premium-gold bg-opacity-10 border-premium-gold border-opacity-25 rounded-4 p-4 mb-5 d-flex align-items-center animate-fade-in">
                <div class="icon-box bg-premium-gold text-blue rounded-circle p-3 me-3 d-none d-sm-flex shadow-sm">
                    <i class="fas fa-star h4 mb-0"></i>
                </div>
                <div>
                    <h6 class="fw-800 text-blue mb-1">Scale Your Impact!</h6>
                    <p class="mb-0 small text-blue opacity-75">Maintain <strong>5,000+ credits</strong> to unlock Unlimited Instant Result Generation and Premium Printing.</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach($packages as $index => $pkg): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="pricing-card p-5 h-100 <?php echo ($index == 1) ? 'featured' : ''; ?>">
                        <div class="icon-shield text-primary">
                            <i class="fas fa-cubes h3 mb-0"></i>
                        </div>
                        <h5 class="fw-900 mb-1"><?php echo htmlspecialchars($pkg['name']); ?></h5>
                        <p class="text-muted small mb-4">Tier-level operational resource node</p>
                        
                        <div class="my-4">
                            <span class="h1 fw-900 text-blue">₦<?php echo number_format($pkg['price_naira']); ?></span>
                        </div>

                        <ul class="list-unstyled mb-5">
                            <li class="mb-3 d-flex align-items-center small fw-bold text-dark">
                                <i class="fas fa-check-circle text-success me-3"></i> 
                                <?php echo number_format($pkg['credits']); ?> Operational Credits
                            </li>
                            <li class="mb-3 d-flex align-items-center small opacity-75">
                                <i class="fas fa-check-circle text-success me-3"></i> Institutional Lifecycle Management
                            </li>
                            <li class="mb-3 d-flex align-items-center small opacity-75">
                                <i class="fas fa-check-circle text-success me-3"></i> Secure Transaction Node
                            </li>
                        </ul>

                        <button class="btn btn-primary w-100 rounded-pill py-3 fw-900 shadow-sm hover-scale" 
                                onclick="payWithPaystack('<?php echo $user_email; ?>', <?php echo $pkg['price_naira']; ?>, <?php echo $pkg['id']; ?>, '<?php echo addslashes($pkg['name']); ?>')">
                            ACQUIRE CREDITS
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 text-center p-5 rounded-4 border-dashed bg-white">
                <div class="text-muted small mb-2"><i class="fas fa-lock me-2"></i> Powered by Paystack Secured Gateways</div>
                <div class="tiny-text opacity-50">Direct Bank Transfer • Credit/Debit Card • USSD supported</div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- INSTITUTIONAL BILLING REQUEST - Period-Based Subscription -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <?php
            $pending_billing = null;
            $billing_active = false;
            try {
                $b_stmt = $pdo->prepare("SELECT * FROM billing_requests WHERE school_id = ? AND status = 'pending' LIMIT 1");
                $b_stmt->execute([$active_school_id]);
                $pending_billing = $b_stmt->fetch();

                $b_stmt = $pdo->prepare("SELECT billing_mode, subscription_active, subscription_type, subscription_end, subscription_price FROM schools WHERE id = ?");
                $b_stmt->execute([$active_school_id]);
                $billing_data = $b_stmt->fetch();
                if ($billing_data && $billing_data['billing_mode'] === 'subscription' && $billing_data['subscription_active']) {
                    $billing_active = true;
                }
            } catch (Exception $e) {}
            ?>

            <div class="mt-5 p-0 rounded-4 overflow-hidden shadow-lg" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);">
                <div class="p-4 p-md-5 position-relative">
                    <!-- Decorative -->
                    <div class="position-absolute top-0 end-0 p-3" style="opacity: 0.05;">
                        <i class="fas fa-university" style="font-size: 8rem; color: #fff;"></i>
                    </div>

                    <div class="row align-items-center position-relative">
                        <div class="col-lg-7 mb-4 mb-lg-0">
                            <span class="badge rounded-pill px-3 py-2 fw-800 shadow-sm mb-3" style="background: #F4B400; color: #1e3a5f; font-size: 0.6rem; letter-spacing: 1px;">
                                <i class="fas fa-star me-1"></i> ENTERPRISE ALTERNATIVE
                            </span>
                            <h4 class="fw-900 text-white mb-2" style="letter-spacing: -0.3px;">Institutional Period-Based Billing</h4>
                            <p class="text-white small mb-3" style="opacity: 0.8; line-height: 1.7;">
                                Skip credit top-ups entirely. Request a <strong>fixed-period subscription</strong> to unlock 
                                <strong>unlimited operations</strong> — report sheets, CBT exams, SMS, and more — at an agreed institutional price between you and the Super Admin.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge rounded-pill px-3 py-2 text-white" style="background: rgba(255,255,255,0.1); font-size: 0.65rem;">
                                    <i class="fas fa-infinity me-1 text-warning"></i> Unlimited Ops
                                </span>
                                <span class="badge rounded-pill px-3 py-2 text-white" style="background: rgba(255,255,255,0.1); font-size: 0.65rem;">
                                    <i class="fas fa-handshake me-1 text-warning"></i> Agreed Pricing
                                </span>
                                <span class="badge rounded-pill px-3 py-2 text-white" style="background: rgba(255,255,255,0.1); font-size: 0.65rem;">
                                    <i class="fas fa-shield-alt me-1 text-warning"></i> No Deductions
                                </span>
                            </div>
                        </div>
                        <div class="col-lg-5 text-lg-end">
                            <?php if($billing_active): ?>
                                <div class="p-4 rounded-4 text-center" style="background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3);">
                                    <i class="fas fa-check-circle text-success h3 mb-2"></i>
                                    <div class="text-white fw-900 small mb-1">INSTITUTIONAL BILLING ACTIVE</div>
                                    <div class="text-white small" style="opacity: 0.7;">
                                        <?php echo htmlspecialchars($billing_data['subscription_type'] ?? ''); ?> • Expires <?php echo date('M d, Y', strtotime($billing_data['subscription_end'] ?? 'now')); ?>
                                    </div>
                                    <div class="text-warning fw-900 mt-2">₦<?php echo number_format($billing_data['subscription_price'] ?? 0); ?></div>
                                </div>
                            <?php elseif($pending_billing): ?>
                                <div class="p-4 rounded-4 text-center" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);">
                                    <i class="fas fa-clock text-warning h3 mb-2"></i>
                                    <div class="text-white fw-900 small mb-1">REQUEST PENDING REVIEW</div>
                                    <div class="text-white small" style="opacity: 0.7;">
                                        <?php echo htmlspecialchars($pending_billing['requested_plan']); ?> • <?php echo htmlspecialchars($pending_billing['duration']); ?>
                                    </div>
                                    <div class="text-warning extra-small fw-800 mt-2">Submitted <?php echo date('M d, Y', strtotime($pending_billing['request_date'])); ?></div>
                                </div>
                            <?php else: ?>
                                <button type="button" class="btn w-100 rounded-pill py-3 fw-900 shadow-lg hover-scale" style="background: #F4B400; color: #1e3a5f; font-size: 0.85rem; letter-spacing: 1px;" data-bs-toggle="modal" data-bs-target="#adminBillingModal">
                                    <i class="fas fa-paper-plane me-2"></i> REQUEST INSTITUTIONAL PLAN
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Billing Request Modal -->
            <?php if(!$billing_active && !$pending_billing): ?>
            <div class="modal fade" id="adminBillingModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
                        <form id="adminBillingForm">
                            <div class="modal-header border-bottom-0 p-4 pb-2" style="background: linear-gradient(135deg, #0f172a, #1e3a8a); color: white;">
                                <div>
                                    <h5 class="modal-title fw-900 text-white mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Request Institutional Billing</h5>
                                    <p class="small mb-0 opacity-75">Our team will review and contact you within 24 hours</p>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-800 text-muted uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-layer-group me-1 text-primary"></i> Select Plan
                                        </label>
                                        <select class="form-select rounded-3 px-4 py-3 fw-600 border-light shadow-sm" name="requested_plan" required>
                                            <option value="1 Year Unlimited">1 Year Institutional Unlimited</option>
                                            <option value="1 Session Unlimited">1 Session Institutional Unlimited</option>
                                            <option value="1 Term Unlimited">1 Term Institutional Unlimited</option>
                                            <option value="Term Plan"><?php echo get_label('Term'); ?> Based Plan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-800 text-muted uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-calendar-alt me-1 text-primary"></i> Duration
                                        </label>
                                        <input type="text" class="form-control rounded-3 px-4 py-3 fw-600 border-light shadow-sm" name="duration" placeholder="e.g. 2025/2026 Session" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-800 text-muted uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-comment-dots me-1 text-primary"></i> Notes
                                        </label>
                                        <textarea class="form-control rounded-3 p-4 fw-600 border-light shadow-sm" name="notes" rows="3" placeholder="Institution size, expected usage, special requirements..."></textarea>
                                    </div>
                                </div>
                                <div class="mt-4 p-3 rounded-3 d-flex align-items-center gap-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                    <i class="fas fa-info-circle text-success"></i>
                                    <span class="small text-success fw-600">Upon approval, all credit-based operations become unlimited for the agreed period.</span>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 p-4 pt-2 d-flex gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-700" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-900 shadow-sm">
                                    <i class="fas fa-paper-plane me-2"></i> SEND REQUEST
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            $(document).ready(function() {
                $('#adminBillingForm').on('submit', function(e) {
                    e.preventDefault();
                    const btn = $(this).find('button[type="submit"]');
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

                    $.ajax({
                        url: '../ajax/submit_billing_request.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(resp) {
                            if (resp.success) {
                                Swal.fire({icon: 'success', title: 'Request Submitted!', text: resp.message, confirmButtonColor: '#1e40af'}).then(() => location.reload());
                            } else {
                                Swal.fire({icon: 'error', title: 'Failed', text: resp.message, confirmButtonColor: '#dc2626'});
                                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i> SEND REQUEST');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Network error. Please try again.', 'error');
                            btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i> SEND REQUEST');
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>
        </div>
            <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <?php include '../includes/spinner.php'; ?>

    <!-- Paystack Inline JS -->
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        function payWithPaystack(email, amount, packageId, packageName) {
            // Amount must be in kobo for Paystack (amount * 100)
            const amountKobo = amount * 100;
            const ref = "ERP-" + Math.floor((Math.random() * 1000000000) + 1);

            let handler = PaystackPop.setup({
                key: '<?php echo $public_key; ?>',
                email: email,
                amount: amountKobo,
                currency: "NGN",
                ref: ref,
                metadata: {
                    package_id: packageId,
                    school_id: <?php echo $active_school_id; ?>,
                    package_name: packageName
                },
                callback: function(response) {
                    // Success! Reference: response.reference
                    Spinner.show('Verifying institutional payment...');
                    $.post('../ajax/verify_payment.php', {
                        reference: response.reference,
                        package_id: packageId
                    }, function(res) {
                        Spinner.hide();
                        if(res.success) {
                            window.location.href = 'billing.php?status=success&ref=' + response.reference;
                        } else {
                            alert("Verification error: " + res.message);
                        }
                    }, 'json');
                },
                onClose: function() {
                    alert('Transaction aborted by node operator.');
                }
            });

            handler.openIframe();
        }
    </script>
</body>
</html>
