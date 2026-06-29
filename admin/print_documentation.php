<?php
// admin/print_documentation.php
require_once __DIR__ . '/../includes/auth_check.php';

// Logic to determine context
$is_super_admin = ($role === 'super_admin');
$school_name = $active_school['school_name'] ?? 'EduRemarks Platform';

// Fetch dynamic settings for "Short Description" and "Why Schools Choose Us"
$platform_description = get_setting('hero_subtitle', 'EduRemarks is a high-performance, SaaS-driven school management ecosystem designed for modern excellence.');
$why_us_content = get_setting('why_us_content', 'EduRemarks was founded on the belief that school management should be simple, efficient, and data-driven. We automate tedious tasks so you can focus on teaching.');

// Fetch Features (Services)
try {
    $stmt = $pdo->query("SELECT * FROM platform_services ORDER BY sort_order ASC");
    $all_features = $stmt->fetchAll();
} catch (Exception $e) { $all_features = []; }

$filtered_features = $is_super_admin ? $all_features : $all_features; // Show full capability for marketing documentation

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Operations Handbook | <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary: #1F3C88;
            --secondary: #D4AF37;
            --dark: #0F172A;
            --text: #334155;
            --light-bg: #F8FAFC;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            color: var(--text);
            background: #f1f5f9;
            line-height: 1.5;
        }

        /* A4 Page Layout */
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        @media print {
            body { background: none; margin: 0; padding: 0; }
            .page { 
                margin: 0; 
                box-shadow: none; 
                width: 100%;
                min-height: 210mm;
                height: 100vh;
                page-break-after: always;
            }
            .page:last-child { page-break-after: avoid; }
            .no-print { display: none; }
        }

        /* Cover Page */
        .cover-page {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            flex-grow: 1;
            border: 8px double var(--primary);
            padding: 40px;
        }

        .cover-logo { width: 100px; margin-bottom: 25px; }
        .cover-title { 
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1.1;
            margin-bottom: 15px;
        }
        .cover-subtitle {
            font-size: 1.2rem;
            color: var(--secondary);
            font-weight: 800;
            margin-bottom: 40px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .institutional-info {
            margin-top: 80px;
            border-top: 1px solid #e2e8f0;
            padding-top: 25px;
            width: 70%;
        }

        /* Typography & Structure */
        h2 { 
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--secondary);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
        }
        h2 i { font-size: 1.2rem; margin-right: 15px; color: var(--secondary); }

        h3 {
            color: var(--primary);
            font-size: 1.25rem;
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 800;
        }

        p { margin-bottom: 12px; font-size: 0.95rem; text-align: justify; }

        .feature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
            margin-bottom: 25px;
        }

        .feature-card {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            break-inside: avoid;
        }

        .feature-card h4 {
            color: var(--primary);
            font-size: 0.95rem;
            margin-bottom: 5px;
            font-weight: 800;
        }

        .feature-card p {
            font-size: 0.82rem;
            color: #64748B;
            margin-bottom: 0;
            text-align: left;
        }

        .highlight-box {
            background: #fdfaf0;
            border: 1px solid #f9ebcc;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            break-inside: avoid;
        }

        .diagram-container {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            break-inside: avoid;
        }

        .footer {
            margin-top: auto;
            border-top: 1px solid #f1f5f9;
            padding-top: 10px;
            font-size: 0.75rem;
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
        }

        .print-btn {
            position: fixed; top: 20px; right: 20px; background: var(--primary); color: white;
            padding: 12px 25px; border-radius: 50px; border: none; font-weight: 800;
            cursor: pointer; box-shadow: 0 10px 20px rgba(0,0,0,0.15); z-index: 1000;
        }
        
        ul { list-style: none; margin-bottom: 15px; }
        li { padding-left: 20px; position: relative; margin-bottom: 5px; font-size: 0.9rem; }
        li::before { content: '•'; position: absolute; left: 0; color: var(--secondary); font-weight: 900; }

        .economics-table {
            width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85rem;
        }
        .economics-table th, .economics-table td {
            text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0;
        }
        .economics-table th { background: #f8fafc; color: var(--primary); font-weight: 800; }
    </style>
</head>
<body>

    <button onclick="window.print()" class="print-btn no-print">
        <i class="fas fa-file-pdf me-2"></i>SAVE AS PDF
    </button>

    <!-- Page 1: Cover -->
    <div class="page">
        <div class="cover-page">
            <img src="../img/logo.png" alt="Logo" class="cover-logo">
            <div class="cover-title">EDUREMARKS</div>
            <div class="cover-subtitle">Institutional Operations Handbook</div>
            
            <div class="institutional-info">
                <p style="text-align: center; font-weight: 800; margin-bottom: 5px; color: var(--primary);"><?php echo htmlspecialchars($school_name); ?></p>
                <p style="text-align: center; color: #64748B; font-size: 0.85rem;">Official Academic Document &bull; Version 4.1</p>
                <p style="text-align: center; color: #94a3b8; font-size: 0.75rem; margin-top: 10px;">Generated: <?php echo date('F d, Y h:i A'); ?></p>
            </div>
        </div>
        <div class="footer">
            <span>© <?php echo date('Y'); ?> EduRemarks Platform | Academic Engineering Node</span>
            <span>Handout 01</span>
        </div>
    </div>

    <!-- Page 2: Foundation & Economics -->
    <div class="page">
        <h2><i class="fas fa-university"></i> 1. Platform Foundation</h2>
        <p class="lead" style="font-size: 1.1rem; font-weight: 600; color: var(--primary); margin-bottom: 20px;">
            <?php echo $platform_description; ?>
        </p>

        <h3>Why Schools Choose EduRemarks</h3>
        <div class="highlight-box">
            <p style="font-weight: 600; color: #92400E; margin-bottom: 0; line-height: 1.5; font-style: italic;">
                "<?php echo $why_us_content; ?>"
            </p>
        </div>

        <h3>Operational Economics (Pay-As-You-Measure)</h3>
        <p>EduRemarks utilizes a transparent <strong>Credit-Based Economy</strong>. Unlike traditional subscription models, institutions only invest in operational credits required for specific academic deliverables.</p>
        
        <table class="economics-table">
            <thead>
                <tr>
                    <th>Academic Activity</th>
                    <th>Billing Frequency</th>
                    <th>System Impact</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Result Synchronization</td>
                    <td>1 Credit / Student</td>
                    <td>Atomic processing & PDF archival</td>
                </tr>
                <tr>
                    <td>CBT Examination hosting</td>
                    <td>2 Credits / Student</td>
                    <td>Real-time proctoring & instant marking</td>
                </tr>
                <tr>
                    <td>Admission Management</td>
                    <td>Zero (Inclusive)</td>
                    <td>Unlimited ID generation & digital files</td>
                </tr>
                <tr>
                    <td><?php echo get_label('Staff'); ?> Portal</td>
                    <td>Zero (Inclusive)</td>
                    <td>Unlimited lesson plans & assignments</td>
                </tr>
            </tbody>
        </table>

        <h3>Onboarding Lifecycle</h3>
        <p>The institutional setup is orchestrated through a structured data verification flow, ensuring that school identity and academic records are accurately mapped before full operations begin.</p>
        <div class="diagram-container" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
            <div style="font-weight: 800; color: var(--primary); margin-bottom: 5px;">Data Synchronization Protocol</div>
            <div style="font-size: 0.75rem; color: #64748b;">Registration → Verification → Branding → Academic Setup → Full Sync</div>
        </div>

        <div class="footer">
            <span>Section: Economics & Foundation</span>
            <span>Handout 02</span>
        </div>
    </div>

    <!-- Page 3: Full Platform Feature Matrix -->
    <div class="page">
        <h2><i class="fas fa-cubes"></i> 2. Platform Feature Matrix</h2>
        <p>EduRemarks integrates all essential academic nodes into a single, synchronized hub for administrative excellence.</p>

        <div class="feature-grid">
            <div class="feature-card">
                <h4>Advanced Result Hub</h4>
                <p>Automated broadsheets, termly performance analytics, and synchronized grade computation with 100% accuracy.</p>
            </div>
            <div class="feature-card">
                <h4>Financial Orchestration</h4>
                <p>Integrated payment gateways, automated fee receipts, and real-time institutional financial reporting.</p>
            </div>
            <div class="feature-card">
                <h4>Institutional CBT Hub</h4>
                <p>Scalable online examination engine with automated marking, timers, and randomized question banks.</p>
            </div>
            <div class="feature-card">
                <h4>Admission Engineering</h4>
                <p>Customizable admission ID patterns, digital student folders, and automated onboarding protocols.</p>
            </div>
            <div class="feature-card">
                <h4><?php echo get_label('Staff'); ?> Node</h4>
                <p>Digital lesson planning, attendance tracking, and dynamic classroom assignments for every educator.</p>
            </div>
            <div class="feature-card">
                <h4>Global Broadcaster</h4>
                <p>Institutional SMS and Email notification relay for instant communication with parents and stakeholders.</p>
            </div>
            <div class="feature-card">
                <h4>Parent Performance Portal</h4>
                <p>Real-time academic previews for parents, secure result checking, and transparent fee history access.</p>
            </div>
            <div class="feature-card">
                <h4>Inventory & Resource Bank</h4>
                <p>Digital management of classroom assets, textbook distribution, and institutional resource leveling.</p>
            </div>
        </div>

        <h3>Additional Academic Services</h3>
        <ul style="column-count: 2;">
            <li>Digital Attendance Registers</li>
            <li>Behavioural Tracking Nodes</li>
            <li>Subject & Scheme Mapping</li>
            <li>Bulk Student Data Ingestion</li>
            <li>Academic Broadcaster</li>
            <li>Institutional Asset Tracking</li>
        </ul>

        <div class="highlight-box" style="margin-top: 30px; border-left: 5px solid var(--secondary);">
            <h4 style="color: var(--primary); margin-bottom: 5px;">Institutional Scalability</h4>
            <p style="font-size: 0.85rem; margin-bottom: 0;">Our platform is architected to scale from individual classrooms to multi-campus institutions without loss of data integrity or performance speed.</p>
        </div>

        <div class="footer">
            <span>Section: Feature Capability Matrix</span>
            <span>Handout 03</span>
        </div>
    </div>

</body>
</html>
