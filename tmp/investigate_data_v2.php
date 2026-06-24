<?php
$pdo = new PDO('mysql:host=localhost;dbname=eduremarks_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$school_id = 4;
$term_id = 16;
$class_id = 7;

echo "--- Current Terms for School $school_id ---\n";
$stmt = $pdo->prepare("SELECT id, name FROM academic_terms WHERE school_id = ?");
$stmt->execute([$school_id]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n--- Subjects for School $school_id ---\n";
$stmt = $pdo->prepare("SELECT id, name, semester_id FROM subjects WHERE school_id = ?");
$stmt->execute([$school_id]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n--- Staff Class Subjects for Class $class_id ---\n";
$stmt = $pdo->prepare("SELECT * FROM staff_class_subjects WHERE class_id = ?");
$stmt->execute([$class_id]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
