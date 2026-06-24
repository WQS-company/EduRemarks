-- ============================================================
-- EduRemarks Academic Management System Migration
-- Run this against your eduremarks_db database
-- ============================================================
USE eduremarks_db;

-- 1. Classes
CREATE TABLE IF NOT EXISTS classes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    school_id   INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(30)  NOT NULL,
    section     VARCHAR(50)  DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uq_class_code (school_id, code)
) ENGINE=InnoDB;

-- 2. Subjects / Courses (is_course=1 for tertiary institutions)
CREATE TABLE IF NOT EXISTS subjects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    school_id   INT NOT NULL,
    name        VARCHAR(120) NOT NULL,
    code        VARCHAR(30)  NOT NULL,
    period      VARCHAR(80)  DEFAULT NULL,
    is_course   TINYINT(1)   DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uq_subject_code (school_id, code)
) ENGINE=InnoDB;

-- 3. Map subjects to classes
CREATE TABLE IF NOT EXISTS class_subjects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    class_id    INT NOT NULL,
    subject_id  INT NOT NULL,
    UNIQUE KEY uq_cs (class_id, subject_id),
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Students table (if not yet created)
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    school_id       INT NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    admission_no    VARCHAR(50)  NOT NULL,
    student_class   VARCHAR(80)  DEFAULT NULL,
    gender          ENUM('Male','Female','Other') DEFAULT 'Male',
    dob             DATE         DEFAULT NULL,
    guardian_name   VARCHAR(150) DEFAULT NULL,
    guardian_phone  VARCHAR(20)  DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    UNIQUE KEY uq_admission (school_id, admission_no)
) ENGINE=InnoDB;

-- 5. Allocate students to classes
CREATE TABLE IF NOT EXISTS student_classes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    class_id     INT NOT NULL,
    school_id    INT NOT NULL,
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sc (student_id, school_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Allocate class+subjects to staff
CREATE TABLE IF NOT EXISTS staff_class_subjects (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    staff_detail_id INT NOT NULL,
    class_id        INT NOT NULL,
    subject_id      INT NOT NULL,
    school_id       INT NOT NULL,
    UNIQUE KEY uq_scs (staff_detail_id, class_id, subject_id),
    FOREIGN KEY (staff_detail_id) REFERENCES staff_details(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)        REFERENCES classes(id)       ON DELETE CASCADE,
    FOREIGN KEY (subject_id)      REFERENCES subjects(id)      ON DELETE CASCADE
) ENGINE=InnoDB;
