<?php
// refund.php - Institutional Refund & Credit Doctrine
require_once 'config/db.php';
require_once 'includes/functions.php';

$qp = $pdo->query("SELECT * FROM platform_settings");
$settings = [];
while($row = $qp->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = "Refund Policy";
include 'includes/header.php';
?>

<style>
    .legal-container { padding: 120px 0 80px; background: #f8fafc; }
    .legal-card { background: white; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.05); overflow: hidden; }
    .legal-header { background: #dc2626; color: white; padding: 60px 40px; text-align: center; }
    .legal-body { padding: 60px 80px; color: #475569; line-height: 1.8; white-space: pre-line; }
    
    @media (max-width: 768px) {
        .legal-body { padding: 40px 25px; }
    }
</style>

<section class="legal-container">
    <div class="container">
        <div class="legal-card reveal reveal-up">
            <div class="legal-header">
                <h1 class="fw-900 mb-2">Refund Policy</h1>
                <p class="opacity-75 uppercase tracking-1 fs-6">Fiscal Doctrine & Credit Management</p>
            </div>
            
            <div class="legal-body">
                <?php 
                if(!empty($settings['refund_policy'])) {
                    echo htmlspecialchars($settings['refund_policy']);
                } else {
                    echo "The EduRemarks fiscal policy operates on a non-refundable node strategy. Once institutional credits have been commissioned and allocated to a school cockpit, they represent academic resource capacity and cannot be reversed or converted back to fiat. We encourage all administrators to perform a pilot audit using the introductory credit package before scaling their operations.";
                }
                ?>

                <div class="text-center mt-5 opacity-50 small border-top pt-4">
                    Fiscal synchronization v1.0.2 | Last Updated: <?php echo date('F d, Y'); ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
