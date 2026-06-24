<?php
// verify.php - Public endpoint for QR code ID verification
require_once 'config/db.php';
require_once 'includes/functions.php';

$raw_id  = $_GET['id'] ?? '';
$type    = $_GET['t'] ?? 'student';
$sch_id  = (int)($_GET['s'] ?? 0);

$user_id = (int)base64_decode($raw_id);

if (!$user_id || !$sch_id || !in_array($type, ['student','staff'])) {
    die("Invalid Verification Link");
}

// Fetch member data
$member = null;
$school = null;

// Fetch School
$shStmt = $pdo->prepare("SELECT school_name, logo_path, address FROM schools WHERE id = ?");
$shStmt->execute([$sch_id]);
$school = $shStmt->fetch();

if ($type === 'student') {
    $stmt = $pdo->prepare("SELECT full_name, admission_no as id_num, student_class as role_text, image_path as photo, status FROM students WHERE id = ? AND school_id = ?");
    $stmt->execute([$user_id, $sch_id]);
    $member = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT u.full_name, u.id as id_num, 'Staff Member' as role_text, u.profile_picture as photo, sd.status FROM users u JOIN staff_details sd ON sd.user_id = u.id WHERE u.id = ? AND sd.school_id = ?");
    $stmt->execute([$user_id, $sch_id]);
    $member = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verification | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .verification-card { max-width: 420px; width: 100%; margin: 2rem auto; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.08); position: relative; }
        .v-header { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 2.5rem 2rem 5rem; text-align: center; position: relative; }
        .v-header svg { position: absolute; bottom: 0; left: 0; width: 100%; height: auto; margin-bottom: -1px; }
        .v-logo { max-width: 80px; max-height: 80px; object-fit: contain; margin-bottom: 1rem; border-radius: 8px; background: rgba(255,255,255,0.1); padding: 5px; }
        
        .v-body { padding: 0 2rem 2.5rem; text-align: center; margin-top: -3.5rem; position: relative; z-index: 10; }
        .v-photo-wrapper { width: 110px; height: 110px; margin: 0 auto 1.5rem; border-radius: 50%; padding: 4px; background: #fff; box-shadow: 0 8px 20px rgba(0,0,0,0.12); position: relative; }
        .v-photo { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background: #e2e8f0; }
        .v-status-badge { position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; border-radius: 50%; border: 3px solid #fff; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: #fff; }
        
        /* Status Colors */
        .status-active .v-status-badge { background: #10b981; }
        .status-active .v-status-text { color: #10b981; background: #d1fae5; }
        .status-inactive .v-status-badge { background: #ef4444; }
        .status-inactive .v-status-text { color: #ef4444; background: #fee2e2; }

        .v-detail-row { display: flex; flex-direction: column; text-align: left; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9; }
        .v-detail-row:last-child { border-bottom: none; }
        .v-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700; margin-bottom: 0.2rem; }
        .v-value { font-size: 1rem; font-weight: 600; color: #1e293b; }

        .v-footer { text-align: center; padding: 1.5rem; background: #f8fafc; border-top: 1px solid #f1f5f9; border-radius: 0 0 20px 20px; }
        .power-logo { height: 20px; opacity: 0.6; filter: grayscale(1); transition: 0.3s; }
        .power-logo:hover { opacity: 1; filter: grayscale(0); }
        
        .watermark { position: absolute; top: 30%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 5rem; font-weight: 900; color: rgba(0,0,0,0.03); white-space: nowrap; pointer-events: none; }
    </style>
</head>
<body>

<div class="container px-3">
    <?php if (!$member || !$school): ?>
        <div class="verification-card">
            <div class="v-header" style="background: linear-gradient(135deg, #ef4444, #b91c1c); padding-bottom:3rem;">
                <i class="fas fa-exclamation-circle fa-4x mb-3 text-white opacity-75"></i>
                <h3 class="fw-bold mb-0">Record Not Found</h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="#ffffff" fill-opacity="1" d="M0,192L48,181.3C96,171,192,149,288,144C384,139,480,149,576,170.7C672,192,768,224,864,229.3C960,235,1056,213,1152,186.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>
            </div>
            <div class="v-body" style="margin-top:0; padding-top:2rem;">
                <p class="text-muted">The ID card scanned does not match any valid records in our system. Ensure the code was not tampered with.</p>
                <div class="mt-4"><a href="index.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold">Return Home</a></div>
            </div>
        </div>
    <?php else: 
        $isActive = ($member['status'] === 'active');
        $statusClass = $isActive ? 'status-active' : 'status-inactive';
        $iconClass = $isActive ? 'fa-check' : 'fa-times';
        $statusLabel = $isActive ? 'ACTIVE & VALID' : 'INACTIVE / EXPIRED';
        
        $logo_html = '';
        if (!empty($school['logo_path']) && file_exists($school['logo_path'])) {
            $logo_html = '<img src="' . htmlspecialchars($school['logo_path']) . '" class="v-logo" alt="School Logo">';
        } else {
            $logo_html = '<div class="v-logo d-flex align-items-center justify-content-center text-white fw-bold fs-4 mx-auto" style="width:60px;height:60px;">'.substr($school['school_name'],0,1).'</div>';
        }
    ?>
    
    <div class="verification-card <?php echo $statusClass; ?>">
        <div class="watermark">EduRemarks</div>
        
        <div class="v-header">
            <?php echo $logo_html; ?>
            <h5 class="fw-bold mb-1 opacity-90"><?php echo htmlspecialchars($school['school_name']); ?></h5>
            <div class="small opacity-50"><?php echo htmlspecialchars($school['address']); ?></div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="#ffffff" fill-opacity="1" d="M0,192L48,181.3C96,171,192,149,288,144C384,139,480,149,576,170.7C672,192,768,224,864,229.3C960,235,1056,213,1152,186.7C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>
        </div>
        
        <div class="v-body">
            <div class="v-photo-wrapper">
                <?php if (!empty($member['photo']) && file_exists($member['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($member['photo']); ?>" class="v-photo" alt="Photo">
                <?php else: ?>
                    <img src="img/default_picture.png" class="v-photo" alt="Photo">
                <?php endif; ?>
                <div class="v-status-badge"><i class="fas <?php echo $iconClass; ?>"></i></div>
            </div>
            
            <h4 class="fw-800 mb-1 text-dark"><?php echo htmlspecialchars($member['full_name']); ?></h4>
            <div class="text-muted small fw-600 mb-3"><?php echo htmlspecialchars($member['role_text']); ?></div>
            
            <div class="d-inline-block px-3 py-1 rounded-pill v-status-text fw-bold extra-small mb-4 shadow-sm text-uppercase">
                <i class="fas <?php echo $iconClass; ?> me-1"></i><?php echo $statusLabel; ?>
            </div>
            
            <div class="v-details mt-2">
                <div class="v-detail-row">
                    <div class="v-label">ID Number</div>
                    <div class="v-value"><?php echo htmlspecialchars($member['id_num'] ?? '---'); ?></div>
                </div>
                <div class="v-detail-row">
                    <div class="v-label">Member Type</div>
                    <div class="v-value text-capitalize"><?php echo htmlspecialchars($type); ?></div>
                </div>
                <div class="v-detail-row">
                    <div class="v-label">Verification Time</div>
                    <div class="v-value"><?php echo date('d M Y, H:i A'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="v-footer">
            <div class="small opacity-50 mb-2">Verified securely via</div>
            <a href="index.php"><img src="images/logo.png" class="power-logo" alt="EduRemarks API" onerror="this.style.display='none';this.insertAdjacentHTML('afterend', '<strong class=\'text-dark\'>EduRemarks API</strong>');"></a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
