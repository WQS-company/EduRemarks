<?php
// ajax/manage_curriculum.php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$school_id = $_SESSION['school_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'No school context active.']);
    exit();
}

// Security: Only owner/super_admin can write. Staff can only read (if we merge list logic here)
$can_write = in_array($role, ['owner', 'super_admin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'save') {
    if (!$can_write) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit();
    }

    $id = intval($_POST['id'] ?? 0);
    $section_id = intval($_POST['section_id'] ?? 0) ?: null;
    $class_id = intval($_POST['class_id'] ?? 0) ?: null;
    $subject_id = intval($_POST['subject_id'] ?? 0) ?: null;
    $term = intval($_POST['term'] ?? 1);
    $week = intval($_POST['week'] ?? 1);
    $topic = trim($_POST['topic'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $resources = trim($_POST['resources'] ?? '');

    if (empty($topic)) {
        echo json_encode(['success' => false, 'message' => 'Topic is required.']);
        exit();
    }

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE curriculum_nodes SET section_id=?, class_id=?, subject_id=?, term=?, week=?, topic=?, objectives=?, content=?, resources=? WHERE id=? AND school_id=?");
        $success = $stmt->execute([$section_id, $class_id, $subject_id, $term, $week, $topic, $objectives, $content, $resources, $id, $school_id]);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO curriculum_nodes (school_id, section_id, class_id, subject_id, term, week, topic, objectives, content, resources) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$school_id, $section_id, $class_id, $subject_id, $term, $week, $topic, $objectives, $content, $resources]);
    }

    echo json_encode(['success' => $success, 'message' => $success ? 'Curriculum updated.' : 'Failed to save curriculum.']);
} 

elseif ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM curriculum_nodes WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, $school_id]);
    $node = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($node) {
        echo json_encode(['success' => true, 'node' => $node]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
    }
}

elseif ($action === 'delete') {
    if (!$can_write) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit();
    }
    $id = intval($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM curriculum_nodes WHERE id = ? AND school_id = ?");
    $success = $stmt->execute([$id, $school_id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Item deleted.' : 'Deletion failed.']);
}

elseif ($action === 'fetch_all') {
    // Both admin and staff can fetch
    $section_id = intval($_GET['section_id'] ?? 0);
    $class_id = intval($_GET['class_id'] ?? 0);
    $subject_id = intval($_GET['subject_id'] ?? 0);
    $term = intval($_GET['term'] ?? 0);

    $sql = "SELECT c.*, s.section_name, cl.name as class_name, sub.name as subject_name 
            FROM curriculum_nodes c 
            LEFT JOIN school_sections s ON s.id = c.section_id
            LEFT JOIN classes cl ON cl.id = c.class_id
            LEFT JOIN subjects sub ON sub.id = c.subject_id
            WHERE c.school_id = ?";
    $params = [$school_id];

    if ($section_id) { $sql .= " AND c.section_id = ?"; $params[] = $section_id; }
    if ($class_id) { $sql .= " AND c.class_id = ?"; $params[] = $class_id; }
    if ($subject_id) { $sql .= " AND c.subject_id = ?"; $params[] = $subject_id; }
    if ($term) { $sql .= " AND c.term = ?"; $params[] = $term; }

    $sql .= " ORDER BY c.term ASC, c.week ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'nodes' => $nodes]);
}
