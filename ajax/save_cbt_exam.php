<?php
// ajax/save_cbt_exam.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($role !== 'staff') die(json_encode(['success'=>false, 'message'=>'Unauthorized']));

$school_id = $_SESSION['school_id'] ?? null;
$id        = $_POST['id'] ?? null;
$title     = trim($_POST['title'] ?? '');
$class_id  = $_POST['class_id'] ?? null;
$subject_id= $_POST['subject_id'] ?? null;
$duration  = intval($_POST['duration_mins'] ?? 60);
$total_q   = intval($_POST['total_questions'] ?? 10);
$start     = $_POST['start_time'] ?? '';
$end       = $_POST['end_time'] ?? '';
$token     = $_POST['token'] ?? '';
$order     = $_POST['order_type'] ?? 'random';
$marks     = floatval($_POST['marks_per_question'] ?? 1.0);
$instr     = trim($_POST['instructions'] ?? '');
$status    = $_POST['status'] ?? 'draft';
$type      = $_POST['assessment_type'] ?? 'test';
$cat       = ($type === 'test') ? ($_POST['test_category'] ?? '1st_ca') : null;

// ─── Classification Checks ───
if (!in_array($type, ['test', 'exam'])) {
    die(json_encode(['success'=>false, 'message'=>'Invalid assessment type.']));
}
if ($type === 'test' && !in_array($cat, ['1st_ca', '2nd_ca'])) {
    die(json_encode(['success'=>false, 'message'=>'Invalid test category.']));
}

// ─── Title Auto-Generation for Tests ───
if ($type === 'test' && empty($title)) {
    // We'll fetch subject and class names to build a clear, descriptive title
    $subQuery = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
    $subQuery->execute([$subject_id]);
    $subName = $subQuery->fetchColumn();

    $classQuery = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
    $classQuery->execute([$class_id]);
    $className = $classQuery->fetchColumn();

    $catLabel = ($cat === '1st_ca') ? '1st C.A' : '2nd C.A';
    $title = "$className $subName ($catLabel)";
}

// ─── Required Fields ───
if (!$title || !$class_id || !$subject_id || !$start || !$end || !$school_id) {
    die(json_encode(['success'=>false, 'message'=>'Please fill in all required fields (type, class, subject, start/end times).']));
}

// ─── Title Length for Exams ───
if ($type === 'exam' && strlen($title) < 5) {
    die(json_encode(['success'=>false, 'message'=>'Exam title must be at least 5 characters long.']));
}

// ─── Duration Validation ───
if ($duration < 5 || $duration > 480) {
    die(json_encode(['success'=>false, 'message'=>'Duration must be between 5 and 480 minutes (8 hours max).']));
}

// ─── Question Count ───
if ($total_q < 1 || $total_q > 500) {
    die(json_encode(['success'=>false, 'message'=>'Question count must be between 1 and 500.']));
}

// ─── Marks ───
if ($marks <= 0 || $marks > 100) {
    die(json_encode(['success'=>false, 'message'=>'Marks per question must be between 0.5 and 100.']));
}

// ─── Time Validation ───
$start_ts = strtotime($start);
$end_ts   = strtotime($end);

if (!$start_ts || !$end_ts) {
    die(json_encode(['success'=>false, 'message'=>'Invalid date/time format. Please use the date picker.']));
}

if ($end_ts <= $start_ts) {
    die(json_encode(['success'=>false, 'message'=>'End time must be after the start time.']));
}

$window_minutes = ($end_ts - $start_ts) / 60;
if ($window_minutes < $duration) {
    die(json_encode(['success'=>false, 'message'=>"The exam window ({$window_minutes} min) is shorter than the exam duration ({$duration} min). Students won't have enough time. Please extend the end time."]));
}

if ($window_minutes > 1440) {
    die(json_encode(['success'=>false, 'message'=>'Exam access window cannot exceed 24 hours. Please narrow the start-end time range.']));
}

// For NEW exams, start time should not be in the past
if (!$id) {
    $now = time();
    if ($start_ts < ($now - 300)) { // 5 min grace period
        die(json_encode(['success'=>false, 'message'=>'Start time cannot be in the past. Please select a future start time.']));
    }
}

// ─── Status Validation ───
if (!in_array($status, ['draft', 'active', 'closed'])) {
    die(json_encode(['success'=>false, 'message'=>'Invalid exam status.']));
}

// ─── Order Validation ───
if (!in_array($order, ['asc', 'desc', 'random'])) {
    die(json_encode(['success'=>false, 'message'=>'Invalid question ordering.']));
}

// ─── Classification Validation ───
if (!in_array($type, ['test', 'exam'])) {
    die(json_encode(['success'=>false, 'message'=>'Invalid assessment type.']));
}
if ($type === 'test' && !in_array($cat, ['1st_ca', '2nd_ca'])) {
    die(json_encode(['success'=>false, 'message'=>'Invalid test category.']));
}

// ─── Fetch staff detail id ───
$stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id=? AND school_id=? AND status='active'");
$stmt->execute([$user_id, $school_id]);
$staff = $stmt->fetch();
if (!$staff) die(json_encode(['success'=>false, 'message'=>'Staff record not found. You may not have active access to this school.']));
$staff_detail_id = $staff['id'];

// ─── Verify class & subject belong to this school ───
$stmt = $pdo->prepare("SELECT id FROM classes WHERE id=? AND school_id=?");
$stmt->execute([$class_id, $school_id]);
if (!$stmt->fetch()) die(json_encode(['success'=>false, 'message'=>'Selected class does not belong to this school.']));

$stmt = $pdo->prepare("SELECT id FROM subjects WHERE id=? AND school_id=?");
$stmt->execute([$subject_id, $school_id]);
if (!$stmt->fetch()) die(json_encode(['success'=>false, 'message'=>'Selected subject does not belong to this school.']));

try {
    if ($id) {
        // Verify ownership before editing
        $stmt = $pdo->prepare("SELECT id FROM cbt_exams WHERE id=? AND staff_id=? AND school_id=?");
        $stmt->execute([$id, $staff_detail_id, $school_id]);
        if (!$stmt->fetch()) die(json_encode(['success'=>false, 'message'=>'Exam not found or you do not have permission to edit it.']));

        $stmt = $pdo->prepare("
            UPDATE cbt_exams SET 
                title=?, class_id=?, subject_id=?, duration_mins=?, total_questions=?, start_time=?, end_time=?, 
                order_type=?, marks_per_question=?, instructions=?, status=?, assessment_type=?, test_category=?
            WHERE id=? AND staff_id=? AND school_id=?
        ");
        $stmt->execute([$title, $class_id, $subject_id, $duration, $total_q, $start, $end, $order, $marks, $instr, $status, $type, $cat, $id, $staff_detail_id, $school_id]);
        echo json_encode(['success'=>true, 'message'=>'Exam configuration updated successfully.']);
    } else {
        // Generate token if empty
        if (!$token) $token = bin2hex(random_bytes(16));

        // === CREDIT SYSTEM INTEGRATION ===
        $credit_cost = 500;
        $activity_log = "Created new CBT Assessment: " . $title . " (" . ucfirst($type) . ")";
        if (!deductCredits($pdo, $school_id, $credit_cost, $activity_log)) {
            die(json_encode(['success'=>false, 'message'=>"INSUFFICIENT CREDITS: Your institution requires 500 credits to create a new assessment. Current balance is low."]));
        }

        $stmt = $pdo->prepare("
            INSERT INTO cbt_exams (school_id, staff_id, class_id, subject_id, title, instructions, duration_mins, total_questions, start_time, end_time, order_type, marks_per_question, token, status, assessment_type, test_category)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$school_id, $staff_detail_id, $class_id, $subject_id, $title, $instr, $duration, $total_q, $start, $end, $order, $marks, $token, $status, $type, $cat]);
        echo json_encode(['success'=>true, 'message'=>'Exam created successfully. 500 credits deducted.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'message'=>'Database error: ' . $e->getMessage()]);
}
