<?php
require_once 'c:/xampp/htdocs/dashboard/eduremarks/config/db.php';
try {
    $pdo->exec("ALTER TABLE platform_blog ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) AFTER image_path");
    echo "Success: video_url added.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE platform_blog ADD COLUMN IF NOT EXISTS author VARCHAR(100) AFTER author_id");
    echo "Success: author column added.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
