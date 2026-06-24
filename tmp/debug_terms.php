<?php
require_once 'config/db.php';

$school_id = 4;
$stmt = $pdo->prepare("SELECT id, session_id, name FROM academic_terms WHERE school_id = ? ORDER BY id DESC");
$stmt->execute([$school_id]);
$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- Terms for School $school_id ---\n";
foreach ($terms as $t) {
    echo "ID: {$t['id']} | Session ID: {$t['session_id']} | Name: {$t['name']}\n";
}

$stmt = $pdo->prepare("SELECT current_session_id, current_term_id FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\n--- Active Settings ---\n";
echo "Active Session: {$school['current_session_id']}\n";
echo "Active Term ID: {$school['current_term_id']}\n";
