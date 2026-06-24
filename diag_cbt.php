<?php
require_once 'config/db.php';

echo "--- 1. EXAM CHECK ---\n";
$stmt = $pdo->query("SELECT * FROM cbt_exams");
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($exams as $e) {
    echo "ID: {$e['id']} | Title: {$e['title']} | School ID: {$e['school_id']} | Staff ID: {$e['staff_id']} | Token: {$e['token']} | Status: {$e['status']}\n";
}

echo "\n--- 2. QUESTION COUNT PER EXAM ---\n";
$stmt = $pdo->query("SELECT exam_id, COUNT(*) as cnt FROM cbt_questions GROUP BY exam_id");
$counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($counts as $c) {
    echo "Exam ID: {$c['exam_id']} | Questions: {$c['cnt']}\n";
}

echo "\n--- 3. ORPHAN QUESTIONS (No Exam ID) ---\n";
$stmt = $pdo->query("SELECT * FROM cbt_questions WHERE exam_id NOT IN (SELECT id FROM cbt_exams)");
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Orphan Count: " . count($orphans) . "\n";

echo "\n--- 4. STUDENT VERIFICATION (for token=6cfc966c485df725d76f55ce99fcaab7, uid=1) ---\n";
$token = '6cfc966c485df725d76f55ce99fcaab7';
$uid = 1;

$stmt = $pdo->prepare("SELECT * FROM cbt_exams WHERE token = ?");
$stmt->execute([$token]);
$exam = $stmt->fetch();

if ($exam) {
    echo "Exam ID: {$exam['id']} Found.\n";
    $stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.student_class, c.name as class_name
        FROM students s
        JOIN classes c ON c.id = ?
        WHERE s.id = ?
    ");
    $stmt->execute([$exam['class_id'], $uid]);
    $student = $stmt->fetch();
    echo "Student: " . ($student ? $student['full_name'] . " (Class: " . $student['student_class'] . ", Target Class: " . $student['class_name'] . ")" : "Not Found") . "\n";
    
    if ($student && $student['student_class'] !== $student['class_name']) {
        echo "WARNING: Class Mismatch!\n";
    }
} else {
    echo "Exam Token Incorrect\n";
}
echo "\n--- 5. ROLE & STAFF VERIFICATION ---\n";
$stmt = $pdo->query("SELECT id, full_name, role FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "User ID: {$u['id']} | Name: {$u['full_name']} | Role: {$u['role']}\n";
}

echo "\nSTAFF DETAILS:\n";
$stmt = $pdo->query("SELECT sd.id, sd.user_id, sd.school_id, u.full_name FROM staff_details sd JOIN users u ON sd.user_id = u.id");
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($staffs as $s) {
    echo "Staff Detail ID: {$s['id']} | User ID: {$s['user_id']} | School ID: {$s['school_id']} | Name: {$s['full_name']}\n";
}
echo "\n--- 6. ALL QUESTIONS IN DATABASE ---\n";
$stmt = $pdo->query("SELECT * FROM cbt_questions");
$all_qs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($all_qs as $q) {
    echo "QID: {$q['id']} | Exam ID: {$q['exam_id']} | Text: " . substr($q['question_text'], 0, 50) . "...\n";
}
?>
