<?php
require_once 'config/db.php';
$stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_id = ?");
$stmt->execute([4]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
