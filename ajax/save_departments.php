<?php
// ajax/save_departments.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) die(json_encode(['success' => false, 'message' => 'No active school context.']));

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['departments'])) die(json_encode(['success' => false, 'message' => 'No data received.']));

try {
    $inserted = 0;
    $updated  = 0;

    foreach ($data['departments'] as $dept) {
        $id   = intval($dept['id'] ?? 0);
        $name = sanitize($dept['name'] ?? '');
        $code = sanitize($dept['code'] ?? '');

        if (!$name) continue;

        if ($id > 0) {
            // Update existing section/department
            $stmt = $pdo->prepare("UPDATE school_sections SET section_name = ?, section_code = ? WHERE id = ? AND school_id = ?");
            $stmt->execute([$name, $code, $id, $school_id]);
            $updated++;
        } else {
            // Insert new section/department
            $stmt = $pdo->prepare("INSERT INTO school_sections (school_id, section_name, section_code) VALUES (?, ?, ?)");
            $stmt->execute([$school_id, $name, $code]);
            $inserted++;
        }
    }

    $label_pl = get_label('Sections');
    echo json_encode([
        'success' => true, 
        'message' => "$label_pl synchronized successfuly ($inserted new, $updated updated)."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
