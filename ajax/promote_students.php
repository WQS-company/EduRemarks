<?php
// ajax/promote_students.php — Multi-Path Academic Promotion Engine
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

if ($role !== 'owner' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$school_id = $active_school['id'];
$data = json_decode(file_get_contents('php://input'), true);
$mappings = $data['mappings'] ?? [];

if (empty($mappings)) {
    die(json_encode(['success' => false, 'message' => 'No promotion mappings provided.']));
}

try {
    $pdo->beginTransaction();

    // 1. Update Next Class IDs in the classes table first
    foreach ($mappings as $m) {
        $stmt = $pdo->prepare("UPDATE classes SET next_class_id = ? WHERE id = ? AND school_id = ?");
        $next_id = !empty($m['next_class_id']) ? intval($m['next_class_id']) : null;
        $stmt->execute([$next_id, intval($m['class_id']), $school_id]);
    }

    // 2. Fetch all mappings to perform student moves
    // We must move students carefully to avoid those already moved being moved again in the same cycle.
    // Temporary table or status flag is usually safest, but here we can use the source class name.
    
    // First, let's get a map of Source Class ID -> Target Class Name
    $stmt = $pdo->prepare("
        SELECT c1.id as source_id, c1.name as source_name, c2.name as target_name 
        FROM classes c1
        LEFT JOIN classes c2 ON c1.next_class_id = c2.id
        WHERE c1.school_id = ?
    ");
    $stmt->execute([$school_id]);
    $pathways = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $log = [];
    $total_promoted = 0;
    
    // We'll use a temporary mapping to store student updates so they don't get double-processed
    // Actually, SQL allows us to do this in one go if we use a JOIN or CASE, but since class names might overlap, 
    // it's safest to use a temporary update flag.
    
    // Clear any previous promotion flags (though there shouldn't be any)
    // $pdo->exec("UPDATE students SET promotion_flag = 0 WHERE school_id = $school_id"); // If we had a flag
    
    // Professional approach: Process each pathway
    foreach ($pathways as $p) {
        if ($p['target_name']) {
            // PROMOTE
            $stmt = $pdo->prepare("UPDATE students SET student_class = ? WHERE student_class = ? AND school_id = ? AND status = 'active'");
            $stmt->execute([$p['target_name'] . '_PROMOTED_TEMP', $p['source_name'], $school_id]);
            $count = $stmt->rowCount();
            if($count > 0) {
                $log[] = "{$p['source_name']} → {$p['target_name']} ($count students)";
                $total_promoted += $count;
            }
        } else {
            // GRADUATE
            $stmt = $pdo->prepare("UPDATE students SET status = 'graduated' WHERE student_class = ? AND school_id = ? AND status = 'active'");
            $stmt->execute([$p['source_name'], $school_id]);
            $count = $stmt->rowCount();
            if($count > 0) {
                $log[] = "{$p['source_name']} → Graduated ($count students)";
            }
        }
    }

    // Finalize: Remove temperature suffixes from class names (14 chars: _PROMOTED_TEMP)
    $stmt = $pdo->prepare("UPDATE students SET student_class = SUBSTR(student_class, 1, LENGTH(student_class) - 14) WHERE student_class LIKE '%\_PROMOTED\_TEMP' AND school_id = ?");
    $stmt->execute([$school_id]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => "Academic Promotion Cycle Complete!\n" . (empty($log) ? "No students found for promotion." : implode("\n", $log))
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
