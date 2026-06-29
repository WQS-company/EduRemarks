<?php require_once 'includes/security.php'; ?>
<?php
require_once 'includes/config.php';
$pageTitle = "Register";
$path_prefix = '';
$sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
$platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo get_setting('hero_title', 'EduRemarks'); ?></title>
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
    :root {
        --auth-blue: #1F3C88;
        --auth-gold: #F4B400;
        --auth-bg: #F8F9FB;
        --field-bg: rgba(255, 255, 255, 0.95);
        --text-blue: #1F3C88;
        --secondary-blue: #3b5fcc;
    }
    body.auth-body { margin: 0; padding: 0; background: var(--auth-bg); font-family: 'Inter', sans-serif; overflow-x: hidden; }
    .auth-page-wrapper { position: relative; overflow-x: hidden; min-height: 100vh; }
    .tech-grid-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-image: linear-gradient(rgba(31,60,136,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(31,60,136,0.02) 1px, transparent 1px);
        background-size: 30px 30px; z-index: 1;
    }
    .bg-blobs { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
    .blob { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.08; }
    .blob-1 { width: 600px; height: 600px; background: var(--auth-blue); top: -20%; right: -10%; }
    .blob-2 { width: 500px; height: 500px; background: var(--auth-gold); bottom: -15%; left: -10%; }
    .z-index-10 { z-index: 10; }

    /* Wave Animation */
    .wave-propagation-container { display: flex; align-items: center; height: 24px; position: relative; }
    .wave-propagation { position: relative; width: 12px; height: 12px; display: flex; align-items: center; justify-content: center; }
    .core-point { width: 8px; height: 8px; background: var(--auth-gold); border-radius: 50%; position: relative; z-index: 2; box-shadow: 0 0 10px var(--auth-gold); }
    .wave { position: absolute; width: 100%; height: 100%; border: 2px solid var(--auth-gold); border-radius: 50%; opacity: 0; animation: propagate 2s infinite linear; }
    .wave-2 { animation-delay: 0.6s; }
    .wave-3 { animation-delay: 1.2s; }
    @keyframes propagate { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(4); opacity: 0; } }

    /* Glass Card */
    .glass-morph-card {
        background: rgba(255,255,255,0.96); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
        border: 1px solid rgba(255,255,255,0.8); box-shadow: 0 30px 60px -15px rgba(31,60,136,0.12);
        position: relative; overflow: hidden; border-radius: 20px;
    }
    .card-loader-line { height: 3px; background: linear-gradient(to right, var(--auth-blue), var(--auth-gold), var(--auth-blue)); background-size: 200% auto; animation: loader-slide 3s linear infinite; }
    @keyframes loader-slide { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }

    /* Step Indicator */
    .reg-steps { display: flex; align-items: center; justify-content: center; gap: 0; margin: 0 auto 2rem; max-width: 320px; position: relative; }
    .reg-step-node {
        width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.85rem; position: relative; z-index: 2; transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        background: rgba(31,60,136,0.06); color: rgba(31,60,136,0.3); border: 2px solid rgba(31,60,136,0.08);
    }
    .reg-step-node.active {
        background: var(--auth-blue); color: white; border-color: var(--auth-blue);
        box-shadow: 0 0 0 6px rgba(31,60,136,0.1), 0 8px 20px -4px rgba(31,60,136,0.3);
    }
    .reg-step-node.completed {
        background: var(--auth-gold); color: var(--auth-blue); border-color: var(--auth-gold);
        box-shadow: 0 0 0 6px rgba(244,180,0,0.12);
    }
    .reg-step-line { flex: 1; height: 2px; background: rgba(31,60,136,0.08); position: relative; z-index: 1; }
    .reg-step-line .fill { height: 100%; background: var(--auth-gold); width: 0%; transition: width 0.5s ease; border-radius: 2px; }
    .reg-step-line.filled .fill { width: 100%; }
    .reg-step-label {
        position: absolute; top: 52px; left: 50%; transform: translateX(-50%); white-space: nowrap;
        font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        color: rgba(31,60,136,0.3); transition: color 0.3s;
    }
    .reg-step-node.active .reg-step-label, .reg-step-node.completed .reg-step-label { color: var(--auth-blue); }

    /* Field Styles */
    .field-label { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: var(--auth-blue); opacity: 0.6; display: block; margin-bottom: 6px; }
    .field-input-wrapper { position: relative; }
    .field-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--auth-blue); opacity: 0.4; font-size: 1rem; z-index: 2; }
    .tech-field {
        background: var(--field-bg) !important; border: 1.5px solid rgba(31,60,136,0.08) !important;
        padding: 12px 20px 12px 48px !important; border-radius: 12px !important; font-weight: 600;
        font-size: 0.95rem !important; color: var(--text-blue); transition: all 0.3s; box-shadow: none !important; width: 100%;
    }
    .tech-field:focus { border-color: var(--auth-blue) !important; background: white !important; box-shadow: 0 0 0 3px rgba(31,60,136,0.06) !important; }
    .tech-field::placeholder { color: #a0aec0; font-weight: 500; }
    .btn-eye-toggle {
        position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: none;
        border: none; color: #a0aec0; transition: all 0.3s; padding: 5px; z-index: 5; cursor: pointer;
    }

    /* Role Cards */
    .role-card {
        cursor: pointer; border: 2px solid rgba(31,60,136,0.08); border-radius: 16px;
        padding: 2rem 1.5rem; text-align: center; transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        background: white; position: relative; overflow: hidden;
    }
    .role-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, var(--auth-blue), var(--auth-gold)); opacity: 0; transition: opacity 0.3s;
    }
    .role-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -10px rgba(31,60,136,0.12); border-color: rgba(31,60,136,0.15); }
    .role-card:hover::before { opacity: 1; }
    .role-card.selected {
        border-color: var(--auth-gold); background: linear-gradient(135deg, rgba(31,60,136,0.02), rgba(244,180,0,0.04));
        box-shadow: 0 20px 40px -10px rgba(244,180,0,0.15);
    }
    .role-card.selected::before { opacity: 1; }
    .role-icon {
        width: 64px; height: 64px; border-radius: 18px; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1rem; font-size: 1.5rem; transition: all 0.4s;
    }
    .role-card:hover .role-icon { transform: scale(1.08); }
    .role-card.selected .role-icon { transform: scale(1.1); }
    .role-icon.owner-icon { background: linear-gradient(135deg, rgba(31,60,136,0.1), rgba(31,60,136,0.05)); color: var(--auth-blue); }
    .role-card.selected .role-icon.owner-icon { background: var(--auth-blue); color: white; box-shadow: 0 8px 20px -4px rgba(31,60,136,0.3); }
    .role-icon.staff-icon { background: linear-gradient(135deg, rgba(244,180,0,0.15), rgba(244,180,0,0.05)); color: #b8860b; }
    .role-card.selected .role-icon.staff-icon { background: var(--auth-gold); color: var(--auth-blue); box-shadow: 0 8px 20px -4px rgba(244,180,0,0.3); }
    .role-check {
        position: absolute; top: 12px; right: 12px; width: 24px; height: 24px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 0.65rem;
        background: rgba(31,60,136,0.06); color: transparent; transition: all 0.3s;
    }
    .role-card.selected .role-check { background: var(--auth-gold); color: var(--auth-blue); }

    /* Search Dropdown */
    .school-search-wrap { position: relative; }
    #school_search_results {
        position: absolute; width: 100%; z-index: 1000; display: none; background: white;
        border: 1px solid rgba(31,60,136,0.08); border-top: none; border-radius: 0 0 12px 12px;
        max-height: 220px; overflow-y: auto; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.1);
    }
    #school_search_results .result-item {
        padding: 12px 18px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;
    }
    #school_search_results .result-item:hover { background: rgba(31,60,136,0.04); }
    #school_search_results .result-item + .result-item { border-top: 1px solid #f1f5f9; }

    /* Submit Button */
    .btn-register-submit {
        background: var(--auth-blue); color: white; border: none; border-radius: 12px;
        padding: 14px 40px; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.5px;
        position: relative; overflow: hidden; transition: all 0.4s;
        box-shadow: 0 10px 20px -5px rgba(31,60,136,0.3); width: 100%; max-width: 280px;
    }
    .btn-register-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(31,60,136,0.4); background: #172E6F; }
    .btn-register-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .btn-shimmer { position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent); transition: 0.5s; }
    .btn-register-submit:hover .btn-shimmer { left: 100%; transition: 0.8s; }

    /* Nav Buttons */
    .btn-nav-back {
        background: transparent; color: var(--auth-blue); border: 1.5px solid rgba(31,60,136,0.15);
        border-radius: 10px; padding: 10px 24px; font-weight: 700; font-size: 0.82rem;
        transition: all 0.3s; display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-nav-back:hover { border-color: var(--auth-blue); background: rgba(31,60,136,0.04); }
    .btn-nav-next {
        background: var(--auth-blue); color: white; border: none; border-radius: 10px;
        padding: 12px 32px; font-weight: 700; font-size: 0.82rem; transition: all 0.3s;
        box-shadow: 0 6px 16px -4px rgba(31,60,136,0.25); display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-nav-next:hover { transform: translateY(-2px); box-shadow: 0 10px 24px -6px rgba(31,60,136,0.35); background: #172E6F; }

    /* Step Panels */
    .step-panel { display: none; animation: panelIn 0.45s cubic-bezier(0.4,0,0.2,1) forwards; }
    .step-panel.active { display: block; }
    @keyframes panelIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

    /* Alert */
    .tech-alert-box { background: white; border-radius: 12px; font-weight: 600; font-size: 0.85rem; border: 1px solid #e2e8f0 !important; }

    /* Reveal */
    .reveal { opacity: 0; transition: all 0.8s cubic-bezier(0.165, 0.84, 0.44, 1); }
    .reveal-left { transform: translateX(-20px); }
    .reveal-up { transform: translateY(20px); }
    .reveal.active { opacity: 1; transform: translate(0); }
    .stagger-2 { transition-delay: 0.15s; }

    /* Feature Points */
    .feature-point-item { display: flex; align-items: center; gap: 14px; padding: 10px 0; }
    .feature-point-icon {
        width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; flex-shrink: 0;
    }
    .feature-point-icon.gold-bg { background: var(--auth-gold); color: var(--auth-blue); }
    .feature-point-icon.blue-bg { background: var(--auth-blue); color: white; }
    .feature-point-icon.green-bg { background: #10b981; color: white; }

    /* Password Strength */
    .pw-strength-bar { height: 4px; border-radius: 4px; background: #e2e8f0; margin-top: 8px; overflow: hidden; transition: all 0.3s; }
    .pw-strength-bar .fill { height: 100%; border-radius: 4px; transition: all 0.4s; width: 0%; }
    .pw-strength-bar .fill.weak { width: 33%; background: #ef4444; }
    .pw-strength-bar .fill.medium { width: 66%; background: #f59e0b; }
    .pw-strength-bar .fill.strong { width: 100%; background: #10b981; }
    .pw-strength-text { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

    @media (max-width: 991.98px) {
        .branding-side { display: none !important; }
        .glass-morph-card { border-radius: 16px; }
    }
    @media (max-width: 480px) {
        .reg-steps { max-width: 260px; }
        .reg-step-node { width: 36px; height: 36px; font-size: 0.75rem; }
    }
    </style>
</head>
<body class="auth-body">
    <div class="auth-page-wrapper">
        <div class="tech-grid-overlay"></div>
        <div class="bg-blobs">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
        </div>

        <div class="container position-relative z-index-10">
            <div class="row align-items-center justify-content-center min-vh-100 py-3 py-lg-4">

                <!-- Left Branding -->
                <div class="col-lg-5 d-none d-lg-block branding-side reveal reveal-left">
                    <div class="pe-xl-4">
                        <div class="wave-propagation-container mb-3">
                            <div class="wave-propagation">
                                <span class="wave wave-1"></span>
                                <span class="wave wave-2"></span>
                                <span class="wave wave-3"></span>
                                <div class="core-point"></div>
                            </div>
                            <span class="ms-4" style="font-size: 0.65rem; color: var(--auth-blue); font-weight: 800; letter-spacing: 1px;">INSTITUTIONAL ONBOARDING</span>
                        </div>
                        <h2 class="fw-800 mb-2" style="font-size: 2.2rem; line-height: 1.1; letter-spacing: -1.5px; color: var(--auth-blue);">
                            Launch Your <span style="color: var(--auth-gold); text-shadow: 0 0 10px rgba(244,180,0,0.15);">Digital</span><br>School Platform
                        </h2>
                        <p class="mb-4 op-7 text-muted" style="line-height: 1.6; font-size: 0.92rem; max-width: 380px;">
                            Set up your institution's complete academic management system in under 5 minutes. Trusted by 2,000+ schools.
                        </p>
                        <div class="feature-point-item">
                            <div class="feature-point-icon gold-bg"><i class="fas fa-bolt"></i></div>
                            <div>
                                <h6 class="mb-0 fw-700 text-blue small">Instant Setup</h6>
                                <small class="text-muted" style="font-size: 0.72rem;">Ready in minutes, not days</small>
                            </div>
                        </div>
                        <div class="feature-point-item">
                            <div class="feature-point-icon blue-bg"><i class="fas fa-shield-halved"></i></div>
                            <div>
                                <h6 class="mb-0 fw-700 text-blue small">Bank-Grade Security</h6>
                                <small class="text-muted" style="font-size: 0.72rem;">256-bit encrypted data</small>
                            </div>
                        </div>
                        <div class="feature-point-item">
                            <div class="feature-point-icon green-bg"><i class="fas fa-gift"></i></div>
                            <div>
                                <h6 class="mb-0 fw-700 text-blue small">Free Trial Credits</h6>
                                <small class="text-muted" style="font-size: 0.72rem;">3,000 free credits on signup</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registration Card -->
                <div class="col-xl-5 col-lg-6 col-md-8 col-12 reveal reveal-up stagger-2">
                    <div class="glass-morph-card shadow-lg">
                        <div class="card-loader-line"></div>
                        <div class="p-4 p-md-5">
                            <!-- Logo -->
                            <div class="text-center mb-3">
                                <a href="index.php"><img src="<?php echo get_setting('platform_logo', 'img/logo.png'); ?>" alt="EduRemarks" style="max-height: 35px;"></a>
                            </div>

                            <header class="text-center mb-4">
                                <h3 class="fw-800" style="font-size: 1.5rem; letter-spacing: -0.5px; color: var(--auth-blue);">Create Account</h3>
                                <p class="text-muted small uppercase fw-700" style="font-size: 0.68rem; letter-spacing: 1px; opacity: 0.6;">Join the world's leading school portal</p>
                            </header>

                            <!-- Step Indicator -->
                            <div class="reg-steps">
                                <div class="reg-step-node active" id="step-ind-1">
                                    1
                                    <span class="reg-step-label">Details</span>
                                </div>
                                <div class="reg-step-line" id="step-line-1"><div class="fill"></div></div>
                                <div class="reg-step-node" id="step-ind-2">
                                    2
                                    <span class="reg-step-label">Role</span>
                                </div>
                                <div class="reg-step-line" id="step-line-2"><div class="fill"></div></div>
                                <div class="reg-step-node" id="step-ind-3">
                                    3
                                    <span class="reg-step-label">Setup</span>
                                </div>
                            </div>

                            <!-- Alert -->
                            <div id="regAlert" class="alert d-none tech-alert-box mb-3" role="alert"></div>

                            <form id="registrationForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo Security::csrf_token(); ?>">

                                <!-- STEP 1: Personal Details -->
                                <div class="step-panel active" id="step1">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">Full Name</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-user field-icon"></i>
                                                    <input type="text" class="form-control tech-field" name="full_name" placeholder="e.g. John Doe" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">Email Address</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-envelope field-icon"></i>
                                                    <input type="email" class="form-control tech-field" name="email" placeholder="e.g. john@school.com" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">Phone Number</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-phone field-icon"></i>
                                                    <input type="tel" class="form-control tech-field" name="phone" placeholder="e.g. +234 800 000 0000" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">Create Password</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-lock field-icon"></i>
                                                    <input type="password" class="form-control tech-field" name="password" id="regPassword" placeholder="Min. 8 characters" required minlength="8">
                                                    <button type="button" class="btn-eye-toggle" onclick="togglePw('regPassword', this)">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </button>
                                                </div>
                                                <div class="pw-strength-bar"><div class="fill" id="pwFill"></div></div>
                                                <div class="pw-strength-text" id="pwText"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="button" class="btn-nav-next" onclick="goStep(2)">Continue <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></button>
                                    </div>
                                </div>

                                <!-- STEP 2: Role Selection -->
                                <div class="step-panel" id="step2">
                                    <div class="row g-3 justify-content-center">
                                        <div class="col-sm-6">
                                            <div class="role-card" onclick="selectRole('owner')" id="roleCardOwner">
                                                <div class="role-check"><i class="fas fa-check"></i></div>
                                                <div class="role-icon owner-icon"><i class="fas fa-school"></i></div>
                                                <h6 class="fw-800 mb-1" style="color: var(--auth-blue); font-size: 1rem;">School Owner</h6>
                                                <p class="text-muted small mb-0" style="font-size: 0.78rem;">Register and manage your institution(s)</p>
                                                <input type="radio" name="role" value="owner" id="role_owner" class="d-none" required>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="role-card" onclick="selectRole('staff')" id="roleCardStaff">
                                                <div class="role-check"><i class="fas fa-check"></i></div>
                                                <div class="role-icon staff-icon"><i class="fas fa-user-tie"></i></div>
                                                <h6 class="fw-800 mb-1" style="color: var(--auth-blue); font-size: 1rem;">School Staff</h6>
                                                <p class="text-muted small mb-0" style="font-size: 0.78rem;">Join an existing school as staff</p>
                                                <input type="radio" name="role" value="staff" id="role_staff" class="d-none" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <button type="button" class="btn-nav-back" onclick="goStep(1)"><i class="fas fa-arrow-left" style="font-size:0.7rem;"></i> Back</button>
                                        <button type="button" class="btn-nav-next" onclick="goStep(3)">Continue <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></button>
                                    </div>
                                </div>

                                <!-- STEP 3A: Owner School Setup -->
                                <div class="step-panel" id="step3-owner">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">Official School Name</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-building field-icon"></i>
                                                    <input type="text" class="form-control tech-field" name="school_name" placeholder="e.g. EduRemarks Academy">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-field">
                                                <label class="field-label">School Type</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-graduation-cap field-icon"></i>
                                                    <select class="form-select tech-field" name="school_type" style="padding-left: 48px !important;">
                                                        <option selected disabled value="">Choose type...</option>
                                                        <option>Nursery & Primary</option>
                                                        <option>Secondary / High School</option>
                                                        <option>Tertiary / Vocational</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">School Address</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-map-marker-alt field-icon"></i>
                                                    <input type="text" class="form-control tech-field" name="school_address" placeholder="e.g. 123 Education Way, Lagos">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <button type="button" class="btn-nav-back" onclick="goStep(2)"><i class="fas fa-arrow-left" style="font-size:0.7rem;"></i> Back</button>
                                        <button type="submit" class="btn-register-submit" id="submitBtn">
                                            <span class="btn-inner"><span class="txt">Complete Registration</span><i class="fas fa-check-circle ms-2"></i></span>
                                            <div class="btn-shimmer"></div>
                                        </button>
                                    </div>
                                </div>

                                <!-- STEP 3B: Staff Join School -->
                                <div class="step-panel" id="step3-staff">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">Search for Your School</label>
                                                <div class="field-input-wrapper school-search-wrap">
                                                    <i class="fas fa-search field-icon"></i>
                                                    <input type="text" class="form-control tech-field" id="school_search" placeholder="Start typing school name..." autocomplete="off">
                                                    <div id="school_search_results"></div>
                                                    <input type="hidden" name="school_id" id="selected_school_id">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-field">
                                                <label class="field-label">School Unique ID</label>
                                                <div class="field-input-wrapper">
                                                    <i class="fas fa-id-card field-icon"></i>
                                                    <input type="text" class="form-control tech-field" name="unique_school_id" id="unique_school_id" placeholder="e.g. ER445324QR">
                                                </div>
                                                <small class="text-muted mt-1 d-block" style="font-size: 0.72rem;"><i class="fas fa-info-circle me-1"></i> Request this code from your school administrator.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-4">
                                        <button type="button" class="btn-nav-back" onclick="goStep(2)"><i class="fas fa-arrow-left" style="font-size:0.7rem;"></i> Back</button>
                                        <button type="submit" class="btn-register-submit" id="submitBtnStaff">
                                            <span class="btn-inner"><span class="txt">Submit Request</span><i class="fas fa-paper-plane ms-2"></i></span>
                                            <div class="btn-shimmer"></div>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Login Link -->
                            <div class="text-center mt-4 pt-3" style="border-top: 1px solid rgba(31,60,136,0.06);">
                                <p class="mb-0 text-muted" style="font-size: 0.78rem;">
                                    Already have an account? <a href="login.php" class="fw-700 text-decoration-none" style="color: var(--auth-blue);">Sign in here</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Overlay -->
    <div id="process-overlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.88); backdrop-filter:blur(10px); z-index:10000; align-items:center; justify-content:center; flex-direction:column;">
        <div style="position:relative; width:80px; height:80px; margin-bottom:25px;">
            <div style="position:absolute; width:100%; height:100%; border:4px solid rgba(244,180,0,0.1); border-top:4px solid var(--auth-gold); border-radius:50%; animation: auth-spin 1s linear infinite;"></div>
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:12px; height:12px; background:var(--auth-gold); border-radius:50%; box-shadow:0 0 15px var(--auth-gold);"></div>
        </div>
        <div class="text-center">
            <h5 class="text-white fw-900 mb-2 uppercase" style="font-size:0.9rem; letter-spacing:3px;">PROCESSING...</h5>
            <p class="text-white opacity-50" style="font-size:0.65rem; letter-spacing:1px;">Configuring Your Institutional Node</p>
        </div>
    </div>

    <style>@keyframes auth-spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }</style>

    <!-- Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-morph-card" style="border-radius:20px;">
                <div class="modal-body text-center p-5">
                    <div id="modalIcon" class="mb-3" style="font-size:3.5rem;"></div>
                    <h4 id="modalTitle" class="fw-800 mb-2" style="color: var(--auth-blue);"></h4>
                    <p id="modalMessage" class="text-muted mb-4 small"></p>
                    <button type="button" class="btn-register-submit" data-bs-dismiss="modal" style="max-width:200px;">
                        <span class="btn-inner"><span class="txt">Done</span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentStep = 1;
    let selectedRole = '';

    // Reveal
    setTimeout(() => { document.querySelectorAll('.reveal').forEach(el => el.classList.add('active')); }, 100);

    // Password Toggle
    function togglePw(id, btn) {
        const inp = document.getElementById(id);
        const icon = btn.querySelector('i');
        if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye'; btn.style.color = 'var(--auth-blue)'; }
        else { inp.type = 'password'; icon.className = 'fas fa-eye-slash'; btn.style.color = '#a0aec0'; }
    }

    // Password Strength
    document.getElementById('regPassword')?.addEventListener('input', function() {
        const v = this.value;
        const fill = document.getElementById('pwFill');
        const txt = document.getElementById('pwText');
        if (v.length === 0) { fill.className = 'fill'; txt.textContent = ''; return; }
        let score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        if (score <= 1) { fill.className = 'fill weak'; txt.textContent = 'Weak'; txt.style.color = '#ef4444'; }
        else if (score <= 2) { fill.className = 'fill medium'; txt.textContent = 'Fair'; txt.style.color = '#f59e0b'; }
        else { fill.className = 'fill strong'; txt.textContent = 'Strong'; txt.style.color = '#10b981'; }
    });

    // Step Navigation
    function goStep(n) {
        if (n === 2 && currentStep === 1) {
            const fields = document.querySelectorAll('#step1 input[required]');
            let ok = true;
            fields.forEach(f => {
                if (!f.value.trim()) { ok = false; f.style.borderColor = '#ef4444'; f.addEventListener('input', () => f.style.borderColor = '', {once:true}); }
                else { f.style.borderColor = ''; }
            });
            if (!ok) { showAlert('Please fill in all required fields.', 'warning'); return; }
        }
        if (n === 3 && !selectedRole) { showAlert('Please select your role to continue.', 'warning'); return; }
        if (n === 3 && selectedRole === 'owner') {
            document.getElementById('step3-owner').classList.add('active');
            document.getElementById('step3-staff').classList.remove('active');
        } else if (n === 3 && selectedRole === 'staff') {
            document.getElementById('step3-staff').classList.add('active');
            document.getElementById('step3-owner').classList.remove('active');
        }

        document.getElementById('step' + currentStep).classList.remove('active');

        // Update indicators
        const prevInd = document.getElementById('step-ind-' + currentStep);
        prevInd.classList.remove('active');
        if (n > currentStep) prevInd.classList.add('completed');

        // Lines
        if (n > currentStep && currentStep < 3) {
            document.getElementById('step-line-' + currentStep).classList.add('filled');
        }
        if (n < currentStep && currentStep <= 3) {
            document.getElementById('step-ind-' + currentStep).classList.remove('completed');
            if (currentStep <= 3) document.getElementById('step-line-' + (currentStep - 1))?.classList.remove('filled');
        }

        const newInd = document.getElementById('step-ind-' + n);
        newInd.classList.add('active');
        if (n < currentStep) newInd.classList.remove('completed');

        currentStep = n;

        if (n === 1) document.getElementById('step1').classList.add('active');
        if (n === 2) document.getElementById('step2').classList.add('active');
    }

    // Role Selection
    function selectRole(role) {
        selectedRole = role;
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        document.getElementById('role_' + role).checked = true;
        document.getElementById('roleCard' + role.charAt(0).toUpperCase() + role.slice(1)).classList.add('selected');
    }

    // School Search
    const schoolSearch = document.getElementById('school_search');
    const searchResults = document.getElementById('school_search_results');
    schoolSearch?.addEventListener('input', function() {
        const q = this.value;
        if (q.length < 2) { searchResults.style.display = 'none'; return; }
        fetch('ajax/get_schools.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.schools.length > 0) {
                    searchResults.innerHTML = '';
                    data.schools.forEach(s => {
                        const d = document.createElement('div');
                        d.className = 'result-item';
                        d.innerHTML = '<strong>' + s.school_name + '</strong> <span class="text-muted ms-2" style="font-size:0.75rem;">(' + s.unique_id.substring(0,4) + '***)</span>';
                        d.onclick = () => {
                            schoolSearch.value = s.school_name;
                            document.getElementById('selected_school_id').value = s.id;
                            searchResults.style.display = 'none';
                        };
                        searchResults.appendChild(d);
                    });
                    searchResults.style.display = 'block';
                } else { searchResults.style.display = 'none'; }
            });
    });
    document.addEventListener('click', e => {
        if (!schoolSearch.contains(e.target) && !searchResults.contains(e.target)) searchResults.style.display = 'none';
    });

    // Alert
    function showAlert(msg, type) {
        const el = document.getElementById('regAlert');
        el.className = 'alert tech-alert-box mb-3 d-block text-' + type;
        el.innerHTML = '<i class="fas fa-' + (type === 'warning' ? 'exclamation-triangle' : 'info-circle') + ' me-2"></i> ' + msg;
        setTimeout(() => el.classList.add('d-none'), 4000);
    }

    // Form Submit
    function handleSubmit(e, btnId) {
        e.preventDefault();
        const btn = document.getElementById(btnId);
        const orig = btn.innerHTML;
        const overlay = document.getElementById('process-overlay');
        overlay.style.display = 'flex';
        btn.disabled = true;

        fetch('ajax/register.php', { method: 'POST', body: new FormData(e.target) })
        .then(r => r.json())
        .then(data => {
            overlay.style.display = 'none';
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            const icon = document.getElementById('modalIcon');
            const title = document.getElementById('modalTitle');
            const msg = document.getElementById('modalMessage');

            if (data.success) {
                icon.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981;"></i>';
                title.innerText = 'Registration Successful!';
                msg.innerText = data.message;
                e.target.reset();
                setTimeout(() => { window.location.href = 'login.php'; }, 4000);
            } else {
                icon.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444;"></i>';
                title.innerText = 'Registration Failed';
                msg.innerText = data.message;
            }
            modal.show();
        })
        .catch(() => {
            overlay.style.display = 'none';
            showAlert('A system error occurred. Please try again.', 'danger');
        })
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
    }

    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        const ownerPanel = document.getElementById('step3-owner');
        const staffPanel = document.getElementById('step3-staff');
        if (ownerPanel.classList.contains('active')) handleSubmit(e, 'submitBtn');
        else if (staffPanel.classList.contains('active')) handleSubmit(e, 'submitBtnStaff');
        else e.preventDefault();
    });
    </script>
</body>
</html>
