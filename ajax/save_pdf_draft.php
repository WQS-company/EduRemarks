<?php
// ajax/save_pdf_draft.php
ob_start();
error_reporting(E_ERROR);
ini_set('display_errors', 0);
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    die(json_encode(['success'=>false, 'message'=>'Invalid request method.']));
}

$school_id = $active_school['id'] ?? null;
$draft_id  = (int)($_POST['draft_id'] ?? 0);

if (!$school_id || !$draft_id || empty($_FILES['pdf_file'])) {
    ob_end_clean();
    die(json_encode(['success'=>false, 'message'=>'Missing PDF file or draft ID.']));
}

// Verify draft ownership
$stmt = $pdo->prepare("SELECT id FROM generated_id_cards WHERE id = ? AND school_id = ?");
$stmt->execute([$draft_id, $school_id]);
$draft = $stmt->fetch();
if (!$draft) {
    ob_end_clean();
    die(json_encode(['success'=>false, 'message'=>'Draft not found or unauthorized.']));
}

// Handle upload
$file = $_FILES['pdf_file'];
if ($file['error'] !== UPLOAD_ERR_OK || $file['type'] !== 'application/pdf') {
    ob_end_clean();
    die(json_encode(['success'=>false, 'message'=>'Invalid PDF file upload.']));
}

$upload_dir = dirname(__DIR__) . '/uploads/id_cards/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$filename = 'idcards_' . $school_id . '_' . time() . '_' . mt_rand(100,999) . '.pdf';
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $rel_path = 'uploads/id_cards/' . $filename;
    
    // Update draft to mark as generated 
    $pdo->prepare("UPDATE generated_id_cards SET pdf_path = ?, status = 'generated' WHERE id = ?")->execute([$rel_path, $draft_id]);
    
    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'message'  => 'PDF generated successfully!',
        'pdf_path' => $rel_path
    ]);
} else {
    ob_end_clean();
    echo json_encode(['success'=>false, 'message'=>'Failed to save PDF to server.']);
}
?>
