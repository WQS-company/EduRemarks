<?php
require_once 'config/db.php';

$school_id = 4; // College of Health Doka

$stmt = $pdo->prepare("SELECT id, name, session_id, created_at FROM academic_terms WHERE school_id = ? ORDER BY created_at ASC");
$stmt->execute([$school_id]);
$terms = $stmt->fetchAll();

echo "All terms for school ID 4:\n";
foreach ($terms as $t) {
    echo "ID: " . $t['id'] . " | Name: " . $t['name'] . " | Session ID: " . $t['session_id'] . "\n";
}

$stmt = $pdo->prepare("SELECT id, name FROM academic_sessions WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$sessions = $stmt->fetchAll();
echo "\nAll sessions for school ID 4:\n";
foreach ($sessions as $s) {
    echo "ID: " . $s['id'] . " | Name: " . $s['name'] . "\n";
}
