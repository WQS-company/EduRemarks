<?php
require_once 'config/db.php';
try {
    $cols = $pdo->query("DESCRIBE support_messages")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('file_path', $cols)) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN file_path VARCHAR(255) DEFAULT NULL");
    }
    if (!in_array('attachment_type', $cols)) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN attachment_type VARCHAR(50) DEFAULT NULL");
    }

    echo "Migration Successful: File support added to transmissions.\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
