<?php
// ajax/bulk_upload_students.php
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method.']));
}

if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($role === 'staff' && empty($staff_permissions['can_manage_students'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}

$school_id = $_SESSION['school_id'];
$target_class_id = intval($_POST['class_id'] ?? 0);

if (!$school_id || !$target_class_id) {
    die(json_encode(['success' => false, 'message' => 'School or Class not selected.']));
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['success' => false, 'message' => 'Please upload a valid CSV file.']));
}

// Verify class exists and belongs to school
$stmt = $pdo->prepare("SELECT name FROM classes WHERE id = ? AND school_id = ?");
$stmt->execute([$target_class_id, $school_id]);
$class_data = $stmt->fetch();
if (!$class_data) {
    die(json_encode(['success' => false, 'message' => 'Target class is invalid.']));
}
$class_name = $class_data['name'];

// Fetch School Admin Protocol
$stmt = $pdo->prepare("SELECT adm_no_type, adm_no_pattern, adm_no_counter, unique_id FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$sch_set = $stmt->fetch();

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

// Skip header
$header = fgetcsv($handle);

$success_count = 0;
$errors = [];
$row_index = 1; // Starting from data row

$pdo->beginTransaction();

try {
    while (($data = fgetcsv($handle)) !== false) {
        $row_index++;
        if (empty(array_filter($data))) continue; // skip empty rows

        // Map columns (adjust based on template)
        // Expected: FullName, AdmissionNo, Gender, DOB, GuardianName, GuardianPhone, Address
        $full_name     = sanitize($data[0] ?? '');
        $admission_no  = sanitize($data[1] ?? '');
        $gender        = sanitize($data[2] ?? 'Male');
        $dob           = sanitize($data[3] ?? '');
        $guardian_name = sanitize($data[4] ?? '');
        $guardian_phone= sanitize($data[5] ?? '');
        $address       = sanitize($data[6] ?? '');

        if (empty($full_name)) {
            $errors[] = "Row $row_index: Full Name is missing.";
            continue;
        }

        // Handle Admission Number
        if (empty($admission_no)) {
            if ($sch_set['adm_no_type'] === 'system') {
                $admission_no = "ADM" . mt_rand(100000, 999999);
            } elseif ($sch_set['adm_no_type'] === 'pattern') {
                $pattern = $sch_set['adm_no_pattern'];
                $counter = (int)$sch_set['adm_no_counter'];
                
                $admission_no = str_replace('{YEAR}', date('Y'), $pattern);
                $admission_no = str_replace('{SCH}', $sch_set['unique_id'], $admission_no);
                $admission_no = str_replace('{ID}', str_pad($counter, 3, '0', STR_PAD_LEFT), $admission_no);
                
                // Atomic Counter Increment (we'll do this once per successful row)
                $pdo->prepare("UPDATE schools SET adm_no_counter = adm_no_counter + 1 WHERE id = ?")->execute([$school_id]);
                $sch_set['adm_no_counter']++; // update local counter for next row
            } else {
                $errors[] = "Row $row_index: Admission Number is required for manual protocols.";
                continue;
            }
        }

        // Check for existing admission number
        $stmt = $pdo->prepare("SELECT id FROM students WHERE school_id = ? AND admission_no = ?");
        $stmt->execute([$school_id, $admission_no]);
        if ($stmt->fetch()) {
            $errors[] = "Row $row_index: Admission number '$admission_no' already exists.";
            continue;
        }

        // Insert Student
        $stmt = $pdo->prepare("
            INSERT INTO students (school_id, full_name, admission_no, student_class, gender, dob, guardian_name, guardian_phone, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$school_id, $full_name, $admission_no, $class_name, $gender, $dob ?: null, $guardian_name, $guardian_phone, $address]);
        $student_id = $pdo->lastInsertId();

        // Map to class
        $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id, school_id) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, $target_class_id, $school_id]);

        $success_count++;
    }

    $pdo->commit();
    fclose($handle);

    echo json_encode([
        'success' => true, 
        'message' => "$success_count students uploaded successfully.",
        'errors' => $errors
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
}
?>
