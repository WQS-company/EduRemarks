<?php
$host = 'localhost';
$db   = 'eduremarks_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

function describe($table, $pdo) {
    echo "--- $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            printf("%-20s %-20s %-10s %-10s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key']);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

describe('staff_class_subjects', $pdo);
describe('staff_details', $pdo);
describe('subjects', $pdo);
describe('academic_terms', $pdo);
describe('students', $pdo);
describe('departments', $pdo);
describe('semesters', $pdo);
