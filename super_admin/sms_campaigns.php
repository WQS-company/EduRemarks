<?php
// super_admin/sms_campaigns.php - Global Platform Broadcaster
require_once 'auth_check.php';

// Fetch Platform-wide Campaign History
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as sender_name 
    FROM sms_campaigns c 
    JOIN users u ON c.sender_id = u.id 
    WHERE u.role = 'super_admin' 
    ORDER BY c.created_at DESC
");
$stmt->execute();
$campaigns = $stmt->fetchAll();

// Fetch dynamic credit cost from Global Pricing Node
$credit_cost_per_sms = getCreditRate('credit_per_sms', $pdo);
if (!$credit_cost_per_sms) $credit_cost_per_sms = 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Broadcaster | EduRemarks Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 220px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .status-badge { font-size: 0.65rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; text-transform: uppercase; }
        .sms-preview { background: #f8fafc; border-radius: 16px; padding: 20px; border: 1px dashed #cbd5e1; position: relative; }
        .sms-preview::after { content: 'PLATFORM PREVIEW'; position: absolute; top: -10px; right: 20px; background: #fff; padding: 0 10px; font-size: 0.6rem; font-weight: 800; color: #1e40af; }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/sa_header.php'; ?>
    <?php include '../includes/sa_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
            <div>
                <h4 class="fw-800 mb-0">Global Broadcaster</h4>
                <p class="text-muted small">Dispatch platform-wide transmissions to institutional nodes</p>
            </div>
            <button class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newGlobalCampaignModal">
                <i class="fas fa-paper-plane me-2"></i>New Global Broadcast
            </button>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div class="glass-card p-4">
                    <h6 class="fw-800 mb-4 text-primary uppercase tracking-1">Platform-Wide History</h6>
                    
                    <?php if (empty($campaigns)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-broadcast-tower text-muted fa-3x mb-3 opacity-25"></i>
                            <h5>No global transmissions recorded</h5>
                            <p class="text-muted small">Initialize a broadcast to reach school owners or administrators nationwide.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light">
                                    <tr class="text-muted small uppercase fw-bold border-bottom">
                                        <th>Subject Node</th>
                                        <th>Population Reach</th>
                                        <th>Economics</th>
                                        <th>Dispatch Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaigns as $c): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-800 text-dark"><?php echo htmlspecialchars($c['subject']); ?></div>
                                            <div class="tiny-text text-muted text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($c['message']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3"><?php echo strtoupper($c['target_group']); ?></span>
                                            <div class="tiny-text mt-1 fw-bold opacity-50"><?php echo $c['recipients_count']; ?> Recipients</div>
                                        </td>
                                        <td>
                                            <div class="small fw-800 text-warning"><?php echo number_format($c['total_credits']); ?> <i class="fas fa-bolt ms-1"></i></div>
                                        </td>
                                        <td><div class="tiny-text opacity-75 fw-bold"><?php echo date('M d, Y &bull; h:i A', strtotime($c['created_at'])); ?></div></td>
                                        <td>
                                            <?php if ($c['status'] == 'sent'): ?>
                                                <span class="status-badge bg-success bg-opacity-10 text-success">Synchronized</span>
                                            <?php else: ?>
                                                <span class="status-badge bg-danger bg-opacity-10 text-danger">Dropped</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include '../includes/spinner.php'; ?>
        <?php include '../includes/success_overlay.php'; ?>
        <?php include '../includes/notifications.php'; ?>
    </main>

    <!-- New Global Campaign Modal -->
    <div class="modal fade" id="newGlobalCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content border-0 shadow-lg rounded-4" id="globalCampaignForm">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Initialize Global Broadcast</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="small fw-bold mb-2 text-primary">Broadcast Subject</label>
                            <input type="text" name="subject" class="form-control rounded-3" placeholder="e.g. System Maintenance Alert" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold mb-2 text-primary">Target Tier</label>
                            <select name="target_group" id="targetGroup" class="form-select rounded-3" required onchange="updateEstimates()">
                                <option value="all_owners">All School Owners</option>
                                <option value="all_admins">All Institutional Admins</option>
                                <option value="low_credits">Schools with Low Credits (< 100)</option>
                                <option value="custom">Custom Node Selection</option>
                            </select>
                        </div>
                    </div>

                    <div id="customNodes" class="mb-4 d-none">
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="small fw-bold mb-2">Select Target Schools</label>
                            <div class="row g-2" style="max-height: 150px; overflow-y: auto;" id="schoolList">
                                <!-- Populated via JS -->
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="small fw-bold mb-2 text-primary d-flex justify-content-between">
                            Message Content <span class="tiny-text opacity-50" id="charCount">0 / 160</span>
                        </label>
                        <textarea name="message" id="smsMessage" class="form-control rounded-3" rows="4" maxlength="160" placeholder="Compose world-class platform update..." required oninput="updatePreview()"></textarea>
                    </div>

                    <div class="sms-preview mb-4">
                        <div id="previewText" class="small text-muted italic">Type to preview broadcast content...</div>
                    </div>

                    <div class="alert alert-dark border-0 rounded-4 p-3 d-flex align-items-center justify-content-between mb-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div>
                                <div class="extra-small fw-bold opacity-50 uppercase">Reach Analysis</div>
                                <div class="fw-900 h5 mb-0"><span id="recipientCount">0</span> Targets</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="extra-small fw-bold opacity-50 uppercase">Platform Cost</div>
                            <div class="fw-900 h6 mb-0 text-warning"><span id="totalCost">0</span> Credits</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">AUTHORIZE GLOBAL DISPATCH <i class="fas fa-bolt ms-2"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Global Confirmation Modal -->
    <div class="modal fade" id="confirmGlobalModal" tabindex="-1" style="z-index: 1061;">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <div class="bg-dark bg-opacity-10 text-dark rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                            <i class="fas fa-broadcast-tower fa-2x"></i>
                        </div>
                        <h5 class="fw-900 mb-2">Global Authorization</h5>
                        <p class="text-muted small mb-0">You are about to dispatch a nationwide broadcast to <strong class="text-dark" id="glCount">0</strong> nodes. Proceed with strategic transmission?</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">Abort</button>
                        <button type="button" class="btn btn-dark w-100 rounded-pill py-2 fw-bold shadow" id="finalizeGlobalBtn">Execute</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const COST_PER_NODE = <?php echo $credit_cost_per_sms; ?>;

        function updatePreview() {
            const val = $('#smsMessage').val();
            $('#charCount').text(val.length + ' / 160');
            $('#previewText').text(val || 'Type to preview broadcast content...');
        }

        function updateEstimates() {
            const group = $('#targetGroup').val();
            const customDiv = $('#customNodes');

            if(group === 'custom') {
                customDiv.removeClass('d-none');
                fetchSchools();
            } else {
                customDiv.addClass('d-none');
                $.get('../ajax/sa_estimate_sms.php', { target: group }, function(res) {
                    renderEstimates(res.count);
                }, 'json');
            }
        }

        function fetchSchools() {
            const list = $('#schoolList');
            if(list.children().length > 0) return;
            $.get('../ajax/sa_get_schools.php', function(res) {
                list.empty();
                res.data.forEach(s => {
                    list.append(`
                        <div class="col-md-6">
                            <label class="d-flex align-items-center gap-2 p-2 border rounded-2 cursor-pointer w-100 mb-0 hover-bg-light">
                                <input type="checkbox" name="selected_schools[]" value="${s.id}" class="form-check-input school-item" onchange="countCustom()">
                                <div class="text-truncate">
                                    <div class="fw-bold tiny-text">${s.school_name}</div>
                                    <div class="extra-small opacity-50">${s.owner_phone}</div>
                                </div>
                            </label>
                        </div>
                    `);
                });
            }, 'json');
        }

        function countCustom() {
            renderEstimates($('.school-item:checked').length);
        }

        function renderEstimates(count) {
            $('#recipientCount').text(count);
            $('#totalCost').text(count * COST_PER_NODE);
        }

        const globalConfirmModal = new bootstrap.Modal(document.getElementById('confirmGlobalModal'));

        $('#globalCampaignForm').on('submit', function(e) {
            e.preventDefault();
            const count = $('#recipientCount').text();
            $('#glCount').text(count);
            globalConfirmModal.show();
        });

        $('#finalizeGlobalBtn').on('click', function() {
            globalConfirmModal.hide();
            Spinner.show('Orchestrating Global Dispatch...');
            const formData = new FormData(document.getElementById('globalCampaignForm'));
            $.ajax({
                url: '../ajax/sa_send_sms.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    Spinner.hide();
                    if(res.success) {
                        showSuccess('Global Broadcast Complete', 'Platform-wide transmission has been synchronized and dispatched successfully.', { reload: true });
                    } else {
                        Notif.show(res.message, 'error');
                    }
                },
                error: function() {
                    Spinner.hide();
                    Notif.show('Platform Timeout: Verification of global dispatch failed.', 'error');
                }
            });
        });

        $(document).ready(() => updateEstimates());
    </script>
</body>
</html>
