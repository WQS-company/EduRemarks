<?php
// dashboard.php - Entry point Router
require_once 'includes/auth_check.php';

if ($role === 'super_admin') {
    header('Location: super_admin/dashboard.php');
} else if ($role === 'owner') {
    header('Location: admin/dashboard.php');
} else if ($role === 'staff') {
    header('Location: user/dashboard.php');
} else {
    // Fallback or error
    header('Location: login.php?error=invalid_role');
}
exit();
?>
