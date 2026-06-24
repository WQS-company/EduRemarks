<?php
// ajax/cbt_student_auth.php

require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| FORCE CORRECT SERVER TIMEZONE
|--------------------------------------------------------------------------
*/
date_default_timezone_set('Africa/Lagos');

/*
|--------------------------------------------------------------------------
| JSON RESPONSE HEADER
|--------------------------------------------------------------------------
*/
header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| SANITIZE INPUT
|--------------------------------------------------------------------------
*/
$admission_no = isset($_POST['admission_no']) ? trim($_POST['admission_no']) : '';
$token        = isset($_POST['token']) ? trim($_POST['token']) : '';

if (empty($admission_no) || empty($token)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request',
        'type'    => 'INVALID_REQUEST'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. FIND THE EXAM
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT e.*, c.name as allowed_class_name
    FROM cbt_exams e
    JOIN classes c ON c.id = e.class_id
    WHERE e.token = ?
");
$stmt->execute([$token]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    echo json_encode([
        'success' => false,
        'message' => 'Assessment not found',
        'type'    => 'INVALID_EXAM'
    ]);
    exit;
}

$term = ($exam['assessment_type'] === 'test') ? 'test' : 'examination';
$Term = ucfirst($term);

/*
|--------------------------------------------------------------------------
| 2. EXAM STATUS CHECK
|--------------------------------------------------------------------------
*/
if ($exam['status'] !== 'active') {

    $msg = ($exam['status'] === 'draft')
        ? "This $term is not yet active."
        : "This $term is closed.";

    echo json_encode([
        'success' => false,
        'message' => $msg,
        'type'    => 'INACTIVE'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. FIND STUDENT
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, full_name, student_class
    FROM students
    WHERE admission_no = ?
    AND school_id = ?
");
$stmt->execute([$admission_no, $exam['school_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode([
        'success' => false,
        'message' => "The student with admission number {$admission_no} does not belong to this institution.",
        'type'    => 'INVALID_AUTH'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 4. VERIFY CLASS MATCH (SAFE STRING COMPARISON)
|--------------------------------------------------------------------------
*/
if (trim($student['student_class']) !== trim($exam['allowed_class_name'])) {
    echo json_encode([
        'success' => false,
        'message' => "This $term is not allocated to your class (" . $student['student_class'] . "). Access denied.",
        'type'    => 'CLASS_MISMATCH'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 4b. CHECK FOR EXISTING ATTEMPT
|--------------------------------------------------------------------------
| Prevent multiple attempts for the same exam
|
*/
$stmt = $pdo->prepare("
    SELECT status 
    FROM cbt_student_attempts 
    WHERE exam_id = ? 
    AND student_id = ? 
    AND (status = 'submitted' OR status = 'timed_out')
");
$stmt->execute([$exam['id'], $student['id']]);
$existingAttempt = $stmt->fetch();

if ($existingAttempt) {
    echo json_encode([
        'success' => false,
        'message' => "Records show you have already completed this $term. Access to re-take is restricted.",
        'type'    => 'ALREADY_TAKEN'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 5. STRICT DATETIME VALIDATION (FIXED VERSION)
|--------------------------------------------------------------------------
|
| This replaces your NOW() SQL logic completely.
| It prevents timezone mismatch and wrong-day comparison.
|
*/

try {

    $currentTime = new DateTime(); // Nigeria time
    $startTime   = new DateTime($exam['start_time']);
    $endTime     = new DateTime($exam['end_time']);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid exam schedule configuration.',
        'type'    => 'TIME_CONFIG_ERROR'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK IF NOT STARTED
|--------------------------------------------------------------------------
*/
if ($currentTime < $startTime) {

    echo json_encode([
        'success' => false,
        'message' => "This $term has not started yet. Please check back at " 
                     . $startTime->format('h:i A'),
        'type'    => 'NOT_STARTED'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK IF TIME EXPIRED
|--------------------------------------------------------------------------
*/
// Check if student has an active 'started' attempt (e.g. they were granted a time extension)
$stmt = $pdo->prepare("SELECT status FROM cbt_student_attempts WHERE exam_id=? AND student_id=?");
$stmt->execute([$exam['id'], $student['id']]);
$current_status = $stmt->fetchColumn();

if ($currentTime > $endTime && $current_status !== 'started') {

    echo json_encode([
        'success' => false,
        'message' => "The $term window has already closed. Access denied.",
        'type'    => 'TIME_EXPIRED'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success'      => true,
    'student_id'   => $student['id'],
    'student_name' => $student['full_name']
]);
exit;