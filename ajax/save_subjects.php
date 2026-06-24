<?php
// ajax/save_subjects.php
require_once '../includes/auth_check.php';
header('Content-Type: application/json');
if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_academics'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) die(json_encode(['success'=>false,'message'=>'No active school']));

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['subjects'])) die(json_encode(['success'=>false,'message'=>'No data received']));

$is_course = intval($data['is_course'] ?? 0);
try {
    $ins = 0; $upd = 0;
    foreach ($data['subjects'] as $sub) {
        $id     = intval($sub['id'] ?? 0);
        $name   = sanitize($sub['name'] ?? '');
        $code   = sanitize($sub['code'] ?? '');
        $period = sanitize($sub['period'] ?? '');
        $credit_units = intval($sub['credit_units'] ?? 0);
        $semester_id  = intval($sub['semester_id'] ?? 0);
        $semester_id  = ($semester_id > 0) ? $semester_id : null;
        $department_id = intval($sub['department_id'] ?? 0);
        $department_id = ($department_id > 0) ? $department_id : null;

        if (!$name || !$code) continue;

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE subjects SET name=?, code=?, period=?, is_course=?, credit_units=?, semester_id=?, department_id=? WHERE id=? AND school_id=?");
            $stmt->execute([$name, $code, $period, $is_course, $credit_units, $semester_id, $department_id, $id, $school_id]);
            $upd++;
        } else {
            $stmt = $pdo->prepare("INSERT INTO subjects (school_id, name, code, period, is_course, credit_units, semester_id, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), period=VALUES(period), credit_units=VALUES(credit_units), semester_id=VALUES(semester_id), department_id=VALUES(department_id)");
            $stmt->execute([$school_id, $name, $code, $period, $is_course, $credit_units, $semester_id, $department_id]);
            $ins++;
        }
    }
    echo json_encode(['success'=>true,'message'=>"Saved ($ins added, $upd updated)"]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
