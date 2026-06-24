<?php
// ajax/sa_save_blog.php - Super Admin Knowledge Node Orchestrator
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized Access Attempt']));
}

$id        = $_POST['id'] ?? null;
$title     = $_POST['title'] ?? '';
$content   = $_POST['content'] ?? '';
$status    = $_POST['status'] ?? 'draft';
$video_url = $_POST['video_url'] ?? '';
$delete    = isset($_POST['delete']);

if (!$delete && (!$title || !$content)) {
    die(json_encode(['success' => false, 'message' => 'Knowledge node payload is incomplete.']));
}

$image_path = $_POST['existing_image'] ?? '';

// Handle Primary Image (Cover)
if (!$delete && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/blog/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if (in_array($file_ext, $allowed_exts)) {
        $new_filename = 'blog_cover_' . time() . '_' . uniqid() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
            $image_path = 'uploads/blog/' . $new_filename;
        }
    }
}

// Generate Slug
$slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

try {
    $pdo->beginTransaction();

    if ($delete) {
        $stmt = $pdo->prepare("DELETE FROM platform_blog WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Knowledge node decommissioned.']);
        exit;
    } else if ($id) {
        $stmt = $pdo->prepare("UPDATE platform_blog SET title=?, slug=?, content=?, image_path=?, video_url=?, author=?, status=? WHERE id=?");
        $author = 'Super Admin';
        $stmt->execute([$title, $slug, $content, $image_path, $video_url, $author, $status, $id]);
        $blog_id = $id;
        $message = 'Knowledge node configuration updated.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO platform_blog (title, slug, content, image_path, video_url, author, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $author = 'Super Admin';
        $stmt->execute([$title, $slug, $content, $image_path, $video_url, $author, $status]);
        $blog_id = $pdo->lastInsertId();
        $message = 'New knowledge node instantiated on the platform.';
    }

    // Handle Multiple Additional Images
    if (isset($_FILES['gallery_images'])) {
        $upload_dir = '../uploads/blog/gallery/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION));
                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $new_filename = 'blog_gal_' . time() . '_' . uniqid() . '.' . $file_ext;
                    if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                        $gal_path = 'uploads/blog/gallery/' . $new_filename;
                        $pdo->prepare("INSERT INTO platform_blog_images (blog_id, image_path) VALUES (?, ?)")->execute([$blog_id, $gal_path]);
                    }
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Knowledge Orchestration Error: ' . $e->getMessage()]);
}
