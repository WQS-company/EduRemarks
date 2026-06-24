<?php
// super_admin/content.php - Platform Content Orchestration
// Fixed include path and standardized layout
require_once 'auth_check.php';

// Fetch existing settings with defensive checks
try {
    $settings_stmt = $pdo->query("SELECT * FROM platform_settings");
    $settings = [];
    foreach ($settings_stmt->fetchAll() as $s) $settings[$s['setting_key']] = $s['setting_value'];
} catch (Exception $e) {
    $settings = [];
    $db_error = $e->getMessage();
}

// Fetch Services
try {
    $services = $pdo->query("SELECT * FROM platform_services ORDER BY sort_order ASC")->fetchAll();
} catch (Exception $e) {
    $services = [];
}

// Fetch Hero Slides
try {
    $hero_slides = $pdo->query("SELECT * FROM platform_hero_slides ORDER BY sort_order ASC")->fetchAll();
} catch (Exception $e) {
    // If the table doesn't exist yet, we'll just show an empty list
    $hero_slides = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Content | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="<?php echo (string)$platform_favicon; ?>" type="image/x-icon">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .tiny-text { font-size: 0.75rem; }
        .fw-800 { font-weight: 800; }
        .text-blue { color: #1e3a8a; }

        .nav-pills .nav-link { color: #64748b; background: transparent; transition: 0.3s; }
        .nav-pills .nav-link.active { background: #3b82f6 !important; color: white !important; }
        
        .hero-slide-card { border-radius: 15px; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,0.05); transition: 0.3s; }
        .hero-slide-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }

        .btn-xs { padding: 0.25rem 0.6rem; font-size: 0.65rem; }
        .extra-small { font-size: 0.65rem; }
        .transition-base { transition: all 0.2s ease; }
        .hover-shadow:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important; }

        .image-preview-node { width: 100%; height: 180px; object-fit: cover; border-radius: 12px; border: 2px dashed #e2e8f0; margin-bottom: 10px; }
        .logo-preview-node { width: 60px; height: 60px; object-fit: contain; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 15px; }
        }

        @media (max-width: 576px) {
            #cpContentTabs {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 8px !important;
            }
            #cpContentTabs .nav-item {
                flex: unset !important;
                margin: 0 !important;
            }
            #cpContentTabs .nav-link {
                width: 100% !important;
                padding: 8px 6px !important;
                font-size: 0.72rem !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        @media (max-width: 320px) {
            #cpContentTabs .nav-link {
                font-size: 0.65rem !important;
                padding: 6px 4px !important;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h4 class="fw-800 mb-0">Platform Orchestration</h4>
            <p class="text-muted small">Manage global landing page content and slides</p>
        </div>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> Content node communication error: <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <!-- TABS Navigation -->
    <ul class="nav nav-pills mb-4 gap-2" id="cpContentTabs">
        <li class="nav-item">
            <button class="nav-link active rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="tab" data-bs-target="#tabHero">Hero & About</button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="tab" data-bs-target="#tabServices">Services (Why Us)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="tab" data-bs-target="#tabFooter">Footer & Socials</button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-bold shadow-sm text-premium-gold" data-bs-toggle="tab" data-bs-target="#tabLegal">Governance & Policy</button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="tab" data-bs-target="#tabSystem" style="background: #fefce8; color: #854d0e;"><i class="fas fa-server me-2"></i>Resource Controls</button>
        </li>
    </ul>

    <div class="tab-content mt-4">
        <!-- HERO & ABOUT -->
        <div class="tab-pane fade show active" id="tabHero">
            <div class="glass-card p-4 border-0 shadow-sm">
               <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                   <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                       <i class="fas fa-magic fa-lg"></i>
                   </div>
                   <div>
                       <h6 class="fw-800 mb-0">Hero Atmosphere & About Narrative</h6>
                       <p class="text-muted tiny-text mb-0">Define the first impression for visitors entering the portal.</p>
                   </div>
               </div>
               <form id="heroForm" class="row g-4" enctype="multipart/form-data">
                  <div class="col-md-12">
                     <div class="d-flex flex-wrap align-items-center gap-4 p-4 bg-light rounded-4 border-dashed mb-2">
                        <div class="text-center me-md-4 mb-3 mb-md-0">
                            <label class="extra-small fw-bold d-block mb-2 uppercase opacity-50">Platform Logo</label>
                            <img src="../<?php echo get_setting('platform_logo', 'img/logo.png'); ?>" class="logo-preview-node mb-2 shadow-sm" id="logoPreview" style="width: 80px; height: 80px;">
                            <input type="file" name="platform_logo" class="d-none" id="logoInput" accept="image/*">
                            <button type="button" class="btn btn-xs btn-white border rounded-pill shadow-sm d-block mx-auto" onclick="document.getElementById('logoInput').click()">Change Logo</button>
                        </div>
                        <div class="text-center pe-md-4 border-md-end mb-3 mb-md-0">
                            <label class="extra-small fw-bold d-block mb-2 uppercase opacity-50">Sidebar Logo</label>
                            <img src="../<?php echo get_setting('sidebar_logo', 'img/logo.png'); ?>" class="logo-preview-node mb-2 shadow-sm" id="sidebarLogoPreview" style="width: 80px; height: 80px;">
                            <input type="file" name="sidebar_logo" class="d-none" id="sidebarLogoInput" accept="image/*">
                            <button type="button" class="btn btn-xs btn-white border rounded-pill shadow-sm d-block mx-auto" onclick="document.getElementById('sidebarLogoInput').click()">Change Sidebar</button>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-800 mb-1">Institutional Identity & Brand Roster</h6>
                            <p class="tiny-text text-muted mb-0">Platform Logo: Used for Favicons and Landing Page Header.</p>
                            <p class="tiny-text text-muted mb-0 mt-1">Sidebar Logo: Specifically optimized for the vertical dashboard navigation.</p>
                        </div>
                     </div>
                  </div>

                  <div class="col-md-12">
                     <label class="small fw-bold mb-2 text-dark opacity-75">MAIN HEADLINE (H1)</label>
                     <input type="text" name="hero_title" class="form-control rounded-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['hero_title'] ?? 'The Future of School Management'); ?>">
                  </div>
                  <div class="col-md-12">
                     <label class="small fw-bold mb-2 text-dark opacity-75">SUPPORTING SUBTEXT</label>
                     <textarea name="hero_subtitle" class="form-control rounded-3 border-light-subtle shadow-sm" rows="2"><?php echo htmlspecialchars($settings['hero_subtitle'] ?? 'EduRemarks empowers institutions with world-class automation.'); ?></textarea>
                  </div>
                  <div class="col-md-6 mt-4 pt-4 border-top">
                     <label class="small fw-bold mb-2 text-dark opacity-75">ABOUT US MISSION STATEMENT</label>
                     <textarea name="about_content" class="form-control rounded-3 border-light-subtle shadow-sm" rows="5"><?php echo htmlspecialchars($settings['about_content'] ?? 'EduRemarks is a localized yet globalized academic ERP built for the modern educational ecosystem.'); ?></textarea>
                  </div>
                  <div class="col-md-6 mt-4 pt-4 border-top">
                     <label class="small fw-bold mb-2 text-dark opacity-75">ABOUT SECTION VISUAL</label>
                     <div class="position-relative">
                        <img src="../<?php echo get_setting('about_image', 'img/about.png'); ?>" class="image-preview-node" id="aboutPreview">
                        <input type="file" name="about_image" class="d-none" id="aboutInput" accept="image/*">
                        <button type="button" class="btn btn-xs btn-white border rounded-pill shadow-sm position-absolute bottom-0 end-0 m-3" onclick="document.getElementById('aboutInput').click()">
                            <i class="fas fa-camera me-1"></i> Replace Image
                        </button>
                     </div>
                  </div>
                  <div class="col-12 text-end">
                     <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold py-2 shadow-sm btn-sm w-mobile-100">
                        <i class="fas fa-sync-alt me-2 tiny-text"></i>REDEPLOY LAYOUT
                     </button>
                  </div>
               </form>

               <div class="mt-5 pt-4 border-top">
                  <div class="d-flex justify-content-between align-items-center mb-4">
                     <h6 class="fw-bold mb-0 text-blue small"><i class="fas fa-images me-2 text-primary opacity-50"></i>HERO SLIDESHOW NODES</h6>
                     <button class="btn btn-xs btn-outline-primary rounded-pill px-3 fw-bold btn-sm" onclick="newHeroSlide()">
                        <i class="fas fa-plus me-1 tiny-text"></i> Add Slide
                     </button>
                  </div>
                  <div class="row g-3">
                     <?php if(empty($hero_slides)): ?>
                        <div class="col-12 text-center py-4 opacity-50 small italic">No secondary slides configured. The main hero remains active.</div>
                     <?php else: foreach($hero_slides as $sl): ?>
                     <div class="col-md-4">
                        <div class="hero-slide-card p-3 position-relative shadow-sm border-0 h-100 bg-light-subtle">
                           <div class="d-flex flex-column h-100">
                               <div class="fw-800 extra-small text-blue mb-1"><?php echo htmlspecialchars($sl['caption']); ?></div>
                               <div class="tiny-text text-muted mb-3"><i class="fas fa-link me-1 opacity-50"></i> <?php echo $sl['image_path'] ?: 'Default Asset'; ?></div>
                               <div class="mt-auto text-end">
                                   <button class="btn btn-xs btn-white border rounded-circle shadow-sm" onclick="deleteHeroSlide(<?php echo $sl['id']; ?>)">
                                      <i class="fas fa-trash-alt text-danger extra-small"></i>
                                   </button>
                               </div>
                           </div>
                        </div>
                     </div>
                     <?php endforeach; endif; ?>
                  </div>
               </div>
            </div>
        </div>

        <!-- SERVICES -->
        <div class="tab-pane fade" id="tabServices">
            <div class="glass-card p-4 border-0 shadow-sm">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                   <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-3">
                       <i class="fas fa-rocket fa-lg"></i>
                   </div>
                   <div>
                       <h6 class="fw-800 mb-0">Functional Modules (Service Layer)</h6>
                       <p class="text-muted tiny-text mb-0">Showcase the core pillars and features of the EduRemarks platform.</p>
                   </div>
                   <button class="btn btn-xs btn-outline-success rounded-pill px-3 fw-bold btn-sm ms-auto" onclick="newService()">
                        <i class="fas fa-plus me-1 tiny-text"></i> Create Node
                    </button>
                </div>
                <div class="row g-3">
                   <?php if(empty($services)): ?>
                      <div class="col-12 text-center py-5 opacity-50 italic small">No service nodes configured.</div>
                   <?php else: foreach($services as $serv): ?>
                   <div class="col-md-6 col-lg-4">
                      <div class="p-4 bg-white rounded-4 border position-relative h-100 shadow-sm transition-base hover-shadow">
                         <div class="icon-box bg-primary bg-opacity-10 text-primary p-3 rounded-circle mb-3 d-inline-flex border border-primary border-opacity-25 shadow-sm" style="width: 44px; height: 44px; align-items:center; justify-content:center;">
                            <i class="<?php echo $serv['icon'] ?? 'fas fa-cube'; ?> small"></i>
                         </div>
                         <div class="fw-800 small text-blue"><?php echo htmlspecialchars($serv['title']); ?></div>
                         <div class="tiny-text opacity-75 mt-2 line-clamp-3" style="line-height: 1.5;"><?php echo htmlspecialchars($serv['description']); ?></div>
                         <div class="dropdown position-absolute top-0 end-0 p-2">
                             <button class="btn btn-xs btn-light rounded-circle shadow-sm" data-bs-toggle="dropdown" style="width:28px; height:28px; padding:0;"><i class="fas fa-ellipsis-v extra-small text-muted"></i></button>
                             <ul class="dropdown-menu border-0 shadow-lg p-2 rounded-3">
                                <li><a class="dropdown-item extra-small rounded-2 py-2" href="#" onclick='editService(<?php echo htmlspecialchars(json_encode($serv), ENT_QUOTES, 'UTF-8'); ?>)'>
                                    <i class="fas fa-edit me-2 text-primary"></i> Modify Feature</a></li>
                                <li><hr class="dropdown-divider opacity-50"></li>
                                <li><a class="dropdown-item extra-small text-danger rounded-2 py-2" href="#" onclick="deleteService(<?php echo $serv['id']; ?>)">
                                    <i class="fas fa-trash-alt me-2"></i> Wipe Record</a></li>
                             </ul>
                         </div>
                      </div>
                   </div>
                   <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- FOOTER & SOCIALS -->
        <div class="tab-pane fade" id="tabFooter">
            <div class="glass-card p-4 border-0 shadow-sm">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                   <div class="bg-dark bg-opacity-10 text-dark p-2 rounded-3 me-3">
                       <i class="fas fa-fingerprint fa-lg"></i>
                   </div>
                   <div>
                       <h6 class="fw-800 mb-0">Global Branding & Digital Footprint</h6>
                       <p class="text-muted tiny-text mb-0">Configure contact coordinates and social architecture.</p>
                   </div>
               </div>
               <form id="footerForm" class="row g-4">
                  <div class="col-md-6">
                     <label class="small fw-bold mb-2 text-dark opacity-75">OFFICIAL CONTACT HOTLINE</label>
                     <input type="text" name="footer_phone" class="form-control rounded-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['footer_phone'] ?? '+234 000 000 0000'); ?>">
                  </div>
                  <div class="col-md-6">
                     <label class="small fw-bold mb-2 text-dark opacity-75">CENTRAL SUPPORT EMAIL</label>
                     <input type="email" name="footer_email" class="form-control rounded-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['footer_email'] ?? 'support@eduremarks.com'); ?>">
                  </div>
                  <div class="col-md-12">
                     <label class="small fw-bold mb-2 text-dark opacity-75">INSTITUTIONAL HEADQUARTERS ADDRESS</label>
                     <textarea name="footer_address" class="form-control rounded-3 border-light-subtle shadow-sm" rows="2"><?php echo htmlspecialchars($settings['footer_address'] ?? 'Lagos, Nigeria'); ?></textarea>
                  </div>
                  <div class="col-md-4">
                     <label class="small fw-bold mb-2 text-dark opacity-75"><i class="fab fa-twitter me-1 text-info"></i> TWITTER (X) HANDLE</label>
                     <input type="text" name="social_twitter" class="form-control rounded-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? '#'); ?>">
                  </div>
                  <div class="col-md-4">
                     <label class="small fw-bold mb-2 text-dark opacity-75"><i class="fab fa-facebook me-1 text-primary"></i> FACEBOOK PAGE</label>
                     <input type="text" name="social_facebook" class="form-control rounded-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? '#'); ?>">
                  </div>
                  <div class="col-md-4">
                     <label class="small fw-bold mb-2 text-dark opacity-75"><i class="fab fa-instagram me-1 text-danger"></i> INSTAGRAM PROFILE</label>
                     <input type="text" name="social_instagram" class="form-control rounded-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? '#'); ?>">
                  </div>
                  <div class="col-12 text-end">
                     <button type="submit" class="btn btn-dark rounded-pill px-4 fw-bold py-2 shadow-sm btn-sm w-mobile-100">
                        <i class="fas fa-lock me-2 tiny-text opacity-50"></i>SYNCHRONIZE FOOTER
                     </button>
                  </div>
               </form>
            </div>
        </div>

        <!-- GOVERNANCE & POLICIES -->
        <div class="tab-pane fade" id="tabLegal">
            <div class="glass-card p-4 border-0 shadow-sm">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                   <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                       <i class="fas fa-gavel fa-lg"></i>
                   </div>
                   <div>
                       <h6 class="fw-800 mb-0">Legal Architecture & Platform Protocols</h6>
                       <p class="text-muted tiny-text mb-0">Orchestrate the institutional legal frameworks and refund policies.</p>
                   </div>
                </div>
                <form id="legalForm" class="row g-4">
                   <div class="col-md-12">
                      <label class="small fw-bold mb-2 text-dark opacity-75 d-flex align-items-center"><i class="fas fa-scroll me-2 text-primary opacity-50"></i> TERMS OF SERVICE ARCHIVE</label>
                      <textarea name="terms_content" class="form-control rounded-3 border-light-subtle shadow-sm" rows="6" placeholder="Define the core platform governance..."><?php echo htmlspecialchars($settings['terms_content'] ?? ''); ?></textarea>
                      <div class="tiny-text mt-2 text-muted">This content will be synchronized with the institutional Terms page.</div>
                   </div>
                   <div class="col-md-12">
                      <label class="small fw-bold mb-2 text-dark opacity-75 d-flex align-items-center"><i class="fas fa-user-shield me-2 text-success opacity-50"></i> PRIVACY ORCHESTRATION LAYER</label>
                      <textarea name="privacy_policy" class="form-control rounded-3 border-light-subtle shadow-sm" rows="6" placeholder="Define data protection standards..."><?php echo htmlspecialchars($settings['privacy_policy'] ?? ''); ?></textarea>
                   </div>
                   <div class="col-md-12">
                      <label class="small fw-bold mb-2 text-dark opacity-75 d-flex align-items-center"><i class="fas fa-undo-alt me-2 text-danger opacity-50"></i> INSTITUTIONAL REFUND DOCTRINE</label>
                      <textarea name="refund_policy" class="form-control rounded-3 border-light-subtle shadow-sm" rows="6" placeholder="Define fiscal return and credit protocols..."><?php echo htmlspecialchars($settings['refund_policy'] ?? ''); ?></textarea>
                   </div>
                   <div class="col-12 text-end">
                      <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold py-2 shadow-sm btn-sm w-mobile-100">
                         <i class="fas fa-fingerprint me-2 tiny-text"></i>SYNCHRONIZE GOVERNANCE
                      </button>
                   </div>
                </form>
            </div>
        </div>

        <!-- SYSTEM RESOURCE CONTROLS -->
        <div class="tab-pane fade" id="tabSystem">
            <div class="glass-card p-4 border-0 shadow-sm">
                <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                   <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3 me-3">
                       <i class="fas fa-hdd fa-lg"></i>
                   </div>
                   <div>
                       <h6 class="fw-800 mb-0">Global Resource & Media Protocols</h6>
                       <p class="text-muted tiny-text mb-0">Control institutional upload boundaries and credit economics.</p>
                   </div>
                </div>
                <form id="systemForm" class="row g-4">
                   <div class="col-md-6">
                      <label class="small fw-bold mb-2 text-dark opacity-75">MAX UPLOAD LIMIT (MB)</label>
                      <div class="input-group">
                         <span class="input-group-text bg-light border-0"><i class="fas fa-file-upload text-muted tiny-text"></i></span>
                         <input type="number" name="max_upload_size" class="form-control rounded-end-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['max_upload_size'] ?? '2'); ?>" step="0.1" min="0.1">
                      </div>
                      <div class="tiny-text mt-2 text-muted">Defines the maximum allowed size for Student/Staff profile images.</div>
                   </div>
                   <div class="col-md-6">
                      <label class="small fw-bold mb-2 text-dark opacity-75">IMAGE UPLOAD CREDIT COST</label>
                      <div class="input-group">
                         <span class="input-group-text bg-light border-0"><i class="fas fa-coins text-warning tiny-text"></i></span>
                         <input type="number" name="credit_cost_image_upload" class="form-control rounded-end-3 border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($settings['credit_cost_image_upload'] ?? '50'); ?>">
                      </div>
                      <div class="tiny-text mt-2 text-muted">Credits deducted from school balance per successful image commission.</div>
                   </div>
                   <div class="col-12 text-end">
                      <button type="submit" class="btn btn-warning text-dark rounded-pill px-5 fw-bold py-2 shadow-sm btn-sm w-mobile-100">
                         <i class="fas fa-save me-2 tiny-text"></i>COMMIT RESOURCE SETTINGS
                      </button>
                   </div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Hero Slide Modal -->
<div class="modal fade" id="heroSlideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="heroSlideForm">
            <div class="modal-header border-0 p-4 bg-light">
                <h6 class="fw-bold mb-0">Commission Hero Slide Node</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="save">
                <div class="mb-3">
                    <label class="tiny-text fw-bold mb-1 opacity-75 uppercase">Key Display Caption</label>
                    <input type="text" name="caption" class="form-control form-control-sm rounded-2" required placeholder="e.g. Smart CBT Automation">
                </div>
                <div class="mb-3">
                    <label class="tiny-text fw-bold mb-1 opacity-75 uppercase">Visual Asset (Image Upload)</label>
                    <div class="bg-light p-3 rounded-3 text-center border-dashed position-relative">
                        <img id="modalSlidePreview" src="../img/hero.png" style="max-height: 100px; width: 100%; object-fit: cover; display: none;" class="rounded-2 mb-2">
                        <div id="modalSlidePlaceholder">
                            <i class="fas fa-cloud-upload-alt fa-2x opacity-25 mb-2"></i>
                            <p class="extra-small text-muted mb-0">Drag or click to upload</p>
                        </div>
                        <input type="file" name="slide_image" id="slideImageInput" class="position-absolute inset-0 opacity-0 cursor-pointer" accept="image/*" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="tiny-text fw-bold mb-1 opacity-75 uppercase">Priority Index (Sort Order)</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm rounded-2" value="0">
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm btn-sm">DEPLOY SLIDE NODE</button>
            </div>
        </form>
    </div>
</div>

<!-- Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="serviceForm">
            <div class="modal-header border-0 p-4 bg-light">
                <h6 class="fw-bold mb-0">Feature Node Management</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="servId">
                <div class="mb-3">
                    <label class="tiny-text fw-bold mb-1 opacity-75 uppercase">Icon Vector (FontAwesome Class)</label>
                    <input type="text" name="icon" id="servIcon" class="form-control form-control-sm rounded-2" placeholder="fas fa-microchip" required>
                    <a href="https://fontawesome.com/v6/search?m=free" target="_blank" class="extra-small mt-1 d-block text-primary text-decoration-none"><i class="fas fa-external-link-alt me-1"></i> Reference Vector Library</a>
                </div>
                <div class="mb-3">
                    <label class="tiny-text fw-bold mb-1 opacity-75 uppercase">Feature Designation (Title)</label>
                    <input type="text" name="title" id="servTitle" class="form-control form-control-sm rounded-2" placeholder="e.g. Result Forge" required>
                </div>
                <div class="mb-2">
                    <label class="tiny-text fw-bold mb-1 opacity-75 uppercase">Functional Value (Description)</label>
                    <textarea name="description" id="servDesc" class="form-control form-control-sm rounded-2" rows="3" placeholder="Explain the module utility..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" id="servSubmitBtn" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm btn-sm">SAVE FEATURE NODE</button>
            </div>
        </form>
    </div>
</div>


<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<!-- Core Dependencies -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const EDUREMARKS_CSRF_TOKEN = '<?php echo Security::csrf_token(); ?>';
    
    $(document).ready(function() {
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': EDUREMARKS_CSRF_TOKEN },
            data: { csrf_token: EDUREMARKS_CSRF_TOKEN }
        });
    });

    $('#heroForm, #footerForm, #legalForm, #systemForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Synchronizing content nodes...');
        
        let fd = new FormData(this);
        
        $.ajax({
            url: '../ajax/sa_save_settings.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                Spinner.hide();
                if(res.success) {
                    showSuccess('Platform Synced', 'Global content layers have been updated successfully.', { reload: true });
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function() {
                Spinner.hide();
                alert('Communication failure.');
            }
        });
    });

    // Preview Handlers
    $('#logoInput').change(function(){ readURL(this, '#logoPreview'); });
    $('#sidebarLogoInput').change(function(){ readURL(this, '#sidebarLogoPreview'); });
    $('#aboutInput').change(function(){ readURL(this, '#aboutPreview'); });
    $('#slideImageInput').change(function(){ 
        readURL(this, '#modalSlidePreview'); 
        $('#modalSlidePreview').show();
        $('#modalSlidePlaceholder').hide();
    });

    function readURL(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { $(previewId).attr('src', e.target.result); }
            reader.readAsDataURL(input.files[0]);
        }
    }

    const servModal = new bootstrap.Modal(document.getElementById('serviceModal'));
    const slideModal = new bootstrap.Modal(document.getElementById('heroSlideModal'));

    $('#serviceForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Saving feature node...');
        $.post('../ajax/sa_save_service.php', $(this).serialize(), function(res) {
            Spinner.hide();
            if(res.success) {
                servModal.hide();
                showSuccess('Feature Node Saved', 'The feature module has been synchronized.', { reload: true });
            } else {
                alert('Error: ' + res.message);
            }
        }, 'json').fail(function() {
            Spinner.hide();
            alert('Communication failure.');
        });
    });

    function newService() {
        document.getElementById('serviceForm').reset();
        document.getElementById('servId').value = '';
        document.getElementById('servSubmitBtn').innerText = 'COMMISSION NODE';
        servModal.show();
    }

    function editService(s) {
        document.getElementById('servId').value = s.id;
        document.getElementById('servIcon').value = s.icon;
        document.getElementById('servTitle').value = s.title;
        document.getElementById('servDesc').value = s.description;
        document.getElementById('servSubmitBtn').innerText = 'UPDATE NODE';
        servModal.show();
    }

    function newHeroSlide() {
        document.getElementById('heroSlideForm').reset();
        slideModal.show();
    }

    $('#heroSlideForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Commissioning hero slide...');
        
        let fd = new FormData(this);
        
        $.ajax({
            url: '../ajax/sa_save_hero_slide.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                Spinner.hide();
                if(res.success) {
                    slideModal.hide();
                    showSuccess('Slide Commissioned', 'New hero slide has been deployed.', { reload: true });
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function() {
                Spinner.hide();
                alert('Communication failure.');
            }
        });
    });

    function deleteHeroSlide(id) {
        if(confirm('Decommission this hero slide node?')) {
            Spinner.show('Removing...');
            $.post('../ajax/sa_save_hero_slide.php', { id: id, action: 'delete' }, function(res) {
                Spinner.hide();
                if(res.success) {
                    showSuccess('Node Decommissioned', 'Hero slide has been removed.', { reload: true });
                } else {
                    alert(res.message);
                }
            }, 'json').fail(function() {
                Spinner.hide();
                alert('Communication failure.');
            });
        }
    }

    function deleteService(id) {
        if(confirm('Wipe this feature node permanently?')) {
            Spinner.show('Purging...');
            $.post('../ajax/sa_save_service.php', { id: id, delete: true }, function(res) {
                Spinner.hide();
                if(res.success) {
                    showSuccess('Feature Purged', 'The feature node has been removed from the platform.', { reload: true });
                } else {
                    alert(res.message);
                }
            }, 'json').fail(function() {
                Spinner.hide();
                alert('Communication failure.');
            });
        }
    }
</script>
</body>
</html>
