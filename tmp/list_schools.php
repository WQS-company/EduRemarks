<?php
require_once 'config/db.php';

$stmt = $pdo->query("SELECT id, school_name, current_session_id, current_term_id, school_type FROM schools");
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- All Schools ---\n";
foreach ($schools as $s) {
    echo "ID: {$s['id']} | Name: {$s['school_name']} | Session: {$s['current_session_id']} | Term: {$s['current_term_id']} | Type: {$s['school_type']}\n";
    
    // Fetch term name for each school's active term
    if ($s['current_term_id']) {
        $t_stmt = $pdo->prepare("SELECT name FROM academic_terms WHERE id = ?");
        $t_stmt->execute([$s['current_term_id']]);
        $name = $t_stmt->fetchColumn();
        echo "   Active Term Name: $name\n";
    }
}
