<?php
// config/db.php

$host = 'localhost';
$db = 'eduremarks_db';
$user = 'root'; // Update with your DB username
$pass = ''; // Update with your DB password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false,
];
try {
     $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
     // Log the real error internally, show a generic one to the world
     error_log("DB Connection Failure: " . $e->getMessage());
     die("System Transmission Error: Secure node connection failed. Please contact the administrator.");
}
