<?php
// blog_post.php - Public Knowledge Node
require_once 'includes/config.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) { header("Location: blog.php"); exit(); }

$stmt = $pdo->prepare("SELECT * FROM platform_blog WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$post = $stmt->fetch();

// Fetch gallery images
$stmt_gal = $pdo->prepare("SELECT image_path FROM platform_blog_images WHERE blog_id = ? ORDER BY sort_order ASC");
$stmt_gal->execute([$post['id']]);
$gallery = $stmt_gal->fetchAll();

$pageTitle = $post['title'];
include 'includes/header.php';
?>

<section class="hero-section reveal reveal-up pb-0" style="min-height: 40vh; padding-top: 150px; background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);">
    <div class="container text-center">
        <div class="mb-4 d-inline-block">
            <span class="badge bg-premium-gold bg-opacity-10 text-premium-gold rounded-pill px-4 py-2 fw-bold small uppercase tracking-2">Institutional Insight</span>
        </div>
        <h1 class="hero-title mb-4" style="font-size: clamp(2rem, 5vw, 4rem);"><span><?php echo htmlspecialchars($post['title']); ?></span></h1>
        <div class="d-flex align-items-center justify-content-center gap-3 mb-5">
            <div class="bg-blue text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:40px; height:40px;">SA</div>
            <div class="text-start">
                <div class="fw-bold text-blue small">By <?php echo htmlspecialchars($post['author'] ?: 'Super Admin'); ?></div>
                <div class="extra-small text-muted"><?php echo date('F d, Y', strtotime($post['created_at'])); ?></div>
            </div>
        </div>
    </div>
</section>

<section class="pb-5 reveal reveal-up">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Main Media (Image or Video) -->
                <?php if(!empty($post['video_url'])): ?>
                    <div class="glass-card p-2 border-0 shadow-premium mb-5 overflow-hidden" style="border-radius: 40px;">
                        <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow-sm">
                            <?php 
                            $vurl = $post['video_url'];
                            if(strpos($vurl, 'youtube.com') !== false || strpos($vurl, 'youtu.be') !== false) {
                                $vid = strpos($vurl, 'v=') !== false ? substr($vurl, strpos($vurl, 'v=')+2) : substr($vurl, strrpos($vurl, '/')+1);
                                if(strpos($vid, '&') !== false) $vid = substr($vid, 0, strpos($vid, '&'));
                                echo '<iframe src="https://www.youtube.com/embed/'.$vid.'" allowfullscreen></iframe>';
                            } elseif(strpos($vurl, 'vimeo.com') !== false) {
                                $vid = substr($vurl, strrpos($vurl, '/')+1);
                                echo '<iframe src="https://player.vimeo.com/video/'.$vid.'" allowfullscreen></iframe>';
                            } else {
                                echo '<video src="'.$vurl.'" controls class="w-100"></video>';
                            }
                            ?>
                        </div>
                    </div>
                <?php elseif(!empty($post['image_path'])): ?>
                    <div class="mb-5 text-center">
                        <img src="<?php echo $post['image_path']; ?>" class="img-fluid shadow-premium" style="border-radius: 40px; max-height: 600px; width: 100%; object-fit: cover; object-position: top;">
                    </div>
                <?php endif; ?>

                <article class="glass-card p-4 p-md-5 border-0 shadow-sm reveal reveal-scale" style="border-radius:30px; background:#fff;">
                    <div class="blog-content mb-5 opacity-85 leading-relaxed" style="font-size: 1.15rem; color: #334155;">
                        <?php echo nl2br($post['content']); ?>
                    </div>
                    
                    <!-- Gallery Section -->
                    <?php if(!empty($gallery)): ?>
                    <div class="mt-5 pt-5 border-top">
                        <h5 class="fw-900 text-blue mb-4 uppercase tracking-2">Gallery Nodes</h5>
                        <div class="row g-3">
                            <?php foreach($gallery as $img): ?>
                            <div class="col-6 col-md-4">
                                <div class="gallery-item overflow-hidden rounded-4 shadow-sm" style="height: 200px;">
                                    <img src="<?php echo $img['image_path']; ?>" class="w-100 h-100 object-fit-cover shadow-hover cursor-pointer" onclick="window.open(this.src)" style="object-position: top; transition:0.4s;">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-5 pt-5 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="fw-bold text-muted small">Synchronize this insight to your circle:</div>
                        <div class="social-links-minimal d-flex gap-4">
                            <a href="#" class="text-blue opacity-50 h4 mb-0 hover-scale"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="text-blue opacity-50 h4 mb-0 hover-scale"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="text-blue opacity-50 h4 mb-0 hover-scale"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="text-blue opacity-50 h4 mb-0 hover-scale"><i class="fas fa-link"></i></a>
                        </div>
                    </div>
                </article>
                
                <div class="text-center mt-5">
                    <a href="blog.php" class="btn btn-premium-gold px-5 py-3 rounded-pill fw-900 shadow hover-up"><i class="fas fa-th-large me-2"></i> EXPLORE ALL KNOWLEDGE NODES</a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .shadow-premium { box-shadow: 0 40px 80px -15px rgba(15, 23, 42, 0.15) !important; }
    .gallery-item:hover img { transform: scale(1.05); }
    .leading-relaxed { line-height: 1.8; }
    .shadow-hover:hover { filter: brightness(0.9); }
    @media (max-width: 576px) {
        .glass-card { padding: 25px !important; }
        .hero-title { font-size: 1.75rem !important; }
        .gallery-item { height: 150px !important; }
    }
</style>

<?php include 'includes/footer.php'; ?>
