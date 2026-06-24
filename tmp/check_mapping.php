<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESC class_subjects");
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "---\n";
$stmt = $pdo->query("DESC staff_class_subjects");
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
