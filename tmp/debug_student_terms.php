<?php
require_once 'config/db.php';

$school_id = 4;
$current_session_id = 4; // 2025/2026 session ID

$t_stmt = $pdo->prepare("SELECT id, name FROM academic_terms WHERE session_id = ? AND school_id = ? ORDER BY created_at ASC");
$t_stmt->execute([$current_session_id, $school_id]);
$terms = $t_stmt->fetchAll();

echo "Count: " . count($terms) . "\n";
print_r($terms);
