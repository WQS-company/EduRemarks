<?php
$pdo = new PDO('mysql:host=localhost;dbname=eduremarks_db;charset=utf8mb4', 'root', '');
$pdo->exec('ALTER TABLE academic_orchestration ADD COLUMN entry_deadline DATE NULL');
echo "Done\n";
