<?php
// ajax/login.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
Security::init();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

// 0. Throttling Node
if (!Security::checkRateLimit('login', 10, 300)) { // 10 attempts per 5 mins
    die(json_encode(['success' => false, 'message' => 'Too many failed attempts. Identity locked for 5 minutes.']));
}

$identity = sanitize($_POST['identity']);
$password = $_POST['password'];

if (empty($identity) || empty($password)) {
    die(json_encode(['success' => false, 'message' => 'Please enter both login identity and password.']));
}

try {
    // Identity can be Email OR Phone Number
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Login Successful
        session_regenerate_id(true); // Security: Prevent session fixation
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        
        // Determine initial school environment
        $target_school_id = null;
        if ($user['last_school_id']) {
            $target_school_id = $user['last_school_id'];
        }

        // If staff, verify they aren't suspended
        if ($user['role'] === 'staff') {
            $stmt = $pdo->prepare("SELECT school_id, status FROM staff_details WHERE user_id = ? " . ($target_school_id ? "AND school_id = ?" : "LIMIT 1"));
            $params = [$user['id']];
            if ($target_school_id) $params[] = $target_school_id;
            
            $stmt->execute($params);
            $staff = $stmt->fetch();
            
            if ($staff && $staff['status'] === 'inactive') {
                die(json_encode(['success' => false, 'message' => 'Your access has been suspended. Please contact the administrator.']));
            }
            
            $_SESSION['school_id'] = $staff ? $staff['school_id'] : null;
        } else {
            // Role is owner
            if ($target_school_id) {
                $_SESSION['school_id'] = $target_school_id;
            } else {
                $stmt = $pdo->prepare("SELECT id FROM schools WHERE owner_id = ? LIMIT 1");
                $stmt->execute([$user['id']]);
                $school = $stmt->fetch();
                $_SESSION['school_id'] = $school ? $school['id'] : null;
            }
        }

        echo json_encode(['success' => true, 'message' => 'Login successful! Redirecting...']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
