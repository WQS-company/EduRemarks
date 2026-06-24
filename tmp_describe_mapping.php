<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE student_classes");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "STUDENT_CLASSES:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";

$stmt = $pdo->query("DESCRIBE classes");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "CLASSES:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n\n";
?>
