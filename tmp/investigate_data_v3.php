<?php
$pdo = new PDO('mysql:host=localhost;dbname=eduremarks_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$class_id = 7;

echo "--- Class Subjects for Class $class_id ---\n";
$stmt = $pdo->prepare("SELECT * FROM class_subjects WHERE class_id = ?");
$stmt->execute([$class_id]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
