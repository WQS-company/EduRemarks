<!-- Success Overlay Component -->
<div id="successOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,60,0.85); backdrop-filter:blur(10px); z-index:99999; align-items:center; justify-content:center; flex-direction:column;">
    <div style="background:white; border-radius:28px; padding:48px 56px; text-align:center; box-shadow:0 30px 100px rgba(15,23,42,0.5); max-width:400px; width:90%;">
        <div id="checkmarkCircle">
            <svg viewBox="0 0 52 52" style="width:100px;height:100px;">
                <circle cx="26" cy="26" r="24" class="brand-checkmark-ring"/>
                <path d="M14 27l8 8 16-16" class="brand-checkmark-tick"/>
            </svg>
        </div>
        <h4 class="fw-800 mt-4 mb-2" style="color:#1F3C88;" id="overlayTitle">Operation Successful!</h4>
        <p class="text-muted mb-4" id="overlayMessage">The changes have been synchronized successfully.</p>
        <button id="overlayCloseBtn" onclick="closeSuccess()" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm" style="background:linear-gradient(135deg,#1F3C88,#2D6CDF); border:none;">
            <i class="fas fa-check me-2"></i>CONTINUE
        </button>
    </div>
</div>

<style>
    #successOverlay.visible { display: flex !important; }
    .brand-checkmark-ring { 
        fill: none;
        stroke: #1F3C88;
        stroke-width: 2.5;
        stroke-dasharray: 157;
        stroke-dashoffset: 157;
        animation: draw-ring 0.65s ease forwards 0.15s; 
    }
    .brand-checkmark-tick { 
        fill: none;
        stroke: #F4B400;
        stroke-width: 3;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 36;
        stroke-dashoffset: 36;
        animation: draw-tick 0.45s ease forwards 0.8s; 
    }
    @keyframes draw-ring { to { stroke-dashoffset: 0; } }
    @keyframes draw-tick { to { stroke-dashoffset: 0; } }
</style>

<script>
    function showSuccess(title, message, options = {}) {
        const overlay = document.getElementById('successOverlay');
        document.getElementById('overlayTitle').innerText = title;
        document.getElementById('overlayMessage').innerText = message;
        
        if (options.buttonText) {
            document.getElementById('overlayCloseBtn').innerHTML = `<i class="fas fa-check me-2"></i>${options.buttonText}`;
        }
        
        overlay.classList.add('visible');
        
        // Reset animations
        const ring = overlay.querySelector('.brand-checkmark-ring');
        const tick = overlay.querySelector('.brand-checkmark-tick');
        ring.style.animation = 'none'; tick.style.animation = 'none';
        void ring.offsetWidth;
        ring.style.animation = '';
        tick.style.animation = '';
        
        // Storage for later
        overlay.dataset.reload = options.reload || false;
        overlay.dataset.redirect = options.redirect || '';
        overlay.callback = options.callback || null;
    }

    function closeSuccess() {
        const overlay = document.getElementById('successOverlay');
        overlay.classList.remove('visible');
        
        if (overlay.callback) {
            overlay.callback();
        } else if (overlay.dataset.redirect) {
            window.location.href = overlay.dataset.redirect;
        } else if (overlay.dataset.reload === 'true') {
            location.reload();
        }
    }
</script>
