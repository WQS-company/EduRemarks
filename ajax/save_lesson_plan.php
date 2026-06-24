<?php
// ajax/save_lesson_plan.php
require_once '../includes/auth_check.php';

if ($role !== 'staff' && $role !== 'owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = $_SESSION['school_id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !$school_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$id                      = $input['id'] ?? null;
$class_id                = $input['class_id'] ?? null;
$subject_id              = $input['subject_id'] ?? null;
$topic                   = $input['topic'] ?? '';
$sub_topic               = $input['sub_topic'] ?? '';
$date_planned            = $input['date_planned'] ?? '';
$duration                = $input['duration'] ?? '';
$learning_objectives     = $input['learning_objectives'] ?? '';
$instructional_materials = $input['instructional_materials'] ?? '';
$introduction            = $input['introduction'] ?? '';
$presentation_steps      = $input['presentation_steps'] ?? '';
$evaluation_questions    = $input['evaluation_questions'] ?? '';
$conclusion              = $input['conclusion'] ?? '';
$lesson_note             = $input['lesson_note'] ?? '';
$status                  = $input['status'] ?? 'draft';

if (!$class_id || !$subject_id || !$topic || !$date_planned) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Get staff detail id
    $stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
    $stmt->execute([$user_id, $school_id]);
    $staff = $stmt->fetch();
    
    if (!$staff) {
        // Owners might not have a staff_details entry, but the system expects it for lesson plans.
        // For simplicity, let's say owners can't create lesson plans or we need a staff record.
        echo json_encode(['success' => false, 'message' => 'Active staff record not found for this account.']);
        exit();
    }
    $staff_detail_id = $staff['id'];

    if ($id) {
        // Update
        $stmt = $pdo->prepare("
            UPDATE lesson_plans SET 
                class_id=?, subject_id=?, topic=?, sub_topic=?, date_planned=?, duration=?, 
                learning_objectives=?, instructional_materials=?, introduction=?, presentation_steps=?, 
                evaluation_questions=?, conclusion=?, lesson_note=?, status=?
            WHERE id=? AND staff_detail_id=? AND school_id=?
        ");
        $stmt->execute([
            $class_id, $subject_id, $topic, $sub_topic, $date_planned, $duration,
            $learning_objectives, $instructional_materials, $introduction, $presentation_steps,
            $evaluation_questions, $conclusion, $lesson_note, $status,
            $id, $staff_detail_id, $school_id
        ]);
        echo json_encode(['success' => true, 'message' => 'Lesson plan updated successfully']);
    } else {
        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO lesson_plans (
                school_id, staff_detail_id, class_id, subject_id, topic, sub_topic, date_planned, duration,
                learning_objectives, instructional_materials, introduction, presentation_steps,
                evaluation_questions, conclusion, lesson_note, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $school_id, $staff_detail_id, $class_id, $subject_id, $topic, $sub_topic, $date_planned, $duration,
            $learning_objectives, $instructional_materials, $introduction, $presentation_steps,
            $evaluation_questions, $conclusion, $lesson_note, $status
        ]);
        echo json_encode(['success' => true, 'message' => 'Lesson plan saved successfully']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
