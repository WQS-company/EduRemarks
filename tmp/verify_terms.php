<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Simulate school context (School ID 4, which is a tertiary institution based on previous logs)
$school_id = 4;
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$active_school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$active_school) {
    die("School not found.\n");
}

echo "--- School Info ---\n";
echo "Name: " . $active_school['school_name'] . "\n";
echo "Type: " . $active_school['school_type'] . "\n";
echo "Current Session ID: " . $active_school['current_session_id'] . "\n";

echo "\n--- Terminology Check ---\n";
echo "Label for 'Term': " . get_label('Term') . "\n";
echo "Label for '1st Term': " . get_label('1st Term') . "\n";
echo "Label for 'Semester': " . get_label('Semester') . "\n";

echo "\n--- Terms in Database for Active Session ---\n";
$current_session_id = $active_school['current_session_id'];
$t_stmt = $pdo->prepare("SELECT id, name FROM academic_terms WHERE session_id = ? AND school_id = ? ORDER BY created_at ASC");
$t_stmt->execute([$current_session_id, $school_id]);
$terms = $t_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($terms as $t) {
    echo "- ID: {$t['id']} | Raw Name: {$t['name']} | Display Label: " . get_label($t['name']) . "\n";
}
