<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESC subjects");
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
