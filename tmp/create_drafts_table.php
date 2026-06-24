<?php
require_once 'C:/xampp/htdocs/dashboard/eduremarks/config/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS question_paper_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        staff_id INT NOT NULL,
        title VARCHAR(255),
        subject_id INT,
        academic_session VARCHAR(50),
        term VARCHAR(50),
        assessment_type VARCHAR(50),
        instructions TEXT,
        questions_json LONGTEXT,
        numbering_format VARCHAR(10),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (school_id),
        INDEX (staff_id)
    )");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
