<?php
require_once 'C:/xampp/htdocs/dashboard/eduremarks/config/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($tables);
