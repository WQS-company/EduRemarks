<?php
// ajax/register.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
Security::init();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

// 1. Sanitize Personal Info
$full_name = sanitize($_POST['full_name']);
$email     = sanitize($_POST['email']);
$phone     = sanitize($_POST['phone']);
$password  = $_POST['password']; // Will hash later
$role      = sanitize($_POST['role']); // owner or staff

// Basic Validation
if (empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($role)) {
    die(json_encode(['success' => false, 'message' => 'Please fill all required personal fields.']));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['success' => false, 'message' => 'Invalid email format.']));
}

try {
    $pdo->beginTransaction();

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Email already registered.']));
    }

    // Hash Password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 2. Insert User
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $email, $phone, $hashed_password, $role]);
    $user_id = $pdo->lastInsertId();

    // 3. Handle Role Specific Logic
    if ($role === 'owner') {
        $school_name    = sanitize($_POST['school_name']);
        $school_type    = sanitize($_POST['school_type']);
        $school_address = sanitize($_POST['school_address']);

        if (empty($school_name) || empty($school_type) || empty($school_address)) {
            $pdo->rollBack();
            die(json_encode(['success' => false, 'message' => 'Please provide complete school details.']));
        }

        // Generate Unique School ID
        $unique_id = generateSchoolID($pdo);

        // Insert School (Credits default to 3000 in DB, but we specify it for clarity)
        $initial_credits = 3000;
        $stmt = $pdo->prepare("INSERT INTO schools (unique_id, school_name, school_type, school_address, owner_id, credits) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$unique_id, $school_name, $school_type, $school_address, $user_id, $initial_credits]);
        $school_id = $pdo->lastInsertId();

        // Log Initial Credits Reward
        $stmt = $pdo->prepare("INSERT INTO credit_logs (school_id, amount, activity) VALUES (?, ?, ?)");
        $stmt->execute([$school_id, $initial_credits, "Welcome Gift: Free Trial Credits"]);
        
        $message = "Registration successful! Your Unique School ID is: " . $unique_id;

    } else if ($role === 'staff') {
        $school_id      = sanitize($_POST['school_id']);
        $input_school_id = sanitize($_POST['unique_school_id']); // The code they entered

        if (empty($school_id) || empty($input_school_id)) {
            $pdo->rollBack();
            die(json_encode(['success' => false, 'message' => 'Please select a school and enter its Unique ID.']));
        }

        // Verify School ID matches
        $stmt = $pdo->prepare("SELECT id FROM schools WHERE id = ? AND unique_id = ?");
        $stmt->execute([$school_id, $input_school_id]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            die(json_encode(['success' => false, 'message' => 'Incorrect School Unique ID. Please verify with your school admin.']));
        }

        // Associate Staff with School
        $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, school_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$user_id, $school_id]);

        $message = "Registration successful! Your account is pending approval from the school administrator.";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
