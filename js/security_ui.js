// js/security_ui.js

document.addEventListener('DOMContentLoaded', function () {
    // Universal Password Toggle Handler
    document.body.addEventListener('click', function (e) {
        if (e.target.classList.contains('password-toggle') || e.target.parentElement.classList.contains('password-toggle')) {
            const toggleBtn = e.target.classList.contains('password-toggle') ? e.target : e.target.parentElement;
            const targetId = toggleBtn.getAttribute('data-target');
            const input = document.getElementById(targetId) || toggleBtn.previousElementSibling;

            if (input && (input.type === 'password' || input.type === 'text')) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';

                // Update Icon
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye', !isPassword);
                    icon.classList.toggle('fa-eye-slash', isPassword);
                }
            }
        }
    });

    // Auto-init input group focus borders (optional enhancement)
    const inputs = document.querySelectorAll('.input-group .form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            const group = input.closest('.input-group');
            if (group) group.classList.add('focused');
        });
        input.addEventListener('blur', () => {
            const group = input.closest('.input-group');
            if (group) group.classList.remove('focused');
        });
    });
});

