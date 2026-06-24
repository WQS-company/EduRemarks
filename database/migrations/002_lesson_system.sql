-- ============================================================
-- EduRemarks Lesson Planning System Migration
-- ============================================================
USE eduremarks_db;

CREATE TABLE IF NOT EXISTS lesson_plans (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    school_id               INT NOT NULL,
    staff_detail_id         INT NOT NULL,
    class_id                INT NOT NULL,
    subject_id              INT NOT NULL,
    topic                   VARCHAR(255) NOT NULL,
    sub_topic               VARCHAR(255) DEFAULT NULL,
    date_planned            DATE NOT NULL,
    duration                VARCHAR(50) DEFAULT NULL,
    learning_objectives     TEXT DEFAULT NULL,
    instructional_materials TEXT DEFAULT NULL,
    introduction            TEXT DEFAULT NULL,
    presentation_steps      TEXT DEFAULT NULL,
    evaluation_questions    TEXT DEFAULT NULL,
    conclusion              TEXT DEFAULT NULL,
    lesson_note             LONGTEXT DEFAULT NULL,
    status                  ENUM('draft', 'published') DEFAULT 'draft',
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_detail_id) REFERENCES staff_details(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;
