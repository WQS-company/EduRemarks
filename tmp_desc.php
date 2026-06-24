<?php
require 'config/db.php';
$stmt = $pdo->query("DESCRIBE classes");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
