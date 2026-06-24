<?php
$files = [
    'ajax/save_subjects.php',
    'ajax/delete_class.php',
    'ajax/delete_subject.php',
    'ajax/save_class_subjects.php',
    'ajax/save_academic_session.php',
    'ajax/set_active_session.php',
    'ajax/save_academic_term.php',
    'ajax/set_active_term.php',
    'ajax/archive_session.php',
    'ajax/delete_academic_session.php',
    'ajax/delete_academic_term.php',
    'ajax/promote_students.php'
];

$replacement = <<<'EOF'
if ($role !== 'owner' && $role !== 'super_admin' && $role !== 'staff') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}
if ($role === 'staff' && empty($staff_permissions['can_manage_academics'])) {
    die(json_encode(['success' => false, 'message' => 'Permission denied.']));
}
EOF;

foreach ($files as $f) {
    if (!file_exists($f)) continue;
    $content = file_get_contents($f);
    $content = str_replace(
        "if (\$role !== 'owner') die(json_encode(['success'=>false,'message'=>'Unauthorized']));", 
        $replacement, 
        $content
    );
    file_put_contents($f, $content);
}
echo "Done replacing.\n";
