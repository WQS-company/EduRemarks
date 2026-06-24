<?php
require 'includes/config.php';
try {
    $pdo->exec('ALTER TABLE staff_details ADD COLUMN can_manage_academics TINYINT(1) NOT NULL DEFAULT 0');
    echo "Added can_manage_academics\n";
} catch (Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
