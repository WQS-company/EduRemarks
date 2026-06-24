<?php
$pageTitle = "Pricing";
include 'includes/header.php';

// Fetch Pricing Packages from DB
try {
    $packages = $pdo->query("SELECT * FROM pricing_packages ORDER BY price_naira ASC")->fetchAll();
} catch (Exception $e) {
    $packages = [];
}

// Billing Request Logic
$school_id = $_SESSION['school_id'] ?? null;
$pending_req = null;
$is_subscribed = false;
$is_owner = (isset($_SESSION['role']) && $_SESSION['role'] === 'owner');

if ($school_id && $is_owner) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM billing_requests WHERE school_id = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$school_id]);
        $pending_req = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT billing_mode, subscription_active FROM schools WHERE id = ?");
        $stmt->execute([$school_id]);
        $sch_data = $stmt->fetch();
        if ($sch_data && $sch_data['billing_mode'] === 'subscription' && $sch_data['subscription_active']) {
            $is_subscribed = true;
        }
    } catch (Exception $e) {}
}

// Feature list per package tier
$tier_features = [
    0 => ['CBT Result Generation', 'Institutional Report Processing', 'Paystick & SMS Automation'],
    1 => ['CBT Result Generation', 'Institutional Report Processing', 'Paystick & SMS Automation', 'Priority Support Channel'],
    2 => ['CBT Result Generation', 'Institutional Report Processing', 'Paystick & SMS Automation', 'Priority Support Channel', 'Dedicated Account Manager'],
];
$tier_labels = ['Academic operational node', 'Academic operational node', 'Academic operational node'];
$tier_badges = [null, 'BEST VALUE', null];
$tier_icons  = ['fa-seedling', 'fa-gem', 'fa-crown'];
$tier_colors = [
    ['card_border' => '#e2e8f0', 'accent' => '#1e40af', 'btn_bg' => '#1e40af', 'btn_text' => '#fff'],
    ['card_border' => '#F4B400', 'accent' => '#1e40af', 'btn_bg' => '#F4B400', 'btn_text' => '#1e3a5f'],
    ['card_border' => '#e2e8f0', 'accent' => '#1e40af', 'btn_bg' => '#1e40af', 'btn_text' => '#fff'],
];
?>

    <!-- Page Header -->
    <section class="hero-section reveal reveal-up" style="padding: 100px 0 60px;">
        <div class="container text-center">
            <h1 class="hero-title" style="font-size: 2.8rem;">Flexible Pricing for Every School</h1>
            <p class="hero-subtitle">Scalable plans built to grow with your institution.</p>
        </div>
    </section>

    <!-- Pricing Cards Section -->
    <section class="reveal reveal-up" style="padding: 0 0 60px;">
        <div class="container">
            <div class="row g-4 justify-content-center mb-5">
                <?php foreach ($packages as $i => $pkg):
                    $idx = min($i, 2);
                    $badge = $tier_badges[$idx] ?? null;
                    $features = $tier_features[$idx] ?? $tier_features[0];
                    $colors = $tier_colors[$idx] ?? $tier_colors[0];
                    $icon = $tier_icons[$idx] ?? 'fa-cubes';
                    $label = $tier_labels[$idx] ?? '';
                    $is_featured = ($badge === 'BEST VALUE');
                ?>
                <div class="col-lg-3 col-md-6">
                    <div class="glass-card p-0 h-100 position-relative overflow-hidden border-0 shadow-lg" style="border-radius: 24px; border-top: 4px solid <?php echo $is_featured ? '#F4B400' : 'transparent'; ?> !important; transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);">
                        <?php if ($badge): ?>
                            <div class="text-center py-2" style="background: #F4B400; color: #1e3a5f; font-weight: 900; font-size: 0.7rem; letter-spacing: 2px;"><?php echo $badge; ?></div>
                        <?php endif; ?>
                        <div class="p-4 p-xl-5 text-center">
                            <h4 class="fw-900 mb-1" style="color: #1e3a5f;"><?php echo htmlspecialchars($pkg['name']); ?></h4>
                            <p class="text-muted small mb-4"><?php echo $label; ?></p>

                            <div class="mb-4">
                                <span class="fw-900" style="font-size: 2.2rem; color: #1e3a5f;">₦<?php echo number_format($pkg['price_naira']); ?></span>
                            </div>

                            <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                                <i class="fas fa-bolt text-warning"></i>
                                <span class="fw-800 small"><?php echo number_format($pkg['credits']); ?> Operational Credits</span>
                            </div>

                            <ul class="list-unstyled text-start mb-5 px-3">
                                <?php foreach ($features as $feat): ?>
                                <li class="mb-3 d-flex align-items-center small">
                                    <i class="fas fa-check-circle text-success me-3 flex-shrink-0"></i>
                                    <span><?php echo $feat; ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>

                            <a href="signup.php" class="btn w-100 rounded-pill py-3 fw-900 shadow-sm hover-scale" style="background: <?php echo $colors['btn_bg']; ?>; color: <?php echo $colors['btn_text']; ?>; font-size: 0.85rem; letter-spacing: 1px;">
                                <i class="fas fa-rocket me-2"></i> DEPLOY NOW
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- ═══════ CUSTOM BILLING CARD ═══════ -->
                <div class="col-lg-3 col-md-6">
                    <div class="h-100 position-relative overflow-hidden border-0 shadow-lg" style="border-radius: 24px; background: linear-gradient(165deg, #0f172a 0%, #1e3a8a 100%); transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);">
                        <div class="text-center py-2" style="background: linear-gradient(90deg, #F4B400, #f59e0b); color: #1e3a5f; font-weight: 900; font-size: 0.7rem; letter-spacing: 2px;">ENTERPRISE</div>
                        <!-- Decorative watermark -->
                        <div class="position-absolute" style="bottom: -20px; right: -20px; opacity: 0.04;">
                            <i class="fas fa-university" style="font-size: 10rem; color: #fff;"></i>
                        </div>
                        <div class="p-4 p-xl-5 text-center position-relative">
                            <h4 class="fw-900 mb-1 text-white">Custom Billing</h4>
                            <p class="small mb-4" style="color: rgba(255,255,255,0.6);">Institutional subscription node</p>

                            <div class="mb-4">
                                <span class="fw-900 text-white" style="font-size: 2.2rem;">Custom</span>
                                <div class="small mt-1" style="color: rgba(255,255,255,0.5);">Agreed pricing</div>
                            </div>

                            <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                                <i class="fas fa-infinity text-warning"></i>
                                <span class="fw-800 small text-white">Unlimited Operations</span>
                            </div>

                            <ul class="list-unstyled text-start mb-5 px-3">
                                <li class="mb-3 d-flex align-items-center small text-white">
                                    <i class="fas fa-check-circle text-warning me-3 flex-shrink-0"></i>
                                    <span>No Credit Deductions</span>
                                </li>
                                <li class="mb-3 d-flex align-items-center small text-white">
                                    <i class="fas fa-check-circle text-warning me-3 flex-shrink-0"></i>
                                    <span>Period-Based (Term/Session/Year)</span>
                                </li>
                                <li class="mb-3 d-flex align-items-center small text-white">
                                    <i class="fas fa-check-circle text-warning me-3 flex-shrink-0"></i>
                                    <span>Unlimited Reports & CBT</span>
                                </li>
                                <li class="mb-3 d-flex align-items-center small text-white">
                                    <i class="fas fa-check-circle text-warning me-3 flex-shrink-0"></i>
                                    <span>Negotiated Institutional Price</span>
                                </li>
                                <li class="mb-3 d-flex align-items-center small text-white">
                                    <i class="fas fa-check-circle text-warning me-3 flex-shrink-0"></i>
                                    <span>Priority Enterprise Support</span>
                                </li>
                            </ul>

                            <?php if($is_owner): ?>
                                <?php if($is_subscribed): ?>
                                    <div class="rounded-pill py-3 px-4 fw-900 text-center" style="background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.4); color: #34d399; font-size: 0.85rem;">
                                        <i class="fas fa-check-circle me-2"></i> ACTIVE
                                    </div>
                                <?php elseif($pending_req): ?>
                                    <div class="rounded-pill py-3 px-4 fw-900 text-center" style="background: rgba(245,158,11,0.2); border: 1px solid rgba(245,158,11,0.4); color: #fbbf24; font-size: 0.85rem;">
                                        <i class="fas fa-clock me-2"></i> PENDING REVIEW
                                    </div>
                                <?php else: ?>
                                    <button type="button" class="btn w-100 rounded-pill py-3 fw-900 shadow-lg hover-scale" style="background: #F4B400; color: #1e3a5f; font-size: 0.85rem; letter-spacing: 1px;" data-bs-toggle="modal" data-bs-target="#requestBillingModal">
                                        <i class="fas fa-paper-plane me-2"></i> REQUEST PLAN
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php?redirect=pricing.php" class="btn w-100 rounded-pill py-3 fw-900 shadow-lg hover-scale" style="background: #F4B400; color: #1e3a5f; font-size: 0.85rem; letter-spacing: 1px;">
                                    <i class="fas fa-sign-in-alt me-2"></i> LOGIN TO REQUEST
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════════════ -->
            <!-- INSTITUTIONAL BILLING REQUEST SECTION (Visible to ALL visitors)     -->
            <!-- ════════════════════════════════════════════════════════════════════ -->
            <div class="mt-5 p-0 rounded-5 position-relative overflow-hidden border-0 shadow-lg" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #2563eb 100%);">
                <!-- Decorative Elements -->
                <div class="position-absolute top-0 end-0 p-4" style="opacity: 0.06;">
                    <i class="fas fa-university" style="font-size: 12rem; color: #fff;"></i>
                </div>
                <div class="position-absolute bottom-0 start-0" style="opacity: 0.04;">
                    <i class="fas fa-file-invoice-dollar" style="font-size: 8rem; color: #fff; transform: rotate(-15deg);"></i>
                </div>

                <div class="p-5 position-relative">
                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge rounded-pill px-3 py-2 fw-800 shadow-sm" style="background: #F4B400; color: #1e3a5f; font-size: 0.65rem; letter-spacing: 1px;">
                                    <i class="fas fa-star me-1"></i> ENTERPRISE SOLUTION
                                </span>
                            </div>
                            <h3 class="fw-900 text-white mb-3" style="font-size: 2rem; letter-spacing: -0.5px; line-height: 1.3;">
                                Institutional Period-Based Billing
                            </h3>
                            <p class="text-white mb-4" style="opacity: 0.8; font-size: 1.05rem; line-height: 1.7; max-width: 550px;">
                                Skip the credit system entirely. Request a <strong>fixed-period subscription</strong> (Term, Session, or Year) and unlock 
                                <strong>unlimited operations</strong> — report sheets, CBT exams, SMS, and more — at an agreed institutional price.
                            </p>

                            <!-- Benefit Highlights -->
                            <div class="d-flex flex-wrap gap-3 mb-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(255,255,255,0.1);">
                                        <i class="fas fa-infinity text-warning" style="font-size: 0.75rem;"></i>
                                    </div>
                                    <span class="text-white small fw-700" style="opacity: 0.9;">Unlimited Operations</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(255,255,255,0.1);">
                                        <i class="fas fa-handshake text-warning" style="font-size: 0.75rem;"></i>
                                    </div>
                                    <span class="text-white small fw-700" style="opacity: 0.9;">Negotiated Pricing</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(255,255,255,0.1);">
                                        <i class="fas fa-shield-alt text-warning" style="font-size: 0.75rem;"></i>
                                    </div>
                                    <span class="text-white small fw-700" style="opacity: 0.9;">No Credit Deductions</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 text-lg-end mt-4 mt-lg-0">
                            <!-- Billing Agreement Card -->
                            <div class="p-4 rounded-4 text-start" style="background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.12);">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="fas fa-file-contract text-warning"></i>
                                    <span class="text-white fw-800 small">How It Works</span>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <span class="badge rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 22px; height: 22px; background: #F4B400; color: #1e3a5f; font-size: 0.6rem; font-weight: 900;">1</span>
                                        <span class="text-white small" style="opacity: 0.85;">Submit a billing request with your preferred plan</span>
                                    </div>
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <span class="badge rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 22px; height: 22px; background: #F4B400; color: #1e3a5f; font-size: 0.6rem; font-weight: 900;">2</span>
                                        <span class="text-white small" style="opacity: 0.85;">Our team reviews and negotiates an institutional price</span>
                                    </div>
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <span class="badge rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 22px; height: 22px; background: #F4B400; color: #1e3a5f; font-size: 0.6rem; font-weight: 900;">3</span>
                                        <span class="text-white small" style="opacity: 0.85;">Once approved, all operations become unlimited</span>
                                    </div>
                                </div>

                                <hr style="border-color: rgba(255,255,255,0.1);">

                                <?php if($is_owner): ?>
                                    <?php if($is_subscribed): ?>
                                        <div class="text-center p-3 rounded-3" style="background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3);">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span class="text-white fw-800 small">INSTITUTIONAL BILLING ACTIVE</span>
                                        </div>
                                    <?php elseif($pending_req): ?>
                                        <div class="text-center p-3 rounded-3" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);">
                                            <i class="fas fa-clock text-warning me-2"></i>
                                            <span class="text-white fw-800 small">PENDING ADMINISTRATIVE REVIEW</span>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="btn w-100 rounded-pill py-3 fw-900 shadow-lg hover-scale" style="background: #F4B400; color: #1e3a5f; font-size: 0.85rem; letter-spacing: 1px;" data-bs-toggle="modal" data-bs-target="#requestBillingModal">
                                            <i class="fas fa-paper-plane me-2"></i> REQUEST INSTITUTIONAL PLAN
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="login.php?redirect=pricing.php" class="btn w-100 rounded-pill py-3 fw-900 shadow-lg hover-scale" style="background: #F4B400; color: #1e3a5f; font-size: 0.85rem; letter-spacing: 1px;">
                                        <i class="fas fa-sign-in-alt me-2"></i> LOGIN TO REQUEST PLAN
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if($is_owner): ?>
            <!-- ══════════ Request Modal ══════════ -->
            <div class="modal fade" id="requestBillingModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
                        <form id="billingRequestForm">
                            <div class="modal-header border-bottom-0 p-4 pb-2" style="background: linear-gradient(135deg, #0f172a, #1e3a8a); color: white;">
                                <div>
                                    <h5 class="modal-title fw-900 text-white mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Request Institutional Billing</h5>
                                    <p class="small mb-0 opacity-75">Submit your request and our team will review it within 24 hours</p>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-800 text-muted uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-layer-group me-1 text-primary"></i> Select Desired Plan
                                        </label>
                                        <select class="form-select rounded-3 px-4 py-3 fw-600 border-light shadow-sm" name="requested_plan" required style="font-size: 0.9rem;">
                                            <option value="1 Year Unlimited">1 Year Institutional Unlimited</option>
                                            <option value="1 Session Unlimited">1 Session Institutional Unlimited</option>
                                            <option value="1 Term Unlimited">1 Term Institutional Unlimited</option>
                                            <option value="Semester Plan">Semester Based Plan</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-800 text-muted uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-calendar-alt me-1 text-primary"></i> Duration Details
                                        </label>
                                        <input type="text" class="form-control rounded-3 px-4 py-3 fw-600 border-light shadow-sm" name="duration" placeholder="e.g. 2025/2026 Session" required style="font-size: 0.9rem;">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-800 text-muted uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-comment-dots me-1 text-primary"></i> Additional Notes / Requirements
                                        </label>
                                        <textarea class="form-control rounded-3 p-4 fw-600 border-light shadow-sm" name="notes" rows="3" placeholder="Tell us about your institution's size, expected usage, and any special requirements..." style="font-size: 0.9rem;"></textarea>
                                    </div>
                                </div>

                                <!-- Benefits reminder -->
                                <div class="mt-4 p-3 rounded-3 d-flex align-items-center gap-3" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                    <i class="fas fa-info-circle text-success"></i>
                                    <span class="small text-success fw-600">Upon approval, your institution will have unlimited access to all credit-based operations for the agreed period.</span>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 p-4 pt-2 d-flex gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-4 py-2 fw-700" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-900 shadow-sm" style="letter-spacing: 0.5px;">
                                    <i class="fas fa-paper-plane me-2"></i> SEND REQUEST
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script>
            $(document).ready(function() {
                $('#billingRequestForm').on('submit', function(e) {
                    e.preventDefault();
                    const btn = $(this).find('button[type="submit"]');
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

                    $.ajax({
                        url: 'ajax/submit_billing_request.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(resp) {
                            if (resp.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Request Submitted!',
                                    text: resp.message,
                                    confirmButtonColor: '#1e40af'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Request Failed',
                                    text: resp.message,
                                    confirmButtonColor: '#dc2626'
                                });
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

            <!-- Pay-as-you-go explanation -->
            <div class="mt-5 text-center p-5 rounded-5 glass-card animate-fade-in">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <i class="fas fa-info-circle text-blue h3 mb-3"></i>
                        <h5 class="fw-900">The Power of Choice: No Fixed Subscriptions!</h5>
                        <p class="text-muted small mb-0 pe-lg-5 ps-lg-5">EduRemarks operates on a mission-critical <strong>Pay-As-You-Measure</strong> economy. Unlike traditional SaaS, you only replenish credits when your institution is operational. No monthly fees, no hidden costs. Academic excellence at your pace.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
