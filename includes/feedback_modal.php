<?php
// includes/feedback_modal.php - Multi-Layer Experience Orchestration
?>
<div class="modal fade" id="feedbackModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="feedbackForm">
            <div class="modal-header border-0 bg-primary text-white p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-box bg-white text-primary rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-star h5 mb-0"></i>
                    </div>
                    <div>
                        <h5 class="fw-800 mb-0">Experience Orchestrated</h5>
                        <p class="small opacity-75 mb-0">Your insight accelerates our evolution.</p>
                    </div>
                </div>
                <!-- No close button initially? Maybe allow close. -->
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="activity_type" id="activity_type">
                
                <div class="mb-4 text-center">
                    <label class="small fw-bold text-muted uppercase tracking-2 mb-2 d-block">Rate your journey with: <span id="activity_label" class="text-primary fw-800"></span></label>
                    <div class="star-rating d-flex justify-content-center gap-2">
                        <i class="fas fa-star fa-2x text-muted cursor-pointer star-item" data-value="1"></i>
                        <i class="fas fa-star fa-2x text-muted cursor-pointer star-item" data-value="2"></i>
                        <i class="fas fa-star fa-2x text-muted cursor-pointer star-item" data-value="3"></i>
                        <i class="fas fa-star fa-2x text-muted cursor-pointer star-item" data-value="4"></i>
                        <i class="fas fa-star fa-2x text-white-50 cursor-pointer star-item active" data-value="5" style="color:#FFD700 !important"></i>
                        <input type="hidden" name="rating" id="ratingInput" value="5">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="small fw-bold mb-2">Transmission Identity (Name)</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user_name ?? ''); ?>" required placeholder="Your full name">
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold mb-2">Node Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_email ?? ''); ?>" required placeholder="Your email address">
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold mb-2">Insight Feed (Comments)</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="How was your experience with this service node? Any suggestions specifically?"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-900 shadow-sm">PUBLISH INSIGHT <i class="fas fa-paper-plane ms-2"></i></button>
            </div>
        </form>
    </div>
</div>

<style>
.star-item.active { color: #FFD700 !important; }
.star-item:hover { transform: scale(1.2); transition: 0.2s; }
.cursor-pointer { cursor: pointer; }
</style>

<script>
$(document).ready(function() {
    $('.star-item').on('click', function() {
        const val = $(this).data('value');
        $('#ratingInput').val(val);
        $('.star-item').removeClass('active').each(function(i) {
            if (i < val) $(this).addClass('active');
        });
    });

    window.EduRemarks = window.EduRemarks || {};
    window.EduRemarks.feedbackCallback = null;
    window.EduRemarks.showFeedback = function(activityType, activityLabel, callback) {
        $('#activity_type').val(activityType);
        // Reset stars
        $('#ratingInput').val(5);
        $('.star-item').addClass('active');
        
        $('#activity_label').text(activityLabel || activityType);
        window.EduRemarks.feedbackCallback = callback || null;
        new bootstrap.Modal('#feedbackModal').show();
    };

    $('#feedbackForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-sync fa-spin"></i> Synchronizing...');
        
        $.post('<?php echo (strpos($_SERVER['PHP_SELF'], "super_admin") !== false || strpos($_SERVER['PHP_SELF'], "/admin/") !== false || strpos($_SERVER['PHP_SELF'], "/user/") !== false) ? "../" : ""; ?>ajax/save_feedback.php', $(this).serialize(), function(res) {
            if(res.success) {
                $('#feedbackModal').modal('hide');
                if(typeof window.EduRemarks.feedbackCallback === 'function') {
                    window.EduRemarks.feedbackCallback(res);
                } else {
                    if (typeof Notif !== 'undefined') {
                        Notif.show(res.message);
                    } else {
                        alert(res.message);
                    }
                }
            } else {
                if (typeof Notif !== 'undefined') {
                    Notif.show(res.message, 'error');
                } else {
                    alert(res.message);
                }
                btn.prop('disabled', false).html('PUBLISH INSIGHT <i class="fas fa-paper-plane ms-2"></i>');
            }
        }, 'json');
    });
});
</script>
