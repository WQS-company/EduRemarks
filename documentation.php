<?php
// documentation.php - EduRemarks Master Guide & Institutional Specifications
require_once 'config/db.php';
require_once 'includes/security.php';

$pageTitle = "Platform Specifications | EduRemarks Master Guide";
$is_logged_in = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? 'guest';

include 'includes/header.php';
?>

<style>
    :root {
        --docs-primary: #1e3a8a;
        --docs-bg: #f8fafc;
        --docs-text: #334155;
    }
    .docs-wrapper { padding: 80px 0 60px; background: var(--docs-bg); min-height: 100vh; font-family: 'Inter', sans-serif; }
    .docs-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; }
    .docs-content { padding: 40px; color: var(--docs-text); }
    .docs-section { margin-bottom: 30px; }
    .docs-section h2 { font-weight: 800; color: var(--docs-primary); margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; font-size: 1.4rem; }
    .docs-section h3 { font-weight: 700; color: #0f172a; margin-top: 20px; margin-bottom: 10px; font-size: 1.1rem; }
    
    .feature-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
    .feature-item { background: #fdfdfd; border: 1px solid #f1f5f9; padding: 15px; border-radius: 12px; border-left: 3px solid var(--docs-primary); }
    .feature-item h6 { font-weight: 700; color: var(--docs-primary); margin-bottom: 4px; font-size: 0.9rem; }
    .feature-item p { font-size: 0.85rem; margin-bottom: 0; }
    
    .billing-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 12px; height: 100%; }
    .billing-card h5 { font-size: 1rem; font-weight: 700; margin-bottom: 8px; }
    
    .compliance-tag { display: inline-block; padding: 2px 8px; background: #ecfdf5; color: #059669; border-radius: 4px; font-weight: 700; font-size: 0.65rem; margin-right: 5px; text-transform: uppercase; }

    @media print {
        @page { size: A4; margin: 1cm; }
        .docs-nav, footer, .header, #support-chat-widget, .no-print, .btn, .navbar { display: none !important; }
        .docs-wrapper { padding: 0 !important; margin: 0 !important; background: white !important; }
        .docs-card { border: none !important; box-shadow: none !important; width: 100% !important; }
        .docs-content { padding: 0 !important; width: 100% !important; }
        .docs-section { margin-bottom: 20px !important; }
        .feature-grid { gap: 10px; }
        p, li, td { font-size: 10pt !important; line-height: 1.3 !important; }
        h1 { font-size: 18pt !important; }
        h2 { font-size: 14pt !important; margin-bottom: 10px !important; }
        h3 { font-size: 12pt !important; }
        .billing-card { padding: 10px !important; }
        .alert { padding: 10px !important; }
    }
</style>

<div class="docs-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="docs-card">
                    <div class="docs-content">
                        <!-- HEADER -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h1 class="fw-900 mb-1 text-primary">EduRemarks Platform Guide</h1>
                                <p class="text-muted small mb-0">Institutional Digital Infrastructure Specifications</p>
                            </div>
                            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 no-print">
                                <i class="fas fa-print me-2"></i>Print Handbook
                            </button>
                        </div>

                        <!-- 1. OVERVIEW -->
                        <section class="docs-section">
                            <p class="mb-0">EduRemarks is a unified academic ERP designed for modern schools and colleges. We automate result processing, student assessments, and institutional billing with enterprise-grade reliability and security.</p>
                        </section>

                        <!-- 2. CORE FEATURES -->
                        <section class="docs-section">
                            <h2><i class="fas fa-star me-2"></i>Key Platform Capabilities</h2>
                            <div class="feature-grid">
                                <div class="feature-item">
                                    <h6>Result Management</h6>
                                    <p>Automated grading, broadsheets, and instant digital report cards.</p>
                                </div>
                                <div class="feature-item">
                                    <h6>CBT Assessments</h6>
                                    <p>Secure online examination system with automated scoring.</p>
                                </div>
                                <div class="feature-item">
                                    <h6>Student Profiling</h6>
                                    <p>Dynamic ID generation and secure lifelong academic records.</p>
                                </div>
                                <div class="feature-item">
                                    <h6>Admin Cockpit</h6>
                                    <p>Financial tracking, SMS alerts, and staff management tools.</p>
                                </div>
                            </div>
                        </section>

                        <!-- 3. PRICING METHODS -->
                        <section class="docs-section">
                            <h2><i class="fas fa-credit-card me-2"></i>Flexible Pricing Models</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="billing-card">
                                        <h5><i class="fas fa-bolt text-warning me-2"></i>Operational Credits</h5>
                                        <p class="small mb-2"><strong>Pay-as-you-go</strong> model for results and exams.</p>
                                        <ul class="small mb-0 ps-3">
                                            <li>Purchase bulk credits (never expire).</li>
                                            <li>Best for growing institutions.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="billing-card">
                                        <h5><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Custom Billing</h5>
                                        <p class="small mb-2"><strong>Unlimited</strong> termly/yearly subscriptions.</p>
                                        <ul class="small mb-0 ps-3">
                                            <li>Fixed cost for unlimited operations.</li>
                                            <li>Best for large colleges & public bodies.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- 4. SECURITY & COMPLIANCE -->
                        <section class="docs-section">
                            <h2><i class="fas fa-shield-alt me-2"></i>Security & Compliance</h2>
                            <div class="row g-3 align-items-center">
                                <div class="col-md-7">
                                    <p class="small mb-2"><span class="compliance-tag">Data Protection</span> Encrypted storage with 99.9% uptime SLA.</p>
                                    <p class="small mb-2"><span class="compliance-tag">Privacy</span> Strict NDPR/GDPR compliant access controls.</p>
                                    <p class="small mb-0"><span class="compliance-tag">Audit-Ready</span> Transparent audit trails for all institutional actions.</p>
                                </div>
                                <div class="col-md-5">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <div class="h5 fw-bold text-primary mb-0">AES-256</div>
                                        <div class="small text-muted">Encryption Standard</div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- 5. IMPLEMENTATION -->
                        <section class="docs-section mb-0">
                            <h2><i class="fas fa-rocket me-2"></i>Fast Implementation</h2>
                            <p class="small mb-3">Onboarding is completed in three simple phases: Data Mapping (48h), Staff Training (24h), and System Go-Live.</p>
                            
                            <div class="alert alert-primary rounded-4 border-0 p-3 mb-0">
                                <div class="row align-items-center">
                                    <div class="col-md-9">
                                        <h6 class="fw-bold mb-1">Ready to Modernize?</h6>
                                        <p class="small mb-0">Contact our sales team at <strong>support@eduremarks.com</strong> for a custom institutional quote.</p>
                                    </div>
                                    <div class="col-md-3 text-md-end mt-2 mt-md-0">
                                        <i class="fas fa-university fa-2x opacity-25"></i>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="text-center mt-4 pt-3 text-muted border-top" style="font-size: 0.7rem;">
                            <p class="mb-0">© <?php echo date('Y'); ?> EduRemarks Platform. All operations are subject to standard institutional terms.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // Automatically trigger print if ?print=1 is present
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    }
</script>
