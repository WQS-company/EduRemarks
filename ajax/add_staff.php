<?php
// ajax/add_staff.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

if ($role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
}

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die(json_encode(['success' => false, 'message' => 'No active school selected.']));
}

$full_name = sanitize($_POST['full_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');

if (empty($full_name) || empty($email)) {
    die(json_encode(['success' => false, 'message' => 'Full name and email are required.']));
}

try {
    $pdo->beginTransaction();

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch();

    $generated_password = '';
    
    if ($existing_user) {
        $user_id = $existing_user['id'];
        
        // Check if already in staff_details for this school
        $stmt = $pdo->prepare("SELECT id FROM staff_details WHERE user_id = ? AND school_id = ?");
        $stmt->execute([$user_id, $school_id]);
        if ($stmt->fetch()) {
            die(json_encode(['success' => false, 'message' => 'This user is already a staff member in this school.']));
        }
    } else {
        // Create new user
        $generated_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
        $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'staff')");
        $stmt->execute([$full_name, $email, $phone, $hashed_password]);
        $user_id = $pdo->lastInsertId();
    }

    // Add to staff_details
    $stmt = $pdo->prepare("INSERT INTO staff_details (user_id, school_id, status) VALUES (?, ?, 'active')");
    $stmt->execute([$user_id, $school_id]);

    $pdo->commit();

    $response = [
        'success' => true, 
        'message' => 'Staff member added successfully!',
        'credentials' => $generated_password ? [
            'email' => $email,
            'password' => $generated_password
        ] : null
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
