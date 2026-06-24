<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE student_classes");
while ($row = $stmt->fetch()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
?>
