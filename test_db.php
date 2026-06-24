<?php
require 'config/db.php';
$stmt = $pdo->query('DESCRIBE cbt_exams');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
