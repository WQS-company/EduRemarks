<?php
// includes/dashboard_footer.php
?>
<footer class="dash-footer mt-auto py-3 bg-white border-top">
    <div class="container-fluid">
        <div class="row align-items-center justify-content-between">
            <div class="col-sm-6 text-center text-sm-start">
                <span class="text-muted extra-small">&copy; <?php echo date('Y'); ?> EduRemarks Management Portal. v2.1.0</span>
            </div>
            <div class="col-sm-6 text-center text-sm-end d-none d-sm-block">
                <span class="text-muted extra-small">
                    <i class="fas fa-shield-alt me-1"></i> Secure Session
                    <span class="mx-2">|</span>
                    <i class="fas fa-headset me-1"></i> Support
                </span>
            </div>
        </div>
    </div>
</footer>

<style>
    .dash-footer {
        z-index: 10;
        position: relative;
    }
    .extra-small {
        font-size: 0.75rem;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    const originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function(body) {
        if (body instanceof FormData && !body.has('csrf_token')) {
            body.append('csrf_token', EDUREMARKS_CSRF_TOKEN);
        }
        return originalSend.apply(this, arguments);
    };

    // 3. Intercept Fetch API
    const originalFetch = window.fetch;
    window.fetch = function(input, init) {
        if (init && init.method && init.method.toUpperCase() === 'POST' && init.body instanceof FormData) {
            if (!init.body.has('csrf_token')) {
                init.body.append('csrf_token', EDUREMARKS_CSRF_TOKEN);
            }
        }
        return originalFetch.apply(this, arguments);
    };

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
</script>
<?php include __DIR__ . '/notifications.php'; ?>
<?php include __DIR__ . '/feedback_modal.php'; ?>
<?php include __DIR__ . '/support_chat.php'; ?>
