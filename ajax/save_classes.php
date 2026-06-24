<?php
// ajax/save_classes.php
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
if (empty($data['classes'])) die(json_encode(['success'=>false,'message'=>'No class data received']));

try {
    $inserted = 0; $updated = 0;
    foreach ($data['classes'] as $cls) {
        $id      = intval($cls['id'] ?? 0);
        $name    = sanitize($cls['name'] ?? '');
        $code    = sanitize($cls['code'] ?? '');
        $section = sanitize($cls['section'] ?? '');
        $seq     = intval($cls['seq'] ?? 0);

        // Auto-generate code from name if empty or AUTO (tertiary levels have no code field)
        if (!$code || $code === 'AUTO') {
            $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($name, 0, 10)));
        }

        if (!$name || !$code) continue;

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE classes SET name=?, code=?, section=?, sequence_level=? WHERE id=? AND school_id=?");
            $stmt->execute([$name, $code, $section, $seq, $id, $school_id]);
            $updated++;
        } else {
            $stmt = $pdo->prepare("INSERT INTO classes (school_id,name,code,section,sequence_level) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), section=VALUES(section), sequence_level=VALUES(sequence_level)");
            $stmt->execute([$school_id, $name, $code, $section, $seq]);
            $inserted++;
        }
    }
    echo json_encode(['success'=>true,'message'=>"Saved ($inserted added, $updated updated)"]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
