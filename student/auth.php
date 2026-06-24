<?php
// student/auth.php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/config.php';

$student_id = $_SESSION['student_id'];
$school_id = $_SESSION['student_school_id'];

// Refresh session data if needed
$st_stmt = $pdo->prepare("
    SELECT s.*, sch.school_name, sch.logo_path, sch.feature_access, sch.motto, sch.school_address, sch.school_type, sch.status as school_status, sch.current_session_id, sch.current_term_id,
    ss.section_name as department_name
    FROM students s 
    JOIN schools sch ON sch.id = s.school_id 
    LEFT JOIN school_sections ss ON ss.id = s.department_id
    WHERE s.id = ?
");
$st_stmt->execute([$student_id]);
$student = $st_stmt->fetch();

if (!$student || $student['portal_active'] == 0) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Set active school context for functions like get_label()
$active_school = $student;

// Check feature access
$features = explode(',', $student['feature_access'] ?? '');
if (!in_array('STUDENT_PORTAL', $features)) {
    session_destroy();
    $_SESSION['error'] = "Student portal access has been deactivated for your institution.";
    header('Location: login.php');
    exit();
}

// Platform-level Favicon (User requested sidebar logo as global favicon)
$path_prefix = '../';
$sidebar_logo_raw = get_setting('sidebar_logo', 'img/logo.png');
$platform_favicon = (strpos($sidebar_logo_raw, 'http') === 0) ? $sidebar_logo_raw : $path_prefix . $sidebar_logo_raw;
