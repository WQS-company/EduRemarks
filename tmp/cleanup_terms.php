<?php
require_once 'config/db.php';

$school_id = 4;
$session_id = 4;

// Find duplicates
$stmt = $pdo->prepare("SELECT name, COUNT(*) as count FROM academic_terms WHERE school_id = ? AND session_id = ? GROUP BY name HAVING count > 1");
$stmt->execute([$school_id, $session_id]);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "No duplicates found for school $school_id, session $session_id.\n";
} else {
    echo "Found duplicates:\n";
    print_r($duplicates);
    
    // Cleanup logic: keep the first ID for each name, delete the rest
    foreach ($duplicates as $dup) {
        $name = $dup['name'];
        $s = $pdo->prepare("SELECT id FROM academic_terms WHERE school_id = ? AND session_id = ? AND name = ? ORDER BY id ASC");
        $s->execute([$school_id, $session_id, $name]);
        $ids = $s->fetchAll(PDO::FETCH_COLUMN);
        
        $keep = array_shift($ids); // Keep the first one
        $delete_ids = implode(',', $ids);
        
        echo "Keeping ID $keep for '$name'. Deleting: $delete_ids\n";
        $pdo->exec("DELETE FROM academic_terms WHERE id IN ($delete_ids)");
    }
}
