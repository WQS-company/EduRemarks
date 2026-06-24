<?php
require_once 'config/db.php';
$tables = ['classes', 'students', 'departments'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } catch (Exception $e) {
        echo "Error or table missing: " . $e->getMessage() . "\n";
    }
}
?>
