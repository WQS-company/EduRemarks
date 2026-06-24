-- Migration 005: Staff Permission System
-- Adds granular permissions to staff_details for feature-level access control

ALTER TABLE staff_details 
ADD COLUMN can_manage_students TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Allow this staff member to add/edit/change class for students';
