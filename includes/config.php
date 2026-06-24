<?php
// includes/config.php - Platform Orchestration Config
require_once dirname(__DIR__) . '/config/db.php';

// Fetch all settings into a cacheable array
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM platform_settings");
    $platform_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $platform_settings = [];
}

// Helper for dynamic settings
function get_setting($key, $default = '') {
    global $platform_settings;
    return $platform_settings[$key] ?? $default;
}

// Fetch dynamic services
try {
    $services = $pdo->query("SELECT * FROM platform_services ORDER BY sort_order ASC")->fetchAll();
} catch (Exception $e) {
    $services = [];
}
