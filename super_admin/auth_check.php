<?php
// super_admin/auth_check.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Load Core Infrastructure
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

// Role-Based Authorization Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch();

if (!$admin || $admin['role'] !== 'super_admin') {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$path_prefix = '../';
$profile_picture = (!empty($admin['profile_picture'])) ? $path_prefix . $admin['profile_picture'] : $path_prefix . 'img/default_picture.png';
$admin_name = $admin['full_name'];
$user_full_name = $admin['full_name']; // For header compatibility
$user_schools = $pdo->query("SELECT * FROM schools ORDER BY school_name")->fetchAll(); // For switching node

// Platform-level Favicon (User requested sidebar logo as global favicon)
$sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
$platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;
