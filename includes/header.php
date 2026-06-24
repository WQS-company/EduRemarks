<?php
// includes/header.php
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/config.php';
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: upgrade-insecure-requests;");

$current_page = basename($_SERVER['PHP_SELF']);

// Define global favicon for public pages if not already set by auth_check
if (!isset($platform_favicon)) {
    $path_prefix = $path_prefix ?? '';
    $sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
    $platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- SEO & Favicon -->
    <title><?php echo isset($pageTitle) ? $pageTitle . " | ".get_setting('hero_title', 'EduRemarks') : (get_setting('hero_title', 'EduRemarks') . " | Empowering Schools, Simplifying Management"); ?></title>
    <meta name="description" content="<?php echo $pageDescription ?? get_setting('hero_subtitle', 'EduRemarks is a localized yet globalized academic ERP built for the modern educational ecosystem.'); ?>">
    <meta name="keywords" content="School Management System, Result Management, CBT, Educational Software, Nigeria Schools, ERP, EduRemarks">
    <meta name="author" content="EduRemarks">
    <link rel="canonical" href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <link rel="icon" href="<?php echo (string)($platform_favicon ?? ''); ?>" type="image/x-icon">
    <?php include_once 'seo_schema.php'; ?>

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:title" content="<?php echo $pageTitle ?? get_setting('hero_title', 'EduRemarks'); ?>">
    <meta property="og:description" content="<?php echo $pageDescription ?? get_setting('hero_subtitle', 'EduRemarks empowers institutions with world-class automation.'); ?>">
    <meta property="og:image" content="<?php echo (strpos(get_setting('about_image'), 'http') === 0) ? get_setting('about_image') : 'http://' . $_SERVER['HTTP_HOST'] . '/' . get_setting('about_image', 'img/about.png'); ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="twitter:title" content="<?php echo $pageTitle ?? get_setting('hero_title', 'EduRemarks'); ?>">
    <meta property="twitter:description" content="<?php echo $pageDescription ?? get_setting('hero_subtitle', 'EduRemarks empowers institutions with world-class automation.'); ?>">
    <meta property="twitter:image" content="<?php echo (strpos(get_setting('about_image'), 'http') === 0) ? get_setting('about_image') : 'http://' . $_SERVER['HTTP_HOST'] . '/' . get_setting('about_image', 'img/about.png'); ?>">

    <!-- Core Assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $path_prefix ?? ''; ?>css/style.css">
    <script src="<?php echo $path_prefix ?? ''; ?>js/security_ui.js"></script>
</head>

<body>

    <?php include 'preloader.php'; ?>

    <!-- Background Clouds -->
    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
        <!-- Decorative SVG Clouds -->
        <svg class="svg-cloud cloud-drift-slow" style="top: 15%; width: 120px;" viewBox="0 0 24 24">
            <path
                d="M17.5,19c-3.6,0-6.5-2.9-6.5-6.5c0-0.4,0-0.8,0.1-1.2C9.2,12.1,8,13,8,14.5c0,1.9,1.6,3.5,3.5,3.5h6C18.9,18,20,16.9,20,15.5 C20,14.1,18.9,13,17.5,13c-0.3,0-0.6,0.1-0.9,0.2C15.8,10.1,13.1,8,10,8C6.7,8,4,10.7,4,14c0,3.3,2.7,6,6,6h7.5" />
        </svg>
        <svg class="svg-cloud cloud-drift-med" style="top: 45%; left: 20%; width: 180px; opacity: 0.3;"
            viewBox="0 0 24 24">
            <path
                d="M17.5,19c-3.6,0-6.5-2.9-6.5-6.5c0-0.4,0-0.8,0.1-1.2C9.2,12.1,8,13,8,14.5c0,1.9,1.6,3.5,3.5,3.5h6C18.9,18,20,16.9,20,15.5 C20,14.1,18.9,13,17.5,13c-0.3,0-0.6,0.1-0.9,0.2C15.8,10.1,13.1,8,10,8C6.7,8,4,10.7,4,14c0,3.3,2.7,6,6,6h7.5" />
        </svg>
        <svg class="svg-cloud cloud-drift-fast" style="top: 75%; left: 60%; width: 100px; opacity: 0.2;"
            viewBox="0 0 24 24">
            <path
                d="M17.5,19c-3.6,0-6.5-2.9-6.5-6.5c0-0.4,0-0.8,0.1-1.2C9.2,12.1,8,13,8,14.5c0,1.9,1.6,3.5,3.5,3.5h6C18.9,18,20,16.9,20,15.5 C20,14.1,18.9,13,17.5,13c-0.3,0-0.6,0.1-0.9,0.2C15.8,10.1,13.1,8,10,8C6.7,8,4,10.7,4,14c0,3.3,2.7,6,6,6h7.5" />
        </svg>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo get_setting('platform_logo', 'img/logo.png'); ?>" alt="EduRemarks Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'index.php' || $current_page == '') ? 'active' : ''; ?>" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="student/login.php"><i class="fas fa-user-graduate me-1"></i> Student Portal</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'features.php') ? 'active' : ''; ?>" href="features.php">Features</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'pricing.php') ? 'active' : ''; ?>" href="pricing.php">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'blog.php') ? 'active' : ''; ?>" href="blog.php">Blog</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'documentation.php') ? 'active' : ''; ?>" href="documentation.php">Docs</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>" href="contact.php">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center mob-nav-btns mt-3 mt-lg-0">
                    <a href="login.php" class="btn btn-primary-outline me-3 w-100 w-lg-auto">Login</a>
                    <a href="signup.php" class="btn btn-gold w-100 w-lg-auto">Register</a>
                </div>
            </div>
        </div>
    </nav>
