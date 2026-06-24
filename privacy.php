<?php
// privacy.php - Institutional Data Protection Archetype
require_once 'config/db.php';
require_once 'includes/functions.php';

$qp = $pdo->query("SELECT * FROM platform_settings");
$settings = [];
while($row = $qp->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = "Privacy Policy";
include 'includes/header.php';
?>

<style>
    .legal-container { padding: 120px 0 80px; background: #f8fafc; }
    .legal-card { background: white; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.05); overflow: hidden; }
    .legal-header { background: #1e40af; color: white; padding: 60px 40px; text-align: center; }
    .legal-body { padding: 60px 80px; color: #475569; line-height: 1.8; white-space: pre-line; }
    
    @media (max-width: 768px) {
        .legal-body { padding: 40px 25px; }
    }
</style>

<section class="legal-container">
    <div class="container">
        <div class="legal-card reveal reveal-up">
            <div class="legal-header">
                <h1 class="fw-900 mb-2">Privacy Policy</h1>
                <p class="opacity-75 uppercase tracking-1 fs-6">Data Orchestration & Security Protocol</p>
            </div>
            
            <div class="legal-body">
                <?php 
                if(!empty($settings['privacy_policy'])) {
                    echo htmlspecialchars($settings['privacy_policy']);
                } else {
                    echo "Our privacy protocols are currently being synchronized. We implement robust security measures to protect all institutional student and staff information throughout the academic lifecycle.";
                }
                ?>

                <div class="text-center mt-5 opacity-50 small border-top pt-4">
                    Document version 2.4.0 | Last Synchronized: <?php echo date('F d, Y'); ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
