<?php
// super_admin/print_agreement.php
require_once 'auth_check.php';

$school_id = $_GET['id'] ?? null;
if (!$school_id) die("No institution ID provided.");

// Fetch school and owner details
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone
    FROM schools s 
    JOIN users u ON s.owner_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

if (!$school) die("Institution not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Agreement | <?php echo htmlspecialchars($school['school_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --primary: #1F3C88; --secondary: #D4AF37; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f1f5f9; color: #334155; line-height: 1.6; }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none; }
        }

        .header { display: flex; justify-content: space-between; align-items: start; border-bottom: 4px solid var(--primary); padding-bottom: 20px; margin-bottom: 40px; }
        .logo-box { display: flex; align-items: center; gap: 15px; }
        .logo-icon { width: 45px; height: 45px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 24px; }
        .brand-name { font-family: 'Playfair Display', serif; font-size: 24px; font-weight: 900; color: var(--primary); }

        .agreement-title { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 900; color: var(--primary); text-align: center; margin-bottom: 10px; text-transform: uppercase; }
        .agreement-subtitle { text-align: center; color: var(--secondary); font-weight: 800; letter-spacing: 2px; margin-bottom: 50px; }

        .section { margin-bottom: 35px; }
        .section-title { font-size: 14px; font-weight: 800; text-transform: uppercase; color: var(--primary); border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .info-label { font-size: 10px; font-weight: 800; color: #64748B; text-transform: uppercase; margin-bottom: 3px; }
        .info-value { font-size: 14px; font-weight: 600; color: #1e293b; }

        .billing-hero { background: var(--primary); color: white; padding: 30px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; margin-top: 40px; }
        .billing-price { font-size: 36px; font-weight: 900; font-family: 'Playfair Display', serif; }
        .billing-period { font-weight: 800; color: var(--secondary); text-transform: uppercase; letter-spacing: 1px; }

        .terms { font-size: 11px; color: #64748B; line-height: 1.8; margin-top: 50px; }
        .signatures { margin-top: 80px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px; }
        .sig-line { border-top: 1px solid #333; padding-top: 10px; text-align: center; font-size: 12px; font-weight: 800; }

        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 120px; font-weight: 900; color: rgba(31, 60, 136, 0.03); pointer-events: none; white-space: nowrap; }

        .print-btn { position: fixed; bottom: 30px; right: 30px; background: var(--primary); color: white; border: none; padding: 15px 30px; border-radius: 50px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-btn no-print"><i class="fas fa-print me-2"></i> PRINT AGREEMENT</button>

    <div class="page">
        <div class="watermark">OFFICIAL AGREEMENT</div>
        
        <div class="header">
            <div class="logo-box">
                <div class="logo-icon"><i class="fas fa-university"></i></div>
                <div>
                    <div class="brand-name">EDUREMARKS</div>
                    <div style="font-size: 10px; font-weight: 800; letter-spacing: 1px; opacity: 0.7;">PLATFORM ORCHESTRATION</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 12px; font-weight: 800;"><?php echo date('F d, Y'); ?></div>
                <div style="font-size: 10px; color: #64748B;">Agreement Ref: AG-<?php echo $school['unique_id']; ?></div>
            </div>
        </div>

        <div class="agreement-title">Billing Agreement</div>
        <div class="agreement-subtitle">Academic Operational Mandate</div>

        <div class="section">
            <div class="section-title"><i class="fas fa-school"></i> Institutional Beneficiary</div>
            <div class="grid">
                <div class="info-box">
                    <div class="info-label">School Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($school['school_name']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Institutional ID</div>
                    <div class="info-value"><?php echo $school['unique_id']; ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><i class="fas fa-user-tie"></i> Authorized Owner</div>
            <div class="grid">
                <div class="info-box">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($school['owner_name']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Communication Node</div>
                    <div class="info-value"><?php echo htmlspecialchars($school['owner_email']); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><i class="fas fa-file-invoice-dollar"></i> Agreed Commercial Terms</div>
            <div class="info-box mb-3">
                <div class="info-label">Billing Modality</div>
                <div class="info-value">Period-Based Subscription (Institutional Unlimited Node)</div>
            </div>
            
            <div class="billing-hero">
                <div>
                    <div class="billing-period"><?php echo htmlspecialchars($school['subscription_type']); ?> Operational Access</div>
                    <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;">
                        Valid from: <?php echo date('M d, Y', strtotime($school['subscription_start'])); ?> 
                        &bull; 
                        Expires: <?php echo date('M d, Y', strtotime($school['subscription_end'])); ?>
                    </div>
                </div>
                <div class="billing-price">₦<?php echo number_format($school['subscription_price'], 2); ?></div>
            </div>
        </div>

        <div class="section terms">
            <p><strong>Operational Bypassing:</strong> Under this agreement, the institution is granted unlimited access to standard academic operations including result synchronization, student orchestration, and basic faculty coordination without credit consumption.</p>
            <p><strong>Policy Compliance:</strong> This agreement is binding for the specified period. Early termination or breach of platform security protocols may result in immediate decommissioning of the institutional node.</p>
        </div>

        <div class="signatures">
            <div class="sig-line">
                Institutional Representative
                <div style="font-size: 9px; opacity: 0.5; margin-top: 5px;">(Stamp & Signature)</div>
            </div>
            <div class="sig-line">
                EduRemarks Super Admin
                <div style="font-size: 9px; opacity: 0.5; margin-top: 5px;">(Authorized Seal)</div>
            </div>
        </div>
    </div>
</body>
</html>
