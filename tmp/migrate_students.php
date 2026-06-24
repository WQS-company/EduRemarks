<?php
$pdo = new PDO('mysql:host=localhost;dbname=eduremarks_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE students ADD COLUMN department_id INT(11) NULL AFTER school_id");
    $pdo->exec("ALTER TABLE students ADD CONSTRAINT fk_student_dept FOREIGN KEY (department_id) REFERENCES school_sections(id) ON DELETE SET NULL");
    echo "SUCCESS: students.department_id added.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "INFO: Column department_id already exists.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
