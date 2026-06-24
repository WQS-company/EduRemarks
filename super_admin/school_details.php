<?php
// super_admin/school_details.php - Detailed View of a School
require_once 'auth_check.php';

$school_id = $_GET['id'] ?? null;
if (!$school_id) {
    header("Location: schools.php");
    exit();
}

// Fetch school and owner details
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone, u.created_at as owner_joined
    FROM schools s 
    JOIN users u ON s.owner_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

if (!$school) {
    die("School not found.");
}

// Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
$stmt->execute([$school_id]);
$students_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_details WHERE school_id = ?");
$stmt->execute([$school_id]);
$staff_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
$stmt->execute([$school_id]);
$classes_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(amount) FROM platform_payments WHERE school_id = ? AND status = 'success'");
$stmt->execute([$school_id]);
$total_revenue = $stmt->fetchColumn() ?: 0;

// Recent Top-ups
$stmt = $pdo->prepare("
    SELECT * FROM platform_payments 
    WHERE school_id = ? AND status = 'success' 
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$school_id]);
$payments = $stmt->fetchAll();

// Pending Billing Request
$stmt = $pdo->prepare("SELECT * FROM billing_requests WHERE school_id = ? AND status = 'pending' LIMIT 1");
$stmt->execute([$school_id]);
$pending_request = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Profile | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        .glass-card { border-radius: 12px; border: none; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .text-blue { color: #1e3a8a; }
        .tiny-text { font-size: 0.75rem; }
        .fw-800 { font-weight: 800; }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="schools.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left me-1"></i> Back to Schools</a>
            <h4 class="fw-800 mb-0 mt-2"><?php echo htmlspecialchars($school['school_name']); ?></h4>
            <p class="text-muted small">Unique ID: <strong><?php echo htmlspecialchars($school['unique_id']); ?></strong></p>
        </div>
        <div>
            <?php if($school['status'] === 'active'): ?>
                <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i> Active</span>
            <?php elseif($school['status'] === 'pending'): ?>
                <span class="badge bg-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i> Pending</span>
            <?php else: ?>
                <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i> Suspended</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="glass-card p-4 text-center h-100">
                <div class="text-primary mb-2" style="font-size: 2rem;"><i class="fas fa-user-graduate"></i></div>
                <h3 class="fw-800 mb-1"><?php echo number_format($students_count); ?></h3>
                <p class="text-muted tiny-text mb-0 uppercase fw-bold">Students</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-4 text-center h-100">
                <div class="text-info mb-2" style="font-size: 2rem;"><i class="fas fa-users-cog"></i></div>
                <h3 class="fw-800 mb-1"><?php echo number_format($staff_count); ?></h3>
                <p class="text-muted tiny-text mb-0 uppercase fw-bold">Staff</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-4 text-center h-100">
                <div class="text-warning mb-2" style="font-size: 2rem;"><i class="fas fa-bolt"></i></div>
                <h3 class="fw-800 mb-1"><?php echo number_format($school['credits']); ?></h3>
                <p class="text-muted tiny-text mb-0 uppercase fw-bold">Active Credits</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-4 text-center h-100">
                <div class="text-success mb-2" style="font-size: 2rem;"><i class="fas fa-coins"></i></div>
                <h3 class="fw-800 mb-1">₦<?php echo number_format($total_revenue); ?></h3>
                <p class="text-muted tiny-text mb-0 uppercase fw-bold">Total Revenue</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <?php if($pending_request): ?>
                <div class="alert alert-primary border-0 shadow-sm p-4 mb-4" style="border-radius: 20px; background: linear-gradient(135deg, #1F3C88 0%, #2D6CDF 100%); color: white;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="fw-800 mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>Institutional Billing Request Received</h6>
                            <p class="small opacity-75 mb-0">The owner has requested a transition to <strong><?php echo htmlspecialchars($pending_request['requested_plan']); ?></strong> (<?php echo htmlspecialchars($pending_request['duration']); ?>).</p>
                        </div>
                        <button type="button" class="btn btn-gold rounded-pill px-4 fw-800 shadow-sm" data-bs-toggle="modal" data-bs-target="#billingModal">
                            REVIEW & APPROVE
                        </button>
                    </div>
                    <?php if($pending_request['notes']): ?>
                        <div class="mt-3 p-3 rounded-4 bg-white bg-opacity-10 small border border-white border-opacity-10">
                            <strong>Notes:</strong> <?php echo htmlspecialchars($pending_request['notes']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Service & Billing Configuration -->
            <div class="glass-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                    <h5 class="fw-bold mb-0">Service & Billing Configuration</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#billingModal">
                            <i class="fas fa-edit me-1"></i> Configure Billing
                        </button>
                    </div>
                </div>

                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 bg-light mb-3 mb-md-0 border">
                            <small class="text-muted uppercase fw-bold tiny-text d-block mb-1">Active Billing Mode</small>
                            <?php if($school['billing_mode'] === 'subscription' && $school['subscription_active']): ?>
                                <h5 class="fw-800 text-success mb-1"><i class="fas fa-calendar-check me-2"></i>Period-Based Subscription</h5>
                                <p class="small text-muted mb-0">Institutional operations are currently <strong>unlimited</strong>.</p>
                            <?php else: ?>
                                <h5 class="fw-800 text-blue mb-1"><i class="fas fa-bolt me-2"></i>Credit-Based Economy</h5>
                                <p class="small text-muted mb-0">Operations consume institutional credits per activity.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php if($school['billing_mode'] === 'subscription' && $school['subscription_active']): ?>
                            <div class="p-3 rounded-4 bg-success bg-opacity-10 mb-3 mb-md-0 border border-success border-opacity-25">
                                <small class="text-muted uppercase fw-bold tiny-text d-block mb-1">Current Agreement</small>
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold"><?php echo htmlspecialchars($school['subscription_type']); ?></span>
                                    <span class="text-success fw-800">₦<?php echo number_format($school['subscription_price'], 2); ?></span>
                                </div>
                                <div class="small text-muted mt-1">
                                    Expires: <strong><?php echo date('M d, Y', strtotime($school['subscription_end'])); ?></strong>
                                </div>
                                <a href="print_agreement.php?id=<?php echo $school_id; ?>" target="_blank" class="btn btn-sm btn-outline-success w-100 mt-2 rounded-pill">
                                    <i class="fas fa-print me-1"></i> Print Agreement
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="p-3 rounded-4 bg-warning bg-opacity-10 mb-3 mb-md-0 border border-warning border-opacity-25">
                                <small class="text-muted uppercase fw-bold tiny-text d-block mb-1">Liquidity Status</small>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="fw-800 mb-0"><?php echo number_format($school['credits']); ?> <small class="tiny-text opacity-50">PTS</small></h4>
                                    <span class="badge bg-warning text-dark px-3 mt-1">Pay-As-You-Measure</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="glass-card p-4">
                <h5 class="fw-bold mb-4 border-bottom pb-3">Recent Payments (Top-Ups)</h5>
                <?php if(empty($payments)): ?>
                    <p class="text-muted small text-center py-4">No payment history available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr class="tiny-text text-muted uppercase">
                                    <th>Ref</th>
                                    <th>Amount</th>
                                    <th>Credits</th>
                                    <th>Package</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payments as $pay): ?>
                                <tr>
                                    <td class="small font-monospace"><?php echo $pay['reference']; ?></td>
                                    <td class="fw-bold text-success">₦<?php echo number_format($pay['amount'], 2); ?></td>
                                    <td><span class="badge bg-warning text-dark"><i class="fas fa-bolt me-1"></i> <?php echo number_format($pay['credits_awarded']); ?></span></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($pay['package_name'] ?? 'Custom Top-up'); ?></td>
                                    <td class="small text-muted"><?php echo date('M d, Y', strtotime($pay['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card p-4 h-100">
                <h5 class="fw-bold mb-4 border-bottom pb-3">Owner Profile</h5>
                
                <div class="text-center mb-4">
                    <div class="avatar shadow d-inline-block mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: #e0e7ff; color: #3b82f6; font-size: 2.2rem; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h5 class="fw-800 mb-1"><?php echo htmlspecialchars($school['owner_name']); ?></h5>
                    <div class="badge bg-dark bg-opacity-10 text-dark rounded-pill">System Owner</div>
                </div>

                <div class="mb-3">
                    <small class="text-muted d-block uppercase fw-bold mb-1 tiny-text">Email Address</small>
                    <a href="mailto:<?php echo htmlspecialchars($school['owner_email']); ?>" class="fw-600 text-decoration-none text-blue"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($school['owner_email']); ?></a>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted d-block uppercase fw-bold mb-1 tiny-text">Phone Number</small>
                    <a href="tel:<?php echo htmlspecialchars($school['owner_phone']); ?>" class="fw-600 text-decoration-none text-blue"><i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($school['owner_phone'] ?: 'N/A'); ?></a>
                </div>

                <div class="mb-3">
                    <small class="text-muted d-block uppercase fw-bold mb-1 tiny-text">Member Since</small>
                    <div class="fw-600"><i class="fas fa-calendar-alt me-2"></i> <?php echo date('M d, Y', strtotime($school['owner_joined'])); ?></div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <a href="schools.php" class="btn btn-outline-secondary w-100 rounded-pill"><i class="fas fa-cog me-2"></i>Manage Schools</a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Billing Configuration Modal -->
<div class="modal fade" id="billingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form id="billingForm">
                <input type="hidden" name="id" value="<?php echo $school_id; ?>">
                <input type="hidden" name="action" value="subscription">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-800 text-blue"><i class="fas fa-file-invoice-dollar me-2"></i>Institutional Billing Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if($pending_request): ?>
                        <!-- Request Context for Professionalism -->
                        <div class="p-3 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 mb-4">
                            <small class="text-muted uppercase fw-800 extra-small d-block mb-1">Incoming Request Context</small>
                            <div class="fw-bold text-blue small mb-1">Plan: <?php echo htmlspecialchars($pending_request['requested_plan']); ?></div>
                            <div class="small opacity-75 mb-2">Duration: <?php echo htmlspecialchars($pending_request['duration']); ?></div>
                            <?php if($pending_request['notes']): ?>
                                <div class="mt-2 p-2 rounded-3 bg-white small border">
                                    <i class="fas fa-quote-left me-1 opacity-25"></i> <?php echo htmlspecialchars($pending_request['notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">

                        <label class="form-label small fw-bold uppercase text-muted mb-2">Primary Billing Mode</label>
                        <div class="row g-3">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="billing_mode" id="modeCredit" value="credit" <?php echo $school['billing_mode'] === 'credit' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary w-100 p-3 rounded-4 d-flex flex-column" for="modeCredit">
                                    <i class="fas fa-bolt mb-2 fa-lg"></i>
                                    <span class="fw-bold">Credits</span>
                                    <small class="opacity-50">Pay-as-you-go</small>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="billing_mode" id="modeSub" value="subscription" <?php echo $school['billing_mode'] === 'subscription' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-success w-100 p-3 rounded-4 d-flex flex-column" for="modeSub">
                                    <i class="fas fa-calendar-alt mb-2 fa-lg"></i>
                                    <span class="fw-bold">Period</span>
                                    <small class="opacity-50">Unlimited Ops</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="subFields" style="<?php echo $school['billing_mode'] === 'credit' ? 'display:none;' : ''; ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Period Type</label>
                                <select class="form-select rounded-pill" name="subscription_type">
                                    <option value="1 Term" <?php echo $school['subscription_type'] === '1 Term' ? 'selected' : ''; ?>>1 Term</option>
                                    <option value="1 Session" <?php echo $school['subscription_type'] === '1 Session' ? 'selected' : ''; ?>>1 Session</option>
                                    <option value="1 Semester" <?php echo $school['subscription_type'] === '1 Semester' ? 'selected' : ''; ?>>1 Semester</option>
                                    <option value="1 Year" <?php echo $school['subscription_type'] === '1 Year' ? 'selected' : ''; ?>>1 Year</option>
                                    <option value="2 Years" <?php echo $school['subscription_type'] === '2 Years' ? 'selected' : ''; ?>>2 Years</option>
                                    <option value="Lifetime" <?php echo $school['subscription_type'] === 'Lifetime' ? 'selected' : ''; ?>>Unlimited Access</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Agreed Price (₦)</label>
                                <input type="number" class="form-control rounded-pill px-4" name="subscription_price" value="<?php echo floatval($school['subscription_price']); ?>" placeholder="0.00">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Starts From</label>
                                <input type="date" class="form-control rounded-pill px-4" name="subscription_start" value="<?php echo $school['subscription_start']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Ends At</label>
                                <input type="date" class="form-control rounded-pill px-4" name="subscription_end" value="<?php echo $school['subscription_end']; ?>">
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="subscription_active" value="1" id="subActive" <?php echo $school['subscription_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="subActive">Activate Subscription Mode Now</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('input[name="billing_mode"]').change(function() {
        if ($(this).val() === 'subscription') {
            $('#subFields').slideDown();
        } else {
            $('#subFields').slideUp();
        }
    });

    $('#billingForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.text();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

        $.ajax({
            url: '../ajax/sa_update_school.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(resp) {
                if (resp.success) {
                    Swal.fire('Success', resp.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', resp.message, 'error');
                    btn.prop('disabled', false).text(originalText);
                }
            }
        });
    });
});
</script>
</body>
</html>
