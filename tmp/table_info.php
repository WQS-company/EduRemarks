<?php
require_once 'C:/xampp/htdocs/dashboard/eduremarks/config/db.php';
$tables = ['cbt_exams', 'cbt_questions'];
$info = [];
foreach ($tables as $t) {
    $stmt = $pdo->query("DESCRIBE $t");
    $info[$t] = $stmt->fetchAll();
}
echo json_encode($info);
