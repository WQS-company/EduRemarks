    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 text-center text-lg-start">
                    <h5 class="navbar-brand">Edu<span>Remarks</span></h5>
                    <p class="pe-lg-5">Complete digital infrastructure platform for modern schools. Managing education
                        should be simple.</p>
                    <div class="social-links mt-4">
                        <?php if($fb = get_setting('social_facebook')): ?> <a href="<?php echo htmlspecialchars($fb); ?>"><i class="fab fa-facebook-f"></i></a> <?php endif; ?>
                        <?php if($tw = get_setting('social_twitter')): ?> <a href="<?php echo htmlspecialchars($tw); ?>"><i class="fab fa-twitter"></i></a> <?php endif; ?>
                        <?php if($ig = get_setting('social_instagram')): ?> <a href="<?php echo htmlspecialchars($ig); ?>"><i class="fab fa-instagram"></i></a> <?php endif; ?>
                        <?php if($li = get_setting('social_linkedin')): ?> <a href="<?php echo htmlspecialchars($li); ?>"><i class="fab fa-linkedin-in"></i></a> <?php endif; ?>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php">Home</a></li>
                        <li class="mb-2"><a href="student/login.php" class="text-premium-gold fw-bold"><i class="fas fa-user-graduate me-1"></i> Student Portal</a></li>
                        <li class="mb-2"><a href="features.php">Features</a></li>
                        <li class="mb-2"><a href="pricing.php">Pricing</a></li>
                        <li class="mb-2"><a href="about.php">About Us</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-3">
                    <h5>Support & Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="documentation.php" class="fw-bold text-premium-gold">Master Guide</a></li>
                        <li class="mb-2"><a href="faq.php">FAQ</a></li>
                        <li class="mb-2"><a href="contact.php">Contact Support</a></li>
                        <li class="mb-2"><a href="privacy.php">Privacy Policy</a></li>
                        <li class="mb-2"><a href="terms.php">Terms of Service</a></li>
                        <li class="mb-2"><a href="refund.php">Refund Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 text-center text-lg-start">
                    <h5>Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2 text-gold"></i> <?php echo get_setting('footer_address', '123 Education Plaza, Lagos'); ?></li>
                        <li class="mb-2"><i class="fas fa-phone me-2 text-gold"></i> <?php echo get_setting('footer_phone', '+234 812 345 6789'); ?></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2 text-gold"></i> <?php echo get_setting('footer_email', 'info@eduremarks.com'); ?></li>
                    </ul>
                </div>
            </div>
            <hr class="my-5 border-secondary opacity-25">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> EduRemarks. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <?php include 'notifications.php'; ?>
    <?php include 'support_chat.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $path_prefix ?? ''; ?>js/main.js"></script>
    <script>
        // Universal CSRF Orchestration Hub
        const EDUREMARKS_CSRF_TOKEN = '<?php echo Security::csrf_token(); ?>';

        // 1. jQuery AJAX Global Setup
        if (typeof jQuery !== 'undefined') {
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': EDUREMARKS_CSRF_TOKEN },
                data: { csrf_token: EDUREMARKS_CSRF_TOKEN }
            });
        }

        // 2. Intercept XMLHttpRequest (XHR) for FormData
        (function() {
            const originalSend = XMLHttpRequest.prototype.send;
            XMLHttpRequest.prototype.send = function(body) {
                if (body instanceof FormData && !body.has('csrf_token')) {
                    body.append('csrf_token', EDUREMARKS_CSRF_TOKEN);
                }
                return originalSend.apply(this, arguments);
            };
        })();

        // 3. Intercept Fetch API
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(input, init) {
                if (init && init.method && init.method.toUpperCase() === 'POST' && init.body instanceof FormData) {
                    if (!init.body.has('csrf_token')) {
                        init.body.append('csrf_token', EDUREMARKS_CSRF_TOKEN);
                    }
                }
                return originalFetch.apply(this, arguments);
            };
        })();

        // 4. Automatic Form Shielding (Inject CSRF into all POST forms)
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[method="POST"], form:not([method])').forEach(form => {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = EDUREMARKS_CSRF_TOKEN;
                    form.appendChild(input);
                }
            });
        });

        window.addEventListener('load', function () {
            var preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('loaded');
                setTimeout(function () {
                    preloader.style.display = 'none';
                }, 800);
            }
        });
    </script>
</body>

</html>
