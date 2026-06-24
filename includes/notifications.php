<?php
// includes/notifications.php
?>
<style>
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }
    .toast-msg {
        min-width: 250px;
        background: white;
        border-left: 5px solid var(--primary-blue);
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 12px;
        transform: translateX(120%);
        transition: transform 0.3s ease;
    }
    .toast-msg.show {
        transform: translateX(0);
    }
    .toast-msg.success { border-left-color: #28a745; }
    .toast-msg.error { border-left-color: #dc3545; }
    .toast-msg.warning { border-left-color: var(--accent-gold); }
    .toast-msg i { font-size: 1.2rem; }
</style>

<div class="toast-container" id="toastContainer"></div>

<script>
const Notif = {
    show: function(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast-msg ${type}`;
        
        let icon = 'fa-check-circle text-success success-icon-animated';
        if (type === 'error') icon = 'fa-exclamation-circle text-danger';
        if (type === 'warning') icon = 'fa-exclamation-triangle text-warning';
        
        toast.innerHTML = `
            <i class="fas ${icon}"></i>
            <span>${msg}</span>
        `;
        
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
};
</script>
