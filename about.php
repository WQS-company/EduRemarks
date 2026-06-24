<?php
$pageTitle = "About Us";
include 'includes/header.php';
?>

    <!-- Page Header -->
    <section class="hero-section reveal reveal-up" style="padding: 100px 0 60px;">
        <div class="container text-center">
            <h1 class="hero-title" style="font-size: 2.8rem;">Our Mission to Transform Education</h1>
            <p class="hero-subtitle">Providing the digital foundation for the next generation of schools.</p>
        </div>
    </section>

    <!-- About Content -->
    <section class="reveal reveal-up">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-6">
                    <h2 class="mb-4">Driving Efficiency in Schools</h2>
                    <p class="lead text-muted"><?php echo get_setting('about_content', 'EduRemarks was founded on the belief that school management should be simple, efficient, and data-driven.'); ?></p>
                    <p>We provide a comprehensive suite of tools that automate the tedious tasks of academic administration, allowing educators to focus on what matters most: teaching and learning.</p>
                    <div class="row g-4 mt-2">
                        <div class="col-6">
                            <h4 class="text-gold mb-0">500+</h4>
                            <small class="text-muted">Schools Trusted</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-gold mb-0">100k+</h4>
                            <small class="text-muted">Students Managed</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mt-lg-0 mt-5">
                    <div class="feature-card p-0 overflow-hidden shadow-lg border-0">
                        <img src="<?php echo get_setting('about_image', 'img/about.png'); ?>"
                            alt="Education" class="img-fluid rounded-4">
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
