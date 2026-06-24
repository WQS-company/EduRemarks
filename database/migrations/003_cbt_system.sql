-- ============================================================
-- EduRemarks CBT (Computer Based Test) System Migration
-- ============================================================
USE eduremarks_db;

-- 1. CBT Exams Table
CREATE TABLE IF NOT EXISTS cbt_exams (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    school_id           INT NOT NULL,
    staff_id            INT NOT NULL, -- staff_details.id
    class_id            INT NOT NULL,
    subject_id          INT NOT NULL,
    title               VARCHAR(200) NOT NULL,
    instructions        TEXT,
    duration_mins       INT DEFAULT 60,
    total_questions     INT DEFAULT 0,
    marks_per_question  DECIMAL(5,2) DEFAULT 1.0,
    start_time          DATETIME NOT NULL,
    end_time            DATETIME NOT NULL,
    randomize           TINYINT(1) DEFAULT 1, -- 1=random, 0=fixed
    order_type          ENUM('asc', 'desc', 'random') DEFAULT 'random',
    token               VARCHAR(50) UNIQUE NOT NULL, -- link sharing token
    status              ENUM('draft', 'active', 'closed') DEFAULT 'draft',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff_details(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. CBT Questions Table
CREATE TABLE IF NOT EXISTS cbt_questions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT NOT NULL,
    question_text   TEXT NOT NULL,
    image_path      VARCHAR(255) DEFAULT NULL,
    type            ENUM('objective', 'essay', 'tf') DEFAULT 'objective',
    options         JSON DEFAULT NULL, -- For objective: {"A":"...", "B":"...", ...}
    correct_answer  VARCHAR(255) DEFAULT NULL, -- Store "A", "True", or actual text
    marks           DECIMAL(5,2) DEFAULT NULL, -- Override exam default if set
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Student Exam Attempts
CREATE TABLE IF NOT EXISTS cbt_student_attempts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT NOT NULL,
    student_id      INT NOT NULL, -- students.id
    start_time      DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time        DATETIME DEFAULT NULL,
    status          ENUM('started', 'submitted', 'timed_out') DEFAULT 'started',
    total_score     DECIMAL(7,2) DEFAULT 0.0,
    
    UNIQUE KEY uq_exam_student (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Student Answers (Auto-save)
CREATE TABLE IF NOT EXISTS cbt_student_answers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id      INT NOT NULL,
    question_id     INT NOT NULL,
    answer_text     TEXT,
    is_correct      TINYINT(1) DEFAULT 0,
    score_obtained  DECIMAL(5,2) DEFAULT 0.0,
    last_updated    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_attempt_question (attempt_id, question_id),
    FOREIGN KEY (attempt_id) REFERENCES cbt_student_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
