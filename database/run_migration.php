<?php
// database/run_migration.php
require_once __DIR__ . '/../config/db.php';

$migrationFile = $argv[1] ?? null;

if (!$migrationFile) {
    die("Usage: php run_migration.php <migration_file_path>\n");
}

$absolutePath = realpath($migrationFile);
if (!$absolutePath || !file_exists($absolutePath)) {
    die("Error: Migration file not found: $migrationFile\n");
}

try {
    $sql = file_get_contents($absolutePath);
    
    // Split into multiple statements if needed
    // Simple split by ; followed by newline
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    
    echo "✓ Migration successful: " . basename($migrationFile) . "\n";
} catch (PDOException $e) {
    die("❌ Migration failed: " . $e->getMessage() . "\n");
}
