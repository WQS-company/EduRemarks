<?php
// ajax/save_cbt_question.php
require_once '../includes/auth_check.php';

if ($role !== 'staff') die(json_encode(['success'=>false, 'message'=>'Unauthorized']));

$school_id = $_SESSION['school_id'] ?? null;
$id        = $_POST['id'] ?? null;
$exam_id   = $_POST['exam_id'] ?? null;
$type      = $_POST['type'] ?? 'objective';
$text      = $_POST['question_text'] ?? '';
$marks     = $_POST['marks'] ?: null;

if (!$exam_id || !$text) die(json_encode(['success'=>false, 'message'=>'Missing fields']));

// Verify exam ownership
$stmt = $pdo->prepare("SELECT id FROM cbt_exams WHERE id=? AND school_id=?");
$stmt->execute([$exam_id, $school_id]);
if (!$stmt->fetch()) die(json_encode(['success'=>false, 'message'=>'Exam not found or access denied']));

$correct_answer = null;
$options = null;

if ($type === 'objective') {
    $options = json_encode([
        'A' => $_POST['opt_A'] ?? '',
        'B' => $_POST['opt_B'] ?? '',
        'C' => $_POST['opt_C'] ?? '',
        'D' => $_POST['opt_D'] ?? ''
    ]);
    $correct_answer = $_POST['correct_answer'] ?? 'A';
} elseif ($type === 'tf') {
    $correct_answer = $_POST['correct_tf'] ?? 'True';
}

// Handle Image Upload
$image_path = null;
if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === 0) {
    $dir = "../uploads/cbt_questions/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $ext = pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION);
    $filename = "q_" . time() . "_" . uniqid() . "." . $ext;
    if (move_uploaded_file($_FILES['question_image']['tmp_name'], $dir . $filename)) {
        $image_path = "uploads/cbt_questions/" . $filename;
    }
}

try {
    if ($id) {
        $sql = "UPDATE cbt_questions SET question_text=?, type=?, options=?, correct_answer=?, marks=?";
        $params = [$text, $type, $options, $correct_answer, $marks];
        if ($image_path) {
            $sql .= ", image_path=?";
            $params[] = $image_path;
        }
        $sql .= " WHERE id=? AND exam_id=?";
        $params[] = $id;
        $params[] = $exam_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO cbt_questions (exam_id, question_text, image_path, type, options, correct_answer, marks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$exam_id, $text, $image_path, $type, $options, $correct_answer, $marks]);
    }
    echo json_encode(['success'=>true, 'message'=>'Question saved']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
