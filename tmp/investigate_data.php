<?php
$pdo = new PDO('mysql:host=localhost;dbname=eduremarks_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- academic_terms ---\n";
$stmt = $pdo->query("SELECT * FROM academic_terms LIMIT 20");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n--- subjects ---\n";
$stmt = $pdo->query("SELECT id, name, semester_id, period FROM subjects LIMIT 20");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
