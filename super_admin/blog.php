<?php
// super_admin/blog.php - Platform Media Center
// Fixed include path and standardized layout
require_once 'auth_check.php';

// Fetch all posts with defensive check
try {
    $posts = $pdo->query("SELECT * FROM platform_blog ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $posts = [];
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management | School Result Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --sa-blue: #1e40af; --sa-bg: #f3f4f9; }
        body { background: var(--sa-bg); font-family: 'Inter', sans-serif; }
        .sa-main-content { margin-left: 200px; padding: 30px; }
        
        .nav-link-cp { 
            color: rgba(255,255,255,0.7) !important; 
            padding: 14px 25px !important; 
            display: flex !important; 
            align-items: center !important;
            text-decoration: none !important; 
            border-radius: 12px; 
            margin: 8px 15px !important; 
            font-weight: 500; 
            transition: 0.3s; 
            font-size: 0.95rem;
        }
        
        /* Premium Responsive Cards */
        .blog-card { border-radius: 20px; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,0.05); transition: 0.4s; height: 100%; display: flex; flex-direction: column; }
        .blog-card:hover { transform: translateY(-8px); box-shadow: 0 25px 50px rgba(0,0,0,0.1); }
        .blog-media { height: 220px; overflow: hidden; position: relative; background: #f1f5f9; }
        .blog-media img { width: 100%; height: 100%; object-fit: cover; object-position: top; }
        .blog-media .video-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; opacity: 0; transition: 0.3s; }
        .blog-card:hover .video-overlay { opacity: 1; }
        
        .gallery-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .gal-item { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; }
        
        @media (max-width: 991px) {
            .sa-main-content { margin-left: 0; padding: 20px; }
        }
        
        .text-blue { color: #1e3a8a; }
    </style>
</head>
<body>

<?php include '../includes/sa_header.php'; ?>
<?php include '../includes/sa_sidebar.php'; ?>

<main class="sa-main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-5 gap-3">
        <div>
            <h3 class="fw-800 mb-1 text-blue">Institutional Media Nodes</h3>
            <p class="text-muted small">Broadcast world-class insights and multimedia updates.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" onclick="openNewBlogModal()">
            <i class="fas fa-plus-circle me-2"></i>CREATE MASTER NODE
        </button>
    </div>

    <div class="row g-4">
        <?php foreach($posts as $p): ?>
        <div class="col-xl-4 col-md-6 col-sm-12">
            <div class="blog-card">
                <div class="blog-media">
                    <span class="badge badge-status rounded-pill bg-<?php echo ($p['status']=='published')?'success':'warning'; ?> shadow-sm" style="position: absolute; top: 12px; left: 12px; z-index: 5;">
                        <?php echo strtoupper($p['status']); ?>
                    </span>
                    <?php if (!empty($p['video_url'])): ?>
                        <div class="video-overlay"><i class="fas fa-play-circle"></i></div>
                    <?php endif; ?>
                    <?php if (!empty($p['image_path'])): ?>
                        <img src="../<?php echo $p['image_path']; ?>" alt="Cover">
                    <?php else: ?>
                        <div class="h-100 d-flex align-items-center justify-content-center text-muted"><i class="fas fa-image fa-2x opacity-25"></i></div>
                    <?php endif; ?>
                    
                    <div class="dropdown position-absolute top-0 end-0 p-2" style="z-index: 10;">
                        <button class="btn btn-sm btn-white rounded-circle shadow border-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-h text-muted"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 animation-slide">
                            <li><a class="dropdown-item py-2 small" href="#" onclick="editPost(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-pencil-alt me-2 text-primary"></i> Edit Post</a></li>
                            <li><hr class="dropdown-divider opacity-50"></li>
                            <li><a class="dropdown-item py-2 small text-danger" href="#" onclick="deletePost(<?php echo $p['id']; ?>)"><i class="fas fa-trash-alt me-2"></i> Purge Node</a></li>
                        </ul>
                    </div>
                </div>
                <div class="p-4 flex-grow-1">
                    <div class="d-flex align-items-center mb-2 tiny-text fw-bold text-muted">
                        <i class="far fa-calendar-alt me-1"></i> <?php echo date('M d', strtotime($p['created_at'])); ?> &bull; <i class="far fa-user ms-2 me-1"></i> Admin
                    </div>
                    <h5 class="fw-800 text-dark mb-2 text-truncate"><?php echo htmlspecialchars($p['title']); ?></h5>
                    <p class="text-muted extra-small line-clamp-3 mb-0"><?php echo strip_tags($p['content']); ?></p>
                </div>
                <div class="p-4 pt-0 border-top-0 mt-auto d-flex justify-content-between">
                    <button class="btn btn-link text-blue p-0 tiny-text fw-bold text-decoration-none" onclick="editPost(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)">MANAGE MEDIA <i class="fas fa-arrow-right ms-1"></i></button>
                    <?php if(!empty($p['video_url'])): ?>
                        <span class="badge bg-danger rounded-pill extra-small"><i class="fab fa-youtube"></i> VIDEO</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Blog Modal -->
<div class="modal fade" id="blogModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content border-0 shadow-lg rounded-5" id="blogForm">
            <div class="modal-header border-0 p-4 bg-light">
                <h5 class="fw-800 mb-0">Broadcast Mastery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-lg-5">
                <input type="hidden" name="id" id="postId">
                <input type="hidden" name="existing_image" id="existingImage">
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="small fw-bold mb-2">MASTER HEADLINE</label>
                            <input type="text" name="title" id="postTitle" class="form-control rounded-4 shadow-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold mb-2">INSIGHT CONTENT</label>
                            <textarea name="content" id="postContent" class="form-control rounded-4 shadow-sm" rows="10" required></textarea>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3 text-center">
                            <label class="small fw-bold mb-2 d-block">COVER VISUAL</label>
                            <div class="upload-area mb-2" id="coverUploadArea" onclick="document.getElementById('imageInput').click()">
                                <img id="imagePreview" class="img-fluid rounded-4 d-none">
                                <div id="previewIconSection">
                                    <i class="fas fa-camera fa-2x text-muted opacity-50"></i>
                                    <p class="extra-small text-muted mb-0">Upload Main Image</p>
                                </div>
                            </div>
                            <input type="file" name="image" id="imageInput" class="d-none" accept="image/*">
                        </div>
                        
                        <div class="mb-3">
                            <label class="small fw-bold mb-2">GALLERY NODES (ONE OR MORE)</label>
                            <input type="file" name="gallery_images[]" id="galleryInput" class="form-control rounded-pill extra-small" multiple accept="image/*">
                            <div id="galleryPreview" class="gallery-preview"></div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold mb-2">VIDEO NODE URL</label>
                            <input type="text" name="video_url" id="postVideoUrl" class="form-control rounded-pill extra-small" placeholder="YouTube URL">
                        </div>

                        <div class="mb-0">
                            <label class="small fw-bold mb-2 text-dark opacity-75">DISTRIBUTION STATUS</label>
                            <select name="status" id="postStatus" class="form-select rounded-4 py-2 small fw-bold shadow-sm">
                                <option value="draft">DRAFT (INTERNAL)</option>
                                <option value="published">PUBLISHED (GLOBAL)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="submit" id="submitBtn" class="btn btn-primary w-100 rounded-pill py-3 fw-800">EXECUTE BROADCAST</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/spinner.php'; ?>
<?php include '../includes/notifications.php'; ?>
<?php include '../includes/success_overlay.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const EDUREMARKS_CSRF_TOKEN = '<?php echo Security::csrf_token(); ?>';
    const blogModal = new bootstrap.Modal(document.getElementById('blogModal'));

    function openNewBlogModal() {
        document.getElementById('blogForm').reset();
        document.getElementById('postId').value = '';
        document.getElementById('existingImage').value = '';
        document.getElementById('imagePreview').classList.add('d-none');
        document.getElementById('previewIconSection').classList.remove('d-none');
        document.getElementById('galleryPreview').innerHTML = '';
        document.getElementById('submitBtn').innerText = 'EXECUTE BROADCAST';
        blogModal.show();
    }

    document.getElementById('imageInput').addEventListener('change', function(e) {
        if(this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ex) {
                document.getElementById('imagePreview').src = ex.target.result;
                document.getElementById('imagePreview').classList.remove('d-none');
                document.getElementById('previewIconSection').classList.add('d-none');
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    document.getElementById('galleryInput').addEventListener('change', function(e) {
        const preview = document.getElementById('galleryPreview');
        preview.innerHTML = '';
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(ex) {
                const img = document.createElement('img');
                img.src = ex.target.result;
                img.className = 'gal-item';
                preview.appendChild(img);
            }
            reader.readAsDataURL(file);
        });
    });

    $('#blogForm').on('submit', function(e) {
        e.preventDefault();
        Spinner.show('Orchestrating Broadcast...');
        const formData = new FormData(this);
        formData.append('csrf_token', EDUREMARKS_CSRF_TOKEN);

        $.ajax({
            url: '../ajax/sa_save_blog.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                Spinner.hide();
                if(res.success) {
                    blogModal.hide();
                    showSuccess('Broadcast Live', res.message, { reload: true });
                } else {
                    Notif.show(res.message, 'error');
                }
            }
        });
    });

    function editPost(p) {
        document.getElementById('postId').value = p.id;
        document.getElementById('postTitle').value = p.title;
        document.getElementById('postContent').value = p.content;
        document.getElementById('postStatus').value = p.status;
        document.getElementById('postVideoUrl').value = p.video_url || '';
        document.getElementById('existingImage').value = p.image_path || '';
        
        if(p.image_path) {
            document.getElementById('imagePreview').src = '../' + p.image_path;
            document.getElementById('imagePreview').classList.remove('d-none');
            document.getElementById('previewIconSection').classList.add('d-none');
        } else {
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('previewIconSection').classList.remove('d-none');
        }

        document.getElementById('galleryPreview').innerHTML = '<p class="tiny-text text-muted w-100">Add new images below to append to gallery.</p>';
        document.getElementById('submitBtn').innerText = 'UPDATE BROADCAST';
        blogModal.show();
    }

    function deletePost(id) {
        if(confirm('TERMINAL ACTION: Permanently purge this knowledge node?')) {
            Spinner.show('Purging...');
            $.post('../ajax/sa_save_blog.php', { id: id, delete: true, csrf_token: EDUREMARKS_CSRF_TOKEN }, function(res) {
                Spinner.hide();
                if(res.success) showSuccess('Node Purged', res.message, { reload: true });
                else Notif.show(res.message, 'error');
            }, 'json');
        }
    }
</script>
</body>
</html>
