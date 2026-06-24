<!-- includes/spinner.php - Professional Action Processing Overlay -->
<div id="processing-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); z-index: 10000; flex-direction: column; justify-content: center; align-items: center; color: white;">
    <div class="spinner-visual" style="position: relative; width: 70px; height: 70px; margin-bottom: 25px;">
        <div class="ring-sector-blue" style="position: absolute; inset: 0; border: 3px solid transparent; border-top: 3px solid #1F3C88; border-radius: 50%; animation: spin-clockwise 1s linear infinite;"></div>
        <div class="ring-sector-gold" style="position: absolute; inset: 5px; border: 3px solid transparent; border-bottom: 3px solid #F4B400; border-radius: 50%; animation: spin-counter-clockwise 1.2s linear infinite;"></div>
        <div class="spinner-dot" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 8px; height: 8px; background: #F4B400; border-radius: 50%; box-shadow: 0 0 15px #F4B400;"></div>
    </div>
    <div class="processing-text fw-800 uppercase tracking-2" style="font-size: 0.8rem; letter-spacing: 3px;">Processing...</div>
</div>

<style>
    @keyframes spin-clockwise { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes spin-counter-clockwise { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }
</style>

<script>
    const Spinner = {
        show: function(text = 'Processing Request...') {
            const overlay = document.getElementById('processing-overlay');
            const textElement = overlay.querySelector('.processing-text');
            textElement.innerText = text;
            overlay.style.display = 'flex';
        },
        hide: function() {
            document.getElementById('processing-overlay').style.display = 'none';
        }
    };
</script>
