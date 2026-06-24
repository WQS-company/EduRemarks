<?php
require_once 'includes/auth_check.php';

function describe($table, $pdo) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-20s %-20s %-10s %-10s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key']);
    }
    echo "\n";
}

describe('academic_terms', $pdo);
describe('subjects', $pdo);
