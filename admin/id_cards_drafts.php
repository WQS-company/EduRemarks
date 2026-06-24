<?php
// admin/id_cards_drafts.php
require_once '../includes/auth_check.php';
if (!$active_school) { header('Location: add_school.php'); exit(); }

$school_id = $active_school['id'];
$pageTitle = "ID Card Drafts";

// Fetch all drafts
$stmt = $pdo->prepare("SELECT g.*, t.template_name, t.preview_color, t.has_qr, t.has_barcode
    FROM generated_id_cards g
    LEFT JOIN id_card_templates t ON g.template_key = t.template_key
    WHERE g.school_id = ? ORDER BY g.created_at DESC");
$stmt->execute([$school_id]);
$drafts = $stmt->fetchAll();

// Stats
$total_cards   = array_sum(array_map(fn($d) => count(explode(',', $d['member_ids'])), $drafts));
$total_credits = array_sum(array_column($drafts, 'credits_used'));
$total_drafts  = count($drafts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> | EduRemarks</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../css/style.css">
<style>
.draft-row { border-radius:12px; border:1px solid #e2e8f0; background:#fff; padding:1rem 1.25rem; margin-bottom:.75rem; transition:.2s; }
.draft-row:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); border-color:#c7d2fe; }
.tpl-dot { width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px; }
.stat-chip { border-radius:12px; padding:.75rem 1.25rem; background:#fff; border:1px solid #e2e8f0; }
</style>
</head>
<body>
<?php include '../includes/spinner.php'; ?>
<?php if ($role === 'staff'): ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>
    <main class="sa-main-content">
<?php else: ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>
<?php endif; ?>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-800 mb-0"><i class="fas fa-archive me-2 text-primary"></i>ID Card Drafts</h3>
                <p class="text-muted small mb-0">All previously generated institutional identity card batches.</p>
            </div>
            <a href="id_cards.php" class="btn btn-gold rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-plus me-2"></i>Generate New Batch
            </a>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-chip text-center">
                    <div class="h3 fw-900 text-primary mb-0"><?php echo $total_drafts; ?></div>
                    <div class="extra-small text-muted text-uppercase fw-700">Batches</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-chip text-center">
                    <div class="h3 fw-900 text-success mb-0"><?php echo $total_cards; ?></div>
                    <div class="extra-small text-muted text-uppercase fw-700">Total Cards</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-chip text-center">
                    <div class="h3 fw-900 text-warning mb-0"><?php echo number_format($total_credits); ?></div>
                    <div class="extra-small text-muted text-uppercase fw-700">Credits Used</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-chip text-center">
                    <div class="h3 fw-900 text-danger mb-0"><?php echo number_format($active_school['credits']); ?></div>
                    <div class="extra-small text-muted text-uppercase fw-700">Remaining</div>
                </div>
            </div>
        </div>

        <?php if(empty($drafts)): ?>
        <div class="glass-card p-5 text-center">
            <i class="fas fa-id-card fa-3x text-primary opacity-25 mb-3 d-block"></i>
            <h5 class="fw-bold">No Drafts Yet</h5>
            <p class="text-muted">Generate your first batch of ID cards to see them here.</p>
            <a href="id_cards.php" class="btn btn-gold rounded-pill px-5 fw-bold">Generate Now</a>
        </div>
        <?php else: ?>
        <div class="glass-card p-4">
            <!-- Filter -->
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <input type="text" class="form-control form-control-sm w-auto" id="draftSearch" placeholder="Search drafts..." oninput="filterDrafts(this.value)" style="min-width:200px;">
                <select class="form-select form-select-sm w-auto" onchange="filterByType(this.value)">
                    <option value="">All Types</option>
                    <option value="student">Students</option>
                    <option value="staff">Staff</option>
                </select>
            </div>

            <div id="draftsList">
            <?php foreach($drafts as $d):
                $count = count(explode(',', $d['member_ids']));
                $color = $d['preview_color'] ?? '#1a56db';
                $tpl_name = $d['template_name'] ?? $d['template_key'];
            ?>
            <div class="draft-row d-flex align-items-center gap-3 flex-wrap" data-type="<?php echo $d['card_type']; ?>" data-name="<?php echo strtolower($tpl_name); ?>">
                <!-- Color pill -->
                <div style="width:42px;height:42px;border-radius:10px;background:<?php echo $color; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-id-card text-white"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($tpl_name); ?></div>
                    <div class="extra-small text-muted">
                        <span class="badge <?php echo $d['card_type'] === 'student' ? 'bg-primary' : 'bg-success'; ?> bg-opacity-10 <?php echo $d['card_type'] === 'student' ? 'text-primary' : 'text-success'; ?> rounded-pill me-1"><?php echo ucfirst($d['card_type']); ?></span>
                        <?php if($d['has_qr']): ?><span class="badge" style="background:#eef2ff;color:#6366f1;border-radius:20px;font-size:.6rem;">QR</span><?php endif; ?>
                        <?php if($d['has_barcode']): ?><span class="badge" style="background:#f0fdf4;color:#059669;border-radius:20px;font-size:.6rem;">BARCODE</span><?php endif; ?>
                    </div>
                </div>
                <div class="text-center d-none d-md-block">
                    <div class="fw-bold text-dark"><?php echo $count; ?></div>
                    <div class="extra-small text-muted">Cards</div>
                </div>
                <div class="text-center d-none d-md-block">
                    <div class="fw-bold text-warning"><?php echo number_format($d['credits_used']); ?></div>
                    <div class="extra-small text-muted">Credits</div>
                </div>
                <div class="text-center d-none d-md-block">
                    <div class="fw-bold small text-muted"><?php echo date('M d', strtotime($d['created_at'])); ?></div>
                    <div class="extra-small text-muted"><?php echo date('Y', strtotime($d['created_at'])); ?></div>
                </div>
                <div class="d-flex gap-2 flex-shrink-0 ms-auto">
                    <?php if($d['pdf_path'] && file_exists('../'.$d['pdf_path'])): ?>
                    <a href="../<?php echo $d['pdf_path']; ?>" target="_blank" class="btn btn-sm btn-primary rounded-pill px-3" title="View / Print">
                        <i class="fas fa-print me-1"></i><span class="d-none d-sm-inline">Print</span>
                    </a>
                    <a href="../<?php echo $d['pdf_path']; ?>" download class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <?php else: ?>
                    <span class="badge bg-danger rounded-pill px-3 py-2">File Missing</span>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteDraft(<?php echo $d['id']; ?>)" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php include '../includes/dashboard_footer.php'; ?>
    </main>
<?php if ($role !== 'staff'): ?>
</div>
<?php endif; ?>

<script>
function filterDrafts(q) {
    document.querySelectorAll('#draftsList .draft-row').forEach(row => {
        row.style.display = row.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
    });
}
function filterByType(type) {
    document.querySelectorAll('#draftsList .draft-row').forEach(row => {
        row.style.display = (!type || row.dataset.type === type) ? '' : 'none';
    });
}
function deleteDraft(id) {
    if (!confirm('Delete this draft permanently?')) return;
    Spinner.show('Deleting draft...');
    fetch('../ajax/delete_id_card_draft.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'id=' + id
    }).then(r => r.json()).then(d => {
        Spinner.hide();
        if (d.success) { Notif.show('Draft deleted.'); setTimeout(() => location.reload(), 1200); }
        else Notif.show(d.message, 'error');
    });
}
</script>
</body>
</html>
