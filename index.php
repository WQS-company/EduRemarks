<?php
$pageTitle = "Home";
include 'includes/header.php';
?>

    <!-- Hero Section -->
    <section class="hero-section reveal reveal-up">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content text-center text-lg-start pe-lg-5">
                    <h1 class="hero-title mb-4"><?php echo get_setting('hero_title', 'Empower Your School with <br><span>EduRemarks</span>'); ?></h1>
                    <p class="hero-subtitle mb-5"><?php echo get_setting('hero_subtitle', 'A world-class digital infrastructure for complete school management and automation.'); ?></p>
                    <div
                        class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start mt-4">
                        <a href="signup.php" class="btn btn-premium-gold px-4 py-3">Register Your School</a>
                        <a href="student/login.php" class="btn btn-premium-outline px-4 py-3"><i class="fas fa-user-graduate me-2"></i>Student Login</a>
                        <a href="#features" class="btn btn-link text-white text-decoration-none px-4 py-3 opacity-75">Explore Features</a>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0 text-center">
                    <div class="hero-image-container reveal reveal-scale">
                        <div class="hero-img-wrapper">
                            <div class="hero-slideshow">
                                <?php 
                                $slides = $pdo->query("SELECT * FROM platform_hero_slides ORDER BY sort_order ASC")->fetchAll();
                                if(!empty($slides)): foreach($slides as $index => $sl):
                                ?>
                                <div class="hero-slide <?php echo ($index == 0) ? 'active' : ''; ?>" style="background-image: url('<?php echo $sl['image_path'] ?: 'hero.png'; ?>');">
                                    <div class="hero-caption"><?php echo htmlspecialchars($sl['caption']); ?></div>
                                    <?php if ($index == 0): ?>
                                    <!-- Rising Stack SVG - Keep on first slide for brand style -->
                                    <svg class="rising-stack position-absolute bottom-0 end-0 mb-4 me-4" style="width: 80px;" viewBox="0 0 100 120">
                                        <rect class="stack-block stack-block-1 stack-block-blue" x="10" y="100" width="80" height="15" rx="4" />
                                        <rect class="stack-block stack-block-2 stack-block-gold" x="10" y="80" width="80" height="15" rx="4" />
                                        <rect class="stack-block stack-block-3 stack-block-blue" x="10" y="60" width="80" height="15" rx="4" />
                                        <rect class="stack-block stack-block-4 stack-block-gold" x="10" y="40" width="80" height="15" rx="4" />
                                        <rect class="stack-block stack-block-5 stack-block-blue" x="10" y="20" width="80" height="15" rx="4" />
                                    </svg>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; else: ?>
                                <div class="hero-slide active" style="background-image: url('hero.png');"><div class="hero-caption">Global Excellence</div></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section pb-5 reveal reveal-up">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Why Choose EduRemarks?</h2>
                <p class="text-muted">Comprehensive tools designed to transform your school's efficiency.</p>
            </div>
            <div class="row g-4">
                <?php if(!empty($services)): foreach($services as $sv): ?>
                <div class="col-md-4">
                    <div class="feature-card text-center text-md-start h-100 reveal reveal-up">
                        <div class="icon-box mx-auto ms-md-0"><i class="<?php echo $sv['icon']; ?>"></i></div>
                        <h3><?php echo htmlspecialchars($sv['title']); ?></h3>
                        <p class="text-muted"><?php echo htmlspecialchars($sv['description']); ?></p>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="col-md-4"><div class="feature-card h-100"><h3>Standard Reporting</h3><p>Automated result compilation and broadsheets.</p></div></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2>How It Works</h2>
                <p class="text-muted">Set up your school portal in three simple steps.</p>
            </div>
            <div class="row mt-5">
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="step-container">
                        <div class="step-number">1</div>
                        <h3>Register School</h3>
                        <p class="text-muted">Create your school account and verify your portal credentials.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <div class="step-container">
                        <div class="step-number">2</div>
                        <h3>Setup Structure</h3>
                        <p class="text-muted">Add classes, subjects, and staff to configure your academic ecosystem.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-container">
                        <div class="step-number">3</div>
                        <h3>Go Live</h3>
                        <p class="text-muted">Start managing results, payments, and communication effortlessly.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <?php 
    $packages = $pdo->query("SELECT * FROM pricing_packages ORDER BY price_naira ASC")->fetchAll();
    ?>
    <section id="pricing">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Simple & Transparent Pricing</h2>
                <p class="text-muted">High-fidelity credit packages for your institutional operations.</p>
            </div>
            <div class="row g-4 align-items-center">
                <?php if(!empty($packages)): foreach($packages as $index => $pkg): ?>
                <div class="col-lg-4">
                    <div class="pricing-card <?php echo ($index == 1) ? 'featured' : ''; ?>">
                        <?php if($index == 1): ?><div class="pricing-badge">MOST POPULAR</div><?php endif; ?>
                        <h3><?php echo htmlspecialchars($pkg['name']); ?></h3>
                        <p class="text-muted">Scale your institutional power</p>
                        <div class="price">₦<?php echo number_format($pkg['price_naira']); ?></div>
                        <ul class="list-unstyled mb-4">
                            <li><i class="fas fa-bolt text-warning me-2"></i> <strong><?php echo number_format($pkg['credits']); ?></strong> Operational Credits</li>
                            <li><i class="fas fa-check text-success me-2"></i> Result Management</li>
                            <li><i class="fas fa-check text-success me-2"></i> CBT Assessment Engine</li>
                            <li><i class="fas fa-check text-success me-2"></i> Billing & SMS Node</li>
                        </ul>
                        <a href="signup.php" class="btn <?php echo ($index == 1) ? 'btn-gold' : 'btn-primary-outline'; ?> w-100">Get Started</a>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="col-lg-4">
                    <div class="pricing-card">
                        <h3>Starter Pack</h3>
                        <p class="text-muted">1,000 Credits</p>
                        <div class="price">₦50,000</div>
                        <a href="signup.php" class="btn btn-primary-outline w-100">Get Started</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-5">
                <p class="text-muted small">Need a custom enterprise node? <a href="contact.php" class="text-primary fw-bold">Contact our Sales Squad</a></p>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Loved by School Leaders</h2>
                <p class="text-muted">See what educators are saying about EduRemarks.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="feature-card">
                        <div class="mb-3 text-warning">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>"EduRemarks has completely transformed how we handle our term results. What used to take
                            weeks now happens in minutes."</p>
                        <div class="d-flex align-items-center mt-4">
                            <div class="ms-0">
                                <h6 class="mb-0">Dr. Sarah Johnson</h6>
                                <small class="text-muted">Principal, Grace Excel School</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="mb-3 text-warning">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                                class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>"The SMS feature and wallet system have significantly improved our fee recovery statistics
                            this session."</p>
                        <div class="d-flex align-items-center mt-4">
                            <div class="ms-0">
                                <h6 class="mb-0">Rev. Michael Adams</h6>
                                <small class="text-muted">Admin, Bright Minds Academy</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Institutional Resources Section -->
    <section class="bg-light reveal reveal-up">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="pe-lg-5">
                        <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-3 fw-bold">FOR SCHOOLS & PUBLIC BODIES</span>
                        <h2 class="display-5 fw-800 mb-4">Professional Digital Infrastructure</h2>
                        <p class="lead text-muted mb-4">EduRemarks is built for high-stakes institutional management. Our platform provides the transparency and reliability required by modern educational standards.</p>
                        
                        <div class="d-flex align-items-start mb-4">
                            <div class="icon-box-sm bg-white shadow-sm rounded-circle me-3 flex-shrink-0">
                                <i class="fas fa-file-contract text-primary"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Printable Handbooks</h5>
                                <p class="small text-muted mb-0">Detailed system guides designed for institutional auditing and staff training.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-4">
                            <div class="icon-box-sm bg-white shadow-sm rounded-circle me-3 flex-shrink-0">
                                <i class="fas fa-shield-check text-success"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Regulatory Compliance</h5>
                                <p class="small text-muted mb-0">Built-in data privacy protocols and secure academic record synchronization.</p>
                            </div>
                        </div>

                        <div class="mt-5 d-flex flex-wrap gap-3">
                            <a href="documentation.php" class="btn btn-primary px-4 py-3 rounded-pill fw-bold">
                                <i class="fas fa-book-open me-2"></i>View Master Guide
                            </a>
                            <a href="documentation.php?print=1" class="btn btn-outline-primary px-4 py-3 rounded-pill fw-bold">
                                <i class="fas fa-print me-2"></i>Print Official Specs
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card p-5 border-0 shadow-lg text-center reveal reveal-scale">
                        <i class="fas fa-university fa-5x text-primary-subtle mb-4"></i>
                        <h3 class="mb-3">Institutional Node</h3>
                        <p class="text-muted">Access professional documentation, security whitepapers, and operational blueprints.</p>
                        <hr class="my-4 opacity-10">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="p-3 bg-white rounded-3 border">
                                    <div class="h4 fw-bold text-primary mb-0">100%</div>
                                    <div class="small text-muted">Uptime</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-white rounded-3 border">
                                    <div class="h4 fw-bold text-primary mb-0">AES-256</div>
                                    <div class="small text-muted">Encryption</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section reveal reveal-up">
        <div class="container">
            <h2 class="text-white mb-4">Ready to Modernize Your School?</h2>
            <p class="mb-5 opacity-75">Join over 500 schools trusting EduRemarks for their digital infrastructure.</p>
            <a href="signup.php" class="btn btn-gold btn-lg px-5">Get Started Today</a>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
