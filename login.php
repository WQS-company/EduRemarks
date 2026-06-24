<?php 
require_once 'includes/security.php'; 
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Gateway | <?php echo get_setting('hero_title', 'EduRemarks'); ?></title>
    <?php 
    // Define platform_favicon for login page if not already set (it isn't because login doesn't have auth_check)
    $path_prefix = '';
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;
    ?>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <!-- Essential CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="auth-body">
    <?php include 'includes/preloader.php'; ?>

    <!-- Standalone Auth Container -->
    <div class="auth-page-wrapper">
        <!-- Tech-Grid Background Layer -->
        <div class="tech-grid-overlay"></div>
        
        <!-- Background Blobs -->
        <div class="bg-blobs">
            <div class="blob blob-1"></div>
            <div class="blob blob-2" style="bottom: -10%; left: -10%;"></div>
        </div>

        <div class="container position-relative z-index-10">
            <div class="row align-items-center justify-content-center min-vh-100 py-2 py-lg-4">
                
                <!-- Desktop Branding Column (Decreased Size) -->
                <div class="col-lg-5 d-none d-lg-block reveal reveal-left">
                    <div class="branding-content pe-xl-4 text-start">
                        <div class="wave-propagation-container mb-3">
                            <div class="wave-propagation">
                                <span class="wave wave-1"></span>
                                <span class="wave wave-2"></span>
                                <span class="wave wave-3"></span>
                                <div class="core-point"></div>
                            </div>
                            <span class="badge-text ms-4" style="font-size: 0.65rem; color: var(--auth-blue); font-weight: 800;">INTELLIGENCE BROADCAST</span>
                        </div>
                        <h2 class="display-5 fw-800 tech-title mb-2" style="font-size: 2.2rem;">
                            Digital <span class="text-glow-blue">Intelligence</span>
                        </h2>
                        <p class="mb-3 op-7 text-muted" style="line-height: 1.5; font-size: 0.95rem; max-width: 400px;">
                            Securely access your institution's central hub. Manage operations and scale with precision using EduRemarks.
                        </p>
                        
                        <div class="feature-points small">
                            <div class="d-flex align-items-center mb-2">
                                <div class="tech-icon-box gold-glow" style="width: 35px; height: 35px; font-size: 0.9rem;">
                                    <i class="fas fa-shield-halved"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-0 fw-700 text-blue small">Secured Packets</h6>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="tech-icon-box blue-glow" style="width: 35px; height: 35px; font-size: 0.9rem;">
                                    <i class="fas fa-microchip"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-0 fw-700 text-blue small">Optimized Nodes</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login Card Column (Refined Width) -->
                <div class="col-xl-5 col-lg-6 col-md-8 col-12 reveal reveal-up stagger-2">
                    <main class="login-card-container">
                        <div class="glass-morph-card shadow-lg" style="border-radius: 20px;">
                            <!-- Top Progress Bar Decor -->
                            <div class="card-loader-line"></div>
                            
                            <div class="p-4 p-md-4"> <!-- Restoring a bit of breathing room but keeping it tight -->
                                <!-- Logo Center -->
                                <div class="text-center mb-3">
                                    <a href="index.php"><img src="<?php echo get_setting('platform_logo', 'img/logo.png'); ?>" alt="EduRemarks" class="auth-logo-mobile" style="max-height: 35px;"></a>
                                </div>

                                <header class="text-center mb-3">
                                    <h3 class="fw-800 text-blue" style="font-size: 1.5rem; letter-spacing: -0.5px;">System Entry</h3>
                                    <p class="text-muted small uppercase tracking-1 opacity-75">Secure Authentication</p>
                                </header>

                                <!-- Alert Box -->
                                <div id="loginAlert" class="alert d-none tech-alert-box mb-3" role="alert"></div>

                                <form id="loginForm" class="auth-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <div class="form-field">
                                                <label class="field-label">Instructional Identity</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-fingerprint field-icon"></i>
                                                    <input type="text" name="identity" placeholder="Email or Phone Number" class="form-control tech-field" required autocomplete="username">
                                                    <div class="field-focus-line"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <div class="form-field">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="field-label mb-0">Security Code</label>
                                                    <a href="#" class="auth-sub-link small">Forgot Key?</a>
                                                </div>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-key field-icon"></i>
                                                    <input type="password" name="password" id="loginPassword" placeholder="••••••••" class="form-control tech-field" required autocomplete="current-password">
                                                    <button type="button" class="btn-eye-toggle password-toggle-btn" data-target="loginPassword">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                    <div class="field-focus-line"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <label class="tech-checkbox d-inline-flex align-items-center">
                                            <input type="checkbox" id="remember">
                                            <span class="box-inner"></span>
                                            <span class="box-label fw-600" style="font-size: 0.8rem;">Trust this device</span>
                                        </label>
                                    </div>

                                    <div class="text-center">
                                        <button type="submit" class="btn btn-auth-submit-compact" id="loginBtn">
                                            <span class="btn-inner">
                                                <span class="txt">INITIALIZE LOGIN</span>
                                                <i class="fas fa-chevron-right btn-ico ms-2 small"></i>
                                            </span>
                                            <div class="btn-shimmer"></div>
                                        </button>
                                    </div>
                                    <div class="text-center mt-4 pt-2">
                                        <p class="mb-0 text-muted tiny-text uppercase tracking-1 fw-700" style="font-size: 0.65rem;">
                                            New School? <a href="signup.php" class="text-gold ms-1 hover-scale d-inline-block text-decoration-none border-bottom border-gold">Register Institution</a>
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Submission Overlay -->
    <div id="process-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; flex-direction: column;">
        <div class="loader-visual" style="position: relative; width: 80px; height: 80px; margin-bottom: 25px;">
            <div class="spinner-ring" style="position: absolute; width: 100%; height: 100%; border: 4px solid rgba(244, 180, 0, 0.1); border-top: 4px solid var(--auth-gold); border-radius: 50%; animation: auth-spin 1s linear infinite;"></div>
            <div class="spinner-core" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 12px; height: 12px; background: var(--auth-gold); border-radius: 50%; box-shadow: 0 0 15px var(--auth-gold);"></div>
        </div>
        <div class="loader-message text-center">
            <h5 class="text-white fw-900 mb-2 uppercase tracking-2" style="font-size: 0.9rem; letter-spacing: 3px;">AUTHENTICATING...</h5>
            <p class="text-white opacity-50 tiny-text uppercase tracking-1" style="font-size: 0.65rem;">Syncing Security Tokens</p>
        </div>
    </div>

    <style>
    @keyframes auth-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>

    <style>
    /* === PROFESSIONAL OPTIMIZED AUTH === */
    :root {
        --auth-blue: #1F3C88;
        --auth-gold: #F4B400;
        --auth-white: #FFFFFF;
        --auth-bg: #F8F9FB;
        --field-bg: rgba(255, 255, 255, 0.95);
        --text-blue: #1F3C88;
    }

    body.auth-body {
        margin: 0;
        padding: 0;
        background: var(--auth-bg);
        font-family: 'Inter', sans-serif;
    }

    .auth-page-wrapper {
        position: relative;
        overflow-x: hidden;
        min-height: 100vh;
    }

    .tech-grid-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            linear-gradient(rgba(31, 60, 136, 0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(31, 60, 136, 0.02) 1px, transparent 1px);
        background-size: 30px 30px;
        z-index: 1;
    }

    /* Background Blobs */
    .bg-blobs { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
    .blob { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.08; }
    .blob-1 { width: 600px; height: 600px; background: var(--auth-blue); top: -20%; right: -10%; }
    .blob-2 { width: 500px; height: 500px; background: var(--auth-gold); bottom: -15%; left: -10%; }

    .z-index-10 { z-index: 10; }
    .fw-800 { font-weight: 800; }
    .fw-700 { font-weight: 700; }
    .fw-600 { font-weight: 600; }
    .op-7 { opacity: 0.7; }
    .tracking-1 { letter-spacing: 0.5px; }

    /* Glowing Text */
    .text-glow-blue { color: var(--auth-blue); text-shadow: 0 0 10px rgba(31, 60, 136, 0.1); }
    .text-glow-gold { color: var(--auth-gold); }

    .tech-title { line-height: 1.1; letter-spacing: -1.5px; }

    /* Wave Propagation Animation */
    .wave-propagation-container {
        display: flex;
        align-items: center;
        height: 24px;
        position: relative;
    }
    .wave-propagation {
        position: relative;
        width: 12px;
        height: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .core-point {
        width: 8px;
        height: 8px;
        background: var(--auth-gold);
        border-radius: 50%;
        position: relative;
        z-index: 2;
        box-shadow: 0 0 10px var(--auth-gold);
    }
    .wave {
        position: absolute;
        width: 100%;
        height: 100%;
        border: 2px solid var(--auth-gold);
        border-radius: 50%;
        opacity: 0;
        animation: propagate 2s infinite linear;
    }
    .wave-2 { animation-delay: 0.6s; }
    .wave-3 { animation-delay: 1.2s; }
    
    @keyframes propagate {
        0% { transform: scale(1); opacity: 0.8; }
        100% { transform: scale(4); opacity: 0; }
    }

    /* Feature Icons */
    .tech-icon-box { border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; }
    .gold-glow { background: var(--auth-gold); color: #111; }
    .blue-glow { background: var(--auth-blue); }

    /* Glass Morph Card */
    .glass-morph-card {
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(25px);
        -webkit-backdrop-filter: blur(25px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 30px 60px -15px rgba(31, 60, 136, 0.12);
        position: relative;
        overflow: hidden;
    }

    .card-loader-line {
        height: 3px; background: linear-gradient(to right, var(--auth-blue), var(--auth-gold), var(--auth-blue));
        background-size: 200% auto; animation: loader-slide 3s linear infinite;
    }
    @keyframes loader-slide { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }

    /* Custom Form Fields */
    .field-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: var(--auth-blue); opacity: 0.6; display: block; margin-bottom: 6px; }
    .field-input-wrapper { position: relative; }
    .field-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--auth-blue); opacity: 0.4; font-size: 1rem; }

    .tech-field {
        background: var(--field-bg) !important;
        border: 1.5px solid rgba(31, 60, 136, 0.08) !important;
        padding: 12px 20px 12px 48px !important;
        border-radius: 12px !important;
        font-weight: 600;
        font-size: 0.95rem !important;
        color: var(--text-blue);
        transition: all 0.3s;
        box-shadow: none !important;
    }
    .tech-field:focus { border-color: var(--auth-blue) !important; background: white !important; }

    .btn-eye-toggle {
        position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; transition: all 0.3s; padding: 5px; z-index: 5;
    }

    /* Tech Checkbox */
    .tech-checkbox { cursor: pointer; user-select: none; }
    .tech-checkbox input { display: none; }
    .box-inner { width: 18px; height: 18px; background: rgba(31, 60, 136, 0.04); border: 1.5px solid rgba(31, 60, 136, 0.1); border-radius: 5px; margin-right: 10px; position: relative; transition: all 0.2s; }
    .tech-checkbox input:checked ~ .box-inner { background: var(--auth-blue); border-color: var(--auth-blue); }
    .box-inner::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 9px; color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0); transition: 0.2s; }
    .tech-checkbox input:checked ~ .box-inner::after { transform: translate(-50%, -50%) scale(1); }
    .box-label { color: #64748b; font-weight: 500; }

    /* COMPACT AUTH SUBMIT BUTTON */
    .btn-auth-submit-compact {
        background: var(--auth-blue);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 14px 40px;
        font-weight: 700;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
        transition: all 0.4s;
        box-shadow: 0 10px 20px -5px rgba(31, 60, 136, 0.3);
        width: 100%;
        max-width: 280px;
    }
    .btn-auth-submit-compact:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(31, 60, 136, 0.4); background: #172E6F; }
    .btn-auth-submit-compact:active { transform: translateY(0); }
    .btn-shimmer { position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent); transition: 0.5s; }
    .btn-auth-submit-compact:hover .btn-shimmer { left: 100%; transition: 0.8s; }

    .auth-sub-link { font-weight: 700; color: var(--secondary-blue); text-decoration: none; }
    .border-top-glow { border-top: 1px solid rgba(31, 60, 136, 0.08) !important; }

    /* Alerts */
    .tech-alert-box { background: white; border-radius: 12px; font-weight: 600; font-size: 0.85rem; border: 1px solid #e2e8f0 !important; }

    /* Reveal Animations */
    .reveal { opacity: 0; transition: all 0.8s cubic-bezier(0.165, 0.84, 0.44, 1); }
    .reveal-left { transform: translateX(-20px); }
    .reveal-up { transform: translateY(20px); }
    .reveal.active { opacity: 1; transform: translate(0); }
    .stagger-2 { transition-delay: 0.15s; }

    @media (max-width: 991.98px) {
        .branding-content { text-align: center; margin-bottom: 2rem; padding: 0 !important; }
        .branding-content .tech-title { font-size: 2rem !important; }
        .branding-content p { margin: 0 auto !important; }
        .branding-content .feature-points { display: none; }
        .glass-morph-card { border-radius: 16px; }
        .btn-auth-submit-compact { max-width: 100%; }
    }
    
    @media (max-width: 480px) {
        .p-md-5 { padding: 1.5rem !important; }
        .tech-field { padding: 11px 15px 11px 45px !important; font-size: 0.9rem !important; }
        .auth-header-title { font-size: 1.5rem !important; }
    }
    </style>

    <!-- Essential Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Initial reveal
        setTimeout(() => {
            document.querySelectorAll(".reveal").forEach(el => el.classList.add("active"));
        }, 100);

        // Visibility Toggle
        const toggleBtn = document.querySelector('.password-toggle-btn');
        if(toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const targetInput = document.getElementById(this.dataset.target);
                const icon = this.querySelector('i');
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    icon.className = 'fas fa-eye';
                    this.style.color = 'var(--auth-blue)';
                } else {
                    targetInput.type = 'password';
                    icon.className = 'fas fa-eye-slash';
                    this.style.color = '#a0aec0';
                }
            });
        }

        // Login logic
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');
        const alertBox = document.getElementById('loginAlert');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const originalText = btn.innerHTML;
            const overlay = document.getElementById('process-overlay');
            
            overlay.style.display = 'flex';
            btn.disabled = true;
            alertBox.classList.add('d-none');

            fetch('ajax/login.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('.loader-message h5').innerText = 'ACCESS GRANTED';
                    document.querySelector('.loader-message p').innerText = 'Redirecting to Core Node...';
                    
                    alertBox.className = 'alert alert-success tech-alert-box text-success d-block';
                    alertBox.innerHTML = '<i class="fas fa-circle-check me-2"></i> PERMISSION GRANTED';
                    setTimeout(() => window.location.href = 'dashboard.php', 1200);
                } else {
                    overlay.style.display = 'none';
                    alertBox.className = 'alert alert-danger tech-alert-box text-danger d-block';
                    alertBox.innerHTML = '<i class="fas fa-circle-exclamation me-2"></i> ' + data.message;
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    
                    document.querySelector('.glass-morph-card').animate([
                        { transform: 'translateX(0)' },
                        { transform: 'translateX(-5px)' },
                        { transform: 'translateX(5px)' },
                        { transform: 'translateX(0)' }
                    ], { duration: 250 });
                }
            })
            .catch(err => {
                overlay.style.display = 'none';
                alertBox.className = 'alert alert-danger tech-alert-box d-block';
                alertBox.innerHTML = '<i class="fas fa-wifi-slash me-2"></i> Connection Failed';
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
    </script>
</body>
</html>
