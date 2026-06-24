<?php
// user/curriculum.php — Staff Curriculum Viewer
require_once '../includes/auth_check.php';

if ($role !== 'staff') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

if (!hasFeature('COURSE_CURRICULUM') || !($active_school['show_curriculum'] ?? 1)) {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = "Institutional Curriculum Guide";
$school_id = $_SESSION['school_id'] ?? 0;

// Fetch staff's current section(s) to apply default filter
$staff_stmt = $pdo->prepare("
    SELECT DISTINCT ss.id, ss.section_name 
    FROM staff_class_subjects scs
    JOIN classes c ON c.id = scs.class_id
    JOIN school_sections ss ON ss.section_name = c.section AND ss.school_id = c.school_id
    WHERE scs.staff_detail_id = (SELECT id FROM staff_details WHERE user_id = ? AND school_id = ? AND status='active')
    AND scs.school_id = ?
");
$staff_stmt->execute([$user_id, $school_id, $school_id]);
$my_sections = $staff_stmt->fetchAll();

$default_section_id = !empty($my_sections) ? $my_sections[0]['id'] : 0;

// Fetch ALL sections for the filter
$all_sections = $pdo->prepare("SELECT * FROM school_sections WHERE school_id = ? ORDER BY section_name");
$all_sections->execute([$school_id]);
$all_sections = $all_sections->fetchAll();

$all_subjects = $pdo->prepare("SELECT * FROM subjects WHERE school_id = ? ORDER BY name");
$all_subjects->execute([$school_id]);
$all_subjects = $all_subjects->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | EduRemarks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="<?php echo $school_logo_url; ?>" type="image/x-icon">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .curriculum-item { border-radius: 20px; background: white; border: 1.5px solid #f1f5f9; transition: 0.3s; cursor: pointer; }
        .curriculum-item:hover { border-color: var(--primary-blue); box-shadow: var(--shadow-hover); transform: translateY(-3px); }
        .section-tag { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--primary-blue); background: rgba(31, 60, 136, 0.08); padding: 4px 10px; border-radius: 50px; }
        .curriculum-viewer { background: white; border-radius: 24px; min-height: 500px; box-shadow: var(--shadow-soft); padding: 40px; }
        .content-area { font-family: 'Inter', sans-serif; line-height: 1.8; color: #475569; }
        .content-area h1, .content-area h2, .content-area h3 { color: #1e293b; font-weight: 800; margin-top: 25px; }
        .content-area ul, .content-area ol { padding-left: 20px; }
        .pill-tab { padding: 8px 24px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; border: none; background: #f1f5f9; color: #64748b; transition: 0.3s; }
        .pill-tab.active { background: var(--primary-blue); color: white; box-shadow: 0 4px 12px rgba(31, 60, 136, 0.2); }
        .empty-state { text-align: center; padding: 60px 20px; }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <?php include '../includes/staff_header.php'; ?>
    <?php include '../includes/staff_sidebar.php'; ?>

    <main class="sa-main-content">
        <div class="container-fluid py-4">
            <div class="row g-4">
                <!-- Sidebar Navigation -->
                <div class="col-lg-4">
                    <div class="d-flex flex-column gap-4">
                        <div class="glass-card p-4">
                            <h5 class="fw-900 text-dark mb-1">Standard Guide</h5>
                            <p class="text-muted small mb-4">Official institutional curriculum and modules.</p>
                            
                            <div class="d-flex flex-column gap-2 mb-4">
                                <label class="extra-small fw-800 uppercase tracking-2 opacity-50">Filter Results</label>
                                <select class="form-select border-0 shadow-sm bg-light rounded-pill px-3" id="filt_section" onchange="fetchNodes()">
                                    <option value="">All Institutional <?php echo get_label('Sections'); ?></option>
                                    <?php foreach($all_sections as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $default_section_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['section_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-select border-0 shadow-sm bg-light rounded-pill px-3" id="filt_subject" onchange="fetchNodes()">
                                    <option value="">All Academic <?php echo get_label('Subjects'); ?></option>
                                    <?php foreach($all_subjects as $sub): ?>
                                        <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="nav nav-pills gap-2 justify-content-center" id="term-tabs">
                                <?php if (get_label('Term') === 'Semester'): ?>
                                <button class="pill-tab active" onclick="setTerm(1, this)">SEMESTER 1</button>
                                <button class="pill-tab" onclick="setTerm(2, this)">SEMESTER 2</button>
                                <?php else: ?>
                                <button class="pill-tab active" onclick="setTerm(1, this)">TERM 1</button>
                                <button class="pill-tab" onclick="setTerm(2, this)">TERM 2</button>
                                <button class="pill-tab" onclick="setTerm(3, this)">TERM 3</button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="nodesList" class="d-flex flex-column gap-3">
                            <!-- Dynamic Content -->
                        </div>
                    </div>
                </div>

                <!-- Viewer Area -->
                <div class="col-lg-8">
                    <div class="curriculum-viewer" id="nodeViewer">
                        <div class="empty-state">
                            <i class="fas fa-book-reader fa-5x text-primary opacity-10 mb-4"></i>
                            <h4 class="fw-800 text-dark">Master <?php echo (get_label('Subject') === 'Course') ? 'Course' : 'Curriculum'; ?> Repository</h4>
                            <p class="text-muted">Select a module from the left list to view standard learning objectives, procedures, and resources for your <?php echo strtolower(get_label('Classes')); ?>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php include '../includes/dashboard_footer.php'; ?>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        let currentTerm = 1;
        const termLabel = '<?php echo get_label("Term"); ?>';

        function setTerm(term, btn) {
            currentTerm = term;
            $('.pill-tab').removeClass('active');
            $(btn).addClass('active');
            fetchNodes();
        }

        function fetchNodes() {
            const params = new URLSearchParams({
                action: 'fetch_all',
                section_id: $('#filt_section').val(),
                subject_id: $('#filt_subject').val(),
                term: currentTerm
            });

            Spinner.show('Retrieving Guide...');
            fetch(`../ajax/manage_curriculum.php?${params.toString()}`)
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) renderNodes(d.nodes);
            });
        }

        function renderNodes(nodes) {
            const list = $('#nodesList');
            list.empty();

            if (nodes.length === 0) {
                list.append(`
                    <div class="text-center py-5 opacity-50">
                        <p class="small fw-bold">No modules available for this filter.</p>
                    </div>
                `);
                return;
            }

            nodes.forEach(n => {
                list.append(`
                    <div class="curriculum-item p-3 reveal-fade-up" onclick="viewNode(${n.id})">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                             <span class="section-tag">${n.section_name || 'General'}</span>
                             <span class="fw-900 text-primary small">Week ${n.week}</span>
                        </div>
                        <h6 class="fw-800 text-dark mb-1 text-truncate">${n.topic}</h6>
                        <div class="extra-small text-muted fw-600">
                             <i class="fas fa-book me-1"></i> ${n.subject_name || 'Cross-subject'}
                        </div>
                    </div>
                `);
            });
        }

        function viewNode(id) {
            Spinner.show('Opening Module...');
            fetch(`../ajax/manage_curriculum.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    const n = d.node;
                    $('#nodeViewer').html(`
                        <div class="reveal-fade-up">
                            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-4">
                                <div>
                                    <h2 class="fw-900 text-dark mb-1">${n.topic}</h2>
                                    <div class="d-flex gap-3 extra-small fw-800 text-muted uppercase tracking-2">
                                        <span>${termLabel} ${n.term} &bull; Week ${n.week}</span>
                                        <span>${n.section_name || 'Institution-wide'}</span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="window.print()"><i class="fas fa-print"></i></button>
                                </div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-md-6">
                                    <div class="p-4 bg-light rounded-4 border-0">
                                        <h6 class="fw-900 text-primary uppercase tracking-1 mb-3"><i class="fas fa-bullseye me-2"></i>Learning Objectives</h6>
                                        <div class="small text-muted" style="white-space: pre-wrap;">${n.objectives || 'Standard objectives are being finalized...'}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-4 bg-light rounded-4 border-0">
                                        <h6 class="fw-900 text-primary uppercase tracking-1 mb-3"><i class="fas fa-toolbox me-2"></i>Instructional Resources</h6>
                                        <div class="small text-muted" style="white-space: pre-wrap;">${n.resources || 'Standard materials are being finalized...'}</div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-900 text-dark uppercase tracking-1 mb-4 border-bottom pb-2">Institutional Content & Procedures</h6>
                            <div class="content-area">
                                ${n.content || '<p class="text-muted italic">No detailed procedural content has been uploaded for this unit yet.</p>'}
                            </div>
                        </div>
                    `);
                    // Mobile Scroll to viewer
                    if (window.innerWidth < 992) {
                        document.getElementById('nodeViewer').scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        }

        $(document).ready(fetchNodes);
    </script>
</body>
</html>
