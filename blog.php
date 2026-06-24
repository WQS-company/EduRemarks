<?php
// blog.php - Public Media Hub
require_once 'includes/config.php';
$pageTitle = "Platform Insights & Knowledge Hub";
include 'includes/header.php';

// Fetch all published posts
$stmt = $pdo->query("SELECT * FROM platform_blog WHERE status='published' ORDER BY created_at DESC");
$posts = $stmt->fetchAll();
?>

<section class="hero-section reveal reveal-up" style="min-height: 40vh; padding-top: 150px;">
    <div class="container text-center">
        <h1 class="hero-title mb-4">Educational <span>Knowledge Node</span></h1>
        <p class="hero-subtitle mb-0">Insights, updates, and transformation strategies for modern institutions.</p>
    </div>
</section>

<section class="pb-5 reveal reveal-up">
    <div class="container">
        <div class="row g-4">
            <?php if(empty($posts)): ?>
            <div class="col-12 text-center py-5">
                <div class="glass-card p-5 border-dashed">
                    <i class="fas fa-newspaper h1 mb-3 opacity-25 text-blue"></i>
                    <h4 class="text-muted fw-bold">The Knowledge Hub is currently synchronizing.</h4>
                    <p class="text-muted small">Please return shortly for institutional insights.</p>
                </div>
            </div>
            <?php else: foreach($posts as $p): ?>
            <div class="col-lg-4 col-md-6 col-sm-12">
                <article class="feature-card h-100 reveal reveal-scale d-flex flex-column p-0 overflow-hidden border-0 shadow-sm" style="background:#fff; border-radius:30px; transition:0.4s;">
                    <?php if(!empty($p['image_path'])): ?>
                    <div class="card-media" style="height:250px; position:relative; overflow:hidden;">
                        <img src="<?php echo $p['image_path']; ?>" style="width:100%; height:100%; object-fit:cover; object-position:top; transition:0.6s;">
                        <?php if(!empty($p['video_url'])): ?>
                            <div class="video-badge" style="position:absolute; inset:0; background:rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:center; color:#fff; font-size:2rem;">
                                <i class="fas fa-play-circle shadow"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-4 flex-grow-1">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <span class="badge bg-premium-gold bg-opacity-10 text-premium-gold rounded-pill px-3 py-2 fw-bold extra-small uppercase">Insight</span>
                            <div class="tiny-text fw-bold text-muted"><i class="far fa-clock me-1"></i> <?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                        </div>
                        <h4 class="fw-900 text-blue mb-3 line-clamp-2"><?php echo htmlspecialchars($p['title']); ?></h4>
                        <p class="text-muted small mb-0 line-clamp-3">
                            <?php echo strip_tags($p['content']); ?>
                        </p>
                    </div>
                    
                    <div class="px-4 pb-4 mt-auto">
                        <a href="blog_post.php?slug=<?php echo $p['slug']; ?>" class="btn btn-premium-gold w-100 rounded-pill py-2 fw-bold shadow-sm">READ FULL NODE <i class="fas fa-arrow-right ms-2 extra-small"></i></a>
                    </div>
                </article>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
