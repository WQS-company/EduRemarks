<?php
// seed_platform.php - Initial Platform Branding
require_once 'config/db.php';

$seeds = [
    ['hero_title', 'Master Your Institution with EduRemarks'],
    ['hero_subtitle', 'The all-in-one localized SaaS for student assessment, CBT, and academic orchestration. Powered by AI and precision.'],
    ['about_content', 'EduRemarks is the leading academic ERP platform in Africa, designed to bridge the gap between traditional schooling and digital excellence. Our mission is to empower every teacher with elite tools for student success.'],
    ['footer_phone', '+234 800 EDUREMARKS'],
    ['footer_email', 'hello@eduremarks.com'],
    ['footer_address', 'Innovation Hub, Plot 12, Lagos, Nigeria'],
];

foreach ($seeds as $s) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute($s);
}

$services = [
    [' fas fa-check-double', 'Instant Result Generation', 'Process student scores and generate high-fidelity reports in seconds, not hours.'],
    ['fas fa-laptop-code', 'Elite CBT Engine', 'Our robust Computer Based Test system operates smoothly on any mobile or desktop node.'],
    ['fas fa-shield-alt', 'Institutional Security', 'We employ state-of-the-art encryption to ensure your student data stays private and secure.'],
];

foreach ($services as $sv) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO platform_services (icon, title, description) VALUES (?, ?, ?)");
    $stmt->execute($sv);
}

echo "Platform branding seeded successfully.";
