<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE classes ADD COLUMN show_position TINYINT(1) DEFAULT 1;");
    echo "Added successfully.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
