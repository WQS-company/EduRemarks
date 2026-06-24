<!-- Join School Modal -->
<div class="modal fade" id="joinSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">Request to Join an Institution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="joinSchoolForm">
                <div class="modal-body p-4">
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-bold">Search School Name</label>
                        <input type="text" class="form-control" id="school_search" placeholder="Enter school name..." autocomplete="off">
                        <div id="school_search_results" class="shadow-sm"></div>
                        <input type="hidden" name="school_id" id="join_school_id">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Verification Secret (School ID)</label>
                        <input type="text" class="form-control text-uppercase" name="unique_school_id" required placeholder="e.g. ER123456XY">
                        <div class="form-text mt-2"><i class="fas fa-lock me-1"></i> Provided by the school head.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium-gold px-4">Send Join Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#school_search_results {
    position: absolute;
    width: 100%;
    z-index: 1051;
    display: none;
    background: white;
    border: 1px solid #ddd;
    max-height: 200px;
    overflow-y: auto;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.result-item { padding: 12px; cursor: pointer; border-bottom: 1px solid #f8f9fa; font-size: 0.85rem; }
.result-item:hover { background: #f0f7ff; color: var(--sa-blue); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Determine correct relative path to ajax folder
    const isInSubdir = window.location.pathname.indexOf('/user/') !== -1 || window.location.pathname.indexOf('/admin/') !== -1;
    const ajaxPath = isInSubdir ? '../ajax/' : 'ajax/';

    const joinForm = document.getElementById('joinSchoolForm');
    if(joinForm) {
        joinForm.onsubmit = (e) => {
            e.preventDefault();
            Spinner.show('Submitting Request...');
            
            fetch(ajaxPath + 'join_school.php', { method: 'POST', body: new FormData(joinForm) })
            .then(async r => {
                const text = await r.text();
                try {
                    const d = JSON.parse(text);
                    if (d.success) {
                        Notif.show(d.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Notif.show(d.message, 'error');
                        Spinner.hide();
                    }
                } catch(err) {
                    console.error("AJAX Error Response:", text);
                    Notif.show("A server error occurred. Please try again.", "error");
                    Spinner.hide();
                }
            })
            .catch(err => {
                console.error("Fetch Error:", err);
                Notif.show("Network error. Please try again.", "error");
                Spinner.hide();
            });
        };
    }

    const searchInput = document.getElementById('school_search');
    const resultsDiv = document.getElementById('school_search_results');
    if (searchInput) {
        searchInput.oninput = () => {
            const q = searchInput.value;
            if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
            
            fetch(ajaxPath + `get_schools.php?q=${q}`)
            .then(r => r.json()).then(d => {
                resultsDiv.innerHTML = '';
                if (d.success && d.schools.length > 0) {
                    d.schools.forEach(s => {
                        const div = document.createElement('div');
                        div.className = 'result-item';
                        div.innerHTML = `<strong>${s.school_name}</strong> <span class="text-muted small ms-2">(${s.unique_id.substring(0,4)}***)</span>`;
                        div.onclick = () => {
                            searchInput.value = s.school_name;
                            document.getElementById('join_school_id').value = s.id;
                            resultsDiv.style.display = 'none';
                        };
                        resultsDiv.appendChild(div);
                    });
                    resultsDiv.style.display = 'block';
                }
            }).catch(e => {
                console.error("Error fetching schools", e);
            });
        };
    }
});
</script>
