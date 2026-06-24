<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$school_id = 4;
$new_term_id = 14; // Second Semester

// Update school to Second Semester
echo "Updating school $school_id to term $new_term_id...\n";
$stmt = $pdo->prepare("UPDATE schools SET current_term_id = ? WHERE id = ?");
$stmt->execute([$new_term_id, $school_id]);

// Now simulate the student dashboard check
$st_stmt = $pdo->prepare("SELECT sch.current_term_id FROM schools sch WHERE sch.id = ?");
$st_stmt->execute([$school_id]);
$current_term_id = $st_stmt->fetchColumn();

$t = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
$t->execute([$current_term_id]);
$term_name = $t->fetchColumn();

// Simulation of get_label
$active_school = ['school_type' => 'Tertiary'];
$display_name = get_label($term_name);

echo "New Active Term ID: $current_term_id\n";
echo "Raw Term Name: $term_name\n";
echo "Display Label: $display_name\n";

// Reset back to 13 to be safe
$pdo->prepare("UPDATE schools SET current_term_id = 13 WHERE id = ?")->execute([$school_id]);
echo "Reset school to term 13.\n";
