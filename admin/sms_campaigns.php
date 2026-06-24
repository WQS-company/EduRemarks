<?php
// admin/sms_campaigns.php - Institutional SMS Campaign Hub
require_once '../includes/auth_check.php';

if (!hasFeature('SMS_ALERTS')) {
    header('Location: dashboard.php');
    exit();
}

$school_id = $active_school['id'];

// Fetch Campaign History
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as sender_name 
    FROM sms_campaigns c 
    JOIN users u ON c.sender_id = u.id 
    WHERE c.school_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$school_id]);
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
    <title>SMS Campaigns | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .campaign-card { transition: all 0.3s ease; border: 1px solid #eef2f6; }
        .campaign-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(31, 60, 136, 0.1); }
        .status-badge { font-size: 0.65rem; padding: 4px 10px; border-radius: 20px; font-weight: 700; text-transform: uppercase; }
        .group-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .sms-preview { background: #f8fafc; border-radius: 16px; padding: 20px; border: 1px dashed #cbd5e1; position: relative; }
        .sms-preview::after { content: 'PREVIEW'; position: absolute; top: -10px; right: 20px; background: #fff; padding: 0 10px; font-size: 0.6rem; font-weight: 800; color: #94a3b8; }
        
        /* Custom Selection UI Utilities */
        .btn-tiny { font-size: 0.65rem; padding: 2px 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .shadow-hover { transition: all 0.2s ease; }
        .shadow-hover:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-color: var(--primary-blue) !important; }
        .custom-nodes-wrapper::-webkit-scrollbar { width: 5px; }
        .custom-nodes-wrapper::-webkit-scrollbar-track { background: transparent; }
        .custom-nodes-wrapper::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .transition-all { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/success_overlay.php'; ?>
    <?php include '../includes/notifications.php'; ?>
    
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
                <div>
                    <h3 class="fw-800 mb-0">SMS Campaign Hub</h3>
                    <p class="text-muted small">Broadcast professional updates to your institutional network</p>
                </div>
                <div class="d-flex gap-3">
                    <div class="glass-card px-4 py-2 d-flex align-items-center gap-3">
                        <div class="text-warning"><i class="fas fa-bolt fa-lg"></i></div>
                        <div>
                            <div class="tiny-text opacity-50 fw-bold">ECONOMICS</div>
                            <div class="fw-800 small"><?php echo $credit_cost_per_sms; ?> Credits / Node</div>
                        </div>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                        <i class="fas fa-paper-plane me-2"></i>New Campaign
                    </button>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div class="glass-card p-4">
                        <h6 class="fw-800 mb-4 text-primary uppercase tracking-1">Transmission History</h6>
                        
                        <?php if (empty($campaigns)): ?>
                            <div class="text-center py-5">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                                    <i class="fas fa-history text-muted fa-2x"></i>
                                </div>
                                <h5>No active transmissions found</h5>
                                <p class="text-muted small">Initialize your first SMS campaign to engage with parents and staff.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr class="text-muted small uppercase fw-bold border-bottom">
                                            <th>Campaign Identity</th>
                                            <th>Target Population</th>
                                            <th>Economics</th>
                                            <th>Orchestrator</th>
                                            <th>Transmission Time</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($campaigns as $c): ?>
                                        <tr class="reveal-fade-up">
                                            <td>
                                                <div class="fw-800 text-dark"><?php echo htmlspecialchars($c['subject']); ?></div>
                                                <div class="tiny-text text-muted text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($c['message']); ?></div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-light text-dark border rounded-pill px-3"><?php echo strtoupper($c['target_group']); ?></span>
                                                    <span class="small fw-800 opacity-50"><?php echo $c['recipients_count']; ?> Nodes</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small fw-800 text-warning"><?php echo number_format($c['total_credits']); ?> <i class="fas fa-bolt ms-1"></i></div>
                                            </td>
                                            <td><div class="small fw-700"><?php echo htmlspecialchars($c['sender_name']); ?></div></td>
                                            <td><div class="tiny-text opacity-75"><?php echo date('M d, Y &bull; h:i A', strtotime($c['created_at'])); ?></div></td>
                                            <td>
                                                <?php if ($c['status'] == 'sent'): ?>
                                                    <span class="status-badge bg-success bg-opacity-10 text-success">Synchronized</span>
                                                <?php elseif ($c['status'] == 'failed'): ?>
                                                    <span class="status-badge bg-danger bg-opacity-10 text-danger">Dropped</span>
                                                <?php else: ?>
                                                    <span class="status-badge bg-warning bg-opacity-10 text-warning">Processing</span>
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

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <!-- New Campaign Modal -->
    <div class="modal fade" id="newCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content border-0 shadow-lg rounded-4" id="campaignForm">
                <div class="modal-header border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0">Initialize SMS Transmission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="small fw-bold mb-2">Message Subject (Internal Reference)</label>
                            <input type="text" name="subject" class="form-control rounded-3" placeholder="e.g. PTA Meeting Notice" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold mb-2">Target Population</label>
                            <select name="target_group" id="targetGroup" class="form-select rounded-3" required onchange="calculateEconomics()">
                                <option value="all">Global (All Records)</option>
                                <option value="parents" selected>Guardians & Parents</option>
                                <option value="staff">Institutional Staff</option>
                                <option value="custom">Custom Selection</option>
                            </select>
                        </div>
                    </div>

                    <div id="customPopulation" class="mb-4 d-none">
                        <div class="p-3 bg-light rounded-4 border shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="small fw-800 text-primary uppercase tracking-1 mb-0">Node Discovery & Selection</label>
                                <div class="input-group input-group-sm w-50">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search opacity-50"></i></span>
                                    <input type="text" id="nodeSearch" class="form-control border-start-0" placeholder="Filter nodes..." oninput="filterNodes()">
                                </div>
                            </div>
                            
                            <div id="nodesAccordion" class="custom-nodes-wrapper" style="max-height: 300px; overflow-y: auto;">
                                <div class="text-center py-4 text-muted small">Initializing connection to institutional records...</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <label class="small fw-bold">Message Content</label>
                            <div class="tiny-text opacity-75 fw-bold" id="charCount">0 / 160 Characters</div>
                        </div>
                        <textarea name="message" id="smsMessage" class="form-control rounded-3" rows="4" maxlength="160" placeholder="Type your professional update here..." required oninput="updateCharCount()"></textarea>
                    </div>

                    <div class="sms-preview mb-4">
                        <div class="fw-800 small mb-1">Preview Transmission:</div>
                        <div id="previewText" class="small text-muted italic" style="min-height: 40px; font-style: italic;">Greetings from <?php echo htmlspecialchars($active_school['school_name']); ?>...</div>
                    </div>

                    <div class="alert alert-primary border-0 rounded-4 p-3 d-flex align-items-center justify-content-between mb-0 shadow-sm">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div>
                                <div class="extra-small fw-bold opacity-75">ESTIMATED ECONOMICS</div>
                                <div class="fw-900 h5 mb-0"><span id="totalCost">0</span> <span class="small fw-bold opacity-75">Credits</span></div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="extra-small fw-bold opacity-75">NODES</div>
                            <div class="fw-900 h6 mb-0 text-primary"><span id="recipientCount">0</span> Targets</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow">AUTHORIZE TRANSMISSION <i class="fas fa-paper-plane ms-2"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transmission Confirmation Modal -->
    <div class="modal fade" id="confirmDispatchModal" tabindex="-1" style="z-index: 1061;">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                            <i class="fas fa-shield-check fa-2x"></i>
                        </div>
                        <h5 class="fw-900 mb-2">Protocol Authorization</h5>
                        <p class="text-muted small mb-0">You are about to synchronize <strong class="text-dark" id="dispCount">0</strong> node transmissions costing <strong class="text-warning" id="dispCost">0</strong> credits. Continue?</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light w-100 rounded-pill py-2 fw-bold" data-bs-dismiss="modal">Abort</button>
                        <button type="button" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow" id="finalizeDispatchBtn">Finalize</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        const CREDIT_COST_PER_SMS = <?php echo $credit_cost_per_sms; ?>;
        
        function updateCharCount() {
            const msg = $('#smsMessage').val();
            $('#charCount').text(msg.length + ' / 160 Characters');
            $('#previewText').text(msg || 'Greetings from <?php echo htmlspecialchars($active_school['school_name']); ?>...');
        }

        function calculateEconomics() {
            const group = $('#targetGroup').val();
            const customDiv = $('#customPopulation');
            
            if(group === 'custom') {
                customDiv.removeClass('d-none');
                fetchInstitutionalNodes();
            } else {
                customDiv.addClass('d-none');
                updateEstimates();
            }
        }

        function fetchInstitutionalNodes() {
            const accordion = $('#nodesAccordion');
            if(accordion.find('.accordion-item').length > 0) return;

            $.get('../ajax/get_school_contacts.php', function(res) {
                if(res.success) {
                    accordion.empty();
                    
                    // 1. Staff Section
                    if(res.staff.length > 0) {
                        renderGroup('Staff Members', 'staff', res.staff);
                    }

                    // 2. Classes Sections
                    for(const [className, parents] of Object.entries(res.classes)) {
                        renderGroup(`Class: ${className}`, `class_${className.replace(/\s+/g, '_')}`, parents);
                    }
                }
            }, 'json');
        }

        function renderGroup(title, id, nodes) {
            const accordion = $('#nodesAccordion');
            let html = `
                <div class="group-category mb-3 node-group" data-group-name="${title.toLowerCase()}">
                    <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded-3 border mb-2 group-header">
                        <div class="fw-800 small text-dark">${title} <span class="badge bg-primary bg-opacity-10 text-primary ms-2">${nodes.length}</span></div>
                        <button type="button" class="btn btn-tiny btn-outline-primary rounded-pill px-3" onclick="toggleGroupSelection(this)">Select All</button>
                    </div>
                    <div class="row g-2 px-1 group-content">
            `;

            nodes.forEach(node => {
                html += `
                    <div class="col-md-6 node-item" data-node-name="${node.name.toLowerCase()}">
                        <label class="d-flex align-items-center gap-2 p-2 rounded-3 border hover-bg-light mb-0 cursor-pointer w-100 transition-all shadow-hover">
                            <input type="checkbox" name="selected_nodes[]" value="${node.phone}" class="form-check-input node-checkbox" onchange="updateEstimates()">
                            <div class="text-truncate">
                                <div class="fw-bold tiny-text">${node.name}</div>
                                <div class="extra-small opacity-50">${node.phone}</div>
                            </div>
                        </label>
                    </div>
                `;
            });

            html += `</div></div>`;
            accordion.append(html);
        }

        function toggleGroupSelection(btn) {
            const group = $(btn).closest('.group-category');
            const checkboxes = group.find('.node-checkbox');
            const isAllSelected = checkboxes.length === group.find('.node-checkbox:checked').length;
            
            checkboxes.prop('checked', !isAllSelected);
            $(btn).text(isAllSelected ? 'Select All' : 'Deselect All');
            updateEstimates();
        }

        function filterNodes() {
            const query = $('#nodeSearch').val().toLowerCase();
            $('.node-group').each(function() {
                let groupVisible = false;
                $(this).find('.node-item').each(function() {
                    const match = $(this).data('node-name').includes(query);
                    $(this).toggle(match);
                    if(match) groupVisible = true;
                });
                $(this).toggle(groupVisible || $(this).data('group-name').includes(query));
            });
        }

        function updateEstimates() {
            const group = $('#targetGroup').val();
            let count = 0;

            if(group === 'custom') {
                count = $('.node-checkbox:checked').length;
                renderEstimates(count);
            } else {
                $.get('../ajax/sms_estimate_count.php', { target: group }, function(res) {
                    if(res.success) renderEstimates(res.count);
                }, 'json');
            }
        }

        function renderEstimates(count) {
            $('#recipientCount').text(count);
            $('#totalCost').text(count * CREDIT_COST_PER_SMS);
        }

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmDispatchModal'));

        $('#campaignForm').on('submit', function(e) {
            e.preventDefault();
            const count = parseInt($('#recipientCount').text());
            if(count === 0) { Notif.show('No target nodes selected for transmission.', 'warning'); return; }
            
            $('#dispCount').text(count);
            $('#dispCost').text(count * CREDIT_COST_PER_SMS);
            confirmModal.show();
        });

        $('#finalizeDispatchBtn').on('click', function() {
            confirmModal.hide();
            Spinner.show('Synchronizing with SMS Gateway...');
            
            const formData = new FormData(document.getElementById('campaignForm'));
            
            $.ajax({
                url: '../ajax/send_sms_campaign.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    Spinner.hide();
                    if(res.success) {
                        bootstrap.Modal.getInstance(document.getElementById('newCampaignModal')).hide();
                        showSuccess('Transmission Synchronized', 'Your institutional broadcast has been dispatched to the SMS gateway successfully.', { reload: true });
                    } else {
                        Notif.show(res.message, 'error');
                    }
                },
                error: function() {
                    Spinner.hide();
                    Notif.show('Platform Timeout: Verification of gateway response failed.', 'error');
                }
            });
        });

        $(document).ready(() => {
            calculateEconomics(); // Initial calc
        });
    </script>
</body>
</html>
