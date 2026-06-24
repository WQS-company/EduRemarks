<?php
// ajax/delete_id_card_draft.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(['success'=>false,'message'=>'Invalid request.']));

$school_id = $_SESSION['school_id'] ?? null;
$draft_id  = (int)($_POST['id'] ?? 0);

if (!$school_id || !$draft_id) die(json_encode(['success'=>false,'message'=>'Invalid parameters.']));

try {
    // Fetch to confirm ownership + get file path
    $stmt = $pdo->prepare("SELECT pdf_path FROM generated_id_cards WHERE id = ? AND school_id = ?");
    $stmt->execute([$draft_id, $school_id]);
    $draft = $stmt->fetch();
    if (!$draft) die(json_encode(['success'=>false,'message'=>'Draft not found.']));

    // Delete file
    if (!empty($draft['pdf_path'])) {
        $abs = dirname(__DIR__) . '/' . ltrim($draft['pdf_path'],'/');
        if (file_exists($abs)) @unlink($abs);
    }

    // Delete DB record
    $pdo->prepare("DELETE FROM generated_id_cards WHERE id = ? AND school_id = ?")->execute([$draft_id, $school_id]);

    echo json_encode(['success'=>true,'message'=>'Draft deleted successfully.']);
} catch(Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
?>
