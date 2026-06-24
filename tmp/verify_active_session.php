<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Simulate school context (School ID 4)
$school_id = 4;
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$active_school = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$active_school) {
    die("School not found.\n");
}

echo "--- School Active Settings ---\n";
echo "Current Session ID: " . $active_school['current_session_id'] . "\n";
echo "Current Term ID: " . $active_school['current_term_id'] . "\n";

echo "\n--- Names from DB ---\n";
$current_session_id = $active_school['current_session_id'];
$current_term_id = $active_school['current_term_id'];

$t = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
$t->execute([$current_term_id]); 
echo "Term Name: " . $t->fetchColumn() . "\n";

$s = $pdo->prepare("SELECT name FROM academic_sessions WHERE id = ?");
$s->execute([$current_session_id]); 
echo "Session Name: " . $s->fetchColumn() . "\n";

echo "\n--- Dashboard Header Render ---\n";
$term_name = $t->fetchColumn() ?: ''; // Will be empty because fetch already run
// Re-run for display
$t->execute([$current_term_id]);
$term_name = $t->fetchColumn() ?: '';
$s->execute([$current_session_id]);
$session_name = $s->fetchColumn() ?: '';

echo "Display Term Name: " . get_label($term_name) . "\n";
echo "Display Session Name: " . $session_name . "\n";
