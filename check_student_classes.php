<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT student_id, COUNT(*) as c FROM student_classes GROUP BY student_id HAVING c > 1 LIMIT 5");
$res = $stmt->fetchAll();
if (empty($res)) {
    echo "No students with multiple class allocations found.\n";
} else {
    print_r($res);
}
?>
