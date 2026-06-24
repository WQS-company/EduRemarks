<?php
// Terms of Service with Dynamic Credit Resource Pricing Node
require_once 'config/db.php';
require_once 'includes/functions.php';

$qp = $pdo->query("SELECT * FROM platform_settings");
$pricing = [];
while($row = $qp->fetch()) {
    $pricing[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = "Terms of Service";
include 'includes/header.php';
?>

<style>
    .tos-container { padding: 100px 0 60px; background: #fff; }
    .section-title { color: var(--primary-blue); font-weight: 800; font-size: 1.25rem; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .pricing-table { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 16px; padding: 24px; }
    .pricing-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #edf2f7; font-size: 0.9rem; }
    .pricing-item:last-child { border-bottom: none; }
    .rate-badge { background: #e0f2fe; color: #0369a1; padding: 4px 12px; border-radius: 50px; font-weight: 700; font-size: 0.75rem; border: 1px solid #bae6fd; }
    .tos-content { color: #475569; font-size: 0.95rem; line-height: 1.7; }
    .divider { height: 1px; background: radial-gradient(circle, #e2e8f0 0%, transparent 100%); margin: 40px 0; }
</style>

<section class="tos-container">
    <div class="container maxWidth-900">
        <div class="mb-5">
            <h6 class="text-primary fw-bold text-uppercase tracking-2 small mb-2">Legal Framework</h6>
            <h2 class="fw-900 text-dark mb-4">Terms of Service & Platform Economics</h2>
            <div class="tos-content">
                <p>EduRemarks provides a high-performance institutional management layer. By utilizing our services, you agree to the protocols of governance and resource allocation defined below. All platform interactions are synchronized with our global synchronization matrix.</p>
            </div>
        </div>

        <div class="row g-5">
            <div class="col-12">
                <div class="tos-section">
                    <h3 class="section-title"><i class="fas fa-bolt text-warning"></i> Resource Consumption Matrix</h3>
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5">
                            <div class="tos-content">
                                <p class="mb-3">Our <strong>Institutional Credit Economy</strong> ensures precise billing. These rates are synchronized nationwide to maintain platform equilibrium.</p>
                                <p class="small text-muted mb-0">Credits are non-refundable and tied to the active institutional session.</p>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="pricing-table">
                                <div class="pricing-item">
                                    <span><i class="fas fa-file-invoice me-2 text-primary opacity-50"></i> Result Generation</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_student_result'] ?? 1; ?> Credits</span>
                                </div>
                                <div class="pricing-item">
                                    <span><i class="fas fa-book me-2 text-primary opacity-50"></i> Answer Booklets (Per Booklet)</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_answer_sheet'] ?? 1; ?> Credits</span>
                                </div>
                                <div class="pricing-item">
                                    <span><i class="fas fa-laptop-code me-2 text-primary opacity-50"></i> CBT Hosting</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_cbt_test'] ?? 1; ?> Credits</span>
                                </div>
                                <div class="pricing-item">
                                    <span><i class="fas fa-graduation-cap me-2 text-primary opacity-50"></i> Exam Hosting</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_cbt_exam'] ?? 2; ?> Credits</span>
                                </div>
                                <div class="pricing-item">
                                    <span><i class="fas fa-paper-plane me-2 text-primary opacity-50"></i> SMS Campaign</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_per_sms'] ?? 10; ?> Credits</span>
                                </div>
                                <div class="pricing-item">
                                    <span><i class="fas fa-id-card me-2 text-primary opacity-50"></i> Digital ID Generation</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_cost_id_card'] ?? 10; ?> Credits</span>
                                </div>
                                <div class="pricing-item">
                                    <span><i class="fas fa-id-badge me-2 text-primary opacity-50"></i> Admission Application</span>
                                    <span class="rate-badge"><?php echo $pricing['credit_admission_applicant'] ?? 5; ?> Credits</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="section-title" style="font-size: 1.1rem;"><i class="fas fa-handshake"></i> Acceptance</h3>
                        <div class="tos-content small">
                            Initializing a node signifies full compliance with our administrative framework and protocol standards.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h3 class="section-title" style="font-size: 1.1rem;"><i class="fas fa-user-shield"></i> Data Integrity</h3>
                        <div class="tos-content small">
                            Records are processed via our secure encryption layer, maintaining zero-compromise organizational privacy.
                        </div>
                    </div>
                </div>

                <?php if(!empty($pricing['terms_content'])): ?>
                <div class="divider"></div>
                <div class="tos-section">
                    <h3 class="section-title"><i class="fas fa-scroll text-primary"></i> Institutional Governance</h3>
                    <div class="tos-content small" style="white-space: pre-line; background: #fbfcfd; padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9;">
                        <?php echo htmlspecialchars($pricing['terms_content']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-5 pt-4 text-center border-top">
                    <div class="text-muted extra-small uppercase tracking-2">
                        Last Global Sync: <?php echo date('M d, Y'); ?> &bull; Platform Integrity Verified
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
