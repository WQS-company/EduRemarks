<?php
// super_admin/campaigns.php - Platform Broadcast Command
// Fixed include path and standardized layout
require_once 'auth_check.php';

// Fetch all campaigns with defensive check
try {
    $campaigns = $pdo->query("SELECT * FROM platform_campaigns ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $campaigns = [];
    $db_error = $e->getMessage();
}

// Fetch all schools for individual target select
try {
    $schools = $pdo->query("SELECT id, school_name FROM schools WHERE status='active' ORDER BY school_name")->fetchAll();
} catch (Exception $e) {
    $schools = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaigns | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .tiny-text { font-size: 0.75rem; }
        .fw-800 { font-weight: 800; }
        .text-blue { color: #1e3a8a; }

        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h4 class="fw-800 mb-0">Platform Broadcast Hub</h4>
            <p class="text-muted small">Dispatch campaigns and messages to institutional nodes</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="openNewCampaignModal()">
            <i class="fas fa-bullhorn me-2"></i>New Campaign
        </button>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> Broadcast node communication error: <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php endif; ?>

    <div class="glass-card p-4 border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr class="text-muted small uppercase fw-bold">
                        <th>Subject</th>
                        <th>Target Scope</th>
                        <th>Sent On</th>
                        <th>Operations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($campaigns)): ?>
                    <tr><td colspan="4" class="text-center py-5 opacity-50">Zero broadcast activity logged.</td></tr>
                    <?php else: foreach($campaigns as $camp): ?>
                    <tr>
                        <td>
                            <div class="fw-800 text-blue"><?php echo htmlspecialchars($camp['subject']); ?></div>
                            <div class="tiny-text opacity-75 line-clamp-1"><?php echo strip_tags($camp['message']); ?></div>
                        </td>
                        <td>
                            <span class="badge rounded-pill px-3 py-2 bg-<?php echo ($camp['target_school_ids']) ? 'primary' : 'success'; ?> bg-opacity-10 text-<?php echo ($camp['target_school_ids']) ? 'primary' : 'success'; ?> border border-<?php echo ($camp['target_school_ids']) ? 'primary' : 'success'; ?> border-opacity-25">
                                <i class="fas <?php echo ($camp['target_school_ids']) ? 'fa-bullseye' : 'fa-globe-africa'; ?> me-1"></i>
                                <?php echo ($camp['target_school_ids']) ? 'Targeted Nodes' : 'GLOBAL BROADCAST'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="small fw-600"><?php echo date('M d, Y', strtotime($camp['created_at'])); ?></div>
                            <div class="tiny-text opacity-75"><?php echo date('h:i A', strtotime($camp['created_at'])); ?></div>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-light rounded-pill px-3 fw-bold" onclick='previewCampaign(<?php echo htmlspecialchars(json_encode($camp), ENT_QUOTES, 'UTF-8'); ?>)'>
                                <i class="fas fa-eye me-1"></i> PREVIEW
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Campaign Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" id="campaignForm">
            <div class="modal-header border-0 p-4 bg-light">
                <h5 class="fw-bold mb-0">Platform Broadcast Dispatch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="small fw-bold mb-1">Target Institutions (Deselect all for GLOBAL BROADCAST)</label>
                    <select name="target_schools[]" class="form-select rounded-3 shadow-sm" multiple style="height: 150px;">
                        <?php foreach($schools as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['school_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="tiny-text text-muted mt-1 px-1">Hold Ctrl (Windows) or Command (Mac) to select multiple institutions.</div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1">Campaign Subject</label>
                    <input type="text" name="subject" class="form-control rounded-3" placeholder="RE: Platform Upgrade or Critical Alert..." required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold mb-1">Message Payload (HTML Support Enabled)</label>
                    <textarea name="message" class="form-control rounded-3" rows="8" placeholder="Enter the broadcast content..." required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm">
                    <i class="fas fa-paper-plane me-2"></i> INITIALIZE BROADCAST
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 p-4 bg-light">
                <h5 class="fw-bold mb-0">Broadcast Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <h4 id="previewSubject" class="fw-800 text-blue mb-3"></h4>
                <hr>
                <div id="previewMessage" class="p-3 bg-light rounded-4 border"></div>
            </div>
            <div class="modal-footer border-0 p-3">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/spinner.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<!-- Core Dependencies -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const EDUREMARKS_CSRF_TOKEN = '<?php echo Security::csrf_token(); ?>';
    
    $(document).ready(function() {
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': EDUREMARKS_CSRF_TOKEN },
            data: { csrf_token: EDUREMARKS_CSRF_TOKEN }
        });
    });

    const campaignModal = new bootstrap.Modal(document.getElementById('campaignModal'));
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

    function openNewCampaignModal() {
        document.getElementById('campaignForm').reset();
        campaignModal.show();
    }

    $('#campaignForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Broadcasting to institutional nodes...');
        $.post('../ajax/sa_send_campaign.php', $(this).serialize(), function(res) {
            Spinner.hide();
            if(res.success) {
                campaignModal.hide();
                showSuccess('Broadcast Initialized', 'The campaign has been dispatched to the selected institutional nodes.', { reload: true });
            } else {
                alert('Broadcast Error: ' + res.message);
            }
        }, 'json').fail(function() {
            Spinner.hide();
            alert('Communication failure with the central server.');
        });
    });

    function previewCampaign(c) {
        document.getElementById('previewSubject').innerText = c.subject;
        document.getElementById('previewMessage').innerHTML = c.message;
        previewModal.show();
    }
</script>
</body>
</html>
