<?php
// includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

if (!isset($_SESSION['user_id'])) {
    $redir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/super_admin/') !== false) ? '../login.php' : 'login.php';
    header("Location: $redir");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    $redir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/super_admin/') !== false) ? '../login.php' : 'login.php';
    header("Location: $redir");
    exit();
}

// Synchronize session role with DB to ensure updates take effect
$role = $user['role'];
$_SESSION['role'] = $role;

// 0. Path Prefix for Subdirectories
$is_subdir = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/user/') !== false || strpos($_SERVER['PHP_SELF'], '/student/') !== false) ? true : false;
$path_prefix = $is_subdir ? '../' : '';

$user_full_name = $user['full_name'];
$user_email = $user['email'];
$user_phone = $user['phone'] ?? '';
$profile_picture = (!empty($user['profile_picture'])) ? $path_prefix . $user['profile_picture'] : $path_prefix . 'img/default_picture.png';

// Get Schools Associated with the user
if ($role === 'super_admin') {
    $user_schools = $pdo->query("SELECT * FROM schools ORDER BY school_name")->fetchAll();
} else if ($role === 'owner') {
    $stmt = $pdo->prepare("SELECT * FROM schools WHERE owner_id = ?");
    $stmt->execute([$user_id]);
    $user_schools = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT s.*, sd.status FROM schools s JOIN staff_details sd ON s.id = sd.school_id WHERE sd.user_id = ?");
    $stmt->execute([$user_id]);
    $user_schools = $stmt->fetchAll();
}

// 1. Force reload preference if session is missing
if (!isset($_SESSION['school_id']) && $user['last_school_id']) {
    $_SESSION['school_id'] = $user['last_school_id'];
}

// 2. Validate current session school_id
$active_school_id = $_SESSION['school_id'] ?? null;
$has_access = false;
if ($active_school_id && !empty($user_schools)) {
    foreach ($user_schools as $s) {
        if ($s['id'] == $active_school_id) {
            $has_access = true;
            break;
        }
    }
}

// 3. Fallback logic: if no access or no school selected, pick the best candidate
if (!$has_access && !empty($user_schools)) {
    // Check if the DB preference is valid even if session was wrong
    $db_pref_valid = false;
    foreach ($user_schools as $s) {
        if ($s['id'] == $user['last_school_id']) {
            $_SESSION['school_id'] = $user['last_school_id'];
            $db_pref_valid = true;
            break;
        }
    }
    
    // If even DB preference is invalid/missing, pick the first school
    if (!$db_pref_valid) {
        $_SESSION['school_id'] = $user_schools[0]['id'];
    }
}

$active_school_id = $_SESSION['school_id'] ?? null;
$active_school = null;

if ($active_school_id) {
    foreach ($user_schools as $s) {
        if ($s['id'] == $active_school_id) {
            $active_school = $s;
            break;
        }
    }
}

// Global Dynamic Favicon/Logo URL
$school_logo_url = ($active_school && !empty($active_school['logo_path'])) ? $path_prefix . $active_school['logo_path'] : $path_prefix . 'img/logo.png';

// Platform-level Favicon (User requested sidebar logo as global favicon)
$sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
$platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;

// Fetch Staff Permissions
$staff_permissions = [
    'can_manage_students' => false,
    'can_manage_academics' => false,
    'can_manage_cbt' => false,
    'can_edit_history' => false
];
if ($role === 'staff' && $active_school_id) {
    $perm_stmt = $pdo->prepare("SELECT can_manage_students, can_manage_academics, can_manage_cbt, can_edit_history FROM staff_details WHERE user_id = ? AND school_id = ?");
    $perm_stmt->execute([$user_id, $active_school_id]);
    $perms = $perm_stmt->fetch(PDO::FETCH_ASSOC);
    if ($perms) {
        $staff_permissions['can_manage_students'] = (bool)$perms['can_manage_students'];
        $staff_permissions['can_manage_academics'] = (bool)$perms['can_manage_academics'];
        $staff_permissions['can_manage_cbt'] = (bool)$perms['can_manage_cbt'];
        $staff_permissions['can_edit_history'] = (bool)$perms['can_edit_history'];
    }
} else if ($role === 'owner' || $role === 'super_admin') {
    $staff_permissions['can_manage_students'] = true; 
    $staff_permissions['can_manage_academics'] = true;
    $staff_permissions['can_manage_cbt'] = true; 
    $staff_permissions['can_edit_history'] = true;
}

?>
