<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE academic_orchestration");
while ($row = $stmt->fetch()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
?>
