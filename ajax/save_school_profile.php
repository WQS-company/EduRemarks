<?php
// ajax/save_school_profile.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

// Only owners can edit their school profile
if ($role !== 'owner') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
}

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die(json_encode(['success' => false, 'message' => 'No active school selected.']));
}

// Sanitize inputs
$motto         = sanitize($_POST['motto'] ?? '');
$contact_email = sanitize($_POST['contact_email'] ?? '');
$phone_1       = sanitize($_POST['phone_1'] ?? '');
$phone_2       = sanitize($_POST['phone_2'] ?? '');
$phone_3       = sanitize($_POST['phone_3'] ?? '');
$description   = sanitize($_POST['description'] ?? '');
$school_type   = sanitize($_POST['school_type'] ?? '');
$school_address = sanitize($_POST['school_address'] ?? '');
$adm_no_type   = sanitize($_POST['adm_no_type'] ?? 'system');
$adm_no_pattern = sanitize($_POST['adm_no_pattern'] ?? '{YEAR}/{ID}');
$adm_no_counter = (int)($_POST['adm_no_counter'] ?? 1);
$show_curriculum = isset($_POST['show_curriculum']) ? 1 : 0;

// Basic validation
if ($contact_email && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['success' => false, 'message' => 'Invalid email format.']));
}

try {
    // Fetch current assets to maintain them if no new ones are uploaded
    $stmt = $pdo->prepare("SELECT logo_path, proprietor_signature, director_signature, school_stamp FROM schools WHERE id = ? AND owner_id = ?");
    $stmt->execute([$school_id, $user_id]);
    $current = $stmt->fetch();
    $logo_path = $current['logo_path'] ?? null;
    $proprietor_signature = $current['proprietor_signature'] ?? null;
    $director_signature = $current['director_signature'] ?? null;
    $school_stamp = $current['school_stamp'] ?? null;

    // Handle Logo Upload (Existing logic remains...)
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['school_logo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            die(json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP, GIF allowed.']));
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            die(json_encode(['success' => false, 'message' => 'File too large. Maximum allowed size is 2MB.']));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = 'school_' . $school_id . '_' . time() . '.' . $ext;
        $upload_dir = dirname(__DIR__) . '/uploads/schools_logo/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if ($logo_path && file_exists(dirname(__DIR__) . '/' . $logo_path)) @unlink(dirname(__DIR__) . '/' . $logo_path);
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
            die(json_encode(['success' => false, 'message' => 'Failed to upload logo.']));
        }
        $logo_path = 'uploads/schools_logo/' . $new_name;
    }

    // Handle Proprietor Signature Upload
    if (isset($_FILES['proprietor_signature']) && $_FILES['proprietor_signature']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['proprietor_signature'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = 'prop_sig_' . $school_id . '_' . time() . '.' . $ext;
        $upload_dir = dirname(__DIR__) . '/uploads/signatures/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if ($proprietor_signature && file_exists(dirname(__DIR__) . '/' . $proprietor_signature)) @unlink(dirname(__DIR__) . '/' . $proprietor_signature);
        move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
        $proprietor_signature = 'uploads/signatures/' . $new_name;
    }

    // Handle Director Signature Upload
    if (isset($_FILES['director_signature']) && $_FILES['director_signature']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['director_signature'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = 'dir_sig_' . $school_id . '_' . time() . '.' . $ext;
        $upload_dir = dirname(__DIR__) . '/uploads/signatures/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if ($director_signature && file_exists(dirname(__DIR__) . '/' . $director_signature)) @unlink(dirname(__DIR__) . '/' . $director_signature);
        move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
        $director_signature = 'uploads/signatures/' . $new_name;
    }

    // Handle School Stamp Upload
    if (isset($_FILES['school_stamp']) && $_FILES['school_stamp']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['school_stamp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = 'stamp_' . $school_id . '_' . time() . '.' . $ext;
        $upload_dir = dirname(__DIR__) . '/uploads/signatures/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if ($school_stamp && file_exists(dirname(__DIR__) . '/' . $school_stamp)) @unlink(dirname(__DIR__) . '/' . $school_stamp);
        move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
        $school_stamp = 'uploads/signatures/' . $new_name;
    }

    $stmt = $pdo->prepare(
        "UPDATE schools SET motto = ?, contact_email = ?, phone_1 = ?, phone_2 = ?, phone_3 = ?, description = ?, school_type = ?, school_address = ?, logo_path = ?, adm_no_type = ?, adm_no_pattern = ?, adm_no_counter = ?, proprietor_signature = ?, director_signature = ?, school_stamp = ?, show_curriculum = ? WHERE id = ? AND owner_id = ?"
    );
    $stmt->execute([$motto, $contact_email, $phone_1, $phone_2, $phone_3, $description, $school_type, $school_address, $logo_path, $adm_no_type, $adm_no_pattern, $adm_no_counter, $proprietor_signature, $director_signature, $school_stamp, $show_curriculum, $school_id, $user_id]);

    // Handle incoming school sections
    $sections = $_POST['sections'] ?? [];
    $pdo->prepare("DELETE FROM school_sections WHERE school_id = ?")->execute([$school_id]);
    
    if (is_array($sections) && !empty($sections)) {
        $insertSec = $pdo->prepare("INSERT INTO school_sections (school_id, section_name) VALUES (?, ?)");
        $unique_sections = array_unique(array_map('trim', $sections));
        foreach ($unique_sections as $sec) {
            if ($sec !== '') {
                $insertSec->execute([$school_id, $sec]);
            }
        }
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'School profile updated successfully!',
        'logo_path' => $logo_path ? '../' . $logo_path : null
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
