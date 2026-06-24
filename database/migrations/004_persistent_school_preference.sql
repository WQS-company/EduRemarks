-- ============================================================
-- EduRemarks Persistent School Preference Migration
-- ============================================================
USE eduremarks_db;

-- Add last_school_id to users table to persist school selection across sessions
ALTER TABLE users ADD COLUMN last_school_id INT DEFAULT NULL;

-- Add foreign key constraint (optional but recommended for data integrity)
-- Note: We don't strictly enforce it here in case a school is deleted, we want the preference to just become NULL.
-- But if you want strict integrity:
-- ALTER TABLE users ADD FOREIGN KEY (last_school_id) REFERENCES schools(id) ON DELETE SET NULL;
