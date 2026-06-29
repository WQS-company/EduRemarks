<?php
// admin/curriculum.php
require_once '../includes/auth_check.php';

if ($role !== 'owner' && $role !== 'super_admin') { 
    header('Location: ../dashboard.php'); 
    exit(); 
}

if (!hasFeature('COURSE_CURRICULUM') && $role !== 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = get_label('Subject') . " Curriculum Management";

// Pre-fetch basic data for filters
$sections = $pdo->prepare("SELECT * FROM school_sections WHERE school_id = ? ORDER BY section_name");
$sections->execute([$active_school_id]);
$sections = $sections->fetchAll();

$classes = $pdo->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY name");
$classes->execute([$active_school_id]);
$classes = $classes->fetchAll();

$subjects = $pdo->prepare("SELECT * FROM subjects WHERE school_id = ? ORDER BY name");
$subjects->execute([$active_school_id]);
$subjects = $subjects->fetchAll();
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
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .curriculum-card { border-radius: 16px; border: none; transition: 0.3s; box-shadow: var(--shadow-soft); overflow: hidden; }
        .curriculum-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); }
        .term-badge { background: var(--primary-blue); color: white; padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }
        .week-badge { background: #E8F5E9; color: #2E7D32; padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }
        #editor { height: 350px; border-radius: 0 0 16px 16px; }
        .filter-bar { 
            background: white; 
            border-radius: 50px; 
            padding: 5px 15px; 
            box-shadow: var(--shadow-soft); 
            display: flex;
            align-items: center;
            gap: 15px;
            overflow-x: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .filter-bar::-webkit-scrollbar { display: none; }
        .filter-item {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-right: 1.5px solid #f1f5f9;
        }
        .filter-item:last-child { border-right: none; }
        .filter-select { border: none; background: transparent; font-weight: 700; color: var(--dark-text); cursor: pointer; font-size: 0.82rem; white-space: nowrap; }
        .filter-select:focus { outline: none; box-shadow: none; }
        @media (max-width: 991px) {
            .filter-bar { border-radius: 15px; padding: 10px; margin-bottom: 30px !important; }
            .filter-item { border-right: none; background: #f8fafc; border-radius: 10px; padding: 5px 12px; }
            .container-fluid { padding-left: 12px !important; padding-right: 12px !important; }
            .main-content { overflow-x: hidden !important; width: 100%; }
            html, body { overflow-x: hidden; position: relative; width: 100%; }
        }
        .offcanvas { z-index: 3000 !important; }
        .offcanvas-backdrop { z-index: 2999 !important; background-color: rgba(31, 60, 136, 0.4) !important; backdrop-filter: blur(8px); }
        .offcanvas-curriculum { 
            width: 80% !important; 
            border-radius: 40px 0 0 40px; 
            border-left: 15px solid var(--primary-blue);
            border-top: 6px solid var(--premium-gold);
            box-shadow: -20px 0 60px rgba(0,0,0,0.15);
        }
        .card-guide-text { font-size: 0.62rem; font-weight: 800; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary-blue); opacity: 0.7; }
        .form-floating > .form-control:focus, .form-floating > .form-select:focus { border-color: var(--primary-blue) !important; box-shadow: 0 0 0 0.25rem rgba(31, 60, 136, 0.1); }
        .form-floating > label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary-blue); opacity: 0.7; }
        .btn-commit { 
            background: linear-gradient(135deg, var(--primary-blue), #2a5298);
            border: none;
            color: white;
            white-space: nowrap;
            letter-spacing: 1px;
            box-shadow: 0 10px 25px rgba(31, 60, 136, 0.3);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .btn-commit:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 15px 35px rgba(31, 60, 136, 0.4);
            color: var(--premium-gold) !important;
        }
        .inner-form-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 24px; padding: 25px; transition: 0.3s; }
        .inner-form-card:hover { border-color: var(--primary-blue); background: #fcfdfe; }
        @media (max-width: 991px) { 
            .offcanvas-curriculum { width: 100% !important; border-radius: 0; border-left: none; border-top: 10px solid var(--primary-blue); }
            .offcanvas-body { padding: 25px 20px !important; }
            .btn-commit { width: 100%; padding-top: 18px; padding-bottom: 18px; font-size: 1rem; border-radius: 16px !important; margin-top: 15px; }
            .inner-form-card { padding: 20px 15px; border-radius: 20px; }
            .container-fluid { overflow-x: hidden; }
        }
        @media (max-width: 480px) {
            .offcanvas-header h4 { font-size: 1.3rem; }
            h3.fw-900 { font-size: 1.6rem; }
            .btn-commit { font-size: 0.85rem !important; }
        }
        .btn-action-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.3s;
        }
        .btn-action-sm i { font-size: 0.8rem; }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/spinner.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="main-content">
            <?php include '../includes/dashboard_top_nav.php'; ?>

            <div class="container-fluid py-4">
                <div class="d-flex flex-mobile-column justify-content-between align-items-center mb-5 gap-4">
                    <div>
                        <h3 class="fw-900 mb-1 text-dark" style="letter-spacing:-1px;"><?php echo get_label('Subject'); ?> Curriculum</h3>
                        <p class="text-muted small mb-0 fw-500">Orchestrate world-class educational standards for your institution.</p>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 py-2 fw-800 shadow-sm" onclick="openNodeModal()">
                        <i class="fas fa-plus-circle me-2"></i>CREATE NEW MODULE
                    </button>
                </div>

                <!-- FILTER BAR -->
                <div class="filter-bar mb-5 reveal-fade-up">
                    <div class="filter-item">
                        <i class="fas fa-filter text-primary opacity-50"></i>
                        <select class="filter-select" id="filt_section" onchange="fetchNodes()">
                            <option value="">All <?php echo get_label('Sections'); ?></option>
                            <?php foreach($sections as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['section_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <i class="fas fa-layer-group text-primary opacity-50"></i>
                        <select class="filter-select" id="filt_class" onchange="fetchNodes()">
                            <option value="">All <?php echo get_label('Classes'); ?></option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <i class="fas fa-book text-primary opacity-50"></i>
                        <select class="filter-select" id="filt_subject" onchange="fetchNodes()">
                            <option value="">All <?php echo get_label('Subjects'); ?></option>
                            <?php foreach($subjects as $sub): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-item">
                        <i class="fas fa-calendar-alt text-primary opacity-50"></i>
                        <select class="filter-select" id="filt_term" onchange="fetchNodes()">
                            <option value="">All <?php echo get_label('Terms'); ?></option>
                            <option value="1">First <?php echo get_label('Term'); ?></option>
                            <option value="2">Second <?php echo get_label('Term'); ?></option>
                            <?php if(get_label('Term') === 'Term'): ?>
                            <option value="3">Third <?php echo get_label('Term'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- NODES GRID -->
                <div id="nodesGrid" class="row g-4">
                    <!-- Dynamic Content -->
                </div>
            </div>

            <?php include '../includes/dashboard_footer.php'; ?>
        </main>
    </div>

    <!-- NODE OFFCANVAS -->
    <div class="offcanvas offcanvas-end offcanvas-curriculum" tabindex="-1" id="nodeOffcanvas">
        <div class="offcanvas-header border-0 p-4 pb-0">
            <div>
                <h4 class="fw-900 mb-0" id="offcanvasTitle">Curriculum Module</h4>
                <p class="text-muted extra-small uppercase tracking-2 fw-700">Educational Standard Guide</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-4 pt-2">
            <form id="nodeForm">
                <input type="hidden" id="node_id" name="id">
                
                <div class="row g-3 mb-5">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="form-floating">
                            <select class="form-select border-0 shadow-sm bg-light" name="section_id" id="node_section">
                                <option value="">Standard / Mixed</option>
                                <?php foreach($sections as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label><i class="fas fa-layer-group me-1"></i> <?php echo get_label('Section'); ?></label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="form-floating">
                            <select class="form-select border-0 shadow-sm bg-light" name="class_id" id="node_class">
                                <option value="">Mixed <?php echo get_label('Classes'); ?></option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label><i class="fas fa-users-rectangle me-1"></i> <?php echo get_label('Class'); ?></label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="form-floating">
                            <select class="form-select border-0 shadow-sm bg-light" name="subject_id" id="node_subject">
                                <option value="">Standard <?php echo get_label('Subject'); ?></option>
                                <?php foreach($subjects as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label><i class="fas fa-book-journal-whills me-1"></i> <?php echo get_label('Subject'); ?></label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-floating">
                                    <select class="form-select border-0 shadow-sm bg-light" name="term" id="node_term">
                                        <?php if (get_label('Term') === 'Semester'): ?>
                                        <option value="1">S1</option>
                                        <option value="2">S2</option>
                                        <?php else: ?>
                                        <option value="1">T1</option>
                                        <option value="2">T2</option>
                                        <option value="3">T3</option>
                                        <?php endif; ?>
                                    </select>
                                    <label><?php echo get_label('Term'); ?></label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="number" class="form-control border-0 shadow-sm bg-light" name="week" id="node_week" value="1" placeholder="1">
                                    <label>Week</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <div class="form-floating">
                        <input type="text" class="form-control border-0 shadow-sm bg-light fw-900 border-start border-5 border-primary" name="topic" id="node_topic" required placeholder="Enter the main topic title..." style="font-size: 1.15rem; padding-top: 1.625rem !important;">
                        <label class="opacity-100"><i class="fas fa-heading me-1"></i> TOPIC / UNIT TITLE *</label>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="inner-form-card h-100">
                            <label class="form-label small fw-900 uppercase tracking-2 text-primary mb-3"><i class="fas fa-bullseye me-2"></i>Learning Objectives</label>
                            <textarea class="form-control border-0 shadow-none bg-transparent p-0" name="objectives" id="node_objectives" rows="4" placeholder="Specify global learning outcomes for this module..." style="resize: none;"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="inner-form-card h-100">
                            <label class="form-label small fw-900 uppercase tracking-2 text-primary mb-3"><i class="fas fa-toolbox me-2"></i>Resources & Materials</label>
                            <textarea class="form-control border-0 shadow-none bg-transparent p-0" name="resources" id="node_resources" rows="4" placeholder="List institutional resources, links, and guides..." style="resize: none;"></textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label small fw-900 uppercase tracking-2 text-dark opacity-50 mb-3"><i class="fas fa-feather-pointed me-2"></i>CURRICULUM CONTENT & PROCEDURES</label>
                    <div id="editor-wrapper" class="shadow-sm rounded-4 overflow-hidden border-0">
                        <div id="editor" style="background: white; min-height: 400px; border:none !important;"></div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-end gap-3 mt-5 standout-footer">
                    <button type="button" class="btn btn-link text-muted text-decoration-none fw-700 order-2 order-md-1" data-bs-dismiss="offcanvas">DISCARD CHANGES</button>
                    <button type="button" class="btn btn-commit rounded-pill px-5 fw-900 order-1 order-md-2" id="syncBtn" onclick="saveNode()">
                        SYNC TO INSTITUTION <i class="fas fa-cloud-arrow-up ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRMATION OFFCANVAS -->
    <div class="offcanvas offcanvas-bottom" tabindex="-1" id="deleteOffcanvas" style="height: auto; border-radius: 30px 30px 0 0;">
        <div class="offcanvas-body p-5">
            <div class="text-center">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle mx-auto d-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                    <i class="fas fa-trash-can fa-2x"></i>
                </div>
                <h4 class="fw-900 text-dark mb-2" style="font-size: 1.2rem;">Institutional Erasure</h4>
                <p class="text-muted mb-4 extra-small fw-600">Are you sure you want to remove <br> <span class="fw-800 text-dark" id="deleteTopic"></span> <br> from the curriculum?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-700 btn-sm" data-bs-dismiss="offcanvas" style="font-size: 0.75rem;">CANCEL</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 fw-900 shadow-sm btn-sm" onclick="executeDelete()" style="font-size: 0.75rem; white-space: nowrap;">CONFIRM ERASE</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Organize high-level instructional content...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['link', 'blockquote', 'code-block', 'image'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    ['clean']
                ]
            }
        });

        function fetchNodes() {
            const params = new URLSearchParams({
                action: 'fetch_all',
                section_id: $('#filt_section').val(),
                class_id: $('#filt_class').val(),
                subject_id: $('#filt_subject').val(),
                term: $('#filt_term').val()
            });

            Spinner.show('Synchronizing Curriculum...');
            fetch(`../ajax/manage_curriculum.php?${params.toString()}`)
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    renderNodes(d.nodes);
                } else Notif.show(d.message, 'error');
            });
        }

        function renderNodes(nodes) {
            const grid = $('#nodesGrid');
            grid.empty();

            if (nodes.length === 0) {
                grid.append(`
                    <div class="col-12 text-center py-5">
                        <div class="opacity-25 mb-3"><i class="fas fa-scroll fa-4x text-muted"></i></div>
                        <h5 class="text-muted fw-bold">No modules found for this selection.</h5>
                        <p class="small text-muted">Click "Create New Module" to populate your institutional guide.</p>
                    </div>
                `);
                return;
            }

            nodes.forEach(n => {
                grid.append(`
                    <div class="col-md-6 col-lg-4 reveal-fade-up">
                        <div class="curriculum-card card h-100 position-relative" style="cursor: pointer;" onclick="editNode(${n.id})">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex gap-2">
                                        <span class="term-badge">TERM ${n.term}</span>
                                        <span class="week-badge">WEEK ${n.week}</span>
                                    </div>
                                    <div class="dropdown" onclick="event.stopPropagation()">
                                        <button class="btn btn-link btn-sm text-muted p-0 no-caret" data-bs-toggle="dropdown">
                                            <i class="fas fa-circle-nodes"></i>
                                        </button>
                                        <ul class="dropdown-menu shadow border-0 p-2">
                                            <li><a class="dropdown-item rounded-2" href="#" onclick="editNode(${n.id})"><i class="fas fa-edit me-2 text-primary"></i> Edit Module</a></li>
                                            <li><a class="dropdown-item rounded-2 text-danger" href="#" onclick="confirmDelete(${n.id}, '${n.topic.replace(/'/g, "\\'")}')"><i class="fas fa-trash me-2"></i> Erase Module</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <h5 class="fw-800 text-dark heading-font mb-2 text-truncate-2">${n.topic}</h5>
                                <div class="extra-small text-muted fw-600 uppercase tracking-1 mb-3">
                                    <i class="fas fa-layer-group me-1"></i> ${n.section_name || 'General'} &bull; 
                                    <i class="fas fa-book me-1"></i> ${n.subject_name || 'General'}
                                </div>
                                <div class="small text-muted mb-4 opacity-75 text-truncate-2">
                                    ${n.objectives || 'No objectives defined for this module.'}
                                </div>
                                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                    <span class="card-guide-text">Institutional Guide</span>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-danger btn-action-sm" onclick="event.stopPropagation(); confirmDelete(${n.id}, '${n.topic.replace(/'/g, "\\'")}')">
                                            <i class="fas fa-trash-alt"></i> ERASE
                                        </button>
                                        <button class="btn btn-light btn-action-sm">
                                            EDIT <i class="fas fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            });
        }

        function openNodeModal() {
            $('#nodeForm')[0].reset();
            $('#node_id').val('');
            quill.root.innerHTML = '';
            $('#offcanvasTitle').text('New Curriculum Module');
            $('#syncBtn').html('PUBLISH NEW MODULE <i class="fas fa-paper-plane ms-2"></i>');
            new bootstrap.Offcanvas('#nodeOffcanvas').show();
        }

        function saveNode() {
            const formData = new FormData($('#nodeForm')[0]);
            formData.append('action', 'save');
            formData.append('content', quill.root.innerHTML);

            Spinner.show('Broadcasting Standards...');
            fetch('../ajax/manage_curriculum.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    bootstrap.Offcanvas.getInstance('#nodeOffcanvas').hide();
                    Notif.show('Curriculum module synchronized.', 'success');
                    fetchNodes();
                } else Notif.show(d.message, 'error');
            });
        }

        function editNode(id) {
            Spinner.show('Retrieving Module...');
            fetch(`../ajax/manage_curriculum.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    const n = d.node;
                    $('#node_id').val(n.id);
                    $('#node_section').val(n.section_id);
                    $('#node_class').val(n.class_id);
                    $('#node_subject').val(n.subject_id);
                    $('#node_term').val(n.term);
                    $('#node_week').val(n.week);
                    $('#node_topic').val(n.topic);
                    $('#node_objectives').val(n.objectives);
                    $('#node_resources').val(n.resources);
                    quill.root.innerHTML = n.content || '';
                    $('#offcanvasTitle').text('Refine Module Guidelines');
                    $('#syncBtn').html('SYNCHRONIZE UPDATE <i class="fas fa-save ms-2"></i>');
                    new bootstrap.Offcanvas('#nodeOffcanvas').show();
                } else Notif.show(d.message, 'error');
            });
        }

        let deleteNodeId = null;
        function confirmDelete(id, topic) {
            deleteNodeId = id;
            $('#deleteTopic').text(topic);
            new bootstrap.Offcanvas('#deleteOffcanvas').show();
        }

        function executeDelete() {
            if (!deleteNodeId) return;
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', deleteNodeId);

            bootstrap.Offcanvas.getInstance('#deleteOffcanvas').hide();
            Spinner.show('Removing Module...');
            fetch('../ajax/manage_curriculum.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                Spinner.hide();
                if (d.success) {
                    Notif.show('Module erased correctly.', 'success');
                    fetchNodes();
                } else Notif.show(d.message, 'error');
            });
        }

        $(document).ready(fetchNodes);
    </script>
</body>
</html>
