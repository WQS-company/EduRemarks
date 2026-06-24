<?php
require_once 'config/db.php';
session_start();
$school_id = $_SESSION['student_school_id'] ?? 4; // Assuming College of Health Doka

$stmt = $pdo->prepare("SELECT id, school_name, school_type, current_session_id, current_term_id FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

echo "School: " . $school['school_name'] . " (ID: $school_id, Type: " . $school['school_type'] . ")\n";
echo "Active Session ID: " . $school['current_session_id'] . "\n";
echo "Active Term ID: " . $school['current_term_id'] . "\n\n";

if ($school['current_session_id']) {
    $stmt = $pdo->prepare("SELECT id, name, session_id, school_id FROM academic_terms WHERE session_id = ? AND school_id = ? ORDER BY created_at ASC");
    $stmt->execute([$school['current_session_id'], $school_id]);
    $terms = $stmt->fetchAll();
    echo "Terms for current session:\n";
    foreach ($terms as $t) {
        echo "ID: " . $t['id'] . " | Name: " . $t['name'] . " | School ID: " . $t['school_id'] . "\n";
    }
}
