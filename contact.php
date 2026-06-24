<?php
$pageTitle = "Contact Us";
include 'includes/header.php';
?>

    <!-- Page Header -->
    <section class="hero-section reveal reveal-up" style="padding: 100px 0 60px;">
        <div class="container text-center">
            <h1 class="hero-title" style="font-size: 2.8rem;">Get in Touch</h1>
            <p class="hero-subtitle">We're here to help you revolutionize your school management.</p>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="reveal reveal-up">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5 text-center text-lg-start">
                    <h2 class="mb-4">Contact Information</h2>
                    <p class="text-muted mb-5">Have questions about our features or pricing? Our team is ready to assist
                        you.</p>

                    <div class="d-flex flex-column flex-lg-row align-items-center align-items-lg-start mb-4">
                        <div class="icon-box me-lg-3 mt-1" style="width: 50px; height: 50px; flex-shrink: 0;"><i
                                class="fas fa-map-marker-alt"></i></div>
                        <div class="mt-3 mt-lg-0">
                            <h5>Our Location</h5>
                            <p class="text-muted">123 Education Plaza, Lagos, Nigeria.</p>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-lg-row align-items-center align-items-lg-start mb-4">
                        <div class="icon-box me-lg-3 mt-1" style="width: 50px; height: 50px; flex-shrink: 0;"><i
                                class="fas fa-phone-alt"></i></div>
                        <div class="mt-3 mt-lg-0">
                            <h5>Call Us</h5>
                            <p class="text-muted">+234 812 345 6789</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="feature-card">
                        <h3 class="mb-4">Send us a Message</h3>
                        <form class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" placeholder="John Doe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">School Name</label>
                                <input type="text" class="form-control" placeholder="ABC Academy">
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-premium-gold w-100 py-3">Send Message</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
